<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\PostViewLog;
use App\Models\User;
use App\Services\ViewStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JYU-543: admin home — 30-day views and this week's top posts.
 */
class ViewStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.display_timezone' => 'Asia/Taipei']);
        // 2026-11-27 12:00 Taipei
        Carbon::setTestNow(Carbon::parse('2026-11-27 04:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makePost(string $title, string $locale = 'zh', ?PostGroup $group = null): Post
    {
        return Post::create([
            'post_group_id' => ($group ?? PostGroup::create([]))->id, 'locale' => $locale, 'slug' => uniqid('p'),
            'title' => $title, 'body' => 'x', 'status' => 'published', 'last_modified_at' => now(),
        ]);
    }

    /** $at is Taipei local time. */
    private function views(Post $post, int $n, string $at): void
    {
        foreach (range(1, $n) as $i) {
            PostViewLog::create(['post_id' => $post->id, 'fingerprint' => uniqid('f', true), 'viewed_at' => Carbon::parse($at, 'Asia/Taipei')->utc()]);
        }
    }

    public function test_total_and_change_against_previous_30_days(): void
    {
        $p = $this->makePost('A');
        $this->views($p, 12, '2026-11-20 10:00');   // in the last 30 days
        $this->views($p, 10, '2026-10-20 10:00');   // previous 30 days
        $this->views($p, 5, '2026-08-01 10:00');    // older, ignored

        $s = app(ViewStatsService::class)->summary();

        $this->assertSame(12, $s['total']);
        $this->assertSame(10, $s['previous']);
        $this->assertSame(20, $s['change']);
    }

    public function test_change_is_null_without_previous_data(): void
    {
        $this->views($this->makePost('A'), 3, '2026-11-25 10:00');

        $this->assertNull(app(ViewStatsService::class)->summary()['change']);
    }

    public function test_daily_series_has_30_days_in_local_time_ending_today(): void
    {
        $p = $this->makePost('A');
        // 2026-11-27 07:00 Taipei = 2026-11-26 23:00 UTC: must count on the 27th.
        $this->views($p, 2, '2026-11-27 07:00');
        $this->views($p, 1, '2026-10-29 09:00');

        $daily = app(ViewStatsService::class)->summary()['daily'];

        $this->assertCount(30, $daily);
        $this->assertSame('2026-10-29', $daily[0]['date']);
        $this->assertSame(1, $daily[0]['count']);
        $this->assertSame('2026-11-27', $daily[29]['date']);
        $this->assertSame(2, $daily[29]['count']);
        $this->assertSame('11/27', $daily[29]['label']);
    }

    public function test_top_four_this_week_merging_translations(): void
    {
        $g = PostGroup::create([]);
        $zh = $this->makePost('我們花了一個月才發現系統壞了', 'zh', $g);
        $en = $this->makePost('We found out a month later', 'en', $g);
        $this->views($zh, 3, '2026-11-26 10:00');
        $this->views($en, 2, '2026-11-25 10:00');
        $b = $this->makePost('MCP 初體驗');
        $this->views($b, 4, '2026-11-24 10:00');
        foreach (['C' => 3, 'D' => 2, 'E' => 1] as $title => $n) {
            $this->views($this->makePost($title), $n, '2026-11-23 10:00');
        }
        $old = $this->makePost('上個月的熱門');
        $this->views($old, 50, '2026-11-01 10:00');

        $top = app(ViewStatsService::class)->summary()['top'];

        $this->assertSame(['我們花了一個月才發現系統壞了', 'MCP 初體驗', 'C', 'D'], $top->map(fn ($t) => $t['post']->title)->all());
        $this->assertSame([5, 4, 3, 2], $top->pluck('views')->all());
    }

    public function test_dashboard_shows_total_change_chart_and_top_posts(): void
    {
        $admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
        $p = $this->makePost('MCP 初體驗');
        $this->views($p, 1234, '2026-11-26 10:00');
        $this->views($p, 1000, '2026-10-10 10:00');

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();
        preg_match('/<section[^>]*aria-labelledby="st-h"[^>]*>(.*?)<\/section>/s', $html, $m);
        $box = $m[1] ?? '';

        $this->assertStringContainsString('近 30 天瀏覽', $box);
        $this->assertStringContainsString('1,234', $box);
        $this->assertStringContainsString('↑ 23%', $box);
        $this->assertMatchesRegularExpression('/role="img"[^>]*aria-label="近 30 天每日瀏覽長條圖/u', $box);
        $this->assertSame(30, substr_count($box, 'data-bar'));
        $this->assertStringContainsString('10/29', $box);
        $this->assertStringContainsString('11/27', $box);
        $this->assertStringContainsString('本週熱門', $box);
        $this->assertMatchesRegularExpression('/MCP 初體驗[\s\S]*?1,234/u', $box);
    }

    public function test_dashboard_empty_state(): void
    {
        $admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);

        $html = $this->actingAs($admin)->get('/admin')->getContent();

        $this->assertStringContainsString('這段期間還沒有瀏覽紀錄', $html);
    }
}
