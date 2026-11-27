<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\DB;

/**
 * Media library files that nothing points to any more (JYU-544):
 * not in any post / tweet / page body, tweet media, or cover image.
 * Trashed content still counts as using a file (it can be restored).
 */
class MediaUsageService
{
    /** @return list<int> */
    public function unusedIds(): array
    {
        $haystack = implode("\n", [
            ...DB::table('posts')->pluck('body')->all(),
            ...DB::table('posts')->whereNotNull('cover_image_path')->pluck('cover_image_path')->all(),
            ...DB::table('tweets')->pluck('body')->all(),
            ...DB::table('tweets')->whereNotNull('media')->pluck('media')->all(),
            ...DB::table('pages')->pluck('body')->all(),
            ...DB::table('pages')->whereNotNull('cover_image_path')->pluck('cover_image_path')->all(),
            ...DB::table('categories')->whereNotNull('cover_image_path')->pluck('cover_image_path')->all(),
        ]);
        // Tweet media is stored as JSON, where "/" may be escaped as "\/".
        $haystack .= "\n".str_replace('\\/', '/', $haystack);

        return Media::query()->get(['id', 'path'])
            ->reject(fn (Media $m) => str_contains($haystack, $m->path))
            ->pluck('id')
            ->all();
    }

    /** @return array{badge: string, title: string, hint: string, url: string, count: int}|null */
    public function attentionItem(): ?array
    {
        $n = count($this->unusedIds());

        return $n === 0 ? null : [
            'badge' => '🖼', 'count' => $n,
            'title' => "{$n} 個媒體未被使用",
            'hint' => '可以清理媒體庫空間',
            'url' => route('admin.media.index', ['unused' => 1]),
        ];
    }
}
