<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostPublishTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makePost(): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create([])->id,
            'locale' => 'zh', 'slug' => 'hello', 'title' => 'Hello', 'body' => 'Body',
            'status' => Post::STATUS_DRAFT, 'last_modified_at' => now(),
        ]);
    }

    public function test_publish_forbidden_without_publish_ability(): void
    {
        $post = $this->makePost();
        Sanctum::actingAs($this->user(), ['posts:update']); // not publish

        $this->postJson("/api/posts/{$post->id}/publish")->assertForbidden();
        $this->assertSame(Post::STATUS_DRAFT, $post->fresh()->status);
    }

    public function test_publish_with_ability_sets_published(): void
    {
        $post = $this->makePost();
        Sanctum::actingAs($this->user(), ['posts:publish']);

        $this->postJson("/api/posts/{$post->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', Post::STATUS_PUBLISHED);

        $fresh = $post->fresh();
        $this->assertSame(Post::STATUS_PUBLISHED, $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_translation_creates_same_group_draft(): void
    {
        $post = $this->makePost();
        Sanctum::actingAs($this->user(), ['posts:create']);

        $res = $this->postJson("/api/posts/{$post->id}/translations", ['locale' => 'en'])
            ->assertCreated();

        $res->assertJsonPath('data.locale', 'en');
        $res->assertJsonPath('data.post_group_id', $post->post_group_id);
        $res->assertJsonPath('data.status', Post::STATUS_DRAFT);
    }
}
