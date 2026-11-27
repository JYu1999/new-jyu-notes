<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Models\User;
use App\Services\PostService;
use App\Services\TranslationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JYU-544: missing / out-of-date translations and unused media.
 */
class TranslationStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
    }

    private function makePost(string $locale, string $title, ?PostGroup $group = null, string $modified = '2026-01-01', string $status = 'published'): Post
    {
        return Post::create([
            'post_group_id' => ($group ?? PostGroup::create([]))->id, 'locale' => $locale, 'slug' => uniqid('p'),
            'title' => $title, 'body' => 'x', 'status' => $status, 'last_modified_at' => Carbon::parse($modified),
        ]);
    }

    private function service(): TranslationStatusService
    {
        return app(TranslationStatusService::class);
    }

    public function test_stale_when_original_modified_after_translation(): void
    {
        $g = PostGroup::create([]);
        $original = $this->makePost('zh', '原文', $g, '2026-03-01');
        $fresh = $this->makePost('en', 'Fresh', $g, '2026-03-02');
        $old = $this->makePost('ja', '古い', $g, '2026-02-01');

        $this->assertFalse($this->service()->isStale($original));
        $this->assertFalse($this->service()->isStale($fresh));
        $this->assertTrue($this->service()->isStale($old));
    }

    public function test_updating_the_translation_clears_stale(): void
    {
        $g = PostGroup::create([]);
        $this->makePost('zh', '原文', $g, '2026-03-01');
        $ja = $this->makePost('ja', '古い', $g, '2026-02-01');
        $this->assertTrue($this->service()->isStale($ja));

        app(PostService::class)->update($ja, ['body' => '更新した本文']);

        $this->assertFalse($this->service()->isStale($ja->fresh()));
    }

    public function test_editing_the_original_makes_translations_stale(): void
    {
        $g = PostGroup::create([]);
        $zh = $this->makePost('zh', '原文', $g, '2026-01-01');
        $en = $this->makePost('en', 'Translation', $g, '2026-02-01');
        $this->assertFalse($this->service()->isStale($en));

        app(PostService::class)->update($zh, ['body' => '原文改了']);

        $this->assertTrue($this->service()->isStale($en->fresh()));
    }

    public function test_dashboard_lists_missing_and_stale_items_linking_to_filtered_post_list(): void
    {
        // English is a language the author translates into (one EN translation exists).
        $g1 = PostGroup::create([]);
        $this->makePost('zh', '有英文版', $g1, '2026-01-01');
        $this->makePost('en', 'Has English', $g1, '2026-02-01');
        // Two published originals without English, one draft (doesn't count).
        $this->makePost('zh', '缺英文一');
        $this->makePost('zh', '缺英文二');
        $this->makePost('zh', '草稿', status: 'draft');
        // One stale Japanese translation.
        $g2 = PostGroup::create([]);
        $this->makePost('zh', '日文原文', $g2, '2026-05-01');
        $this->makePost('ja', '古い日本語', $g2, '2026-04-01');

        $html = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();
        preg_match('/<section[^>]*aria-labelledby="todo-h"[^>]*>(.*?)<\/section>/s', $html, $m);
        $box = $m[1];

        // 缺英文: 缺英文一、缺英文二、日文原文 (g2 has no EN).
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.posts.index', ['missing' => 'en']), '/').'"[\s\S]*?3 篇文章缺英文版/u', $box);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.posts.index', ['stale' => 'ja']), '/').'"[\s\S]*?1 篇日文版比原文舊/u', $box);
        // Vietnamese is never used → no nagging.
        $this->assertStringNotContainsString('越南文', $box);

        $missing = $this->actingAs($this->admin)->get(route('admin.posts.index', ['missing' => 'en']))->assertOk()->getContent();
        $this->assertStringContainsString('缺英文版的文章（3）', $missing);
        foreach (['缺英文一', '缺英文二', '日文原文'] as $t) {
            $this->assertStringContainsString($t, $missing);
        }
        $this->assertStringNotContainsString('有英文版', $missing);

        $stale = $this->actingAs($this->admin)->get(route('admin.posts.index', ['stale' => 'ja']))->getContent();
        $this->assertStringContainsString('古い日本語', $stale);
        $this->assertStringNotContainsString('日文原文', $stale);
    }

    public function test_stale_translation_is_labelled_in_list_and_editor_and_label_goes_away_after_update(): void
    {
        $g = PostGroup::create([]);
        $this->makePost('zh', '原文', $g, '2026-05-01');
        $ja = $this->makePost('ja', '古い日本語', $g, '2026-04-01');

        $list = $this->actingAs($this->admin)->get(route('admin.posts.index'))->getContent();
        $this->assertSame(1, substr_count($list, 'data-stale'));
        $this->assertStringContainsString('比原文舊', $list);
        $this->actingAs($this->admin)->get(route('admin.posts.edit', $ja))->assertSee('比原文舊');

        app(PostService::class)->update($ja, ['body' => '同期しました']);

        $this->assertStringNotContainsString('data-stale', $this->actingAs($this->admin)->get(route('admin.posts.index'))->getContent());
        $this->actingAs($this->admin)->get(route('admin.posts.edit', $ja))->assertDontSee('比原文舊');
    }

    public function test_unused_media_item_and_filter(): void
    {
        $used1 = Media::create(['path' => 'uploads/2026/01/in-post.png', 'mime_type' => 'image/png', 'size' => 1, 'original_filename' => 'in-post.png']);
        $used2 = Media::create(['path' => 'uploads/2026/01/in-tweet.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'original_filename' => 'in-tweet.jpg']);
        $used3 = Media::create(['path' => 'uploads/2026/01/cover.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'original_filename' => 'cover.jpg']);
        $unused = Media::create(['path' => 'uploads/2026/01/orphan.png', 'mime_type' => 'image/png', 'size' => 1, 'original_filename' => 'orphan.png']);

        $p = $this->makePost('zh', 'A');
        $p->update(['body' => '![](/storage/uploads/2026/01/in-post.png)', 'cover_image_path' => 'uploads/2026/01/cover.jpg']);
        Tweet::create(['tweet_group_id' => TweetGroup::create([])->id, 'locale' => 'zh', 'body' => 't', 'status' => 'published',
            'media' => [['path' => 'uploads/2026/01/in-tweet.jpg', 'type' => 'image']]]);

        $html = $this->actingAs($this->admin)->get('/admin')->getContent();
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.media.index', ['unused' => 1]), '/').'"[\s\S]*?1 個媒體未被使用/u', $html);

        $media = $this->actingAs($this->admin)->get(route('admin.media.index', ['unused' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('orphan.png', $media);
        $this->assertStringNotContainsString('in-post.png', $media);
        $this->assertStringNotContainsString('in-tweet.jpg', $media);
        $this->assertStringNotContainsString('cover.jpg', $media);
        $this->assertStringContainsString('未被使用的媒體（1）', $media);
    }

    public function test_nothing_to_do(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertSee('目前沒有需要處理的事。');
    }
}
