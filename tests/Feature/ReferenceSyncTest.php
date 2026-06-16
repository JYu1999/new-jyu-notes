<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tweet;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function posts(): PostService
    {
        return app(PostService::class);
    }

    private function tweets(): TweetService
    {
        return app(TweetService::class);
    }

    private function makePost(string $slug, string $body): Post
    {
        return $this->posts()->create([
            'locale' => 'zh', 'slug' => $slug, 'title' => $slug,
            'body' => $body, 'status' => Post::STATUS_PUBLISHED,
        ]);
    }

    private function makeTweet(string $body): Tweet
    {
        return $this->tweets()->create([
            'locale' => 'zh', 'body' => $body, 'status' => Tweet::STATUS_PUBLISHED,
        ]);
    }

    public function test_post_referencing_post_is_recorded(): void
    {
        $target = $this->makePost('target', 'body');
        $source = $this->makePost('source', "看 [連結](/zh/posts/{$target->slug})");

        $this->assertCount(1, $source->fresh()->outgoingReferences);
        $this->assertTrue(
            $target->fresh()->publishedBacklinks()->contains(fn ($m) => $m->is($source))
        );
    }

    public function test_post_referencing_tweet_is_recorded(): void
    {
        $tweet = $this->makeTweet('原推文');
        $post = $this->makePost('p', "引用推文 [t](/zh/tweets/{$tweet->id})");

        $backlinks = $tweet->fresh()->publishedBacklinks();
        $this->assertCount(1, $backlinks);
        $this->assertTrue($backlinks->first()->is($post));
    }

    public function test_tweet_referencing_post_is_recorded(): void
    {
        $post = $this->makePost('p2', 'body');
        $tweet = $this->makeTweet("看文章 [a](/zh/posts/{$post->slug})");

        $backlinks = $post->fresh()->publishedBacklinks();
        $this->assertCount(1, $backlinks);
        $this->assertTrue($backlinks->first()->is($tweet));
    }

    public function test_tweet_referencing_tweet_is_recorded(): void
    {
        $target = $this->makeTweet('被提及');
        $source = $this->makeTweet("看這則 [t](/zh/tweets/{$target->id})");

        $this->assertCount(1, $source->fresh()->outgoingReferences);
        $this->assertTrue($target->fresh()->publishedBacklinks()->first()->is($source));
    }

    public function test_removing_link_on_update_removes_reference(): void
    {
        $target = $this->makePost('t3', 'body');
        $source = $this->makePost('s3', "[x](/zh/posts/{$target->slug})");
        $this->assertCount(1, $source->fresh()->outgoingReferences);

        $this->posts()->update($source, ['body' => '已無連結']);

        $this->assertCount(0, $source->fresh()->outgoingReferences);
    }

    public function test_post_self_link_is_ignored(): void
    {
        $post = $this->makePost('me', '指向自己 [self](/zh/posts/me)');
        $this->assertCount(0, $post->fresh()->outgoingReferences);
    }

    public function test_tweet_self_link_is_ignored(): void
    {
        $tweet = $this->makeTweet('placeholder');
        $this->tweets()->update($tweet, ['body' => "self [x](/zh/tweets/{$tweet->id})"]);
        $this->assertCount(0, $tweet->fresh()->outgoingReferences);
    }

    public function test_unresolvable_link_is_ignored(): void
    {
        $source = $this->makePost('s4', '[ghost](/zh/posts/nope) [t](/zh/tweets/999999)');
        $this->assertCount(0, $source->fresh()->outgoingReferences);
    }
}
