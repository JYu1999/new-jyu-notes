<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use App\Models\Tweet;
use App\Models\User;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JYU-567: tweets are edited in the same composer they are written in,
 * and hidden / unhidden from the tweet list.
 */
class TweetEditComposeTest extends TestCase
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

    private function tweet(string $status, array $extra = []): Tweet
    {
        return app(TweetService::class)->create(['locale' => 'zh', 'body' => '早起這件事', 'status' => $status] + $extra);
    }

    public function test_edit_page_is_the_composer_filled_with_the_tweet(): void
    {
        $tag = $this->tag('生活');
        $this->tag('工作');
        $tweet = $this->tweet('published', [
            'tag_ids' => [$tag->id],
            'media' => [['path' => 'tweets/a.jpg', 'type' => 'image', 'alt' => '早餐', 'sensitive' => true]],
        ]);

        $html = $this->get(route('admin.tweets.edit', $tweet))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-admin-sidebar', $html);
        $this->assertMatchesRegularExpression('/<h1[^>]*>編輯碎念<\/h1>/', $html);
        $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('admin.tweets.update', $tweet), '/').'" id="compose-form"/', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertMatchesRegularExpression('/<textarea id="compose-body"[^>]*>早起這件事<\/textarea>/', $html);
        // Tags and photos start from what the tweet already has.
        $this->assertStringContainsString('\\u0022selected\\u0022:['.$tag->id.']', $html);
        $this->assertMatchesRegularExpression('#tweets[\\\\]*/a\.jpg#', $html);
        $this->assertStringContainsString('\\u0022alt\\u0022:\\u0022早餐\\u0022', $html);
        // Clearing every photo still sends `media` so the server clears it.
        $this->assertStringContainsString('<input type="hidden" name="media" value="">', $html);
        // The language can't be changed while editing.
        $this->assertStringNotContainsString('id="compose-locale"', $html);
        $this->assertMatchesRegularExpression('/data-compose-locale[^>]*>繁中</', $html);
    }

    public function test_edit_page_keeps_translations_history_and_dates(): void
    {
        $tweet = $this->tweet('published');
        $en = app(TweetService::class)->createTranslation($tweet, 'en');

        $html = $this->get(route('admin.tweets.edit', $tweet))->getContent();

        preg_match('/<section[^>]*data-tweet-info.*?<\/section>/s', $html, $m);
        $this->assertNotEmpty($m, 'info section under the text');
        $info = $m[0];
        $this->assertStringContainsString('翻譯版本', $info);
        $this->assertStringContainsString('href="'.route('admin.tweets.edit', $en).'"', $info);
        $this->assertStringContainsString('form="translate-tweet-ja"', $info);
        $this->assertStringContainsString('href="'.route('admin.revisions.index', ['type' => 'tweets', 'id' => $tweet->id]).'"', $info);
        $this->assertStringContainsString('建立', $info);
        $this->assertStringContainsString('更新', $info);
        $this->assertStringContainsString('id="translate-tweet-ja"', $html);
        $this->assertStringNotContainsString('id="translate-tweet-en"', $html);
    }

    public function test_the_new_tweet_composer_has_no_info_section(): void
    {
        $html = $this->get(route('admin.tweets.compose'))->getContent();

        $this->assertStringNotContainsString('data-tweet-info', $html);
        $this->assertMatchesRegularExpression('/<select id="compose-locale"/', $html);
    }

    public function test_drafts_autosave_and_publish(): void
    {
        $html = $this->get(route('admin.tweets.edit', $this->tweet('draft')))->getContent();

        $this->assertMatchesRegularExpression('/name="status" value="published" data-publish[^>]*>發布<\/button>/', $html);
        $this->assertStringContainsString('\\u0022autosave\\u0022:true', $html);
    }

    public function test_live_tweets_only_save_when_asked(): void
    {
        foreach (['published', 'hidden'] as $status) {
            $html = $this->get(route('admin.tweets.edit', $this->tweet($status)))->getContent();

            $this->assertMatchesRegularExpression('/<button type="submit" data-save[^>]*>儲存<\/button>/', $html, $status);
            $this->assertStringNotContainsString('data-publish', $html, $status);
            $this->assertStringContainsString('\\u0022autosave\\u0022:false', $html, $status);
        }
    }

    public function test_cancel_returns_to_where_the_editor_was_opened(): void
    {
        $tweet = $this->tweet('draft');
        $cases = [
            [[], 'tweets', route('admin.tweets.index')],
            [['from' => 'tweets'], 'tweets', route('admin.tweets.index')],
            [['from' => 'dashboard'], 'dashboard', route('admin.dashboard')],
            [['from' => 'drafts'], 'drafts', route('admin.drafts.index')],
            [['from' => 'https://evil.example'], 'tweets', route('admin.tweets.index')],
        ];
        foreach ($cases as [$query, $origin, $cancel]) {
            $html = $this->get(route('admin.tweets.edit', ['tweet' => $tweet] + $query))->getContent();

            $this->assertMatchesRegularExpression('/href="'.preg_quote($cancel, '/').'"[^>]*>取消<\/a>/', $html, json_encode($query));
            $this->assertStringContainsString('name="redirect_to" value="'.$origin.'"', $html, json_encode($query));
        }
    }

    public function test_saving_a_live_tweet_keeps_it_live_and_returns_to_the_list(): void
    {
        $tag = $this->tag('生活');
        $tweet = $this->tweet('hidden', ['tag_ids' => [$tag->id], 'media' => [['path' => 'tweets/a.jpg', 'type' => 'image']]]);

        $this->put(route('admin.tweets.update', $tweet), [
            'body' => '改過了', 'media' => '', 'tags_sent' => '1', 'redirect_to' => 'tweets',
        ])->assertRedirect(route('admin.tweets.index'))->assertSessionHas('success', '已更新');

        $tweet->refresh();
        $this->assertSame('改過了', $tweet->body);
        $this->assertSame('hidden', $tweet->status);
        $this->assertEmpty($tweet->media);
        $this->assertSame(0, $tweet->tags()->count());
    }

    public function test_publishing_a_draft_from_the_editor(): void
    {
        $tweet = $this->tweet('draft');

        $this->put(route('admin.tweets.update', $tweet), [
            'body' => '寫好了', 'status' => 'published', 'tags_sent' => '1', 'redirect_to' => 'drafts',
        ])->assertRedirect(route('admin.drafts.index'))->assertSessionHas('success', '已發布');

        $this->assertSame('published', $tweet->refresh()->status);
    }

    public function test_draft_cards_open_the_editor_and_come_back(): void
    {
        $tweet = $this->tweet('draft');

        $this->assertStringContainsString('href="'.e(route('admin.tweets.edit', ['tweet' => $tweet, 'from' => 'dashboard'])).'" data-draft', $this->get(route('admin.dashboard'))->getContent());
        $this->assertStringContainsString('href="'.e(route('admin.tweets.edit', ['tweet' => $tweet, 'from' => 'drafts'])).'" data-draft', $this->get(route('admin.drafts.index'))->getContent());
    }

    // Hiding moved from the editor to the list.

    public function test_the_list_offers_hide_and_unhide(): void
    {
        $published = $this->tweet('published');
        $hidden = $this->tweet('hidden');
        $draft = $this->tweet('draft');

        $html = $this->get(route('admin.tweets.index'))->getContent();
        $form = fn (Tweet $t, string $status, string $label) => '/<form method="POST" action="'.preg_quote(route('admin.tweets.status', $t), '/').'"[^>]*>[\s\S]*?name="_method" value="PATCH"[\s\S]*?name="status" value="'.$status.'"[\s\S]*?>'.$label.'<\/button>/u';

        $this->assertMatchesRegularExpression($form($published, 'hidden', '隱藏'), $html);
        $this->assertMatchesRegularExpression($form($hidden, 'published', '取消隱藏'), $html);
        $this->assertStringNotContainsString('action="'.route('admin.tweets.status', $draft).'"', $html);

        // The live filter swaps in the same rows.
        $partial = $this->get(route('admin.tweets.index', ['partial' => 1]))->getContent();
        $this->assertMatchesRegularExpression($form($published, 'hidden', '隱藏'), $partial);
    }

    public function test_hiding_and_unhiding_stays_on_the_same_list_page(): void
    {
        $tweet = $this->tweet('published');
        $list = route('admin.tweets.index', ['status' => 'published', 'q' => '早起', 'page' => 2]);

        $this->from($list)->patch(route('admin.tweets.status', $tweet), ['status' => 'hidden'])
            ->assertRedirect($list)->assertSessionHas('success', '已隱藏');
        $this->assertSame('hidden', $tweet->refresh()->status);

        $this->from($list)->patch(route('admin.tweets.status', $tweet), ['status' => 'published'])
            ->assertRedirect($list)->assertSessionHas('success', '已取消隱藏');
        $this->assertSame('published', $tweet->refresh()->status);
    }

    public function test_the_old_edit_form_is_gone(): void
    {
        $this->assertFalse(view()->exists('admin.tweets.edit'));
    }
}
