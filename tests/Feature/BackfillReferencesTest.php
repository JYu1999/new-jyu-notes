<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tweet;
use App\Models\TweetGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillReferencesTest extends TestCase
{
    use RefreshDatabase;

    /** Raw create bypasses the service sync, simulating legacy rows without references. */
    private function rawPost(string $slug, string $body, string $status = 'published'): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create()->id,
            'locale' => 'zh', 'slug' => $slug, 'title' => $slug,
            'body' => $body, 'status' => $status, 'last_modified_at' => now(),
        ]);
    }

    private function rawTweet(string $body, string $status = 'published'): Tweet
    {
        return Tweet::create([
            'tweet_group_id' => TweetGroup::create()->id,
            'locale' => 'zh', 'body' => $body, 'status' => $status,
            'published_at' => now(),
        ]);
    }

    public function test_backfill_populates_post_and_tweet_references(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '看 [這篇](/zh/posts/target)');
        $tweet = $this->rawTweet('被引用');
        $this->rawPost('cites-tweet', "見 [推文](/zh/tweets/{$tweet->id})");

        $this->assertSame(0, DB::table('content_references')->count());

        $this->artisan('references:backfill')->assertExitCode(0);

        $this->assertSame(2, DB::table('content_references')->count());
    }

    public function test_backfill_is_idempotent(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '[x](/zh/posts/target)');

        $this->artisan('references:backfill')->assertExitCode(0);
        $this->artisan('references:backfill')->assertExitCode(0);

        $this->assertSame(1, DB::table('content_references')->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '[x](/zh/posts/target)');

        $this->artisan('references:backfill --dry-run')->assertExitCode(0);

        $this->assertSame(0, DB::table('content_references')->count());
    }

    public function test_unresolvable_link_is_reported_as_anomaly(): void
    {
        $this->rawPost('source', '[ghost](/zh/posts/does-not-exist)');

        $this->artisan('references:backfill --dry-run')
            ->assertExitCode(0)
            ->expectsOutputToContain('找不到對應');

        $this->artisan('references:backfill --dry-run')
            ->assertExitCode(0)
            ->expectsOutputToContain('posts/does-not-exist');
    }
}
