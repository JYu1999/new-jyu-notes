<?php

namespace Tests\Feature\Public;

use App\Models\Tweet;
use App\Models\User;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TweetCardSensitiveTest extends TestCase
{
    use RefreshDatabase;

    private function publishedTweet(array $media): Tweet
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);

        return app(TweetService::class)->create([
            'body' => 'hello world', 'locale' => 'zh', 'author_id' => $admin->id,
            'status' => 'published', 'published_at' => now()->subDay(),
            'media' => $media,
        ]);
    }

    public function test_sensitive_image_renders_blur_wrapper(): void
    {
        $tweet = $this->publishedTweet([
            ['path' => 'uploads/a.jpg', 'type' => 'image', 'alt' => 'x', 'sensitive' => true],
        ]);

        $this->get(route('public.tweets.show', ['zh', $tweet->id]))
            ->assertOk()
            ->assertSee('sensitive-media', false)
            ->assertSee('敏感內容', false);
    }

    public function test_plain_image_is_lightbox_clickable_without_blur(): void
    {
        $tweet = $this->publishedTweet([
            ['path' => 'uploads/b.jpg', 'type' => 'image', 'alt' => 'y', 'sensitive' => false],
        ]);

        $html = $this->get(route('public.tweets.show', ['zh', $tweet->id]))
            ->assertOk()
            ->assertSee('tweet-media-clickable', false)
            ->getContent();

        $this->assertStringNotContainsString('sensitive-media', $html);
    }
}
