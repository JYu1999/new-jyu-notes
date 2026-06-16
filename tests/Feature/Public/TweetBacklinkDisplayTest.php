<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TweetBacklinkDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_mentioning_tweet_appears_in_tweet_backlinks(): void
    {
        $tweet = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '原始推文', 'status' => 'published',
        ]);
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'cites-tweet', 'title' => '引用推文的文章',
            'body' => "見 [推文](/zh/tweets/{$tweet->id})", 'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->get("/zh/tweets/{$tweet->id}")
            ->assertOk()
            ->assertSee('引用推文的文章');
    }

    public function test_tweet_mentioning_tweet_appears_in_backlinks(): void
    {
        $target = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '被提及的推文', 'status' => 'published',
        ]);
        app(TweetService::class)->create([
            'locale' => 'zh', 'body' => "回應 [這則](/zh/tweets/{$target->id})", 'status' => 'published',
        ]);

        $this->get("/zh/tweets/{$target->id}")
            ->assertOk()
            ->assertSee('回應');
    }

    public function test_draft_source_hidden_from_tweet_backlinks(): void
    {
        $tweet = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '目標推文', 'status' => 'published',
        ]);
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'draft-cite', 'title' => '草稿引用',
            'body' => "[x](/zh/tweets/{$tweet->id})", 'status' => Post::STATUS_DRAFT,
        ]);

        $this->get("/zh/tweets/{$tweet->id}")
            ->assertOk()
            ->assertDontSee('草稿引用');
    }
}
