<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\Post;
use App\Models\Tag;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JYU-533: one header / footer menu on every public page, desktop and mobile.
 */
class PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function mainNav(string $html): string
    {
        preg_match('/<nav[^>]*data-nav="main"[^>]*>(.*?)<\/nav>/s', $html, $m);
        $this->assertNotEmpty($m, 'Desktop main navigation not found');

        return $m[1];
    }

    private function mobileNav(string $html): string
    {
        preg_match('/<nav[^>]*data-nav="mobile"[^>]*>(.*?)<\/nav>/s', $html, $m);
        $this->assertNotEmpty($m, 'Mobile navigation not found');

        return $m[1];
    }

    private function makeAboutPage(string $locale = 'zh'): void
    {
        $group = PageGroup::create([]);
        Page::create([
            'page_group_id' => $group->id, 'locale' => $locale, 'slug' => 'about',
            'title' => '關於我', 'body' => '嗨', 'status' => Page::STATUS_PUBLISHED,
        ]);
    }

    private function publishPost(): Post
    {
        return app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'nav-post', 'title' => '導覽測試文章', 'body' => '內文', 'status' => Post::STATUS_PUBLISHED,
        ]);
    }

    public function test_every_public_page_shows_posts_tweets_topics_about_menu(): void
    {
        $this->makeAboutPage();
        $this->publishPost();

        foreach (['/zh', '/zh/posts', '/zh/posts/nav-post', '/zh/tweets', '/zh/search', '/zh/topics', '/zh/about'] as $url) {
            $nav = $this->mainNav($this->get($url)->assertOk()->getContent());

            foreach (['/zh/posts', '/zh/tweets', '/zh/topics', '/zh/about'] as $href) {
                $this->assertStringContainsString('href="'.url($href).'"', $nav, "{$url}: menu is missing {$href}");
            }
            foreach (['文章', '碎念', '主題', '關於'] as $label) {
                $this->assertStringContainsString($label, $nav, "{$url}: menu is missing {$label}");
            }
        }
    }

    public function test_current_section_is_marked_with_aria_current(): void
    {
        $this->makeAboutPage();
        $this->publishPost();

        $cases = [
            '/zh/posts' => '/zh/posts',
            '/zh/posts/nav-post' => '/zh/posts',
            '/zh/tweets' => '/zh/tweets',
            '/zh/topics' => '/zh/topics',
            '/zh/about' => '/zh/about',
        ];

        foreach ($cases as $url => $activeHref) {
            $nav = $this->mainNav($this->get($url)->getContent());

            preg_match_all('/<a [^>]*aria-current="page"[^>]*>/', $nav, $current);
            $this->assertCount(1, $current[0], "{$url}: exactly one menu item should be current");
            $this->assertStringContainsString('href="'.url($activeHref).'"', $current[0][0], "{$url}: wrong current item");
        }
    }

    public function test_tag_and_category_pages_mark_topics_as_current(): void
    {
        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => '技術', 'slug' => 'tech']);
        $cat = Category::create(['sort_method' => 'date_desc']);
        $cat->translations()->create(['locale' => 'zh', 'name' => '系列', 'slug' => 'series']);

        foreach (['/zh/tags/tech', '/zh/categories/series'] as $url) {
            $nav = $this->mainNav($this->get($url)->assertOk()->getContent());
            $this->assertMatchesRegularExpression('/<a [^>]*href="'.preg_quote(url('/zh/topics'), '/').'"[^>]*aria-current="page"|<a [^>]*aria-current="page"[^>]*href="'.preg_quote(url('/zh/topics'), '/').'"/', $nav);
        }
    }

    public function test_home_page_has_no_current_desktop_item(): void
    {
        $nav = $this->mainNav($this->get('/zh')->getContent());

        $this->assertStringNotContainsString('aria-current="page"', $nav);
    }

    public function test_header_has_search_box_with_keyboard_shortcut(): void
    {
        $html = $this->get('/zh/posts')->getContent();

        $this->assertMatchesRegularExpression('/<a [^>]*data-search-trigger[^>]*href="'.preg_quote(url('/zh/search'), '/').'"|<a [^>]*href="'.preg_quote(url('/zh/search'), '/').'"[^>]*data-search-trigger/', $html);
        $this->assertStringContainsString('搜尋文章與碎念', $html);
        $this->assertStringContainsString('⌘K', $html);
    }

    public function test_header_has_language_and_theme_controls_with_localized_labels(): void
    {
        $html = $this->get('/zh')->getContent();

        $this->assertStringContainsString('aria-label="切換語言，目前為繁體中文"', $html);
        $this->assertStringContainsString('>繁中<', $html);
        $this->assertStringContainsString('aria-label="切換深色模式"', $html);
        $this->assertStringContainsString('action="'.route('public.locale.switch', 'en').'"', $html);
    }

    public function test_menu_is_translated(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();
        $nav = $this->mainNav($html);

        foreach (['Posts', 'Tweets', 'Topics', 'About'] as $label) {
            $this->assertStringContainsString($label, $nav);
        }
        $this->assertStringContainsString('aria-label="Change language, current: English"', $html);
    }

    public function test_about_falls_back_to_chinese_page_when_locale_has_none(): void
    {
        $this->makeAboutPage('zh');

        $nav = $this->mainNav($this->get('/vi')->assertOk()->getContent());

        $this->assertStringContainsString('href="'.url('/zh/about').'"', $nav);
    }

    public function test_about_uses_locale_page_when_it_exists(): void
    {
        $this->makeAboutPage('zh');
        $this->makeAboutPage('en');

        $nav = $this->mainNav($this->get('/en')->getContent());

        $this->assertStringContainsString('href="'.url('/en/about').'"', $nav);
    }

    public function test_footer_has_about_changelog_and_copyright_but_no_rss(): void
    {
        $html = $this->get('/zh')->getContent();

        preg_match('/<footer[^>]*>(.*?)<\/footer>/s', $html, $m);
        $footer = $m[1] ?? '';

        $this->assertStringContainsString('href="'.url('/zh/about').'"', $footer);
        $this->assertStringContainsString('href="'.route('changelog').'"', $footer);
        $this->assertStringContainsString('© '.date('Y'), $footer);
        $this->assertStringNotContainsStringIgnoringCase('rss', $footer);
    }

    public function test_mobile_menu_is_horizontally_scrollable_and_contains_every_section(): void
    {
        $html = $this->get('/zh/tweets')->getContent();

        $this->assertMatchesRegularExpression('/<nav[^>]*data-nav="mobile"[^>]*class="[^"]*overflow-x-auto/', $html);
        $nav = $this->mobileNav($html);

        foreach (['/zh', '/zh/posts', '/zh/tweets', '/zh/topics', '/zh/about'] as $href) {
            $this->assertStringContainsString('href="'.url($href).'"', $nav);
        }
        $this->assertMatchesRegularExpression('/<a [^>]*href="'.preg_quote(url('/zh/tweets'), '/').'"[^>]*aria-current="page"/', $nav);
    }

    public function test_menu_links_meet_44px_touch_target(): void
    {
        $html = $this->get('/zh')->getContent();

        foreach ([$this->mainNav($html), $this->mobileNav($html)] as $nav) {
            preg_match_all('/<a [^>]*class="([^"]*)"/', $nav, $m);
            $this->assertNotEmpty($m[1]);
            foreach ($m[1] as $classes) {
                $this->assertMatchesRegularExpression('/\b(min-)?h-11\b/', $classes, "Menu link is shorter than 44px: {$classes}");
            }
        }
    }

    public function test_topics_route_is_not_swallowed_by_static_page_catch_all(): void
    {
        $this->get('/zh/topics')->assertOk()->assertSee('主題');
    }
}
