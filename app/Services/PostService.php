<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostGroup;
use App\Support\PostReferenceExtractor;
use App\Support\SlugGenerator;
use Illuminate\Support\Facades\DB;

class PostService
{
    /** Fields whose change constitutes a content modification (bumps last_modified_at). */
    private const CONTENT_FIELDS = ['title', 'slug', 'excerpt', 'body', 'cover_image_path'];

    /**
     * Create a new post (and its group if not provided).
     */
    public function create(array $data): Post
    {
        return DB::transaction(function () use ($data) {
            $groupId = $data['post_group_id']
                ?? PostGroup::create()->id;

            $locale = $data['locale'];
            $slug = $data['slug'] ?? null;
            if (! $slug) {
                $slug = SlugGenerator::forPost($data['title'], $locale);
            }

            $post = Post::create([
                'post_group_id' => $groupId,
                'locale' => $locale,
                'slug' => $slug,
                'title' => $data['title'],
                'excerpt' => $data['excerpt'] ?? null,
                'body' => $data['body'],
                'cover_image_path' => $data['cover_image_path'] ?? null,
                'status' => $data['status'] ?? Post::STATUS_DRAFT,
                'is_featured' => $data['is_featured'] ?? false,
                'published_at' => $this->resolvePublishedAt($data),
                'last_modified_at' => now(),
                'author_id' => $data['author_id'] ?? auth()->id(),
            ]);

            // Sync tags / categories across all locales of this group
            if (isset($data['tag_ids'])) {
                $this->syncTagsAcrossGroup($post, $data['tag_ids']);
            }
            if (isset($data['category_ids'])) {
                $this->syncCategoriesAcrossGroup(
                    $post,
                    $this->buildCategoryPivot($data['category_ids'], $data['categories_order'] ?? [])
                );
            }

            $this->syncReferences($post);

            return $post->fresh(['tags', 'categories']);
        });
    }

