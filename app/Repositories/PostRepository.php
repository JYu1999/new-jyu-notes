<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PostRepository
{
    public function paginate(
        string $locale,
        string $sort = 'published',
        ?int $tag = null,
        ?int $category = null,
        int $perPage = 12,
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

        $q->sortBy($sort);

        return $q->paginate($perPage);
    }

    public function featured(string $locale, int $limit = 4): Collection
    {
        return Post::query()
            ->with(['tags.translations', 'categories.translations'])
            ->locale($locale)
            ->published()
            ->featured()
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
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
}
