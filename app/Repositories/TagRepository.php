<?php

namespace App\Repositories;

use App\Models\Tag;
use Illuminate\Support\Collection;

class TagRepository
{
    public function all(?string $locale = null): Collection
    {
        return Tag::query()->with('translations')->get();
    }

    public function allWithCounts(): Collection
    {
        return Tag::query()
            ->with('translations')
            ->withCount(['posts', 'tweets'])
            ->orderByDesc('posts_count')
            ->get();
    }

    public function findBySlug(string $locale, string $slug): ?Tag
    {
        return Tag::query()
            ->with('translations')
            ->whereHas('translations', fn ($q) =>
                $q->where('locale', $locale)->where('slug', $slug)
            )
            ->first();
    }

    /**
     * Find a tag by its name in a specific locale (used in Hugo migration).
     */
    public function findByName(string $locale, string $name): ?Tag
    {
        return Tag::query()
            ->with('translations')
            ->whereHas('translations', fn ($q) =>
                $q->where('locale', $locale)->where('name', $name)
            )
            ->first();
    }

    public function popular(string $locale, int $limit = 12): Collection
    {
        return Tag::query()
            ->with('translations')
            ->withCount(['posts as posts_count' => function ($q) {
                $q->where('status', 'published');
            }])
            ->orderByDesc('posts_count')
            ->limit($limit)
            ->get()
            ->filter(fn ($t) => $t->posts_count > 0)
            ->values();
    }
}
