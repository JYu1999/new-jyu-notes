<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionSearchTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): self
    {
        return $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]));
    }

    public function test_returns_both_posts_and_tweets_with_type(): void
    {
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'monitoring', 'title' => '導入監控',
            'body' => 'b', 'status' => Post::STATUS_PUBLISHED,
        ]);
        app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '今天聊聊監控這件事', 'status' => 'published',
        ]);

        $res = $this->actingAdmin()->getJson('/admin/mentions/search?q=監控&locale=zh');

        $res->assertOk();
        $types = collect($res->json())->pluck('type')->unique()->values()->all();
        sort($types);
        $this->assertSame(['post', 'tweet'], $types);
    }

    public function test_tweet_result_label_is_body_snippet_and_url_is_tweet_path(): void
    {
        $tweet = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '只有推文內容沒有標題', 'status' => 'published',
        ]);

        $res = $this->actingAdmin()->getJson('/admin/mentions/search?q=推文&locale=zh');

        $item = collect($res->json())->firstWhere('type', 'tweet');
        $this->assertNotNull($item);
        $this->assertStringContainsString('推文', $item['label']);
        $this->assertSame("/zh/tweets/{$tweet->id}", $item['url']);
    }

    public function test_excludes_self_by_type_and_id(): void
    {
        $tweet = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '自我參照測試內容', 'status' => 'published',
        ]);

        $res = $this->actingAdmin()->getJson(
            "/admin/mentions/search?q=自我&locale=zh&exclude_type=tweet&exclude_id={$tweet->id}"
        );

        $this->assertEmpty(collect($res->json())->where('type', 'tweet')->all());
    }
}
