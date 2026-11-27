<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Existing public pages render the shared UI components
 * (chip / section heading / button / card) rather than per-page styles.
 */
class SharedUiOnPagesTest extends TestCase
{
    use RefreshDatabase;

    private Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = Tag::create(['color' => '#2f5f86']);
        $this->tag->translations()->create(['locale' => 'zh', 'name' => '技術', 'slug' => 'tech']);
    }

    private function publishPost(string $slug, string $title, array $extra = []): Post
    {
        return app(PostService::class)->create([
            'locale' => 'zh', 'slug' => $slug, 'title' => $title, 'body' => "# {$title}\n\n內文",
            'excerpt' => "{$title} 摘要", 'status' => Post::STATUS_PUBLISHED, 'tag_ids' => [$this->tag->id],
            ...$extra,
        ]);
    }

    public function test_home_uses_shared_headings_cards_and_tag_chips(): void
    {
        $this->publishPost('a', '第一篇', ['is_featured' => true]);
        app(TweetService::class)->create(['locale' => 'zh', 'body' => '一則碎念', 'status' => 'published', 'tag_ids' => [$this->tag->id]]);

        $html = $this->get('/zh')->assertOk()->getContent();

        $this->assertStringContainsString('ui-heading', $html);
        $this->assertStringContainsString('ui-card', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*ui-chip[^"]*tag-chip[^"]*"[^>]*style="--tag-color: #2f5f86"/', $html);
        $this->assertStringContainsString('#技術', $html);
    }

    public function test_home_empty_state_uses_card(): void
    {
        $html = $this->get('/zh')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="[^"]*ui-card[^"]*"[^>]*>\s*'.preg_quote(__('public.home_no_featured', [], 'zh'), '/').'/u', $html);
    }

    public function test_post_show_uses_chips_and_cards_for_series_navigation(): void
    {
        $this->publishPost('first', '上一篇文章');
        $post = $this->publishPost('second', '這一篇');

        // Put both posts in the same series so prev/next navigation renders.
        $cat = Category::create(['sort_method' => 'date_asc']);
        $cat->translations()->create(['locale' => 'zh', 'name' => '系列', 'slug' => 'series']);
        Post::query()->each(fn (Post $p) => $p->categories()->attach($cat->id));

        $html = $this->get('/zh/posts/second')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="[^"]*ui-chip[^"]*tag-chip/', $html);
        $this->assertMatchesRegularExpression('/<a [^>]*class="[^"]*ui-card[^"]*ui-card-interactive[^"]*"[^>]*>\s*<div[^>]*>← /u', $html);
    }

    public function test_posts_index_sidebar_uses_label_headings_and_chips(): void
    {
        $this->publishPost('a', '文章 A');

        $html = $this->get('/zh/posts')->assertOk()->getContent();

        $this->assertStringContainsString('ui-heading-label', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*ui-chip/', $html);
    }

    public function test_search_results_use_cards(): void
    {
        $this->publishPost('a', '監控導入');

        $html = $this->get('/zh/search?q=監控')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="[^"]*ui-card[^"]*"[^>]*>[\s\S]*監控導入/u', $html);
    }

    public function test_header_controls_use_shared_icon_buttons(): void
    {
        $html = $this->get('/zh')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button [^>]*class="[^"]*ui-btn-icon[^"]*"[^>]*aria-label="切換深色模式"|<button [^>]*aria-label="切換深色模式"[^>]*class="[^"]*ui-btn-icon/', $html);
    }
}
