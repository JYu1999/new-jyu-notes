<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PostRepository
{
    public function paginate(
        string $locale,
        string $sort = 'published',
        ?int $tag = null,
        ?int $category = null,
        int $perPage = 12,
        bool $featured = false,
    ): LengthAwarePaginator {
        $q = Post::query()
            ->with(['tags.translations', 'categories.translations'])
            ->locale($locale)
            ->published();

        if ($tag !== null) {
            $q->whereHas('tags', fn ($qq) => $qq->where('tags.id', $tag));
        }
        if ($category !== null) {
            $q->whereHas('categories', fn ($qq) => $qq->where('categories.id', $category));
        }

        if ($featured) {
            // Featured list keeps the same order as the home page.
            return $q->featured()->orderForFeatured()->paginate($perPage);
        }

        $q->sortBy($sort);

        return $q->paginate($perPage);
    }

    /**
     * Featured posts for the home page: the first $limit in featured order,
     * plus how many featured posts exist in total (for "N more featured →").
     * No fallback: with no featured posts the home page hides the section.
     *
     * @return array{items: EloquentCollection<int, Post>, total: int}
     */
    public function featuredForHome(string $locale, int $limit = 3): array
    {
        $base = Post::query()->locale($locale)->published()->featured();

        return [
            'items' => (clone $base)
                ->with(['tags.translations', 'categories.translations'])
                ->orderForFeatured()
                ->limit($limit)
                ->get(),
            'total' => (clone $base)->count(),
        ];
    }

    /**
     * Published posts in the same locale that share a tag or category with $post,
     * most shared topics first, then newest. Excludes $post itself.
     */
    public function related(Post $post, int $limit = 3): EloquentCollection
    {
        $tagIds = $post->tags->pluck('id');
        $categoryIds = $post->categories->pluck('id');

        if ($tagIds->isEmpty() && $categoryIds->isEmpty()) {
            return new EloquentCollection;
        }

        return Post::query()
            ->locale($post->locale)
            ->published()
            ->whereKeyNot($post->id)
            ->where(function ($q) use ($tagIds, $categoryIds) {
                $q->whereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds))
                    ->orWhereHas('categories', fn ($c) => $c->whereIn('categories.id', $categoryIds));
            })
            ->withCount([
                'tags as shared_tags' => fn ($t) => $t->whereIn('tags.id', $tagIds),
                'categories as shared_categories' => fn ($c) => $c->whereIn('categories.id', $categoryIds),
            ])
            ->get()
            // Postgres can't ORDER BY select aliases; a blog's candidate set is small.
            ->sortBy([
                fn ($a, $b) => ($b->shared_tags + $b->shared_categories) <=> ($a->shared_tags + $a->shared_categories),
                fn ($a, $b) => $b->published_at <=> $a->published_at,
            ])
            ->take($limit)
            ->values();
    }

    public function findPublishedBySlug(string $locale, string $slug): ?Post
    {
        return Post::query()
            ->with(['tags.translations', 'categories.translations', 'author'])
            ->locale($locale)
            ->where('slug', $slug)
            ->published()
            ->first();
    }

    public function byTag(Tag $tag, string $locale, int $perPage = 12): LengthAwarePaginator
    {
        return Post::query()
            ->with(['tags.translations'])
            ->locale($locale)
            ->published()
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    public function byCategory(Category $category, string $locale, int $perPage = 12): LengthAwarePaginator
    {
        $q = Post::query()
            ->with(['tags.translations'])
            ->locale($locale)
            ->published()
            ->whereHas('categories', fn ($qq) => $qq->where('categories.id', $category->id));

        match ($category->sort_method) {
            Category::SORT_MANUAL => $q->orderBy(
                'category_post.order_in_category'
            )->join('category_post', function ($j) use ($category) {
                $j->on('category_post.post_id', '=', 'posts.id')
                    ->where('category_post.category_id', $category->id);
            })->select('posts.*', 'category_post.order_in_category'),
            Category::SORT_DATE_ASC => $q->orderBy('published_at'),
            default => $q->orderByDesc('published_at'),
        };

        return $q->paginate($perPage);
    }

    public function adminPaginate(
        ?string $status = null,
        ?string $locale = null,
        ?string $search = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $q = Post::query()->with(['tags.translations', 'categories.translations']);

        if ($status === 'trashed') {
            $q->onlyTrashed();
        } elseif ($status !== null && $status !== 'all') {
            $q->where('status', $status);
        }

        if ($locale) {
            $q->where('locale', $locale);
        }

        if ($search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('excerpt', 'ILIKE', "%{$search}%");
            });
        }

        return $q->orderByDesc('updated_at')->paginate($perPage);
    }

    public function countsByStatus(): array
    {
        $counts = Post::query()
            ->select('status')
            ->selectRaw('count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->all();

        $trashed = Post::onlyTrashed()->count();
        $total = Post::query()->count();

        return [
            'all' => $total,
            'published' => $counts[Post::STATUS_PUBLISHED] ?? 0,
            'draft' => $counts[Post::STATUS_DRAFT] ?? 0,
            'hidden' => $counts[Post::STATUS_HIDDEN] ?? 0,
            'trashed' => $trashed,
        ];
    }

    public function countByStatus(string $status): int
    {
        return Post::query()->where('status', $status)->count();
    }

    public function recentForAdmin(int $limit = 5): Collection
    {
        return Post::query()
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Search the author's own PUBLISHED posts in one locale for the @-mention picker.
     * Matches title / slug / excerpt (case-insensitive), title matches ranked first.
     *
     * @return EloquentCollection<int, Post>
     */
    public function searchForMention(string $q, string $locale, ?int $exclude = null, int $limit = 8): EloquentCollection
    {
        $base = Post::query()
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('locale', $locale)
            ->when($exclude, fn ($qb) => $qb->where('id', '!=', $exclude));

        // 以空白把查詢拆成多個關鍵字：每個關鍵字都要在 title/slug/excerpt 任一命中
        // （AND，不限順序）。例如「監控 導入」可命中標題「公司導入監控」。
        $tokens = preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // 空查詢（剛打完 @）：回傳最近發佈的文章供參考——最近的比較容易被提及。
        if (empty($tokens)) {
            return $base
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get(['id', 'title', 'slug', 'locale']);
        }

        foreach ($tokens as $token) {
            $like = '%'.$token.'%';
            $base->where(function ($qb) use ($like) {
                $qb->where('title', 'ILIKE', $like)
                    ->orWhere('slug', 'ILIKE', $like)
                    ->orWhere('excerpt', 'ILIKE', $like);
            });
        }

        // 標題命中（以第一個關鍵字判斷）優先，其次新到舊。
        $firstLike = '%'.$tokens[0].'%';

        return $base
            ->orderByRaw('CASE WHEN title ILIKE ? THEN 0 ELSE 1 END', [$firstLike])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'locale']);
    }
}
