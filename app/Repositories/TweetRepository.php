<?php

namespace App\Repositories;

use App\Models\Tag;
use App\Models\Tweet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TweetRepository
{
    public function paginate(string $locale, int $perPage = 20): LengthAwarePaginator
    {
        return Tweet::query()
            ->with(['tags.translations'])
            ->locale($locale)
            ->published()
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    /**
     * Public tweet list with the reader's filters (JYU-537).
     * $tag: the resolved Tag, or false when the requested slug doesn't exist (→ no results).
     * $month: "YYYY-MM". $mediaOnly: tweets with uploaded media or a video embed.
     */
    public function paginateFiltered(
        string $locale,
        Tag|false|null $tag = null,
        ?string $month = null,
        bool $mediaOnly = false,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $q = Tweet::query()
            ->with(['tags.translations'])
            ->locale($locale)
            ->published();

        if ($tag === false) {
            $q->whereRaw('1 = 0');
        } elseif ($tag instanceof Tag) {
            $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tag->id));
        }

        if ($month !== null) {
            $start = Carbon::createFromFormat('!Y-m', $month);
            $q->where('published_at', '>=', $start)->where('published_at', '<', $start->copy()->addMonth());
        }

        if ($mediaOnly) {
            $q->where(function ($qq) {
                $qq->whereRaw("jsonb_typeof(media) = 'array' and jsonb_array_length(media) > 0")
                    ->orWhere('body', 'like', '%{{< youtube%');
            });
        }

        return $q->orderByDesc('published_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Published tweets per month in this locale, newest month first.
     *
     * @return Collection<int, object{month: string, count: int}>
     */
    public function monthCounts(string $locale): Collection
    {
        return Tweet::query()
            ->locale($locale)
            ->published()
            ->whereNotNull('published_at')
            ->selectRaw("to_char(published_at, 'YYYY-MM') as month, count(*) as count")
            ->groupBy('month')
            ->orderByDesc('month')
            ->toBase()
            ->get()
            ->map(fn ($r) => (object) ['month' => $r->month, 'count' => (int) $r->count]);
    }

    public function recent(string $locale, int $limit = 4): Collection
    {
        return Tweet::query()
            ->with(['tags.translations'])
            ->locale($locale)
            ->published()
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function findPublished(string $locale, int $id): ?Tweet
    {
        return Tweet::query()
            ->with(['tags.translations', 'author'])
            ->locale($locale)
            ->where('id', $id)
            ->published()
            ->first();
    }

    /**
     * 搜尋已發佈推文供 @ 提及自動完成。以 body 多關鍵字 ILIKE 命中;空查詢回最近發佈。
     *
     * @return EloquentCollection<int, Tweet>
     */
    public function searchForMention(string $q, string $locale, ?int $exclude = null, int $limit = 6): EloquentCollection
    {
        $base = Tweet::query()
            ->where('status', Tweet::STATUS_PUBLISHED)
            ->where('locale', $locale)
            ->when($exclude, fn ($qb) => $qb->where('id', '!=', $exclude));

        $tokens = preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (empty($tokens)) {
            return $base->orderByDesc('published_at')->limit($limit)->get(['id', 'body', 'locale']);
        }

        foreach ($tokens as $token) {
            $base->where('body', 'ILIKE', '%'.$token.'%');
        }

        return $base->orderByDesc('published_at')->limit($limit)->get(['id', 'body', 'locale']);
    }

    public function byTag(Tag $tag, string $locale, int $perPage = 20): LengthAwarePaginator
    {
        return Tweet::query()
            ->with(['tags.translations'])
            ->locale($locale)
            ->published()
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    public function adminPaginate(
        ?string $status = null,
        ?string $locale = null,
        ?string $search = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $q = Tweet::query()->with(['tags.translations']);

        if ($status === 'trashed') {
            $q->onlyTrashed();
        } elseif ($status !== null && $status !== 'all') {
            $q->where('status', $status);
        }

        if ($locale) {
            $q->where('locale', $locale);
        }

        if ($search) {
            $q->where('body', 'ILIKE', "%{$search}%");
        }

        return $q->orderByDesc('updated_at')->paginate($perPage);
    }

    public function countsByStatus(): array
    {
        $counts = Tweet::query()
            ->select('status')
            ->selectRaw('count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->all();

        return [
            'all' => Tweet::query()->count(),
            'published' => $counts[Tweet::STATUS_PUBLISHED] ?? 0,
            'draft' => $counts[Tweet::STATUS_DRAFT] ?? 0,
            'hidden' => $counts[Tweet::STATUS_HIDDEN] ?? 0,
            'trashed' => Tweet::onlyTrashed()->count(),
        ];
    }

    public function countByStatus(string $status): int
    {
        return Tweet::query()->where('status', $status)->count();
    }

    public function recentForAdmin(int $limit = 5): Collection
    {
        return Tweet::query()
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }
}
