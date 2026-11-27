<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tweet;
use App\Support\MarkdownRenderer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
    public function fullText(string $query, ?string $locale, string $type = 'all'): array
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
                ->with('tags.translations')
                ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
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
                ->with('tags.translations')
                ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
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

    /**
     * Results for the ⌘K search panel (JYU-538): both types merged newest first,
     * with a plain-text snippet around the first match.
     * $locale = null searches every language.
     *
     * @return array{counts: array{all: int, post: int, tweet: int}, results: list<array<string, mixed>>}
     */
    public function suggest(string $query, ?string $locale): array
    {
        $found = $this->fullText($query, $locale);
        $q = trim($query);

        $posts = $found['posts']->map(fn (Post $p) => [
            'type' => 'post',
            'title' => $p->title,
            'snippet' => self::snippet(implode(' ', array_filter([$p->excerpt, self::plain((string) $p->body)])), $q)
                ?? self::snippet($p->title, $q) ?? ['pre' => (string) $p->excerpt, 'hit' => '', 'post' => ''],
            'date' => $p->published_at?->format('Y/m/d'),
            'tags' => self::tagNames($p, $p->locale),
            'url' => route('public.posts.show', [$p->locale, $p->slug]),
            'locale' => $p->locale,
            'at' => $p->published_at?->getTimestamp() ?? 0,
        ]);

        $tweets = $found['tweets']->map(function (Tweet $t) use ($q) {
            $plain = self::plain((string) $t->body);

            return [
                'type' => 'tweet',
                'title' => Str::limit($plain, 40),
                'snippet' => self::snippet($plain, $q) ?? ['pre' => Str::limit($plain, 120), 'hit' => '', 'post' => ''],
                'date' => $t->published_at?->format('Y/m/d'),
                'tags' => self::tagNames($t, $t->locale),
                'url' => route('public.tweets.show', [$t->locale, $t->id]),
                'locale' => $t->locale,
                'at' => $t->published_at?->getTimestamp() ?? 0,
            ];
        });

        $results = $posts->concat($tweets)->sortByDesc('at')->values()
            ->map(function ($r) {
                unset($r['at']);

                return $r;
            });

        return [
            'counts' => ['all' => $results->count(), 'post' => $posts->count(), 'tweet' => $tweets->count()],
            'results' => $results->all(),
        ];
    }

    /**
     * Cut ~$before chars before and ~$after chars after the first case-insensitive match.
     * The hit keeps the original casing. Returns null when the needle isn't found.
     *
     * @return array{pre: string, hit: string, post: string}|null
     */
    public static function snippet(string $text, string $needle, int $before = 30, int $after = 80): ?array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($needle === '' || $text === '') {
            return null;
        }

        $pos = mb_stripos($text, $needle);
        if ($pos === false) {
            return null;
        }

        $len = mb_strlen($needle);
        $start = max(0, $pos - $before);
        $end = min(mb_strlen($text), $pos + $len + $after);

        return [
            'pre' => ($start > 0 ? '…' : '').mb_substr($text, $start, $pos - $start),
            'hit' => mb_substr($text, $pos, $len),
            'post' => mb_substr($text, $pos + $len, $end - $pos - $len).($end < mb_strlen($text) ? '…' : ''),
        ];
    }

    /** Markdown → plain text (shortcodes and HTML removed). */
    private static function plain(string $markdown): string
    {
        $markdown = preg_replace('/\{\{<.*?>\}\}/s', ' ', $markdown);

        return trim(preg_replace('/\s+/u', ' ', strip_tags(app(MarkdownRenderer::class)->render($markdown))));
    }

    /** @return list<string> */
    private static function tagNames($model, string $locale): array
    {
        return $model->tags
            ->map(fn ($t) => $t->translations->firstWhere('locale', $locale)?->name)
            ->filter()
            ->values()
            ->all();
    }
}
