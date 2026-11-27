<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JYU-535: reading a long post — structure, progress and tools.
 */
class PostReadingTest extends TestCase
{
    use RefreshDatabase;

    private const BODY = "前言段落\n\n## Jira\n\n內容\n\n### Cursor\n\n```json\n{\"a\": 1}\n```\n\n## Gitlab\n\n內容\n\n## 測試\n\n結尾";

    private function makePost(array $attrs = [], ?PostGroup $group = null): Post
    {
        return Post::create([
            'post_group_id' => ($group ?? PostGroup::create([]))->id, 'locale' => 'zh', 'slug' => 'mcp',
            'title' => 'MCP 初體驗', 'body' => self::BODY, 'status' => Post::STATUS_PUBLISHED,
            'published_at' => Carbon::parse('2025-12-20 10:00'), 'last_modified_at' => Carbon::parse('2025-12-20 10:00'),
            'views_count' => 812,
            ...$attrs,
        ]);
    }

    public function test_breadcrumb_title_and_meta(): void
    {
        $post = $this->makePost(['last_modified_at' => Carbon::parse('2026-01-02 09:00')]);
        $cat = Category::create(['sort_method' => 'date_desc']);
        $cat->translations()->create(['locale' => 'zh', 'name' => '技術', 'slug' => 'tech']);
        $post->categories()->attach($cat);

        $html = $this->get('/zh/posts/mcp')->assertOk()->getContent();

        preg_match('/<nav[^>]*aria-label="麵包屑"[^>]*>(.*?)<\/nav>/s', $html, $crumb);
        $this->assertNotEmpty($crumb);
        $this->assertStringContainsString('href="'.url('/zh/posts').'"', $crumb[1]);
        $this->assertStringContainsString('href="'.url('/zh/categories/tech').'"', $crumb[1]);
        $this->assertStringContainsString('技術', $crumb[1]);

        $this->assertMatchesRegularExpression('/<h1[^>]*>MCP 初體驗<\/h1>/u', $html);
        $this->assertStringContainsString('2025 年 12 月 20 日', $html);
        $this->assertStringContainsString('更新於 2026/01/02', $html);
        $this->assertMatchesRegularExpression('/約 \d+ 分鐘/u', $html);
        $this->assertStringContainsString('812 次瀏覽', $html);
    }

    public function test_updated_date_is_hidden_when_never_edited_after_publishing(): void
    {
        $this->makePost();

        $this->get('/zh/posts/mcp')->assertDontSee('更新於');
    }

    public function test_breadcrumb_without_category_has_only_posts_link(): void
    {
        $this->makePost();

        $html = $this->get('/zh/posts/mcp')->getContent();
        preg_match('/<nav[^>]*aria-label="麵包屑"[^>]*>(.*?)<\/nav>/s', $html, $crumb);

        $this->assertSame(1, substr_count($crumb[1], '<a '));
    }

    public function test_other_languages_box_lists_published_translations_only(): void
    {
        $group = PostGroup::create([]);
        $this->makePost([], $group);
        $this->makePost(['locale' => 'en', 'slug' => 'mcp-en', 'title' => 'First look at MCP'], $group);
        $this->makePost(['locale' => 'ja', 'slug' => 'mcp-ja', 'title' => 'MCP', 'status' => Post::STATUS_DRAFT], $group);

        $html = $this->get('/zh/posts/mcp')->getContent();

        $this->assertStringContainsString('這篇也有其他語言：', $html);
        $this->assertMatchesRegularExpression('/<a [^>]*href="'.preg_quote(url('/en/posts/mcp-en'), '/').'"[^>]*lang="en"[^>]*>English<\/a>/', $html);
        $this->assertStringNotContainsString(url('/ja/posts/mcp-ja'), $html);
    }

    public function test_no_other_languages_box_without_translations(): void
    {
        $this->makePost();

        $this->get('/zh/posts/mcp')->assertDontSee('這篇也有其他語言');
    }

