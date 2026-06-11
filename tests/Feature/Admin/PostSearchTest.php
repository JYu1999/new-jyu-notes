<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makePost(array $attrs): Post
    {
        return app(\App\Services\PostService::class)->create(array_merge([
            'locale' => 'zh',
            'title' => 'Untitled',
            'body' => 'b',
            'status' => Post::STATUS_PUBLISHED,
        ], $attrs));
    }

    public function test_search_matches_title_and_returns_url(): void
    {
        $this->makePost(['title' => 'Cloudflare R2 圖床', 'slug' => 'r2-images']);

        $res = $this->actingAs($this->admin())
            ->getJson('/admin/posts/search?q=R2&locale=zh');

        $res->assertOk()
            ->assertJsonFragment(['slug' => 'r2-images', 'url' => '/zh/posts/r2-images']);
    }

    public function test_search_excludes_drafts_and_other_locales_and_self(): void
    {
        $self = $this->makePost(['title' => 'Self R2', 'slug' => 'self']);
        $this->makePost(['title' => 'Draft R2', 'slug' => 'draft', 'status' => Post::STATUS_DRAFT]);
        $this->makePost(['title' => 'EN R2', 'slug' => 'en-r2', 'locale' => 'en']);
        $this->makePost(['title' => 'Other R2', 'slug' => 'other']);

        $res = $this->actingAs($this->admin())
            ->getJson("/admin/posts/search?q=R2&locale=zh&exclude={$self->id}");

        $res->assertOk();
        $data = $res->json();
        $slugs = array_column($data, 'slug');
        $this->assertContains('other', $slugs);
        $this->assertNotContains('draft', $slugs);
        $this->assertNotContains('en-r2', $slugs);
        $this->assertNotContains('self', $slugs);
    }

    public function test_empty_query_returns_empty_array(): void
    {
        $res = $this->actingAs($this->admin())->getJson('/admin/posts/search?q=');
        $res->assertOk()->assertExactJson([]);
    }
}
