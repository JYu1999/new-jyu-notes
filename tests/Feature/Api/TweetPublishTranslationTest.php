<?php

namespace Tests\Feature\Api;

use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TweetPublishTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeTweet(): Tweet
    {
        return Tweet::create([
            'tweet_group_id' => TweetGroup::create([])->id,
            'locale' => 'zh', 'body' => 'A note', 'status' => Tweet::STATUS_DRAFT,
        ]);
    }

    public function test_publish_forbidden_without_publish_ability(): void
    {
        $tweet = $this->makeTweet();
        Sanctum::actingAs($this->user(), ['tweets:update']);

        $this->postJson("/api/tweets/{$tweet->id}/publish")->assertForbidden();
        $this->assertSame(Tweet::STATUS_DRAFT, $tweet->fresh()->status);
    }

    public function test_publish_with_ability_sets_published(): void
    {
        $tweet = $this->makeTweet();
        Sanctum::actingAs($this->user(), ['tweets:publish']);

        $this->postJson("/api/tweets/{$tweet->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', Tweet::STATUS_PUBLISHED);

        $this->assertNotNull($tweet->fresh()->published_at);
    }

    public function test_translation_creates_same_group_draft(): void
    {
        $tweet = $this->makeTweet();
        Sanctum::actingAs($this->user(), ['tweets:create']);

        $this->postJson("/api/tweets/{$tweet->id}/translations", ['locale' => 'en'])
            ->assertCreated()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.tweet_group_id', $tweet->tweet_group_id)
            ->assertJsonPath('data.status', Tweet::STATUS_DRAFT);
    }
}