    public function test_cover_image_comes_after_title_and_meta(): void
    {
        $this->makePost(['cover_image_path' => 'uploads/cover.jpg']);

        $html = $this->get('/zh/posts/mcp')->getContent();

        $this->assertLessThan(strpos($html, 'uploads/cover.jpg'), strpos($html, '812 次瀏覽'));
    }

    public function test_desktop_table_of_contents_is_sticky_and_tracks_active_section(): void
    {
        $this->makePost();

        $html = $this->get('/zh/posts/mcp')->getContent();

        preg_match('/<nav[^>]*data-toc="desktop"[^>]*>(.*?)<\/nav>/s', $html, $toc);
        $this->assertNotEmpty($toc, 'Desktop TOC missing');
        $this->assertMatchesRegularExpression('/data-toc="desktop"[^>]*class="[^"]*sticky/', $toc[0]);
        $this->assertStringContainsString('本文目錄', $toc[1]);
        foreach (['Jira', 'Cursor', 'Gitlab', '測試'] as $heading) {
            $this->assertStringContainsString($heading, $toc[1]);
        }
        $this->assertStringContainsString('aria-current', $toc[1]);
    }

    public function test_reading_progress_bar_is_in_the_header(): void
    {
        $this->makePost();

        $html = $this->get('/zh/posts/mcp')->getContent();
        preg_match('/<header[^>]*>.*?<\/header>/s', $html, $header);

        $this->assertMatchesRegularExpression('/role="progressbar"[^>]*aria-label="閱讀進度"/u', $header[0]);
    }

    public function test_code_blocks_get_a_copy_button_hook(): void
    {
        $this->makePost();

        $html = $this->get('/zh/posts/mcp')->getContent();

        // Buttons are added by resources/js/post-reading.js; the article provides labels.
        $this->assertMatchesRegularExpression('/data-post-body[^>]*data-copy-label="複製"[^>]*data-copied-label="已複製"/u', $html);
        $this->assertStringContainsString('<pre>', $html);
    }

    public function test_font_size_controls_back_to_top_and_copy_link(): void
    {
        $this->makePost();

        $html = $this->get('/zh/posts/mcp')->getContent();

        $this->assertStringContainsString('aria-label="縮小文章字級"', $html);
        $this->assertStringContainsString('aria-label="放大文章字級"', $html);
        $this->assertStringContainsString('aria-label="回到頂部"', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-copy-link[^>]*>[\s\S]*?複製連結/u', $html);
    }

    public function test_font_size_preference_is_restored_before_paint(): void
    {
        $this->makePost();

        $html = $this->get('/zh/posts/mcp')->getContent();

        // An inline script right after the article body reads the saved step from localStorage.
        $this->assertMatchesRegularExpression('/<script>[^<]*localStorage[^<]*post-font-step/', $html);
    }

    public function test_mobile_toc_sheet_shows_section_progress(): void
    {
        $this->makePost();

        $html = $this->get('/zh/posts/mcp')->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*aria-label="開啟目錄"/u', $html);
        preg_match('/<div[^>]*role="dialog"[^>]*data-toc="mobile"[^>]*>(.*?)<\/ol>/s', $html, $sheet);
        $this->assertNotEmpty($sheet, 'Mobile TOC sheet missing');
        $this->assertStringContainsString('讀到第', $sheet[1]);
        $this->assertSame(4, substr_count($sheet[1], 'data-toc-id='));
    }

    public function test_post_without_headings_has_no_toc(): void
    {
        $this->makePost(['body' => '只有一段文字。']);

        $html = $this->get('/zh/posts/mcp')->getContent();

        $this->assertStringNotContainsString('data-toc="desktop"', $html);
        $this->assertStringNotContainsString('aria-label="開啟目錄"', $html);
        // Tools still available.
        $this->assertStringContainsString('aria-label="回到頂部"', $html);
    }

    public function test_page_strings_are_translated(): void
    {
        $this->makePost(['locale' => 'en', 'slug' => 'mcp-en']);

        $html = $this->get('/en/posts/mcp-en')->assertOk()->getContent();

        $this->assertStringContainsString('Dec 20, 2025', $html);
        $this->assertStringContainsString('812 views', $html);
        $this->assertStringContainsString('On this page', $html);
    }
}
