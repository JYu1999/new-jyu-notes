<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JYU-539: topics overview — every category / series and tag with post counts.
 */
class TopicsPageTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function makePost(string $locale = 'zh', string $status = Post::STATUS_PUBLISHED): Post
    {
        $this->seq++;

        return Post::create([
            'post_group_id' => PostGroup::create([])->id, 'locale' => $locale, 'slug' => "p{$this->seq}",
            'title' => "文章{$this->seq}", 'body' => 'x', 'status' => $status, 'published_at' => now(), 'last_modified_at' => now(),
        ]);
    }

    public function test_reachable_from_menu_and_home_all_tags_link(): void
    {
        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => '技術', 'slug' => 'tech']);
        $this->makePost()->tags()->attach($tag);

        $topicsUrl = url('/zh/topics');
        $home = $this->get('/zh')->getContent();

        preg_match('/<nav[^>]*data-nav="main"[^>]*>(.*?)<\/nav>/s', $home, $menu);
        $this->assertStringContainsString('href="'.$topicsUrl.'"', $menu[1]);
        $this->assertMatchesRegularExpression('/<a [^>]*href="'.preg_quote($topicsUrl, '/').'"[^>]*>[\s\S]*?全部標籤/u', $home);

        $this->get('/zh/topics')->assertOk()->assertSee('主題');
    }

    public function test_lists_every_category_with_published_post_count_in_current_language(): void
    {
        $series = Category::create(['sort_method' => 'date_asc']);
        $series->translations()->create(['locale' => 'zh', 'name' => '工作匯報系列', 'slug' => 'report', 'description' => '三篇談工作匯報']);
        $empty = Category::create(['sort_method' => 'date_asc']);
        $empty->translations()->create(['locale' => 'zh', 'name' => '還沒有文章的分類', 'slug' => 'empty']);

        $this->makePost()->categories()->attach($series);
        $this->makePost()->categories()->attach($series);
        $this->makePost(status: Post::STATUS_DRAFT)->categories()->attach($series);
        $this->makePost('en')->categories()->attach($series);

        $html = $this->get('/zh/topics')->getContent();
        preg_match('/<section[^>]*aria-labelledby="topics-categories"[^>]*>(.*?)<\/section>/s', $html, $m);

        $this->assertMatchesRegularExpression('/href="'.preg_quote(url('/zh/categories/report'), '/').'"[\s\S]*工作匯報系列[\s\S]*2 篇[\s\S]*三篇談工作匯報/u', $m[1]);
        $this->assertMatchesRegularExpression('/還沒有文章的分類[\s\S]*0 篇/u', $m[1]);
    }

    public function test_lists_every_tag_with_post_count_linking_to_tag_page(): void
    {
        $ai = Tag::create(['color' => '#6a4590']);
        $ai->translations()->create(['locale' => 'zh', 'name' => '人工智慧', 'slug' => 'ai']);
        $quiet = Tag::create([]);
        $quiet->translations()->create(['locale' => 'zh', 'name' => '冷門', 'slug' => 'quiet']);
        $englishOnly = Tag::create([]);
        $englishOnly->translations()->create(['locale' => 'en', 'name' => 'OnlyEnglish', 'slug' => 'only-en']);

        foreach (range(1, 3) as $_) {
            $this->makePost()->tags()->attach($ai);
        }
        $this->makePost(status: Post::STATUS_DRAFT)->tags()->attach($ai);

        $html = $this->get('/zh/topics')->getContent();
        preg_match('/<section[^>]*aria-labelledby="topics-tags"[^>]*>(.*?)<\/section>/s', $html, $m);

        $this->assertMatchesRegularExpression('/href="'.preg_quote(url('/zh/tags/ai'), '/').'"[^>]*>#人工智慧<span class="ui-chip-count">3<\/span>/u', $m[1]);
        $this->assertMatchesRegularExpression('/#冷門<span class="ui-chip-count">0<\/span>/u', $m[1]);
        $this->assertStringNotContainsString('OnlyEnglish', $m[1]);
        // Most used first.
        $this->assertLessThan(strpos($m[1], '#冷門'), strpos($m[1], '#人工智慧'));
    }

    public function test_empty_state(): void
    {
        $this->get('/zh/topics')->assertOk()->assertSee('目前還沒有分類或標籤。');
    }

    public function test_translated(): void
    {
        $this->get('/ja/topics')->assertOk()->assertSee('トピック')->assertSee('カテゴリやタグはまだありません。');
    }
}
