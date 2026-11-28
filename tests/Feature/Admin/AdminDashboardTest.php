<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tag;
use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JYU-542: admin home — quick tweet + continue writing.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'JYu', 'email' => 'admin@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
    }

    private function draftPost(string $title, string $updatedAt, array $attrs = [], ?PostGroup $group = null): Post
    {
        $post = Post::create([
            'post_group_id' => ($group ?? PostGroup::create([]))->id, 'locale' => 'zh', 'slug' => uniqid('p'),
            'title' => $title, 'body' => str_repeat('字', 1240), 'status' => Post::STATUS_DRAFT, 'last_modified_at' => now(), ...$attrs,
        ]);
        $post->forceFill(['updated_at' => Carbon::parse($updatedAt)])->saveQuietly();

        return $post;
    }

    private function draftTweet(string $body, string $updatedAt): Tweet
    {
        $tweet = Tweet::create(['tweet_group_id' => TweetGroup::create([])->id, 'locale' => 'zh', 'body' => $body, 'status' => Tweet::STATUS_DRAFT]);
        $tweet->forceFill(['updated_at' => Carbon::parse($updatedAt)])->saveQuietly();

        return $tweet;
    }

    public function test_greeting_depends_on_time_of_day_in_display_timezone(): void
    {
        $admin = $this->admin();

        Carbon::setTestNow(Carbon::parse('2026-11-27 13:00', 'Asia/Taipei'));
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('午安，JYu');

        Carbon::setTestNow(Carbon::parse('2026-11-27 08:00', 'Asia/Taipei'));
        $this->actingAs($admin)->get('/admin')->assertSee('早安，JYu');

        Carbon::setTestNow(Carbon::parse('2026-11-27 21:00', 'Asia/Taipei'));
        $this->actingAs($admin)->get('/admin')->assertSee('晚上好，JYu');

        Carbon::setTestNow();
    }

    public function test_quick_tweet_box_has_markdown_mention_paste_tags_sensitive_language_and_actions(): void
    {
        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => '生活', 'slug' => 'life']);

        $html = $this->actingAs($this->admin())->get('/admin')->getContent();
        preg_match('/<section[^>]*aria-labelledby="qc-h"[^>]*>(.*?)<\/section>/s', $html, $m);
        $box = $m[1] ?? '';

        $this->assertStringContainsString('快速碎念', $box);
        $this->assertMatchesRegularExpression('/<form[^>]*method="POST"[^>]*action="'.preg_quote(route('admin.tweets.store'), '/').'"/', $box);
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="body"[^>]*@paste="handlePaste\(\$event\)"/', $box);
        $this->assertStringContainsString('detectMention()', $box);
        $this->assertStringContainsString('支援 Markdown、@ 引用文章、直接貼上圖片', $box);
        $this->assertMatchesRegularExpression('/name="tag_ids\[\]"[^>]*value="'.$tag->id.'"/', $box);
        $this->assertStringContainsString('敏感內容', $box);
        $this->assertMatchesRegularExpression('/<select[^>]*name="locale"/', $box);
        $this->assertMatchesRegularExpression('/<button[^>]*name="status"[^>]*value="draft"[^>]*>\s*存草稿/u', $box);
        $this->assertMatchesRegularExpression('/<button[^>]*name="status"[^>]*value="published"[^>]*>\s*發布/u', $box);
        $this->assertStringContainsString('⌘↵', $box);
        $this->assertStringContainsString('name="redirect_to" value="dashboard"', $box);
    }

    public function test_quick_tweet_publish_returns_to_dashboard(): void
    {
        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => '生活', 'slug' => 'life']);

        $this->actingAs($this->admin())->post(route('admin.tweets.store'), [
            'body' => '**快速**一則 @[MCP 初體驗](/zh/posts/mcp)', 'locale' => 'ja', 'status' => 'published',
            'tag_ids' => [$tag->id], 'media' => [['path' => 'uploads/a.png', 'type' => 'image', 'sensitive' => '1']],
            'redirect_to' => 'dashboard',
        ])->assertRedirect(route('admin.dashboard'))->assertSessionHas('success', '已發布');

        $tweet = Tweet::latest('id')->first();
        $this->assertSame('published', $tweet->status);
        $this->assertSame('ja', $tweet->locale);
        $this->assertTrue((bool) $tweet->media[0]['sensitive']);
        $this->assertTrue($tweet->tags->contains($tag));
    }

    public function test_quick_tweet_save_draft_returns_to_dashboard(): void
    {
        $this->actingAs($this->admin())->post(route('admin.tweets.store'), [
            'body' => '還沒想好', 'locale' => 'zh', 'status' => 'draft', 'redirect_to' => 'dashboard',
        ])->assertRedirect(route('admin.dashboard'))->assertSessionHas('success', '已存草稿');

        $this->assertSame('draft', Tweet::latest('id')->value('status'));
    }

    public function test_normal_tweet_form_still_goes_to_editor(): void
    {
        $this->actingAs($this->admin())->post(route('admin.tweets.store'), [
            'body' => 'x', 'locale' => 'zh', 'status' => 'draft',
        ])->assertRedirect(route('admin.tweets.edit', Tweet::latest('id')->first()));
    }

    public function test_continue_writing_shows_three_most_recent_drafts_with_details(): void
    {
        $this->draftPost('最舊的草稿', '2026-11-20 10:00');
        $post = $this->draftPost('2026 年 4 月', '2026-11-27 10:00');
        $original = Post::create([
            'post_group_id' => ($g = PostGroup::create([]))->id, 'locale' => 'zh', 'slug' => 'report', 'title' => '如何工作匯報？',
            'body' => 'x', 'status' => Post::STATUS_PUBLISHED, 'last_modified_at' => now(),
        ]);
        $translation = $this->draftPost('How to Report Progress?', '2026-11-26 10:00', ['locale' => 'en'], $g);
        $tweet = $this->draftTweet('關於早起這件事，我又試了一次', '2026-11-24 10:00');
        Post::create(['post_group_id' => PostGroup::create([])->id, 'locale' => 'zh', 'slug' => 'pub', 'title' => '已發布', 'body' => 'x', 'status' => 'published', 'last_modified_at' => now()]);

        Carbon::setTestNow(Carbon::parse('2026-11-27 12:00'));
        $html = $this->actingAs($this->admin())->get('/admin')->getContent();
        Carbon::setTestNow();

        preg_match('/<section[^>]*aria-labelledby="cw-h"[^>]*>(.*?)<\/section>/s', $html, $m);
        $cw = $m[1];

        $this->assertSame(3, substr_count($cw, 'data-draft'));
        $this->assertStringNotContainsString('最舊的草稿', $cw);
        $this->assertStringNotContainsString('已發布', $cw);
        // Order: newest edit first.
        $this->assertLessThan(strpos($cw, 'How to Report Progress?'), strpos($cw, '2026 年 4 月'));
        $this->assertLessThan(strpos($cw, '關於早起'), strpos($cw, 'How to Report Progress?'));

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.posts.edit', $post), '/').'"[\s\S]*?文章 · ZH · 2小時前[\s\S]*?1,240 字/u', $cw);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.posts.edit', $translation), '/').'"[\s\S]*?翻譯 · EN · 1天前[\s\S]*?譯自〈如何工作匯報？〉/u', $cw);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(e(route('admin.tweets.edit', ['tweet' => $tweet, 'from' => 'dashboard'])), '/').'"[\s\S]*?碎念 · ZH[\s\S]*?（未命名）關於早起/u', $cw);

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.drafts.index'), '/').'"[^>]*>\s*所有草稿 \(4\) →/u', $cw);
    }

    public function test_all_drafts_page_lists_every_draft(): void
    {
        foreach (range(1, 5) as $i) {
            $this->draftPost("草稿{$i}", "2026-11-2{$i} 10:00");
        }
        $this->draftTweet('碎念草稿', '2026-11-01 10:00');

        $html = $this->actingAs($this->admin())->get(route('admin.drafts.index'))->assertOk()->getContent();

        $this->assertSame(6, substr_count($html, 'data-draft'));
        $this->assertStringContainsString('所有草稿', $html);
    }

    public function test_empty_drafts_state(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertSee('目前沒有草稿');
    }
}
