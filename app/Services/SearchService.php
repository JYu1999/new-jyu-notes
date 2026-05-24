<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tweet;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * Perform a PostgreSQL full-text search across posts and tweets in the given locale.
     *
     * @return array{posts: Collection, tweets: Collection}
     */
    public function fullText(string $query, string $locale, string $type = 'all'): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return [
                'posts' => collect(),
                'tweets' => collect(),
            ];
        }

        $posts = collect();
        $tweets = collect();

        if ($type !== 'tweet') {
            $posts = Post::query()
                ->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$trimmed])
                ->where('locale', $locale)
                ->published()
                ->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('simple', ?)) DESC", [$trimmed])
                ->limit(20)
                ->get();
        }

        if ($type !== 'post') {
            $tweets = Tweet::query()
                ->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$trimmed])
                ->where('locale', $locale)
                ->published()
                ->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('simple', ?)) DESC", [$trimmed])
                ->limit(20)
                ->get();
        }

        return [
            'posts' => $posts,
            'tweets' => $tweets,
        ];
    }
}
