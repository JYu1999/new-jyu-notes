<?php

namespace App\Repositories;

use App\Models\Tag;
use App\Models\Tweet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
