<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
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
        return app(PostService::class)->create(array_merge([
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
            ->getJson('/admin/mentions/search?q=R2&locale=zh');

        $res->assertOk();
        $posts = collect($res->json())->where('type', 'post');
        $urls = $posts->pluck('url')->all();
        $this->assertContains('/zh/posts/r2-images', $urls);
    }

    public function test_search_excludes_drafts_and_other_locales_and_self(): void
    {
        $self = $this->makePost(['title' => 'Self R2', 'slug' => 'self']);
        $this->makePost(['title' => 'Draft R2', 'slug' => 'draft', 'status' => Post::STATUS_DRAFT]);
        $this->makePost(['title' => 'EN R2', 'slug' => 'en-r2', 'locale' => 'en']);
        $this->makePost(['title' => 'Other R2', 'slug' => 'other']);

        $res = $this->actingAs($this->admin())
            ->getJson("/admin/mentions/search?q=R2&locale=zh&exclude_type=post&exclude_id={$self->id}");

        $res->assertOk();
        $postUrls = collect($res->json())->where('type', 'post')->pluck('url')->all();
        $this->assertContains('/zh/posts/other', $postUrls);
        $this->assertNotContains('/zh/posts/draft', $postUrls);
        $this->assertNotContains('/zh/posts/en-r2', $postUrls);
        $this->assertNotContains('/zh/posts/self', $postUrls);
    }

    public function test_empty_query_returns_recent_published_posts(): void
    {
        $this->makePost(['title' => 'Older', 'slug' => 'older', 'published_at' => '2024-01-01 00:00:00']);
        $this->makePost(['title' => 'Newer', 'slug' => 'newer', 'published_at' => '2025-01-01 00:00:00']);
        $this->makePost(['title' => 'Hidden draft', 'slug' => 'd', 'status' => Post::STATUS_DRAFT, 'published_at' => '2025-06-01 00:00:00']);

        $res = $this->actingAs($this->admin())->getJson('/admin/mentions/search?q=&locale=zh');

        $res->assertOk();
        $postUrls = collect($res->json())->where('type', 'post')->pluck('url')->all();
        // 最近發佈的在前，且不含草稿
        $this->assertContains('/zh/posts/newer', $postUrls);
        $this->assertContains('/zh/posts/older', $postUrls);
        $this->assertNotContains('/zh/posts/d', $postUrls);
    }

    public function test_multi_keyword_matches_any_order(): void
    {
        // 標題含「導入」與「監控」但順序相反、且不相鄰——精確子字串搜尋會漏掉，多關鍵字則命中。
        $this->makePost(['title' => '聊聊公司導入監控的契機', 'slug' => 'monitoring']);
        $this->makePost(['title' => '完全無關的文章', 'slug' => 'unrelated']);

        $res = $this->actingAs($this->admin())
            ->getJson('/admin/mentions/search?'.http_build_query(['q' => '監控 導入', 'locale' => 'zh']));

        $res->assertOk();
        $postUrls = collect($res->json())->where('type', 'post')->pluck('url')->all();
        $this->assertContains('/zh/posts/monitoring', $postUrls);
        $this->assertNotContains('/zh/posts/unrelated', $postUrls);
    }
}
