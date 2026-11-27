<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Tests\TestCase;

/**
 * JYU-547: the author decides the order of featured posts.
 */
class FeaturedOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
        $this->actingAs($this->admin);
    }

    private function feature(string $title, string $locale = 'zh', ?int $groupId = null): Post
    {
        return app(PostService::class)->create([
            'locale' => $locale, 'title' => $title, 'slug' => uniqid('p'), 'body' => 'x', 'status' => 'published',
            'is_featured' => true, 'post_group_id' => $groupId,
        ]);
    }

    public function test_newly_featured_articles_go_last(): void
    {
        $a = $this->feature('A');
        $b = $this->feature('B');
        $c = app(PostService::class)->create(['locale' => 'zh', 'title' => 'C', 'slug' => 'c', 'body' => 'x', 'status' => 'published']);
        $this->assertNull($c->fresh()->featured_order);

        app(PostService::class)->update($c, ['is_featured' => true]);

        $this->assertSame([1, 2, 3], [$a->fresh()->featured_order, $b->fresh()->featured_order, $c->fresh()->featured_order]);
    }

    public function test_unfeaturing_removes_the_article_from_the_order(): void
    {
        $a = $this->feature('A');

        app(PostService::class)->update($a, ['is_featured' => false]);

        $this->assertNull($a->fresh()->featured_order);
    }

    public function test_language_versions_share_the_article_order(): void
    {
        $zh = $this->feature('A');
        $en = app(PostService::class)->createTranslation($zh, 'en');

        $this->assertSame($zh->fresh()->featured_order, $en->fresh()->featured_order);
    }

    public function test_featured_tab_lists_articles_with_positions_and_divider_after_the_third(): void
    {
        foreach (['一', '二', '三', '四', '五'] as $t) {
            $this->feature("精選{$t}");
        }

        $html = $this->get(route('admin.posts.index', ['status' => 'featured']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-status-tab="featured"[^>]*>\s*精選 <span class="font-normal text-ink-3">5/u', $html);
        preg_match_all('/<li data-group="(\d+)"[\s\S]*?data-position[^>]*>(\d+)<\/span>[\s\S]*?>(精選.)<\/a>/u', $html, $m);
        $this->assertSame(['1', '2', '3', '4', '5'], $m[2]);
        $this->assertSame(['精選一', '精選二', '精選三', '精選四', '精選五'], $m[3]);
        // Divider visible only under the 3rd.
        preg_match_all('/data-divider class="(flex|hidden) /', $html, $d);
        $this->assertSame(['hidden', 'hidden', 'flex', 'hidden', 'hidden'], $d[1]);
        $this->assertStringContainsString('以下不會出現在首頁，只在「還有 N 篇精選」裡', $html);
        $this->assertStringContainsString('draggable="true"', $html);
        $this->assertStringContainsString((string) Js::from(route('admin.posts.featured-order')), html_entity_decode($html, ENT_QUOTES));
    }

    public function test_no_divider_with_three_or_fewer(): void
    {
        $this->feature('A');
        $this->feature('B');

        $html = $this->get(route('admin.posts.index', ['status' => 'featured']))->getContent();

        $this->assertStringNotContainsString('data-divider class="flex', $html);
    }

    public function test_saving_a_new_order_changes_the_home_page(): void
    {
        $a = $this->feature('第一');
        $b = $this->feature('第二');
        $c = $this->feature('第三');
        $d = $this->feature('第四');

        $this->postJson(route('admin.posts.featured-order'), ['group_ids' => [$d->post_group_id, $a->post_group_id, $c->post_group_id, $b->post_group_id]])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame([2, 4, 3, 1], [$a->fresh()->featured_order, $b->fresh()->featured_order, $c->fresh()->featured_order, $d->fresh()->featured_order]);

        auth()->logout();
        $html = $this->get('/zh')->getContent();
        preg_match('/<section[^>]*aria-labelledby="featured-h"[^>]*>(.*?)<\/section>/s', $html, $m);
        $this->assertLessThan(strpos($m[1], '第一'), strpos($m[1], '第四'));
        $this->assertLessThan(strpos($m[1], '第三'), strpos($m[1], '第一'));
        $this->assertStringNotContainsString('第二', $m[1]);   // 4th → behind "還有 1 篇精選"
        $this->assertStringContainsString('還有 1 篇精選', $m[1]);
    }

    public function test_featured_list_page_follows_the_same_order(): void
    {
        $a = $this->feature('甲');
        $b = $this->feature('乙');
        $this->postJson(route('admin.posts.featured-order'), ['group_ids' => [$b->post_group_id, $a->post_group_id]]);

        $html = $this->get('/zh/posts?featured=1')->getContent();

        $this->assertLessThan(strpos($html, '/zh/posts/'.$a->slug), strpos($html, '/zh/posts/'.$b->slug));
    }

    public function test_reorder_validation(): void
    {
        $this->postJson(route('admin.posts.featured-order'), ['group_ids' => []])->assertUnprocessable();
        $this->postJson(route('admin.posts.featured-order'), ['group_ids' => [99999]])->assertUnprocessable();
        $g = PostGroup::create([]);
        $this->postJson(route('admin.posts.featured-order'), ['group_ids' => [$g->id, $g->id]])->assertUnprocessable();
    }

    public function test_editor_checkbox_is_labelled_set_as_featured(): void
    {
        $p = $this->feature('A');

        $this->get(route('admin.posts.edit', $p))->assertSee('設為精選');
    }
}
