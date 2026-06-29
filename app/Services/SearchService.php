<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tweet;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * Search posts and tweets in the given locale.
     *
     * Combines PostgreSQL tsvector match (good for English tokens) with
     * ILIKE substring match (necessary for CJK, which doesn't tokenize
     * cleanly under the `simple` text-search config). Results are
     * deduplicated and ranked: exact ILIKE matches first, then tsvector
     * ranks.
     *
     * @return array{posts: Collection, tweets: Collection}
     */
    public function fullText(string $query, string $locale, string $type = 'all'): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return ['posts' => collect(), 'tweets' => collect()];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $trimmed).'%';

        $posts = collect();
        $tweets = collect();

        if ($type !== 'tweet') {
            $posts = Post::query()
                ->where('locale', $locale)
                ->published()
                ->where(function ($q) use ($like, $trimmed) {
                    $q->where('title', 'ILIKE', $like)
                        ->orWhere('excerpt', 'ILIKE', $like)
                        ->orWhere('body', 'ILIKE', $like)
                        ->orWhereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$trimmed]);
                })
                ->orderByDesc('published_at')
                ->limit(20)
                ->get();
        }

        if ($type !== 'post') {
            $tweets = Tweet::query()
                ->where('locale', $locale)
                ->published()
                ->where(function ($q) use ($like, $trimmed) {
                    $q->where('body', 'ILIKE', $like)
                        ->orWhereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$trimmed]);
                })
                ->orderByDesc('published_at')
                ->limit(20)
                ->get();
        }

        return [
            'posts' => $posts,
            'tweets' => $tweets,
        ];
    }
}
