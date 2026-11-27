<?php

namespace Tests\Feature\Public;

use App\Models\Tag;
use App\Models\Tweet;
use App\Models\TweetGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JYU-537: browse tweets by tag, month and media.
 */
class TweetsPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeTweet(string $body, string $at, array $attrs = [], array $tags = []): Tweet
    {
        $tweet = Tweet::create([
            'tweet_group_id' => TweetGroup::create([])->id, 'locale' => 'zh', 'body' => $body,
            'status' => Tweet::STATUS_PUBLISHED, 'published_at' => Carbon::parse($at), ...$attrs,
        ]);
        $tweet->tags()->attach($tags);

        return $tweet;
    }

    private function tag(string $name, string $slug): Tag
    {
        $tag = Tag::create(['color' => '#a4472f']);
        $tag->translations()->create(['locale' => 'zh', 'name' => $name, 'slug' => $slug]);

        return $tag;
    }

    private function listHtml(string $html): string
    {
        preg_match('/<section[^>]*data-tweet-list[^>]*>(.*?)<\/section>/s', $html, $m);
        $this->assertNotEmpty($m, 'Tweet list missing');

        return $m[1];
    }

    public function test_shows_intro_in_sidebar(): void
    {
        $this->get('/zh/tweets')->assertOk()->assertSee('比文章短、比推特長。隨手記下的想法、生活片段與讀到的東西。');
    }

    public function test_filter_by_tag_and_all_clears_it(): void
    {
        $life = $this->tag('生活', 'life');
        $this->makeTweet('生活碎念', '2026-03-01', [], [$life->id]);
        $this->makeTweet('技術碎念', '2026-03-02');

        $html = $this->get('/zh/tweets?tag=life')->assertOk()->getContent();
        $list = $this->listHtml($html);

        $this->assertStringContainsString('生活碎念', $list);
        $this->assertStringNotContainsString('技術碎念', $list);

        preg_match('/<nav[^>]*aria-label="依標籤篩選"[^>]*>(.*?)<\/nav>/su', $html, $tags);
        $this->assertMatchesRegularExpression('/<a [^>]*aria-current="true"[^>]*>#生活/u', $tags[1]);
        $this->assertMatchesRegularExpression('/<a [^>]*href="'.preg_quote(url('/zh/tweets'), '/').'"[^>]*>全部/u', $tags[1]);

        $all = $this->listHtml($this->get('/zh/tweets')->getContent());
        $this->assertStringContainsString('技術碎念', $all);
    }

    public function test_media_only_filter(): void
    {
        $this->makeTweet('有照片', '2026-03-01', ['media' => [['type' => 'image', 'path' => 'uploads/a.jpg']]]);
        $this->makeTweet('有 YouTube {{< youtube id="abc" >}}', '2026-03-02');
        $this->makeTweet('只有文字', '2026-03-03');
        $this->makeTweet('空陣列媒體', '2026-03-04', ['media' => []]);

        $html = $this->get('/zh/tweets?media=1')->getContent();
        $list = $this->listHtml($html);

        $this->assertStringContainsString('有照片', $list);
        $this->assertStringContainsString('有 YouTube', $list);
        $this->assertStringNotContainsString('只有文字', $list);
        $this->assertStringNotContainsString('空陣列媒體', $list);
        $this->assertMatchesRegularExpression('/<input[^>]*type="checkbox"[^>]*name="media"[^>]*checked/', $html);
        $this->assertStringContainsString('只看有照片或影片的', $html);
    }

    public function test_archive_lists_months_with_counts_and_filters_by_month(): void
    {
        $this->makeTweet('三月一', '2026-03-05');
        $this->makeTweet('三月二', '2026-03-25');
        $this->makeTweet('二月', '2026-02-17');
        $this->makeTweet('草稿', '2026-01-01', ['status' => Tweet::STATUS_DRAFT]);

        $html = $this->get('/zh/tweets')->getContent();
        preg_match('/<nav[^>]*aria-label="依月份"[^>]*>(.*?)<\/nav>/su', $html, $archive);

        $this->assertNotEmpty($archive);
        $this->assertMatchesRegularExpression('/2026 年 3 月[\s\S]*?>2</u', $archive[1]);
        $this->assertMatchesRegularExpression('/2026 年 2 月[\s\S]*?>1</u', $archive[1]);
        $this->assertStringNotContainsString('2026 年 1 月', $archive[1]);
        $this->assertStringContainsString('href="'.url('/zh/tweets?month=2026-03').'"', $archive[1]);

        $march = $this->listHtml($this->get('/zh/tweets?month=2026-03')->getContent());
        $this->assertStringContainsString('三月一', $march);
        $this->assertStringNotContainsString('>二月<', $march);
    }

    public function test_tweets_are_grouped_by_month_headings(): void
    {
        $this->makeTweet('三月', '2026-03-05');
        $this->makeTweet('二月', '2026-02-05');

        $list = $this->listHtml($this->get('/zh/tweets')->getContent());

        $this->assertMatchesRegularExpression('/<h2[^>]*data-month="2026-03"[^>]*>2026 年 3 月<\/h2>[\s\S]*三月[\s\S]*<h2[^>]*data-month="2026-02"[^>]*>2026 年 2 月<\/h2>[\s\S]*二月/u', $list);
    }

    public function test_existing_media_and_sensitive_behaviour_is_kept(): void
    {
        $this->makeTweet('敏感', '2026-03-05', ['media' => [['type' => 'image', 'path' => 'uploads/x.jpg', 'sensitive' => true]]]);

        $html = $this->get('/zh/tweets')->getContent();

        $this->assertStringContainsString('sensitive-media', $html);
        $this->assertStringContainsString('uploads/x.jpg', $html);
    }

    public function test_load_older_button_and_partial_pages(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeTweet("第{$i}則", Carbon::parse('2026-03-01')->addHours($i)->toDateTimeString());
        }

        $html = $this->get('/zh/tweets')->getContent();
        $this->assertMatchesRegularExpression('/<a (?=[^>]*data-load-older)(?=[^>]*href="'.preg_quote(url('/zh/tweets?page=2'), '/').'")[^>]*>\s*看更早的碎念/u', $html);
        $this->assertStringNotContainsString('第5則', $this->listHtml($html));

        $partial = $this->get('/zh/tweets?page=2&partial=1')->assertOk()->getContent();
        $this->assertStringContainsString('第5則', $partial);
        $this->assertStringContainsString('data-has-more="0"', $partial);
    }

    public function test_filters_are_kept_when_loading_older(): void
    {
        $life = $this->tag('生活', 'life');
        foreach (range(1, 25) as $i) {
            $this->makeTweet("生活{$i}", Carbon::parse('2026-03-01')->addHours($i)->toDateTimeString(), [], [$life->id]);
        }

        $html = $this->get('/zh/tweets?tag=life')->getContent();

        $this->assertMatchesRegularExpression('/data-load-older[^>]*href="[^"]*tag=life[^"]*page=2|href="[^"]*tag=life[^"]*page=2"[^>]*data-load-older/', html_entity_decode($html));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->get('/zh/tweets?month=2026-13')->assertRedirect();
        $this->get('/zh/tweets?tag=does-not-exist')->assertOk();
    }

    public function test_mobile_layout_puts_filters_above_the_list(): void
    {
        $html = $this->get('/zh/tweets')->getContent();

        // Filters come first in source order; desktop moves them into a sticky sidebar.
        $this->assertLessThan(strpos($html, 'data-tweet-list'), strpos($html, 'data-tweet-filters'));
        $this->assertMatchesRegularExpression('/data-tweet-filters[^>]*class="[^"]*md:sticky/', $html);
    }

    public function test_empty_filtered_result_shows_message(): void
    {
        $this->get('/zh/tweets?month=2020-01')->assertOk()->assertSee('沒有符合條件的碎念。');
    }
}
