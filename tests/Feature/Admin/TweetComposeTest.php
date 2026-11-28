<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use App\Models\Tweet;
use App\Models\User;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JYU-551: post a tweet quickly from a phone.
 */
class TweetComposeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);
    }

    private function tag(string $name): Tag
    {
        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => $name, 'slug' => 'tag-'.$tag->id]);

        return $tag;
    }

    public function test_composer_is_a_full_screen_page_with_cancel_and_publish(): void
    {
        $html = $this->get(route('admin.tweets.compose'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-admin-sidebar', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.dashboard'), '/').'"[^>]*>取消<\/a>/', $html);
        $this->assertMatchesRegularExpression('/<h1[^>]*>新碎念<\/h1>/', $html);
        $this->assertMatchesRegularExpression('/name="status" value="published" data-publish[^>]*>發布<\/button>/', $html);
        $this->assertStringContainsString('name="redirect_to" value="dashboard"', $html);
    }

    public function test_composer_offers_language_and_tags(): void
    {
        $this->tag('生活');
        $html = $this->get(route('admin.tweets.compose'))->getContent();

        $this->assertMatchesRegularExpression('/<select id="compose-locale" name="locale"/', $html);
        foreach (['繁中', 'English', '日本語', 'Tiếng Việt', 'Indonesia'] as $label) {
            $this->assertStringContainsString('>'.$label.'</option>', $html);
        }
        $this->assertStringContainsString('＋ 標籤', $html);
        $this->assertStringContainsString('\\u0022name\\u0022:\\u0022生活\\u0022', $html);
        $this->assertStringContainsString('name="tag_ids[]"', $html);
    }

    public function test_composer_has_photos_with_progress_and_removal_sensitive_switch_and_mentions(): void
    {
        $html = $this->get(route('admin.tweets.compose'))->getContent();

        $this->assertStringContainsString('aria-label="加入照片或影片"', $html);
        $this->assertStringContainsString("x-text=\"items.length + pending.length + ' / 4'\"", $html);
        $this->assertStringContainsString('data-upload-progress', $html);
        $this->assertStringContainsString("x-text=\"'上傳中 ' + job.progress + '%'\"", $html);
        $this->assertStringContainsString(":aria-label=\"'移除照片 ' + (i + 1)\"", $html);
        $this->assertStringContainsString('照片會先模糊，讀者點一下才顯示', $html);
        $this->assertMatchesRegularExpression('/\$dispatch\(\'insert-mention\'\)" aria-label="引用文章或碎念"/', $html);
        $this->assertStringContainsString('x-data="tweetComposer(', $html);
        $this->assertStringContainsString('data-autosave-status', $html);
    }

    public function test_publishing_a_new_tweet_with_photos_tags_and_sensitive_flag(): void
    {
        $tag = $this->tag('生活');

        $this->post(route('admin.tweets.store'), [
            'locale' => 'ja', 'body' => '今日は高雄へ', 'status' => 'published', 'redirect_to' => 'dashboard',
            'tag_ids' => [$tag->id],
            'media' => [
                ['path' => 'uploads/a.jpg', 'type' => 'image', 'alt' => '', 'sensitive' => '1'],
                ['path' => 'uploads/b.jpg', 'type' => 'image', 'alt' => '', 'sensitive' => '1'],
            ],
        ])->assertRedirect(route('admin.dashboard'))->assertSessionHas('success', '已發布');

        $tweet = Tweet::firstOrFail();
        $this->assertSame(['published', 'ja'], [$tweet->status, $tweet->locale]);
        $this->assertCount(2, $tweet->media);
        $this->assertTrue((bool) $tweet->media[0]['sensitive']);
        $this->assertSame([$tag->id], $tweet->tags()->pluck('tags.id')->all());
    }

    public function test_photos_are_limited_to_four(): void
    {
        $media = array_fill(0, 5, ['path' => 'uploads/a.jpg', 'type' => 'image']);

        $this->post(route('admin.tweets.store'), ['locale' => 'zh', 'body' => 'x', 'status' => 'published', 'media' => $media])
            ->assertSessionHasErrors('media');
        $this->postJson(route('admin.tweets.autosave'), ['locale' => 'zh', 'body' => 'x', 'media' => $media])
            ->assertStatus(422)->assertJsonValidationErrors('media');
    }

    public function test_autosave_creates_a_draft_then_updates_it(): void
    {
        $tag = $this->tag('生活');

        $res = $this->postJson(route('admin.tweets.autosave'), ['locale' => 'zh', 'body' => '剛開始', 'tag_ids' => [$tag->id]])
            ->assertOk()->assertJson(['saved' => true]);
        $tweet = Tweet::findOrFail($res->json('id'));
        $this->assertSame(Tweet::STATUS_DRAFT, $tweet->status);
        $this->assertSame(route('admin.tweets.update', $tweet), $res->json('update_url'));

        $this->postJson(route('admin.tweets.autosave'), [
            'tweet_id' => $tweet->id, 'body' => '寫完了',
            'media' => [['path' => 'uploads/a.jpg', 'type' => 'image', 'sensitive' => '1']],
        ])->assertOk()->assertJson(['id' => $tweet->id]);

        $tweet->refresh();
        $this->assertSame('寫完了', $tweet->body);
        $this->assertCount(1, $tweet->media);
        $this->assertSame([], $tweet->tags()->pluck('tags.id')->all(), 'a save without tags means the tags were removed');
        $this->assertSame(1, Tweet::count());
    }

    public function test_autosave_skips_empty_drafts_and_never_touches_published_tweets(): void
    {
        $this->postJson(route('admin.tweets.autosave'), ['locale' => 'zh', 'body' => ''])->assertStatus(422);

        $live = app(TweetService::class)->create(['locale' => 'zh', 'body' => '已發布', 'status' => 'published']);
        $this->postJson(route('admin.tweets.autosave'), ['tweet_id' => $live->id, 'body' => '改'])->assertStatus(409);

        $this->assertSame('已發布', $live->fresh()->body);
        $this->assertSame(1, Tweet::count());
    }

    public function test_publishing_an_autosaved_draft_publishes_that_tweet_and_returns_home(): void
    {
        $tag = $this->tag('生活');
        $draft = app(TweetService::class)->create(['locale' => 'zh', 'body' => '草稿', 'status' => 'draft', 'tag_ids' => [$tag->id]]);

        // The form now PUTs to the draft; no tag ticked + tags_sent means "no tags".
        $this->put(route('admin.tweets.update', $draft), [
            'body' => '發出去', 'status' => 'published', 'redirect_to' => 'dashboard', 'tags_sent' => '1',
        ])->assertRedirect(route('admin.dashboard'))->assertSessionHas('success', '已發布');

        $draft->refresh();
        $this->assertSame([Tweet::STATUS_PUBLISHED, '發出去'], [$draft->status, $draft->body]);
        $this->assertNotNull($draft->published_at);
        $this->assertSame(0, $draft->tags()->count());
        $this->assertSame(1, Tweet::count());
    }

    public function test_editing_a_tweet_elsewhere_still_returns_to_the_edit_page(): void
    {
        $tweet = app(TweetService::class)->create(['locale' => 'zh', 'body' => 'x', 'status' => 'draft']);

        $this->put(route('admin.tweets.update', $tweet), ['body' => 'y'])->assertRedirect(route('admin.tweets.edit', $tweet));
    }

    public function test_phones_get_a_shortcut_to_the_composer(): void
    {
        $shortcuts = [
            'admin.dashboard' => route('admin.tweets.compose'),
            'admin.tweets.index' => route('admin.tweets.compose', ['from' => 'tweets']),
        ];
        foreach ($shortcuts as $route => $href) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/href="'.preg_quote(e($href), '/').'" data-compose-fab\s+class="md:hidden/', $html);
        }
    }

    // JYU-563: the composer replaces the old create page on desktop too.

    public function test_new_tweet_button_on_the_list_opens_the_composer(): void
    {
        $html = $this->get(route('admin.tweets.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/href="'.preg_quote(e(route('admin.tweets.compose', ['from' => 'tweets'])), '/').'"[^>]*>\+ 新增 Tweet<\/a>/', $html);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.tweets.create'), 'the old create page is gone');
    }

    public function test_composer_opened_from_the_list_returns_to_the_list(): void
    {
        $html = $this->get(route('admin.tweets.compose', ['from' => 'tweets']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.tweets.index'), '/').'"[^>]*>取消<\/a>/', $html);
        $this->assertStringContainsString('name="redirect_to" value="tweets"', $html);
    }

    public function test_composer_ignores_unknown_origins(): void
    {
        $html = $this->get(route('admin.tweets.compose', ['from' => 'https://evil.example']))->assertOk()->getContent();

        $this->assertStringContainsString('name="redirect_to" value="dashboard"', $html);
    }

    public function test_publishing_from_the_list_returns_to_the_list(): void
    {
        $this->post(route('admin.tweets.store'), [
            'locale' => 'zh', 'body' => '新的', 'status' => 'published', 'redirect_to' => 'tweets',
        ])->assertRedirect(route('admin.tweets.index'))->assertSessionHas('success', '已發布');

        $draft = app(TweetService::class)->create(['locale' => 'zh', 'body' => '草稿', 'status' => 'draft']);
        $this->put(route('admin.tweets.update', $draft), [
            'body' => '發出去', 'status' => 'published', 'redirect_to' => 'tweets', 'tags_sent' => '1',
        ])->assertRedirect(route('admin.tweets.index'))->assertSessionHas('success', '已發布');
    }

    public function test_each_photo_has_its_own_alt_text_and_sensitive_flag(): void
    {
        $html = $this->get(route('admin.tweets.compose'))->getContent();

        $this->assertMatchesRegularExpression('/<input type="text" x-model="item.alt" maxlength="200"/', $html);
        $this->assertStringContainsString('x-model="item.sensitive"', $html);
        $this->assertStringContainsString(':value="item.sensitive ? \'1\' : \'0\'"', $html);
        $this->assertStringNotContainsString('x-model="sensitive"', $html, 'no tweet-wide sensitive switch');
    }

    public function test_publishing_keeps_alt_text_and_sensitive_flag_per_photo(): void
    {
        $this->post(route('admin.tweets.store'), [
            'locale' => 'zh', 'body' => '兩張', 'status' => 'published',
            'media' => [
                ['path' => 'uploads/a.jpg', 'type' => 'image', 'alt' => '海邊', 'sensitive' => '0'],
                ['path' => 'uploads/b.jpg', 'type' => 'image', 'alt' => '傷口', 'sensitive' => '1'],
            ],
        ])->assertSessionHasNoErrors();

        $media = Tweet::firstOrFail()->media;
        $this->assertSame(['海邊', '傷口'], array_column($media, 'alt'));
        $this->assertSame([false, true], array_map(fn ($m) => (bool) $m['sensitive'], $media));
    }

    public function test_composer_requires_admin(): void
    {
        auth()->logout();

        $this->get(route('admin.tweets.compose'))->assertRedirect();
        $this->postJson(route('admin.tweets.autosave'), ['locale' => 'zh', 'body' => 'x'])->assertUnauthorized();
    }
}
