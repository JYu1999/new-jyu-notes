<?php

namespace App\Repositories;

use App\Models\Tag;
use Illuminate\Support\Collection;

class TagRepository
{
    public function all(?string $locale = null): Collection
    {
        $q = Tag::query()->with('translations');
        if ($locale) {
            // Only return tags that actually have a translation in this locale,
            // so the sidebar doesn't leak names from other languages.
            $q->whereHas('translations', fn ($qq) => $qq->where('locale', $locale));
        }

        return $q->get();
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
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->where('slug', $slug)
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
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->where('name', $name)
            )
            ->first();
    }

    public function popular(string $locale, int $limit = 12): Collection
    {
        return Tag::query()
            ->with('translations')
            ->whereHas('translations', fn ($qq) => $qq->where('locale', $locale))
            ->withCount(['posts as posts_count' => function ($q) use ($locale) {
                $q->where('status', 'published')->where('locale', $locale);
            }])
            ->orderByDesc('posts_count')
            ->limit($limit)
            ->get()
            ->filter(fn ($t) => $t->posts_count > 0)
            ->values();
    }

    /**
     * Every tag translated into this locale, with the number of published posts
     * in this locale, most-used first. Tags without posts are kept (they may
     * still have tweets) so the topics page lists the full vocabulary.
     */
    public function allWithPostCounts(string $locale): Collection
    {
        return Tag::query()
            ->with('translations')
            ->whereHas('translations', fn ($qq) => $qq->where('locale', $locale))
            ->withCount(['posts as posts_count' => function ($q) use ($locale) {
                $q->where('status', 'published')->where('locale', $locale);
            }])
            ->orderByDesc('posts_count')
            ->get();
    }

    /** Tags used by published tweets in this locale, most used first (tweets page filter). */
    public function forTweets(string $locale): Collection
    {
        return Tag::query()
            ->with('translations')
            ->whereHas('translations', fn ($qq) => $qq->where('locale', $locale))
            ->withCount(['tweets as tweets_count' => fn ($q) => $q->where('status', 'published')->where('locale', $locale)])
            ->get()
            ->filter(fn ($t) => $t->tweets_count > 0)
            ->sortByDesc('tweets_count')
            ->values();
    }
}
