<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * JYU-545: admin post list — filters, sorting, per-language status.
 */
class AdminPostListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
    }

    private function makePost(string $title, array $attrs = [], ?PostGroup $group = null): Post
    {
        return Post::create([
            'post_group_id' => ($group ?? PostGroup::create([]))->id, 'locale' => 'zh', 'slug' => Str::slug($title) ?: uniqid('p'),
            'title' => $title, 'body' => '內文', 'status' => 'published', 'last_modified_at' => Carbon::parse('2026-01-01'), ...$attrs,
        ]);
    }

    private function list(array $query = []): string
    {
        return $this->actingAs($this->admin)->get(route('admin.posts.index', $query))->assertOk()->getContent();
    }

    private function rowTitles(string $html): array
    {
        preg_match_all('/<tr data-post-row="\d+"[\s\S]*?<a href="[^"]*" class="block font-semibold[^"]*">\s*(?:<span[^>]*>★ <\/span>)?([^<]+)<\/a>/u', $html, $m);

        return array_map('trim', $m[1]);
    }

    public function test_status_tabs_with_counts_per_article(): void
    {
        $g = PostGroup::create([]);
        $this->makePost('A', [], $g);
        $this->makePost('A-en', ['locale' => 'en', 'slug' => 'a-en'], $g);   // same article → counts once
        $this->makePost('B', ['status' => 'draft']);
        $this->makePost('C', ['status' => 'hidden']);
        $this->makePost('D')->delete();

        $html = $this->list();

        foreach (['all' => '全部 <span class="font-normal text-ink-3">3', 'published' => '已發布 <span class="font-normal text-ink-3">1', 'draft' => '草稿 <span class="font-normal text-ink-3">1', 'hidden' => '隱藏 <span class="font-normal text-ink-3">1', 'trashed' => '垃圾桶 <span class="font-normal text-ink-3">1'] as $key => $text) {
            $this->assertMatchesRegularExpression('/data-status-tab="'.$key.'"[^>]*>\s*'.preg_quote($text, '/').'/u', $html, $key);
        }
        $this->assertSame(['A'], $this->rowTitles($this->list(['status' => 'published'])));
        $this->assertSame(['B'], $this->rowTitles($this->list(['status' => 'draft'])));
    }

    public function test_search_title_slug_and_body_including_translations(): void
    {
        $this->makePost('標題命中');
        $this->makePost('其他', ['slug' => 'slug-hit']);
        $this->makePost('第三', ['body' => '內文裡有 bodyhit 字樣']);
        $g = PostGroup::create([]);
        $this->makePost('中文原文', [], $g);
        $this->makePost('English', ['locale' => 'en', 'slug' => 'en-x', 'body' => 'only-in-english'], $g);
        $this->makePost('無關');

        $this->assertSame(['標題命中'], $this->rowTitles($this->list(['q' => '命中'])));
        $this->assertSame(['其他'], $this->rowTitles($this->list(['q' => 'slug-hit'])));
        $this->assertSame(['第三'], $this->rowTitles($this->list(['q' => 'bodyhit'])));
        $this->assertSame(['中文原文'], $this->rowTitles($this->list(['q' => 'only-in-english'])));
    }

    public function test_filter_by_category_tag_and_translation_status(): void
    {
        $cat = Category::create(['sort_method' => 'date_desc']);
        $cat->translations()->create(['locale' => 'zh', 'name' => '技術', 'slug' => 'tech']);
        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => 'AI', 'slug' => 'ai']);

        $a = $this->makePost('有分類');
        $a->categories()->attach($cat);
        $b = $this->makePost('有標籤');
        $b->tags()->attach($tag);
        $g = PostGroup::create([]);
        $this->makePost('有英文', [], $g);
        $this->makePost('English', ['locale' => 'en', 'slug' => 'en-y'], $g);

        $this->assertSame(['有分類'], $this->rowTitles($this->list(['category' => $cat->id])));
        $this->assertSame(['有標籤'], $this->rowTitles($this->list(['tag' => $tag->id])));
        $missingEn = $this->rowTitles($this->list(['translation' => 'missing:en']));
        sort($missingEn);
        $this->assertSame(['有分類', '有標籤'], $missingEn);
    }

    public function test_sort_by_updated_published_and_views(): void
    {
        $old = $this->makePost('舊的', ['published_at' => Carbon::parse('2025-01-01'), 'views_count' => 500]);
        $new = $this->makePost('新的', ['published_at' => Carbon::parse('2026-01-01'), 'views_count' => 10]);
        $old->forceFill(['updated_at' => Carbon::parse('2026-06-01')])->saveQuietly();
        $new->forceFill(['updated_at' => Carbon::parse('2026-02-01')])->saveQuietly();

        $this->assertSame(['舊的', '新的'], $this->rowTitles($this->list()));
        $this->assertSame(['新的', '舊的'], $this->rowTitles($this->list(['sort' => 'published'])));
        $this->assertSame(['舊的', '新的'], $this->rowTitles($this->list(['sort' => 'views'])));
    }

    public function test_row_shows_title_slug_status_category_views_and_modified(): void
    {
        $cat = Category::create(['sort_method' => 'date_desc']);
        $cat->translations()->create(['locale' => 'zh', 'name' => '工作匯報系列', 'slug' => 'report']);
        $g = PostGroup::create([]);
        $p = $this->makePost('如何工作匯報？', ['slug' => 'how-to-report-progress', 'views_count' => 1000], $g);
        $this->makePost('How to report', ['locale' => 'en', 'slug' => 'how-en', 'views_count' => 31], $g);
        $p->categories()->attach($cat);
        $p->forceFill(['updated_at' => Carbon::parse('2026-11-25 10:00')])->saveQuietly();

        $html = $this->list();
        preg_match('/<tr data-post-row="'.$p->id.'"[\s\S]*?<\/tr>/', $html, $row);

        $this->assertStringContainsString('如何工作匯報？', $row[0]);
        $this->assertStringContainsString('/how-to-report-progress', $row[0]);
        $this->assertStringContainsString('已發布', $row[0]);
        $this->assertStringContainsString('工作匯報系列', $row[0]);
        $this->assertStringContainsString('1,031', $row[0]);      // views of all language versions
        $this->assertMatchesRegularExpression('/<time[^>]*>(11\/25|2026\/11\/25)<\/time>/', $row[0]);
    }

    public function test_five_language_states_and_missing_creates_translation(): void
    {
        $g = PostGroup::create([]);
        $zh = $this->makePost('原文', ['last_modified_at' => Carbon::parse('2026-05-01')], $g);
        $this->makePost('EN', ['locale' => 'en', 'slug' => 'en-z', 'last_modified_at' => Carbon::parse('2026-06-01')], $g);
        $this->makePost('JA', ['locale' => 'ja', 'slug' => 'ja-z', 'status' => 'draft', 'last_modified_at' => Carbon::parse('2026-06-01')], $g);
        $this->makePost('VI', ['locale' => 'vi', 'slug' => 'vi-z', 'last_modified_at' => Carbon::parse('2026-04-01')], $g);

        $html = $this->list();

        $this->assertMatchesRegularExpression('/data-lang="zh" data-state="published"/', $html);
        $this->assertMatchesRegularExpression('/data-lang="en" data-state="published"/', $html);
        $this->assertMatchesRegularExpression('/data-lang="ja" data-state="draft"/', $html);
        $this->assertMatchesRegularExpression('/data-lang="vi" data-state="stale"/', $html);
        $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('admin.posts.create-translation', $zh), '/').'"[\s\S]*?name="locale" value="id"[\s\S]*?data-lang="id" data-state="missing"/', $html);

        // Clicking it creates the Indonesian draft.
        $this->actingAs($this->admin)->post(route('admin.posts.create-translation', $zh), ['locale' => 'id'])->assertRedirect();
        $this->assertSame('draft', Post::where('post_group_id', $g->id)->where('locale', 'id')->value('status'));
    }

    public function test_featured_posts_have_a_star(): void
    {
        $this->makePost('精選文章', ['is_featured' => true]);
        $this->makePost('一般文章');

        $html = $this->list();

        $this->assertMatchesRegularExpression('/data-featured[^>]*>★ <\/span>精選文章/u', $html);
        $this->assertSame(1, substr_count($html, 'data-featured'));
    }

    public function test_trash_lists_deleted_versions_and_restores_them(): void
    {
        $p = $this->makePost('被刪掉的');
        $p->delete();

        $html = $this->list(['status' => 'trashed']);

        $this->assertStringContainsString('被刪掉的', $html);
        $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('admin.posts.restore', $p->id), '/').'"[\s\S]*?還原/u', $html);

        $this->actingAs($this->admin)->post(route('admin.posts.restore', $p->id))->assertRedirect();
        $this->assertNull($p->fresh()->deleted_at);
    }

    public function test_live_filter_returns_only_the_table(): void
    {
        $this->makePost('A');

        $html = $this->actingAs($this->admin)->get(route('admin.posts.index', ['partial' => 1, 'q' => 'A']))->assertOk()->getContent();

        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringContainsString('data-post-row', $html);
    }
}
