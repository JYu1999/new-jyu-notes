<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryRepository
{
    public function all(?string $locale = null): Collection
    {
        return Category::query()
            ->with('translations')
            ->withCount(['posts as posts_count' => function ($q) {
                $q->where('status', 'published');
            }])
            ->get();
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
