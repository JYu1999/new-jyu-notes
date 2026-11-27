<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Order of featured articles on the home page (JYU-547).
 * The order lives on every language version of an article (posts.featured_order)
 * so each locale's home page uses the same ranking.
 */
class FeaturedOrderService
{
    public function __construct(private TranslationStatusService $translations) {}

    /** Call after a post is saved: newly featured articles go last, unfeatured ones drop out. */
    public function sync(Post $post): void
    {
        $versions = Post::query()->where('post_group_id', $post->post_group_id);
        $anyFeatured = (clone $versions)->where('is_featured', true)->exists();
        $current = (clone $versions)->whereNotNull('featured_order')->value('featured_order');

        if ($anyFeatured && $current === null) {
            $next = (int) Post::query()->max('featured_order') + 1;
            $versions->update(['featured_order' => $next]);
        } elseif ($anyFeatured) {
            // A new language version joins its article's slot.
            $versions->whereNull('featured_order')->update(['featured_order' => $current]);
        } elseif ($current !== null) {
            $versions->update(['featured_order' => null]);
        }
    }

    /**
     * Featured articles in order, as their original post.
     *
     * @return Collection<int, Post>
     */
    public function articles(): Collection
    {
        return $this->translations->originals()
            ->whereExists(fn ($q) => $q->from('posts as f')
                ->whereColumn('f.post_group_id', 'posts.post_group_id')
                ->where('f.is_featured', true)
                ->whereNull('f.deleted_at'))
            ->orderByRaw('posts.featured_order asc nulls last')
            ->orderByDesc('posts.published_at')
            ->get();
    }

    /** @param  list<int>  $groupIds  featured articles, first = top of the home page */
    public function reorder(array $groupIds): void
    {
        DB::transaction(function () use ($groupIds) {
            foreach (array_values($groupIds) as $i => $groupId) {
                Post::query()->where('post_group_id', $groupId)->update(['featured_order' => $i + 1]);
            }
        });
    }
}
