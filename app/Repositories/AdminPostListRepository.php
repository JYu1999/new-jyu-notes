<?php

namespace App\Repositories;

use App\Models\Post;
use App\Services\TranslationStatusService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Admin post list (JYU-545): one row per article (translation group, shown via
 * its original), with every language version's status. The trash lists
 * individual posts, since each language is trashed on its own.
 */
class AdminPostListRepository
{
    public const STATUSES = ['all', 'published', 'draft', 'hidden', 'trashed'];

    public const SORTS = ['updated', 'published', 'views'];

    public function __construct(private TranslationStatusService $translations) {}

    /**
     * @param  array{status?: ?string, q?: ?string, category?: ?int, tag?: ?int, translation?: ?string, sort?: ?string}  $f
     */
    public function paginate(array $f, int $perPage = 20): LengthAwarePaginator
    {
        $status = $f['status'] ?? 'all';

        if ($status === 'trashed') {
            return Post::onlyTrashed()
                ->with(['categories.translations'])
                ->when($f['q'] ?? null, fn ($q, $term) => $this->matchText($q, $term))
                ->orderByDesc('deleted_at')
                ->paginate($perPage)
                ->withQueryString();
        }

        $q = $this->translations->originals()
            ->with(['categories.translations', 'tags'])
            ->select('posts.*')
            ->selectSub(fn ($s) => $s->from('posts as v')->whereColumn('v.post_group_id', 'posts.post_group_id')
                ->whereNull('v.deleted_at')->selectRaw('coalesce(sum(v.views_count), 0)'), 'group_views');

        if ($status !== 'all') {
            $q->where('posts.status', $status);
        }

        if ($term = $f['q'] ?? null) {
            // Match any language version of the article.
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $q->whereExists(fn ($s) => $s->from('posts as m')
                ->whereColumn('m.post_group_id', 'posts.post_group_id')
                ->whereNull('m.deleted_at')
                ->where(fn ($w) => $w->where('m.title', 'ILIKE', $like)->orWhere('m.slug', 'ILIKE', $like)->orWhere('m.body', 'ILIKE', $like)));
        }

        if ($category = $f['category'] ?? null) {
            $q->whereHas('categories', fn ($c) => $c->where('categories.id', $category));
        }
        if ($tag = $f['tag'] ?? null) {
            $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tag));
        }

        if (preg_match('/^(missing|stale):(zh|en|ja|vi|id)$/', (string) ($f['translation'] ?? ''), $m)) {
            if ($m[1] === 'missing') {
                $q->whereIn('posts.id', $this->translations->missing($m[2])->select('posts.id'));
            } else {
                $q->whereIn('posts.post_group_id', $this->translations->stale($m[2])->select('posts.post_group_id'));
            }
        }

        match ($f['sort'] ?? 'updated') {
            'published' => $q->orderByRaw('posts.published_at desc nulls last'),
            'views' => $q->orderByDesc('group_views'),
            default => $q->orderByDesc('posts.updated_at'),
        };

        return $q->orderByDesc('posts.id')->paginate($perPage)->withQueryString();
    }

    /**
     * Status per language for the articles on a page.
     *
     * @return array<int, array<string, array{state: string, post: ?Post}>> keyed by post_group_id, then locale.
     *                                                                      state: published | draft | hidden | stale | missing
     */
    public function languageStates(Collection $originals): array
    {
        $groupIds = $originals->pluck('post_group_id')->unique()->values();
        $versions = Post::query()->whereIn('post_group_id', $groupIds)->get()->groupBy('post_group_id');
        $staleIds = $this->translations->stale()->whereIn('posts.post_group_id', $groupIds)->pluck('posts.id')->flip();

        $out = [];
        foreach ($groupIds as $gid) {
            foreach (TranslationStatusService::LOCALES as $locale) {
                $post = ($versions[$gid] ?? collect())->firstWhere('locale', $locale);
                $out[$gid][$locale] = [
                    'post' => $post,
                    'state' => match (true) {
                        $post === null => 'missing',
                        isset($staleIds[$post->id]) => 'stale',
                        default => $post->status,
                    },
                ];
            }
        }

        return $out;
    }

    /** @return array<string, int> tab => count (articles; trash counts posts) */
    public function counts(): array
    {
        $byStatus = $this->translations->originals()
            ->selectRaw('posts.status, count(*) as n')->groupBy('posts.status')->pluck('n', 'status');

        return [
            'all' => (int) $byStatus->sum(),
            'published' => (int) ($byStatus['published'] ?? 0),
            'draft' => (int) ($byStatus['draft'] ?? 0),
            'hidden' => (int) ($byStatus['hidden'] ?? 0),
            'trashed' => Post::onlyTrashed()->count(),
        ];
    }

    private function matchText(Builder $q, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $q->where(fn ($w) => $w->where('title', 'ILIKE', $like)->orWhere('slug', 'ILIKE', $like)->orWhere('body', 'ILIKE', $like));
    }
}
