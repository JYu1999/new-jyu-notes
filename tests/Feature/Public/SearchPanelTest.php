<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tag;
use App\Models\Tweet;
use App\Models\TweetGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Js;
use Tests\TestCase;

/**
 * JYU-538: ⌘K search panel — JSON endpoint + panel markup on every page.
 */
class SearchPanelTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $locale, string $slug, string $title, string $body, string $at = '2026-03-21'): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create([])->id, 'locale' => $locale, 'slug' => $slug, 'title' => $title,
            'body' => $body, 'status' => Post::STATUS_PUBLISHED, 'published_at' => Carbon::parse($at), 'last_modified_at' => now(),
        ]);
    }

    private function makeTweet(string $locale, string $body, string $at = '2026-03-06'): Tweet
    {
        return Tweet::create([
            'tweet_group_id' => TweetGroup::create([])->id, 'locale' => $locale, 'body' => $body,
            'status' => Tweet::STATUS_PUBLISHED, 'published_at' => Carbon::parse($at),
        ]);
    }

    public function test_suggest_returns_typed_results_with_highlighted_snippet_date_and_tags(): void
    {
        $post = $this->makePost('zh', 'monitoring', '導入監控的契機', "一年多以前，我就想在公司導入監控系統。\n\n後面的內容");
        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => '技術', 'slug' => 'tech']);
        $post->tags()->attach($tag);
        $this->makeTweet('zh', '今天也在看監控儀表板');
        $this->makePost('zh', 'other', '無關文章', '完全沒有關鍵字');

        $json = $this->getJson('/zh/search/suggest?q=監控')->assertOk()->json();

        $this->assertSame(['all' => 2, 'post' => 1, 'tweet' => 1], $json['counts']);
        $this->assertCount(2, $json['results']);

        $p = collect($json['results'])->firstWhere('type', 'post');
        $this->assertSame('導入監控的契機', $p['title']);
        $this->assertSame('監控', $p['snippet']['hit']);
        $this->assertStringContainsString('公司導入', $p['snippet']['pre']);
        $this->assertSame('2026/03/21', $p['date']);
        $this->assertSame(['技術'], $p['tags']);
        $this->assertSame(url('/zh/posts/monitoring'), $p['url']);

        $t = collect($json['results'])->firstWhere('type', 'tweet');
        $this->assertSame('監控', $t['snippet']['hit']);
        $this->assertStringStartsWith(url('/zh/tweets/'), $t['url']);
    }

    public function test_snippet_hit_keeps_original_case(): void
    {
        $this->makePost('en', 'ai', 'Working with AI agents', 'We adopted an AI Agent this week.');

        $json = $this->getJson('/en/search/suggest?q=agent')->json();

        $this->assertSame('Agent', $json['results'][0]['snippet']['hit']);
    }

    public function test_searches_current_language_by_default_and_all_languages_on_request(): void
    {
        $this->makePost('zh', 'zh-post', '中文文章 Laravel', '內文');
        $this->makePost('en', 'en-post', 'English Laravel post', 'body');

        $local = $this->getJson('/zh/search/suggest?q=Laravel')->json();
        $this->assertSame(1, $local['counts']['all']);

        $all = $this->getJson('/zh/search/suggest?q=Laravel&scope=all')->json();
        $this->assertSame(2, $all['counts']['all']);
        $this->assertContains(url('/en/posts/en-post'), array_column($all['results'], 'url'));
        $this->assertContains('en', array_column($all['results'], 'locale'));
    }

    public function test_empty_query_returns_no_results(): void
    {
        $json = $this->getJson('/zh/search/suggest?q=')->assertOk()->json();

        $this->assertSame(['all' => 0, 'post' => 0, 'tweet' => 0], $json['counts']);
        $this->assertSame([], $json['results']);
    }

    public function test_invalid_scope_is_rejected(): void
    {
        $this->getJson('/zh/search/suggest?q=a&scope=everything')->assertUnprocessable();
    }

    public function test_drafts_are_not_searchable(): void
    {
        Post::create([
            'post_group_id' => PostGroup::create([])->id, 'locale' => 'zh', 'slug' => 'draft', 'title' => '祕密草稿',
            'body' => 'x', 'status' => Post::STATUS_DRAFT, 'last_modified_at' => now(),
        ]);

        $this->assertSame(0, $this->getJson('/zh/search/suggest?q=祕密')->json('counts.all'));
    }

    public function test_every_public_page_has_the_search_panel(): void
    {
        foreach (['/zh', '/zh/posts', '/zh/tweets', '/zh/topics'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<div[^>]*role="dialog"[^>]*aria-modal="true"[^>]*data-search-panel/', $html, $url);
        }
    }

    public function test_panel_has_tabs_shortcuts_hints_scope_and_empty_state_copy(): void
    {
        $html = $this->get('/zh')->getContent();
        $panel = $html;

        foreach (['搜尋文章、碎念、標籤…', '最近搜尋', '直接前往', '↑↓', '選擇', '開啟', '切換類型', '搜尋範圍：繁中內容', '改搜全部語言'] as $text) {
            $this->assertStringContainsString($text, $panel, "Missing: {$text}");
        }
        // Shortcuts point to real pages.
        $this->assertStringContainsString('href="'.url('/zh/tweets').'"', $panel);
        $this->assertStringContainsString('href="'.route('changelog').'"', $panel);
        // Endpoint + "no results" template are passed to the component.
        $decoded = html_entity_decode($panel, ENT_QUOTES);
        $this->assertStringContainsString((string) Js::from(route('public.search.suggest', 'zh')), $decoded);
        $this->assertStringContainsString((string) Js::from(__('public.sp_no_results', [], 'zh')), $decoded);
    }

    public function test_header_search_triggers_open_the_panel_and_fall_back_to_search_page(): void
    {
        $html = $this->get('/zh/posts')->getContent();

        // Both the desktop box and the mobile icon are links to /search (no-JS fallback) marked as triggers.
        $this->assertSame(2, preg_match_all('/<a [^>]*(?=[^>]*data-search-trigger)(?=[^>]*href="'.preg_quote(url('/zh/search'), '/').'")[^>]*>/', $html));
    }

    public function test_search_page_still_works_without_javascript(): void
    {
        $this->makePost('zh', 'monitoring', '導入監控的契機', '內文');

        $this->get('/zh/search?q=監控')->assertOk()->assertSee('導入監控的契機');
    }
}
