<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makePost(string $status = Post::STATUS_DRAFT, string $locale = 'zh', string $slug = 'hello'): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create([])->id,
            'locale' => $locale, 'slug' => $slug, 'title' => 'Hello', 'body' => 'Body',
            'status' => $status, 'last_modified_at' => now(),
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/posts')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['posts:create']); // lacks read
        $this->getJson('/api/posts')->assertForbidden();
    }

    public function test_index_lists_all_statuses_paginated(): void
    {
        $this->makePost(Post::STATUS_DRAFT, 'zh', 'draft-one');
        $this->makePost(Post::STATUS_PUBLISHED, 'en', 'pub-one');
        Sanctum::actingAs($this->user(), ['posts:read']);

        $res = $this->getJson('/api/posts')->assertOk();
        $res->assertJsonStructure(['data' => [['id', 'locale', 'slug', 'title', 'status', 'tag_ids']], 'links', 'meta']);
        $this->assertCount(2, $res->json('data')); // includes the draft
    }

    public function test_create_makes_a_draft(): void
    {
        Sanctum::actingAs($this->user(), ['posts:create']);

        $res = $this->postJson('/api/posts', [
            'locale' => 'zh', 'title' => 'New', 'body' => 'Content',
        ])->assertCreated();

        $res->assertJsonPath('data.status', Post::STATUS_DRAFT);
        $this->assertDatabaseHas('posts', ['title' => 'New', 'status' => 'draft']);
    }

    public function test_update_cannot_change_status(): void
    {
        $post = $this->makePost(Post::STATUS_DRAFT);
        Sanctum::actingAs($this->user(), ['posts:update']);

        $this->patchJson("/api/posts/{$post->id}", [
            'title' => 'Edited', 'status' => 'published',
        ])->assertOk();

        $fresh = $post->fresh();
        $this->assertSame('Edited', $fresh->title);
        $this->assertSame(Post::STATUS_DRAFT, $fresh->status); // still draft
    }

    public function test_delete_soft_deletes(): void
    {
        $post = $this->makePost();
        Sanctum::actingAs($this->user(), ['posts:delete']);

        $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }
}