    /**
     * Update an existing post.
     */
    public function update(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data) {
            // Auto-regenerate slug if title changed and slug not explicitly set
            if (isset($data['title']) && $data['title'] !== $post->title && empty($data['slug'])) {
                $data['slug'] = SlugGenerator::forPost($data['title'], $post->locale, $post->id);
            }

            $updateData = array_filter([
                'title' => $data['title'] ?? null,
                'slug' => $data['slug'] ?? null,
                'excerpt' => $data['excerpt'] ?? null,
                'body' => $data['body'] ?? null,
                'cover_image_path' => $data['cover_image_path'] ?? null,
                'status' => $data['status'] ?? null,
                'is_featured' => $data['is_featured'] ?? null,
                'published_at' => $this->resolvePublishedAtForUpdate($post, $data),
            ], fn ($v) => $v !== null);

            // Allow explicit setting of nullable cover_image_path to empty
            if (array_key_exists('cover_image_path', $data) && $data['cover_image_path'] === null) {
                $updateData['cover_image_path'] = null;
            }

            $post->fill($updateData);

            // Only a genuine content change counts as a modification. Metadata-only
            // edits (is_featured, status, tags/categories) must not bump the timestamp.
            if ($post->isDirty(self::CONTENT_FIELDS)) {
                $post->last_modified_at = now();
            }

            $post->save();

            if (isset($data['tag_ids'])) {
                $this->syncTagsAcrossGroup($post, $data['tag_ids']);
            }
            if (isset($data['category_ids'])) {
                $this->syncCategoriesAcrossGroup(
                    $post,
                    $this->buildCategoryPivot($data['category_ids'], $data['categories_order'] ?? [])
                );
            }

            $this->syncReferences($post);

            return $post->fresh(['tags', 'categories']);
        });
    }

    public function softDelete(Post $post): void
    {
        $post->delete();
    }

    public function restore(Post $post): void
    {
        $post->restore();
    }

    public function updateStatus(Post $post, string $status): void
    {
        // Status changes (publish/draft/hide) are not content modifications, so
        // last_modified_at is left untouched; published_at records the publish time.
        $post->update([
            'status' => $status,
            'published_at' => $status === Post::STATUS_PUBLISHED && ! $post->published_at
                ? now()
                : $post->published_at,
        ]);
    }

    /**
     * Create a new translation for an existing post group.
     */
    public function createTranslation(Post $existingPost, string $newLocale): Post
    {
        return DB::transaction(function () use ($existingPost, $newLocale) {
            $existing = Post::query()
                ->where('post_group_id', $existingPost->post_group_id)
                ->where('locale', $newLocale)
                ->withTrashed()
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                return $existing;
            }

            $newPost = Post::create([
                'post_group_id' => $existingPost->post_group_id,
                'locale' => $newLocale,
                'slug' => SlugGenerator::forPost($existingPost->title, $newLocale),
                'title' => $existingPost->title . ' (' . strtoupper($newLocale) . ')',
                'excerpt' => $existingPost->excerpt,
                'body' => $existingPost->body,
                'cover_image_path' => $existingPost->cover_image_path,
                'status' => Post::STATUS_DRAFT,
                'is_featured' => false,
                'last_modified_at' => now(),
                'author_id' => auth()->id(),
            ]);

            // Inherit existing siblings' tags / categories
            $siblingTagIds = $existingPost->tags()->pluck('tags.id')->all();
            if ($siblingTagIds) {
                $newPost->tags()->sync($siblingTagIds);
            }

            $siblingCategories = $existingPost->categories()->get()->mapWithKeys(
                fn ($c) => [$c->id => ['order_in_category' => $c->pivot->order_in_category]]
            )->all();
            if ($siblingCategories) {
                $newPost->categories()->sync($siblingCategories);
            }

            return $newPost;
        });
    }

    /**
     * Sync tags to every locale in the same post group.
     */
    public function syncTagsAcrossGroup(Post $post, array $tagIds): void
    {
        DB::transaction(function () use ($post, $tagIds) {
            $siblings = Post::where('post_group_id', $post->post_group_id)->get();
            foreach ($siblings as $p) {
                $p->tags()->sync($tagIds);
            }
        });
    }

    /**
     * Sync categories (with order) to every locale in the same post group.
     *
     * @param  array  $categoriesWithOrder  e.g. [123 => ['order_in_category' => 1], 456 => ['order_in_category' => null]]
     */
    public function syncCategoriesAcrossGroup(Post $post, array $categoriesWithOrder): void
    {
        DB::transaction(function () use ($post, $categoriesWithOrder) {
            $siblings = Post::where('post_group_id', $post->post_group_id)->get();
            foreach ($siblings as $p) {
                $p->categories()->sync($categoriesWithOrder);
            }
        });
    }

    /**
     * 解析 body 內部文章連結，覆寫此文章的 outgoing references。
     */
    public function syncReferences(Post $post): void
    {
        $pairs = (new PostReferenceExtractor())->extract((string) $post->body);

        $targetIds = [];
        foreach ($pairs as $pair) {
            $target = Post::query()
                ->where('locale', $pair['locale'])
                ->where('slug', $pair['slug'])
                ->first();

            if ($target && $target->id !== $post->id) {
                $targetIds[$target->id] = true;
            }
        }

        $post->outgoingReferences()->sync(array_keys($targetIds));
    }

    /**
     * Compute previous / next post within the same category (series navigation).
     */
    public function seriesNavigation(Post $post): array
    {
        $cats = $post->categories;
        if ($cats->isEmpty()) {
            return ['previous' => null, 'next' => null];
        }

        $category = $cats->first();
        $sortMethod = $category->sort_method;

        $direction = $sortMethod === 'date_asc' ? 'asc' : 'desc';
        $orderCol = $sortMethod === 'manual' ? 'category_post.order_in_category' : 'posts.published_at';

        $all = Post::query()
            ->join('category_post', 'category_post.post_id', '=', 'posts.id')
            ->where('category_post.category_id', $category->id)
            ->where('posts.locale', $post->locale)
            ->published()
            ->orderBy($orderCol, $direction)
            ->select('posts.*', 'category_post.order_in_category')
            ->get();

        $index = $all->search(fn ($p) => $p->id === $post->id);
        if ($index === false) {
            return ['previous' => null, 'next' => null];
        }

        return [
            'previous' => $index > 0 ? $all[$index - 1] : null,
            'next' => $index < $all->count() - 1 ? $all[$index + 1] : null,
            'category' => $category,
        ];
    }

    /**
     * Given a URL in one locale, find the corresponding URL in another locale.
     *
     * If the post translation doesn't exist, returns the post-list page in target locale
     * (rather than a URL that would 404).
     */
    public function equivalentUrlInLocale(?string $url, string $targetLocale): ?string
    {
        if (! $url) return null;

        $path = parse_url($url, PHP_URL_PATH) ?? '';

        // Post detail
        if (preg_match('#^/(zh|en|ja|vi|id)/posts/([^/]+)/?$#', $path, $m)) {
            $sourceLocale = $m[1];
            $slug = $m[2];
            $post = Post::query()->where('locale', $sourceLocale)->where('slug', $slug)->first();
            if ($post) {
                $sibling = $post->translation($targetLocale);
                if ($sibling && $sibling->status === Post::STATUS_PUBLISHED) {
                    return "/{$targetLocale}/posts/{$sibling->slug}";
                }
            }
            // No translation → fall back to posts list (NOT the same URL, would 404)
            return "/{$targetLocale}/posts";
        }

        // Tweet detail
        if (preg_match('#^/(zh|en|ja|vi|id)/tweets/(\d+)/?$#', $path, $m)) {
            $sourceLocale = $m[1];
            $id = (int) $m[2];
            $tweet = \App\Models\Tweet::query()->where('locale', $sourceLocale)->where('id', $id)->first();
            if ($tweet) {
                $sibling = $tweet->translation($targetLocale);
                if ($sibling && $sibling->status === \App\Models\Tweet::STATUS_PUBLISHED) {
                    return "/{$targetLocale}/tweets/{$sibling->id}";
                }
            }
            return "/{$targetLocale}/tweets";
        }

        // Other locale-prefixed pages: swap locale prefix
        if (preg_match('#^/(zh|en|ja|vi|id)(/.*)?$#', $path, $m)) {
            return "/{$targetLocale}" . ($m[2] ?? '');
        }

        return "/{$targetLocale}";
    }

    private function buildCategoryPivot(array $categoryIds, array $orders): array
    {
        $result = [];
        foreach ($categoryIds as $cid) {
            $result[$cid] = ['order_in_category' => $orders[$cid] ?? null];
        }
        return $result;
    }

    private function resolvePublishedAt(array $data)
    {
        if (! empty($data['published_at'])) {
            return $data['published_at'];
        }
        if (($data['status'] ?? null) === Post::STATUS_PUBLISHED) {
            return now();
        }
        return null;
    }

    private function resolvePublishedAtForUpdate(Post $post, array $data)
    {
        if (array_key_exists('published_at', $data) && $data['published_at']) {
            return $data['published_at'];
        }
        // Auto-set published_at on first publish
        $newStatus = $data['status'] ?? null;
        if ($newStatus === Post::STATUS_PUBLISHED && ! $post->published_at) {
            return now();
        }
        return null;
    }
}
