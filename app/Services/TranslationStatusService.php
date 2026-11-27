<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which post translations are missing or out of date (JYU-544).
 *
 * The original of a translation group is its first post (lowest id).
 * A translation is "比原文舊" (stale) when the original's content was modified
 * after the translation's last content modification. Only modification times
 * are compared (last_modified_at, which only moves on real content edits),
 * not the text itself.
 */
class TranslationStatusService
{
    public const LOCALES = ['zh', 'en', 'ja', 'vi', 'id'];

    /** Human labels used in the admin. */
    public const LANGUAGE_NAMES = ['zh' => '中文', 'en' => '英文', 'ja' => '日文', 'vi' => '越南文', 'id' => '印尼文'];

    public const BADGES = ['zh' => '中', 'en' => 'EN', 'ja' => '日', 'vi' => 'VI', 'id' => 'ID'];

    /** Posts that are the original of their group. */
    public function originals(): Builder
    {
        return Post::query()->whereRaw('posts.id = ('.$this->originalIdSql('posts').')');
    }

    /** Published originals with no translation (any status) in $locale. */
    public function missing(string $locale): Builder
    {
        return $this->originals()
            ->where('posts.status', Post::STATUS_PUBLISHED)
            ->where('posts.locale', '!=', $locale)
            ->whereNotExists(fn ($q) => $q->from('posts as t')
                ->whereColumn('t.post_group_id', 'posts.post_group_id')
                ->where('t.locale', $locale)
                ->whereNull('t.deleted_at'));
    }

    /** Translations in $locale whose original changed after they were last edited. */
    public function stale(?string $locale = null): Builder
    {
        return Post::query()
            ->when($locale, fn ($q) => $q->where('posts.locale', $locale))
            ->whereExists(fn ($q) => $q->from('posts as o')
                ->whereColumn('o.post_group_id', 'posts.post_group_id')
                ->whereRaw('o.id = ('.$this->originalIdSql('posts').')')
                ->whereColumn('o.id', '!=', 'posts.id')
                ->whereColumn('o.last_modified_at', '>', 'posts.last_modified_at'));
    }

    public function isStale(Post $post): bool
    {
        return $this->stale()->whereKey($post->id)->exists();
    }

    /**
     * Languages the author actually translates into: any language that isn't
     * the original language of every group. Missing-translation reminders only
     * cover these, so a never-used language doesn't nag forever.
     *
     * @return list<string>
     */
    public function activeTargetLocales(): array
    {
        return DB::table('posts')
            ->whereNull('deleted_at')
            ->whereRaw('posts.id <> ('.$this->originalIdSql('posts').')')
            ->distinct()
            ->pluck('locale')
            ->sort(fn ($a, $b) => array_search($a, self::LOCALES) <=> array_search($b, self::LOCALES))
            ->values()
            ->all();
    }

    /**
     * "需要你處理" items for translations.
     *
     * @return list<array{badge: string, title: string, hint: string, url: string, count: int}>
     */
    public function attentionItems(): array
    {
        $items = [];
        foreach ($this->activeTargetLocales() as $locale) {
            $n = $this->missing($locale)->count();
            if ($n > 0) {
                $items[] = [
                    'badge' => self::BADGES[$locale], 'count' => $n,
                    'title' => "{$n} 篇文章缺".self::LANGUAGE_NAMES[$locale].'版',
                    'hint' => '點進去會在列表中篩出來',
                    'url' => route('admin.posts.index', ['missing' => $locale]),
                ];
            }
        }
        foreach (self::LOCALES as $locale) {
            $n = $this->stale($locale)->count();
            if ($n > 0) {
                $items[] = [
                    'badge' => self::BADGES[$locale], 'count' => $n,
                    'title' => "{$n} 篇".self::LANGUAGE_NAMES[$locale].'版比原文舊',
                    'hint' => '原文修改後尚未同步',
                    'url' => route('admin.posts.index', ['stale' => $locale]),
                ];
            }
        }

        return $items;
    }

    private function originalIdSql(string $table): string
    {
        return "select min(x.id) from posts as x where x.post_group_id = {$table}.post_group_id and x.deleted_at is null";
    }
}
