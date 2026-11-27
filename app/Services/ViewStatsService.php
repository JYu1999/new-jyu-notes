<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostViewLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin home view stats (JYU-543). Only posts are tracked (tweets have no view log).
 * Each post_view_logs row is already de-duplicated (same visitor within 30 minutes
 * counts once), so "unique views" = number of rows.
 * Days are counted in the author's display timezone (viewed_at is timestamptz).
 */
class ViewStatsService
{
    /**
     * @return array{
     *   total: int, previous: int, change: ?int,
     *   daily: list<array{date: string, label: string, count: int}>,
     *   top: Collection<int, array{post: Post, views: int}>
     * }
     */
    public function summary(?Carbon $now = null, int $days = 30): array
    {
        $tz = (string) config('app.display_timezone', 'UTC');
        $now = ($now ?? Carbon::now())->copy()->setTimezone($tz);

        // Window = the last $days calendar days including today (local time), converted to UTC for the query.
        $start = $now->copy()->startOfDay()->subDays($days - 1);
        $prevStart = $start->copy()->subDays($days);

        $total = $this->countBetween($start, $now);
        $previous = $this->countBetween($prevStart, $start);

        return [
            'total' => $total,
            'previous' => $previous,
            'change' => $previous > 0 ? (int) round(($total - $previous) / $previous * 100) : null,
            'daily' => $this->daily($start, $now, $days, $tz),
            'top' => $this->topPosts($now->copy()->startOfDay()->subDays(6), $now, 4),
        ];
    }

    private function countBetween(Carbon $from, Carbon $to): int
    {
        return PostViewLog::query()
            ->where('viewed_at', '>=', $from->copy()->utc())
            ->where('viewed_at', '<', $to->copy()->utc()->addSecond())
            ->count();
    }

    private function daily(Carbon $start, Carbon $now, int $days, string $tz): array
    {
        $rows = PostViewLog::query()
            ->where('viewed_at', '>=', $start->copy()->utc())
            ->selectRaw("to_char(viewed_at AT TIME ZONE ?, 'YYYY-MM-DD') as day, count(*) as c", [$tz])
            ->groupBy('day')
            ->pluck('c', 'day');

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $out[] = ['date' => $d->toDateString(), 'label' => $d->format('m/d'), 'count' => (int) ($rows[$d->toDateString()] ?? 0)];
        }

        return $out;
    }

    /** Most viewed posts in the window; translations of the same post are added together. */
    private function topPosts(Carbon $from, Carbon $to, int $limit): Collection
    {
        $rows = DB::table('post_view_logs')
            ->join('posts', 'posts.id', '=', 'post_view_logs.post_id')
            ->where('post_view_logs.viewed_at', '>=', $from->copy()->utc())
            ->where('post_view_logs.viewed_at', '<', $to->copy()->utc()->addSecond())
            ->groupBy('posts.post_group_id')
            ->selectRaw('posts.post_group_id, count(*) as views')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        $posts = Post::query()->whereIn('post_group_id', $rows->pluck('post_group_id'))->get()->groupBy('post_group_id');

        return $rows->map(function ($r) use ($posts) {
            $group = $posts[$r->post_group_id] ?? collect();
            $post = $group->firstWhere('locale', 'zh') ?? $group->first();

            return $post ? ['post' => $post, 'views' => (int) $r->views] : null;
        })->filter()->values();
    }
}
