<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostViewLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ViewTrackingService
{
    private const DEDUP_WINDOW_MINUTES = 30;

    public function track(Post $post, string $ip, string $userAgent): void
    {
        $fingerprint = $this->fingerprint($ip, $userAgent);

        $threshold = Carbon::now()->subMinutes(self::DEDUP_WINDOW_MINUTES);

        $exists = PostViewLog::query()
            ->where('post_id', $post->id)
            ->where('fingerprint', $fingerprint)
            ->where('viewed_at', '>=', $threshold)
            ->exists();

        if ($exists) {
            return;
        }

        DB::transaction(function () use ($post, $fingerprint) {
            PostViewLog::create([
                'post_id' => $post->id,
                'fingerprint' => $fingerprint,
                'viewed_at' => now(),
            ]);

            // Increment via the query builder so neither updated_at nor
            // last_modified_at is touched — a view is not a content change.
            DB::table('posts')
                ->where('id', $post->id)
                ->increment('views_count');
        });
    }

    private function fingerprint(string $ip, string $userAgent): string
    {
        $dailySalt = now()->format('Ymd').config('app.key');

        return hash('sha256', $ip.'|'.$userAgent.'|'.$dailySalt);
    }
}
