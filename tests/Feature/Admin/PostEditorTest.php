<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use App\Services\TweetService;
use App\Support\MarkdownRenderer;
use App\Support\TocBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JYU-548: a distraction-free editor that autosaves and previews.
 */
class PostEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);
    }

    private function article(array $attrs = []): Post
    {
        return app(PostService::class)->create($attrs + [
            'locale' => 'zh', 'title' => '原文', 'slug' => uniqid('p'), 'body' => '內文', 'status' => 'published',
        ]);
    }

    public function test_editor_uses_focus_layout_without_admin_sidebar(): void
    {
        $html = $this->get(route('admin.posts.edit', $this->article()))->assertOk()->getContent();

        $this->assertStringContainsString('x-data="postEditor(', $html);
        $this->assertStringNotContainsString('data-admin-sidebar', $html);
        $this->assertStringContainsString('href="'.route('admin.posts.index').'"', $html);
    }

    public function test_header_lists_language_versions_and_offers_to_create_the_missing_ones(): void
    {
        $zh = $this->article();
        $ja = app(PostService::class)->createTranslation($zh, 'ja');

        $html = $this->get(route('admin.posts.edit', $zh))->assertOk()->getContent();
        preg_match('/<nav aria-label="翻譯版本" data-versions.*?<\/nav>/s', $html, $m);
        $nav = $m[0];

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.posts.edit', $zh), '/').'"\s+aria-current="page"/', $nav);
        $this->assertStringContainsString('href="'.route('admin.posts.edit', $ja).'"', $nav);
        foreach (['en', 'vi', 'id'] as $loc) {
            $this->assertStringContainsString('data-create-version="'.$loc.'"', $nav);
            $this->assertStringContainsString('id="translate-post-'.$loc.'"', $html);
        }
        $this->assertStringNotContainsString('data-create-version="ja"', $nav);

        // The "＋" button's form creates that language version.
        $this->post(route('admin.posts.create-translation', $zh), ['locale' => 'en'])->assertRedirect();
        $this->assertDatabaseHas('posts', ['post_group_id' => $zh->post_group_id, 'locale' => 'en']);
    }

    public function test_autosave_creates_a_draft_for_a_new_post_then_updates_it(): void
    {
        $res = $this->postJson(route('admin.posts.autosave'), ['locale' => 'zh', 'title' => '', 'body' => '剛開始寫'])
            ->assertOk()->assertJson(['saved' => true]);
        $post = Post::findOrFail($res->json('id'));

        $this->assertSame(Post::STATUS_DRAFT, $post->status);
        $this->assertSame('未命名草稿', $post->title);
        $this->assertSame(route('admin.posts.update', $post), $res->json('update_url'));

        $this->postJson(route('admin.posts.autosave'), ['post_id' => $post->id, 'title' => '正式標題', 'body' => '寫多一點'])
            ->assertOk()->assertJson(['saved' => true, 'id' => $post->id]);

        $this->assertSame(['正式標題', '寫多一點'], [$post->fresh()->title, $post->fresh()->body]);
        $this->assertSame(1, Post::count());
    }

    public function test_autosave_never_touches_published_posts_and_ignores_empty_drafts(): void
    {
        $published = $this->article();

        $this->postJson(route('admin.posts.autosave'), ['post_id' => $published->id, 'title' => 'X', 'body' => '改了'])
            ->assertStatus(409)->assertJson(['saved' => false, 'reason' => 'not_draft']);
        $this->assertSame('內文', $published->fresh()->body);

        $this->postJson(route('admin.posts.autosave'), ['locale' => 'zh', 'title' => '', 'body' => ''])
            ->assertStatus(422)->assertJson(['reason' => 'empty']);
        $this->assertSame(1, Post::count());
    }

    public function test_autosave_requires_admin(): void
    {
        auth()->logout();

        $this->postJson(route('admin.posts.autosave'), ['locale' => 'zh', 'body' => 'x'])->assertUnauthorized();
        $this->assertSame(0, Post::count());
    }

    public function test_editor_shows_autosave_status_and_three_view_modes(): void
    {
        $html = $this->get(route('admin.posts.edit', $this->article(['status' => 'draft'])))->getContent();

        $this->assertStringContainsString('data-autosave-status', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        foreach (['write' => '寫作', 'split' => '左右對照', 'preview' => '預覽'] as $mode => $label) {
            $this->assertMatchesRegularExpression('/data-mode="'.$mode.'"[^>]*>'.$label.'<\/button>/', $html);
        }
        $this->assertStringContainsString('data-preview-pane', $html);
    }

    public function test_preview_uses_the_public_markdown_pipeline(): void
    {
        $res = $this->postJson(route('admin.posts.preview'), ['body' => "## 小節\n\n**粗體** 與 `code`\n\n<script>alert(1)</script>"])
            ->assertOk();
        $html = $res->json('html');

        $this->assertMatchesRegularExpression('/<h2[^>]*id="[^"]+"[^>]*>/', $html);
        $this->assertStringContainsString('<strong>粗體</strong>', $html);
        $this->assertStringContainsString('<code>code</code>', $html);
        // Identical to what the public post page renders for the same body.
        $expected = TocBuilder::build(app(MarkdownRenderer::class)->render("## 小節\n\n**粗體** 與 `code`\n\n<script>alert(1)</script>"))['html'];
        $this->assertSame($expected, $html);

        // Same prose classes as the public post page.
        $public = $this->get(route('public.posts.show', ['zh', $this->article(['slug' => 'pub'])->slug]))->getContent();
        $this->assertStringContainsString('prose-blog post-body', $public);
        $editor = $this->get(route('admin.posts.create'))->getContent();
        $this->assertStringContainsString('class="prose-blog post-body" x-html="previewHtml"', $editor);
    }

    public function test_format_toolbar_has_the_formatting_buttons(): void
    {
        $html = $this->get(route('admin.posts.create'))->assertOk()->getContent();

        preg_match('/<div role="toolbar" aria-label="格式".*?<\/div>/s', $html, $m);
        foreach (['bold' => '粗體', 'italic' => '斜體', 'heading' => '標題', 'quote' => '引用區塊', 'code' => '程式碼', 'mention' => '引用文章或碎念'] as $kind => $label) {
            $this->assertMatchesRegularExpression("/format\\('{$kind}', \\\$refs.body\\)[^>]*aria-label=\"{$label}\"/", $m[0]);
        }
    }

    public function test_footer_shows_counts_and_shortcuts_are_bound(): void
    {
        $html = $this->get(route('admin.posts.create'))->getContent();

        preg_match('/<div data-editor-stats.*?<\/div>/s', $html, $m);
        $this->assertStringContainsString('x-text="statsLabel"', $m[0]);
        $this->assertStringContainsString('引用 0 篇', $m[0]);
        $this->assertStringContainsString('⌘S</kbd> 儲存', $m[0]);
        $this->assertStringContainsString('⌘⇧P</kbd> 預覽', $m[0]);

        $this->assertStringContainsString('@keydown.window.meta.s.prevent="save()"', $html);
        $this->assertStringContainsString('@keydown.window.meta.shift.p.prevent="togglePreview()"', $html);
    }

    public function test_sidebar_has_publish_settings_slug_excerpt_categories_and_tags(): void
    {
        $post = $this->article(['excerpt' => '十個字的摘要喔喔喔', 'is_featured' => true]);
        $html = $this->get(route('admin.posts.edit', $post))->getContent();

        $this->assertMatchesRegularExpression('/<select name="status"[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/name="is_featured" value="1" checked/', $html);
        $this->assertMatchesRegularExpression('/name="slug" value="'.$post->slug.'"/', $html);
        $this->assertMatchesRegularExpression('/data-excerpt-count.*?<span x-text="n">9<\/span> \/ 120/s', $html);
        $this->assertStringContainsString('分類與標籤', $html);
        $this->assertStringContainsString('name="excerpt"', $html);
    }

    public function test_saving_from_the_editor_persists_sidebar_settings(): void
    {
        $post = $this->article(['status' => 'draft']);

        $this->put(route('admin.posts.update', $post), [
            'title' => '新標題', 'body' => '新內文', 'status' => 'published', 'is_featured' => '1',
            'slug' => 'new-slug', 'excerpt' => '摘要',
        ])->assertRedirect(route('admin.posts.edit', $post));

        $post->refresh();
        $this->assertSame(['published', true, 'new-slug', '摘要'], [$post->status, $post->is_featured, $post->slug, $post->excerpt]);
    }

    public function test_sidebar_lists_what_this_post_cites_and_what_cites_it(): void
    {
        $target = $this->article(['title' => '被我引用的文章', 'slug' => 'target']);
        $post = $this->article(['title' => '本篇', 'body' => '參考 [這篇](/zh/posts/target)']);
        $tweet = app(TweetService::class)->create(['locale' => 'zh', 'body' => '碎念提到 [本篇](/zh/posts/'.$post->slug.')', 'status' => 'published']);

        $html = $this->get(route('admin.posts.edit', $post))->getContent();
        preg_match('/<section[^>]*data-references>.*?<\/section>/s', $html, $m);

        $this->assertStringContainsString('→ 被我引用的文章', $m[0]);
        $this->assertStringContainsString('href="'.route('admin.tweets.edit', $tweet).'"', $m[0]);
        $this->assertStringContainsString('← 碎念提到', $m[0]);
    }

    public function test_sidebar_shows_translation_states_including_stale_drafts(): void
    {
        $zh = $this->article();
        $ja = app(PostService::class)->createTranslation($zh, 'ja');
        $ja->forceFill(['last_modified_at' => Carbon::parse('2026-01-01')])->save();
        $zh->forceFill(['last_modified_at' => Carbon::parse('2026-02-01')])->save();

        $html = $this->get(route('admin.posts.edit', $ja))->getContent();
        preg_match('/<section[^>]*data-translation-states>.*?<\/section>/s', $html, $m);

        $this->assertMatchesRegularExpression('/原文 · 已發布/', $m[0]);
        $this->assertMatchesRegularExpression('/草稿 · 比原文舊/', $m[0]);
        $this->assertStringContainsString('data-stale', $html);
    }

    public function test_youtube_prompt_offers_embed_with_start_time_or_keeping_the_link(): void
    {
        $html = $this->get(route('admin.posts.create'))->getContent();

        $this->assertStringContainsString('偵測到 YouTube 網址，要轉成嵌入影片嗎？', $html);
        $this->assertStringContainsString('aria-label="起始時間"', $html);
        $this->assertMatchesRegularExpression('/@click="embedYoutube\(\)"[^>]*>轉成嵌入</', $html);
        $this->assertMatchesRegularExpression('/@click="dismissYtPrompt\(\)"[^>]*>保留連結</', $html);
    }
}
