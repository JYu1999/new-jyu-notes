<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tweet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The home page "Latest" timeline: posts and tweets merged, newest first,
 * paginated with an opaque cursor so "load more" never skips or repeats
 * items even when several share the same published_at.
 *
 * Order is (published_at DESC, kind DESC, id DESC) where kind post=1, tweet=0.
 */
class HomeFeedService
{
    public const TYPES = ['all', 'post', 'tweet'];

    private const RANK = ['post' => 1, 'tweet' => 0];

    /**
     * @return array{items: Collection<int, array{type: string, model: Post|Tweet, published_at: Carbon}>, next: ?string}
     */
    public function latest(string $locale, string $type = 'all', ?string $cursor = null, int $limit = 10): array
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'all';
        $after = $this->decode($cursor);

        $items = collect();
        if ($type !== 'tweet') {
            $items = $items->concat($this->fetch('post', $locale, $after, $limit + 1));
        }
        if ($type !== 'post') {
            $items = $items->concat($this->fetch('tweet', $locale, $after, $limit + 1));
        }

        $sorted = $items->sort(function (array $a, array $b) {
            return [$b['published_at']->getTimestamp(), self::RANK[$b['type']], $b['model']->id]
                <=> [$a['published_at']->getTimestamp(), self::RANK[$a['type']], $a['model']->id];
        })->values();

        $page = $sorted->take($limit)->values();
        $next = $sorted->count() > $limit ? $this->encode($page->last()) : null;

        return ['items' => $page, 'next' => $next];
    }

    /** @return Collection<int, array{type: string, model: Post|Tweet, published_at: Carbon}> */
    private function fetch(string $type, string $locale, ?array $after, int $limit): Collection
    {
        $query = $type === 'post'
            ? Post::query()->with(['tags.translations', 'categories.translations'])
            : Tweet::query()->with(['tags.translations']);

        $query->locale($locale)->published()->whereNotNull('published_at');

        if ($after !== null) {
            $this->applyCursor($query, self::RANK[$type], $after);
        }

        return $query->orderByDesc('published_at')->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($model) => ['type' => $type, 'model' => $model, 'published_at' => $model->published_at]);
    }

    /** Keep rows that sort strictly after the cursor in (published_at, rank, id) DESC order. */
    private function applyCursor(Builder $query, int $rank, array $after): void
    {
        [$ts, $cursorRank, $cursorId] = $after;
        $at = Carbon::createFromTimestamp($ts);

        $query->where(function (Builder $q) use ($at, $rank, $cursorRank, $cursorId) {
            $q->where('published_at', '<', $at);

            if ($rank < $cursorRank) {
                $q->orWhere('published_at', '=', $at);
            } elseif ($rank === $cursorRank) {
                $q->orWhere(fn (Builder $qq) => $qq->where('published_at', '=', $at)->where('id', '<', $cursorId));
            }
        });
    }

    private function encode(array $item): string
    {
        return implode('.', [$item['published_at']->getTimestamp(), self::RANK[$item['type']], $item['model']->id]);
    }

    /** @return array{0: int, 1: int, 2: int}|null */
    private function decode(?string $cursor): ?array
    {
        if ($cursor === null || preg_match('/^(\d{1,12})\.([01])\.(\d{1,12})$/', $cursor, $m) !== 1) {
            return null;
        }

        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }
}
