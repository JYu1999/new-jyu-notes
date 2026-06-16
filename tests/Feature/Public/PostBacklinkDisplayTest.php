<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostBacklinkDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_post_source_appears_in_backlinks(): void
    {
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'target', 'title' => '目標文章',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'source', 'title' => '來源文章',
            'body' => '看 [這篇](/zh/posts/target)', 'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->get('/zh/posts/target')
            ->assertOk()
            ->assertSee('來源文章');
    }

    public function test_tweet_source_appears_in_post_backlinks(): void
    {
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'target-x', 'title' => '被推文提及的文章',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '推薦這篇 [文章](/zh/posts/target-x)', 'status' => 'published',
        ]);

        $this->get('/zh/posts/target-x')
            ->assertOk()
            ->assertSee('推薦這篇');
    }

    public function test_draft_source_is_hidden(): void
    {
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'target2', 'title' => '目標2',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'draft-source', 'title' => '草稿來源',
            'body' => '[x](/zh/posts/target2)', 'status' => Post::STATUS_DRAFT,
        ]);

        $this->get('/zh/posts/target2')
            ->assertOk()
            ->assertDontSee('草稿來源');
    }
}
