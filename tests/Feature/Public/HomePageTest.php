<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tag;
use App\Models\Tweet;
use App\Models\TweetGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * JYU-534: home page for first-time and returning readers.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function makePost(array $attrs = []): Post
    {
        $this->seq++;

        return Post::create([
            'post_group_id' => PostGroup::create([])->id, 'locale' => 'zh',
            'slug' => "post-{$this->seq}", 'title' => "文章標題{$this->seq}", 'excerpt' => "摘要{$this->seq}",
            'body' => '內文', 'status' => Post::STATUS_PUBLISHED,
            'published_at' => Carbon::parse('2026-03-01')->addDays($this->seq), 'last_modified_at' => now(),
            ...$attrs,
        ]);
    }

    private function makeTweet(array $attrs = []): Tweet
    {
        $this->seq++;

        return Tweet::create([
            'tweet_group_id' => TweetGroup::create([])->id, 'locale' => 'zh', 'body' => "碎念內容{$this->seq}",
            'status' => Tweet::STATUS_PUBLISHED, 'published_at' => Carbon::parse('2026-03-01')->addDays($this->seq),
            ...$attrs,
        ]);
    }

    private function featuredSection(string $html): ?string
    {
        return preg_match('/<section[^>]*aria-labelledby="featured-h"[^>]*>(.*?)<\/section>/s', $html, $m) ? $m[0] : null;
    }

    // ── intro ──────────────────────────────────────────────────────────

    public function test_shows_self_introduction_and_work_button(): void
    {
        $html = $this->get('/zh')->assertOk()->getContent();

        $this->assertStringContainsString('嗨，我是 JYu。', $html);
        $this->assertStringContainsString('寫軟體工程、職涯、閱讀，還有生活。', $html);
        $this->assertMatchesRegularExpression('/<a [^>]*class="[^"]*ui-btn-primary[^"]*"[^>]*>\s*作品與經歷/u', $html);
    }

    public function test_intro_is_translated(): void
    {
        $this->get('/en')->assertOk()->assertSee("Hi, I'm JYu.")->assertSee('Work & experience');
    }

    // ── topics ─────────────────────────────────────────────────────────

    public function test_shows_main_topics_with_post_counts_linking_to_topic(): void
    {
        $tag = Tag::create(['color' => '#3f6236']);
        $tag->translations()->create(['locale' => 'zh', 'name' => '職涯', 'slug' => 'career']);
        foreach (range(1, 3) as $_) {
            $this->makePost()->tags()->attach($tag);
        }

        $html = $this->get('/zh')->getContent();
        preg_match('/<nav[^>]*aria-label="主題"[^>]*>(.*?)<\/nav>/s', $html, $m);

        $this->assertNotEmpty($m);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(url('/zh/tags/career'), '/').'"[\s\S]*職涯[\s\S]*>3</u', $m[1]);
        $this->assertStringContainsString('href="'.url('/zh/topics').'"', $m[1]);
    }

    // ── featured ───────────────────────────────────────────────────────

    public function test_zero_featured_hides_section(): void
    {
        $this->makePost();

        $this->assertNull($this->featuredSection($this->get('/zh')->getContent()));
    }

    public function test_one_featured_is_a_single_full_width_card(): void
    {
        $this->makePost(['is_featured' => true, 'title' => '唯一精選']);

        $section = $this->featuredSection($this->get('/zh')->getContent());

        $this->assertStringContainsString('data-featured-count="1"', $section);
        $this->assertSame(1, substr_count($section, 'data-featured-card='));
        $this->assertMatchesRegularExpression('/data-featured-card="hero"[^>]*class="[^"]*basis-full/', $section);
        $this->assertStringContainsString('唯一精選', $section);
    }

    public function test_two_featured_are_two_equal_cards(): void
    {
        $this->makePost(['is_featured' => true]);
        $this->makePost(['is_featured' => true]);

        $section = $this->featuredSection($this->get('/zh')->getContent());

        $this->assertStringContainsString('data-featured-count="2"', $section);
        $this->assertSame(1, substr_count($section, 'data-featured-card="hero"'));
        $this->assertSame(1, substr_count($section, 'data-featured-card="side"'));
        $this->assertMatchesRegularExpression('/data-featured-card="hero"[^>]*class="[^"]*md:flex-1/', $section);
        $this->assertMatchesRegularExpression('/class="[^"]*md:flex-1[^"]*"[^>]*data-featured-card="side"|data-featured-card="side"[^>]*class="[^"]*md:flex-1/', $section);
    }

    public function test_three_featured_is_one_large_two_small_without_more_link(): void
    {
        foreach (range(1, 3) as $_) {
            $this->makePost(['is_featured' => true]);
        }

        $section = $this->featuredSection($this->get('/zh')->getContent());

        $this->assertStringContainsString('data-featured-count="3"', $section);
        $this->assertSame(2, substr_count($section, 'data-featured-card="side"'));
        $this->assertStringNotContainsString('還有', $section);
    }

    public function test_more_than_three_featured_shows_first_three_and_more_link(): void
    {
        $posts = collect(range(1, 5))->map(fn () => $this->makePost(['is_featured' => true]));

        $section = $this->featuredSection($this->get('/zh')->getContent());

        $this->assertStringContainsString('data-featured-count="3"', $section);
        // Newest first: posts 5, 4, 3 on the home page; 2 and 1 behind the link.
        foreach ([4, 3, 2] as $i) {
            $this->assertStringContainsString($posts[$i]->title, $section);
        }
        foreach ([1, 0] as $i) {
            $this->assertStringNotContainsString('>'.$posts[$i]->title.'<', $section);
        }
        $this->assertStringContainsString('還有 2 篇精選 →', $section);
        $this->assertStringContainsString('href="'.route('public.posts.index', ['locale' => 'zh', 'featured' => 1]).'"', $section);
    }

    public function test_featured_list_link_shows_only_featured_posts(): void
    {
        $this->makePost(['is_featured' => true, 'title' => '精選甲']);
        $this->makePost(['title' => '一般乙']);

        $this->get('/zh/posts?featured=1')->assertOk()->assertSee('精選甲')->assertDontSee('一般乙');
    }

    public function test_only_hero_card_is_shown_on_phones(): void
    {
        foreach (range(1, 3) as $_) {
            $this->makePost(['is_featured' => true]);
        }

        $section = $this->featuredSection($this->get('/zh')->getContent());

        // The side column is hidden below md; the hero is not.
        $this->assertMatchesRegularExpression('/class="hidden md:flex[^"]*"[^>]*>\s*<a [^>]*data-featured-card="side"/', $section);
        $this->assertDoesNotMatchRegularExpression('/data-featured-card="hero"[^>]*class="[^"]*\bhidden\b/', $section);
    }

    // ── latest ─────────────────────────────────────────────────────────

    public function test_latest_mixes_posts_and_tweets_newest_first(): void
    {
        $post = $this->makePost(['title' => '較舊的文章']);
        $tweet = $this->makeTweet(['body' => '較新的碎念']);

        $html = $this->get('/zh')->getContent();
        preg_match('/<section id="latest".*?<\/section>/s', $html, $m);

        $this->assertLessThan(strpos($m[0], '較舊的文章'), strpos($m[0], '較新的碎念'));
        $this->assertStringContainsString('data-feed-item="post"', $m[0]);
        $this->assertStringContainsString('data-feed-item="tweet"', $m[0]);
    }

    public function test_latest_tabs_filter_by_type(): void
    {
        $this->makePost(['title' => '某篇文章']);
        $this->makeTweet(['body' => '某則碎念']);

        $html = $this->get('/zh?type=tweet')->getContent();
        preg_match('/<section id="latest".*?<\/section>/s', $html, $m);

        $this->assertStringContainsString('某則碎念', $m[0]);
        $this->assertStringNotContainsString('某篇文章', $m[0]);
        $this->assertMatchesRegularExpression('/data-feed-tab="tweet"[^>]*aria-pressed="true"/', $m[0]);
        foreach (['all' => '全部', 'post' => '文章', 'tweet' => '碎念'] as $key => $label) {
            $this->assertMatchesRegularExpression('/data-feed-tab="'.$key.'"[\s\S]*?>'.$label.'</u', $m[0]);
        }
    }

    public function test_load_more_returns_older_items_as_json(): void
    {
        foreach (range(1, 12) as $i) {
            $this->makePost(['title' => "第{$i}篇"]);
        }

        $html = $this->get('/zh')->getContent();
        $this->assertStringContainsString('載入更多', $html);
        $this->assertStringNotContainsString('>第2篇<', $html);

        preg_match("/next: '([^']+)'/", html_entity_decode($html, ENT_QUOTES), $m);
        $this->assertNotEmpty($m, 'Initial next cursor missing');

        $json = $this->getJson('/zh?partial=1&before='.urlencode($m[1]))->assertOk()->json();

        $this->assertStringContainsString('第2篇', $json['html']);
        $this->assertStringContainsString('第1篇', $json['html']);
        $this->assertStringNotContainsString('第12篇', $json['html']);
        $this->assertNull($json['next']);
    }

    public function test_load_more_button_hidden_when_everything_fits(): void
    {
        $this->makePost();

        $html = $this->get('/zh')->getContent();

        $this->assertMatchesRegularExpression('/<a [^>]*class="[^"]*ui-btn-lg[^"]*\bhidden\b[^"]*"/', $html);
    }

    public function test_tweet_with_only_a_video_embed_shows_video_box(): void
    {
        $this->makeTweet(['body' => '{{< youtube id="abc123" >}}']);

        $this->get('/zh')->assertOk()->assertSee('aria-label="播放影片"', false);
    }

    public function test_invalid_feed_type_is_rejected(): void
    {
        $this->get('/zh?type=pages')->assertRedirect();
    }

    // ── sidebar ────────────────────────────────────────────────────────

    public function test_sidebar_shows_series_with_counts_tags_and_latest_changelog(): void
    {
        $cat = Category::create(['sort_method' => 'date_asc']);
        $cat->translations()->create(['locale' => 'zh', 'name' => '工作匯報系列', 'slug' => 'report']);
        $this->makePost()->categories()->attach($cat);
        $this->makePost()->categories()->attach($cat);

        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => '閱讀', 'slug' => 'reading']);
        $this->makePost()->tags()->attach($tag);

        $file = tempnam(sys_get_temp_dir(), 'changelog');
        File::put($file, "## 2026-11-20\n- Newest change\n- Second\n\n## 2026-10-01\n- Older change\n");
        config(['changelog.path' => $file]);

        $html = $this->get('/zh')->getContent();
        preg_match('/<aside.*?<\/aside>/s', $html, $m);
        $aside = $m[0];

        $this->assertStringContainsString('系列', $aside);
        $this->assertMatchesRegularExpression('/工作匯報系列[\s\S]*2 篇/u', $aside);
        $this->assertStringContainsString('#閱讀', $aside);
        $this->assertStringContainsString('網站更新', $aside);
        $this->assertStringContainsString('Newest change', $aside);
        $this->assertStringNotContainsString('Older change', $aside);
        $this->assertStringContainsString('href="'.route('changelog').'"', $aside);
        $this->assertStringNotContainsStringIgnoringCase('rss', $aside);

        @unlink($file);
    }

    public function test_page_renders_with_no_content_at_all(): void
    {
        config(['changelog.path' => '/nonexistent']);

        $this->get('/zh')->assertOk()->assertSee('這裡還沒有內容。');
    }
}
