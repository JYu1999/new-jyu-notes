<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tweet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Unfinished writing for the admin home "繼續寫" and the drafts list (JYU-542):
 * draft posts and draft tweets, most recently edited first.
 */
class DraftService
{
    /**
     * @return Collection<int, array{type: string, model: Post|Tweet, kind: string, locale: string, title: string, detail: string, edit_url: string, updated_at: Carbon}>
     */
    public function recent(?int $limit = null): Collection
    {
        $posts = Post::query()->where('status', Post::STATUS_DRAFT)->latest('updated_at')
            ->when($limit, fn ($q) => $q->limit($limit))->get();
        $tweets = Tweet::query()->where('status', Tweet::STATUS_DRAFT)->latest('updated_at')
            ->when($limit, fn ($q) => $q->limit($limit))->get();

        $items = $posts->map(fn (Post $p) => $this->fromPost($p))
            ->concat($tweets->map(fn (Tweet $t) => $this->fromTweet($t)))
            ->sortByDesc(fn ($i) => $i['updated_at']->getTimestamp())
            ->values();

        return $limit ? $items->take($limit)->values() : $items;
    }

    public function count(): int
    {
        return Post::query()->where('status', Post::STATUS_DRAFT)->count()
            + Tweet::query()->where('status', Tweet::STATUS_DRAFT)->count();
    }

    private function fromPost(Post $post): array
    {
        $source = $this->translatedFrom($post);
        $chars = mb_strlen(preg_replace('/\s+/u', '', strip_tags((string) $post->body)));

        return [
            'type' => 'post',
            'model' => $post,
            'kind' => $source ? '翻譯' : '文章',
            'locale' => $post->locale,
            'title' => $post->title ?: '（未命名）',
            'detail' => $source
                ? '譯自〈'.$source->title.'〉'
                : number_format($chars).' 字 · 約 '.$post->reading_time.' 分鐘',
            'edit_url' => route('admin.posts.edit', $post),
            'updated_at' => $post->updated_at,
        ];
    }

    private function fromTweet(Tweet $tweet): array
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $tweet->body)));

        return [
            'type' => 'tweet',
            'model' => $tweet,
            'kind' => '碎念',
            'locale' => $tweet->locale,
            'title' => '（未命名）'.Str::limit($plain, 40),
            'detail' => '',
            'edit_url' => route('admin.tweets.edit', $tweet),
            'updated_at' => $tweet->updated_at,
        ];
    }

    /** A draft is a translation when an earlier post (lower id) in the same group exists in another language. */
    private function translatedFrom(Post $post): ?Post
    {
        return Post::query()
            ->where('post_group_id', $post->post_group_id)
            ->where('locale', '!=', $post->locale)
            ->where('id', '<', $post->id)
            ->orderBy('id')
            ->first();
    }
}
