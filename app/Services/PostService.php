<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\PostSlugRedirect;
use App\Models\Tweet;
use App\Support\SlugGenerator;
use Illuminate\Support\Collection;
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
                'status' => $this->isScheduling($data) ? Post::STATUS_DRAFT : ($data['status'] ?? Post::STATUS_DRAFT),
                'is_featured' => $data['is_featured'] ?? false,
                'published_at' => $this->isScheduling($data) ? null : $this->resolvePublishedAt($data),
                'scheduled_at' => $this->isScheduling($data) ? $data['scheduled_at'] : null,
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

            (new ReferenceSyncer)->sync($post);
            app(FeaturedOrderService::class)->sync($post);
            // A real post now owns this URL; drop any redirect that used it.
            PostSlugRedirect::query()->where('locale', $post->locale)->where('old_slug', $post->slug)->delete();
            app(RevisionService::class)->record($post);

            return $post->fresh(['tags', 'categories']);
        });
    }

    /**
     * Update an existing post.
     */
    public function update(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data) {
            app(RevisionService::class)->ensureBaseline($post);

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
            $this->applySchedule($post, $data);
            $this->keepOldSlug($post);

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

            (new ReferenceSyncer)->sync($post);

            app(FeaturedOrderService::class)->sync($post);
            app(RevisionService::class)->record($post);

            return $post->fresh(['tags', 'categories']);
        });
    }

    public function softDelete(Post $post): void
    {
        $post->delete();
    }

    /** Move an article to the trash: this post and every other language version (JYU-546). */
    public function softDeleteGroup(Post $post): int
    {
        return DB::transaction(function () use ($post) {
            $versions = Post::query()->where('post_group_id', $post->post_group_id)->get();
            $versions->each->delete();

            return $versions->count();
        });
    }

    /**
     * Bulk actions from the admin post list (JYU-546). $posts are the articles' rows.
     *
     * @return string human summary for the flash message
     */
    public function bulk(string $action, Collection $posts, ?int $tagId = null): string
    {
        return DB::transaction(function () use ($action, $posts, $tagId) {
            switch ($action) {
                case 'publish':
                case 'hide':
                    $status = $action === 'publish' ? Post::STATUS_PUBLISHED : Post::STATUS_HIDDEN;
                    $posts->each(fn (Post $p) => $this->updateStatus($p, $status));

                    return ($action === 'publish' ? '已發布 ' : '已隱藏 ').$posts->count().' 篇';

                case 'tag':
                    $posts->each(function (Post $p) use ($tagId) {
                        app(RevisionService::class)->ensureBaseline($p);
                        $this->syncTagsAcrossGroup($p, $p->tags()->pluck('tags.id')->push($tagId)->unique()->values()->all());
                        app(RevisionService::class)->record($p);
                    });

                    return '已為 '.$posts->count().' 篇加上標籤';

                case 'translate_en':
                    $created = 0;
                    foreach ($posts as $p) {
                        $hasEn = Post::withTrashed()->where('post_group_id', $p->post_group_id)->where('locale', 'en')->exists();
                        if (! $hasEn) {
                            $this->createTranslation($p, 'en');
                            $created++;
                        }
                    }
                    $skipped = $posts->count() - $created;

                    return "已建立 {$created} 篇英文翻譯草稿".($skipped ? "（{$skipped} 篇已有英文版，略過）" : '');

                case 'trash':
                    $versions = $posts->sum(fn (Post $p) => $this->softDeleteGroup($p));

                    return '已將 '.$posts->count()." 篇文章（共 {$versions} 個語言版本）移到垃圾桶";
            }

            throw new \InvalidArgumentException("Unknown bulk action: {$action}");
        });
    }

    public function restore(Post $post): void
    {
        $post->restore();
    }

    public function updateStatus(Post $post, string $status): void
    {
        // Status changes (publish/draft/hide) are not content modifications, so
        // last_modified_at is left untouched; published_at records the publish time.
        app(RevisionService::class)->ensureBaseline($post);
        $post->update([
            'status' => $status,
            'published_at' => $status === Post::STATUS_PUBLISHED && ! $post->published_at
                ? now()
                : $post->published_at,
            // An explicit status change replaces any pending schedule (JYU-549).
            'scheduled_at' => null,
        ]);
        app(RevisionService::class)->record($post);
    }

    /** Cancel a pending schedule; the post stays a draft (JYU-549). */
    public function unschedule(Post $post): void
    {
        $post->update(['scheduled_at' => null]);
    }

    /**
     * Publish every scheduled draft whose time has come (JYU-549). The publish
     * date becomes the scheduled time, not the moment the job happened to run.
     *
     * @return int number of posts published
     */
    public function publishDue(): int
    {
        $due = Post::query()->scheduled()->where('scheduled_at', '<=', now())->get();

        foreach ($due as $post) {
            app(RevisionService::class)->asSystem('排程發布', function () use ($post) {
                app(RevisionService::class)->ensureBaseline($post);
                $post->update([
                    'status' => Post::STATUS_PUBLISHED,
                    'published_at' => $post->scheduled_at,
                    'scheduled_at' => null,
                ]);
                app(RevisionService::class)->record($post);
            });
        }

        return $due->count();
    }

    /**
     * When a post that has been published gets a new slug, remember the old one
     * so /{locale}/posts/{old} keeps working with a 301 (JYU-550). Drafts that
     * were never published have no public URL to preserve.
     */
    private function keepOldSlug(Post $post): void
    {
        if (! $post->isDirty('slug')) {
            return;
        }

        $old = $post->getOriginal('slug');
        if ($old && $post->getOriginal('published_at') !== null) {
            PostSlugRedirect::query()->updateOrCreate(
                ['locale' => $post->locale, 'old_slug' => $old],
                ['post_id' => $post->id],
            );
        }

        // The new slug is a live URL again, not a redirect.
        PostSlugRedirect::query()->where('locale', $post->locale)->where('old_slug', $post->slug)->delete();
    }

    private function isScheduling(array $data): bool
    {
        return ($data['publish_when'] ?? null) === 'schedule' && ! empty($data['scheduled_at']);
    }

    /**
     * Editor publish options: "schedule" keeps the post a draft until scheduled_at;
     * "now" (or publishing / hiding it) drops any pending schedule.
     */
    private function applySchedule(Post $post, array $data): void
    {
        if ($this->isScheduling($data)) {
            $post->status = Post::STATUS_DRAFT;
            $post->published_at = $post->getOriginal('published_at');
            $post->scheduled_at = $data['scheduled_at'];
        } elseif (($data['publish_when'] ?? null) === 'now' || $post->status !== Post::STATUS_DRAFT) {
            $post->scheduled_at = null;
        }
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
                'title' => $existingPost->title.' ('.strtoupper($newLocale).')',
                'excerpt' => $existingPost->excerpt,
                'body' => $existingPost->body,
                'cover_image_path' => $existingPost->cover_image_path,
                'status' => Post::STATUS_DRAFT,
                'is_featured' => false,
                // Keep the article's place in the featured order (JYU-547).
                'featured_order' => $existingPost->featured_order,
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
            app(RevisionService::class)->record($newPost);

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
     * Previous / next post for the bottom of a post page.
     * Posts in a series follow the series order; otherwise publish date
     * (previous = older, next = newer) within the same locale.
     *
     * @return array{previous: ?Post, next: ?Post}
     */
    public function adjacentPosts(Post $post): array
    {
        $series = $this->seriesNavigation($post);
        if ($series['previous'] || $series['next']) {
            return ['previous' => $series['previous'], 'next' => $series['next']];
        }

        if (! $post->published_at) {
            return ['previous' => null, 'next' => null];
        }

        $base = fn () => Post::query()->locale($post->locale)->published()->whereKeyNot($post->id);
        $at = $post->published_at;

        return [
            'previous' => $base()
                ->where(fn ($q) => $q->where('published_at', '<', $at)
                    ->orWhere(fn ($qq) => $qq->where('published_at', $at)->where('id', '<', $post->id)))
                ->orderByDesc('published_at')->orderByDesc('id')
                ->first(),
            'next' => $base()
                ->where(fn ($q) => $q->where('published_at', '>', $at)
                    ->orWhere(fn ($qq) => $qq->where('published_at', $at)->where('id', '>', $post->id)))
                ->orderBy('published_at')->orderBy('id')
                ->first(),
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
        if (! $url) {
            return null;
        }

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
            $tweet = Tweet::query()->where('locale', $sourceLocale)->where('id', $id)->first();
            if ($tweet) {
                $sibling = $tweet->translation($targetLocale);
                if ($sibling && $sibling->status === Tweet::STATUS_PUBLISHED) {
                    return "/{$targetLocale}/tweets/{$sibling->id}";
                }
            }

            return "/{$targetLocale}/tweets";
        }

        // Other locale-prefixed pages: swap locale prefix
        if (preg_match('#^/(zh|en|ja|vi|id)(/.*)?$#', $path, $m)) {
            return "/{$targetLocale}".($m[2] ?? '');
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
