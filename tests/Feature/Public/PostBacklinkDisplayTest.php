<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostBacklinkDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PostService
    {
        return app(PostService::class);
    }

    public function test_published_source_appears_in_backlinks(): void
    {
        $this->service()->create([
            'locale' => 'zh', 'slug' => 'target', 'title' => '目標文章',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        $this->service()->create([
            'locale' => 'zh', 'slug' => 'source', 'title' => '來源文章',
            'body' => '看 [這篇](/zh/posts/target)', 'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->get('/zh/posts/target')
            ->assertOk()
            ->assertSee('被以下文章提及')
            ->assertSee('來源文章');
    }

    public function test_draft_source_is_hidden(): void
    {
        $this->service()->create([
            'locale' => 'zh', 'slug' => 'target2', 'title' => '目標2',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        $this->service()->create([
            'locale' => 'zh', 'slug' => 'draft-source', 'title' => '草稿來源',
            'body' => '[x](/zh/posts/target2)', 'status' => Post::STATUS_DRAFT,
        ]);

        $this->get('/zh/posts/target2')
            ->assertOk()
            ->assertDontSee('草稿來源');
    }
}
