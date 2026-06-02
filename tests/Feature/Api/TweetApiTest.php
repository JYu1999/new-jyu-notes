<?php

namespace Tests\Feature\Api;

use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TweetApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeTweet(string $status = Tweet::STATUS_DRAFT, string $locale = 'zh'): Tweet
    {
        return Tweet::create([
            'tweet_group_id' => TweetGroup::create([])->id,
            'locale' => $locale, 'body' => 'A note', 'status' => $status,
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/tweets')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['tweets:create']);
        $this->getJson('/api/tweets')->assertForbidden();
    }

    public function test_index_paginated_all_statuses(): void
    {
        $this->makeTweet(Tweet::STATUS_DRAFT);
        $this->makeTweet(Tweet::STATUS_PUBLISHED, 'en');
        Sanctum::actingAs($this->user(), ['tweets:read']);

        $res = $this->getJson('/api/tweets')->assertOk();
        $res->assertJsonStructure(['data' => [['id', 'locale', 'body', 'status', 'media', 'tag_ids']], 'links', 'meta']);
        $this->assertCount(2, $res->json('data'));
    }

    public function test_create_makes_a_draft(): void
    {
        Sanctum::actingAs($this->user(), ['tweets:create']);

        $this->postJson('/api/tweets', ['locale' => 'zh', 'body' => 'Hello note'])
            ->assertCreated()
            ->assertJsonPath('data.status', Tweet::STATUS_DRAFT);

        $this->assertDatabaseHas('tweets', ['body' => 'Hello note', 'status' => 'draft']);
    }

    public function test_update_cannot_change_status(): void
    {
        $tweet = $this->makeTweet(Tweet::STATUS_DRAFT);
        Sanctum::actingAs($this->user(), ['tweets:update']);

        $this->patchJson("/api/tweets/{$tweet->id}", ['body' => 'Edited', 'status' => 'published'])
            ->assertOk();

        $fresh = $tweet->fresh();
        $this->assertSame('Edited', $fresh->body);
        $this->assertSame(Tweet::STATUS_DRAFT, $fresh->status);
    }

    public function test_delete_soft_deletes(): void
    {
        $tweet = $this->makeTweet();
        Sanctum::actingAs($this->user(), ['tweets:delete']);

        $this->deleteJson("/api/tweets/{$tweet->id}")->assertNoContent();
        $this->assertSoftDeleted('tweets', ['id' => $tweet->id]);
    }
}
