<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\PageGroup;
use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JYU-541: admin sidebar grouped into 寫作 / 整理 / 網站 with counts.
 */
class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'JYu', 'email' => 'admin@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
    }

    private function sidebar(string $html): string
    {
        preg_match('/<aside[^>]*data-admin-sidebar[^>]*>(.*?)<\/aside>/s', $html, $m);
        $this->assertNotEmpty($m, 'Admin sidebar missing');

        return $m[1];
    }

    public function test_groups_and_items(): void
    {
        $nav = $this->sidebar($this->actingAs($this->admin())->get('/admin')->assertOk()->getContent());

        $this->assertMatchesRegularExpression('/寫作[\s\S]*首頁[\s\S]*文章[\s\S]*碎念[\s\S]*頁面[\s\S]*整理[\s\S]*分類與系列[\s\S]*標籤[\s\S]*媒體庫[\s\S]*網站/u', $nav);
        foreach (['admin.posts.index', 'admin.tweets.index', 'admin.pages.index', 'admin.categories.index', 'admin.tags.index', 'admin.media.index'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $nav, $route);
        }
    }

    public function test_counts_posts_tweets_and_pages_once_per_translation_group(): void
    {
        // One post in two languages counts as one post; deleted ones don't count.
        $g = PostGroup::create([]);
        foreach (['zh', 'en'] as $loc) {
            Post::create(['post_group_id' => $g->id, 'locale' => $loc, 'slug' => "a-{$loc}", 'title' => 'A', 'body' => 'x', 'status' => 'draft', 'last_modified_at' => now()]);
        }
        Post::create(['post_group_id' => PostGroup::create([])->id, 'locale' => 'zh', 'slug' => 'b', 'title' => 'B', 'body' => 'x', 'status' => 'published', 'last_modified_at' => now()]);
        Post::create(['post_group_id' => PostGroup::create([])->id, 'locale' => 'zh', 'slug' => 'c', 'title' => 'C', 'body' => 'x', 'status' => 'published', 'last_modified_at' => now()])->delete();
        foreach (range(1, 3) as $_) {
            Tweet::create(['tweet_group_id' => TweetGroup::create([])->id, 'locale' => 'zh', 'body' => 't', 'status' => 'published']);
        }
        Page::create(['page_group_id' => PageGroup::create([])->id, 'locale' => 'zh', 'slug' => 'about', 'title' => '關於', 'body' => 'x', 'status' => 'published']);

        $nav = $this->sidebar($this->actingAs($this->admin())->get('/admin')->getContent());

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.posts.index'), '/').'"[\s\S]*?文章[\s\S]*?>2</u', $nav);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.tweets.index'), '/').'"[\s\S]*?碎念[\s\S]*?>3</u', $nav);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.pages.index'), '/').'"[\s\S]*?頁面[\s\S]*?>1</u', $nav);
    }

    public function test_current_page_is_marked(): void
    {
        $nav = $this->sidebar($this->actingAs($this->admin())->get(route('admin.tags.index'))->getContent());

        $this->assertMatchesRegularExpression('/<a [^>]*href="'.preg_quote(route('admin.tags.index'), '/').'"[^>]*aria-current="page"/', $nav);
        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
    }

    public function test_site_link_opens_public_site_in_new_tab(): void
    {
        $nav = $this->sidebar($this->actingAs($this->admin())->get('/admin')->getContent());

        $this->assertMatchesRegularExpression('/<a [^>]*href="'.preg_quote(route('public.root'), '/').'"[^>]*target="_blank"[^>]*>\s*網站 ↗/u', $nav);
    }

    public function test_footer_shows_signed_in_account_and_logout(): void
    {
        $nav = $this->sidebar($this->actingAs($this->admin())->get('/admin')->getContent());

        $this->assertStringContainsString('JYu', $nav);
        $this->assertStringContainsString('admin@b.c', $nav);
        $this->assertMatchesRegularExpression('/<form[^>]*method="POST"[^>]*action="'.preg_quote(route('auth.logout'), '/').'"[\s\S]*登出/u', $nav);
    }

    public function test_mobile_can_open_and_close_sidebar(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*@click="sidebar = true"[^>]*aria-label="開啟選單"/u', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*@click="sidebar = false"[^>]*aria-label="關閉選單"/u', $html);
    }
}
