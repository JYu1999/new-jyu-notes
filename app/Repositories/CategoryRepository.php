<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryRepository
{
    public function all(?string $locale = null): Collection
    {
        $q = Category::query()
            ->with('translations')
            ->withCount(['posts as posts_count' => function ($pq) use ($locale) {
                $pq->where('status', 'published');
                if ($locale) $pq->where('locale', $locale);
            }]);

        if ($locale) {
            // Only categories that actually have a translation in this locale.
            $q->whereHas('translations', fn ($qq) => $qq->where('locale', $locale));
        }

        return $q->get();
    }

    public function findBySlug(string $locale, string $slug): ?Category
    {
        return Category::query()
            ->with('translations')
            ->whereHas('translations', fn ($q) =>
                $q->where('locale', $locale)->where('slug', $slug)
            )
            ->first();
    }

    public function findBySlugAny(string $slug): ?Category
    {
        return Category::query()
            ->with('translations')
            ->whereHas('translations', fn ($q) => $q->where('slug', $slug))
            ->first();
    }
}
