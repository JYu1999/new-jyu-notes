<?php

namespace Tests\Feature\Admin;

use App\Models\ContentRevision;
use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tag;
use App\Models\User;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\RevisionService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JYU-553: see what changed in each edit and restore an older version.
 */
class RevisionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.display_timezone' => 'Asia/Taipei']);
        $this->travelTo(Carbon::parse('2026-11-27 06:00:00', 'UTC'));
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
        $this->actingAs($this->admin);
    }

    private function article(array $attrs = []): Post
    {
        return app(PostService::class)->create($attrs + [
            'locale' => 'zh', 'title' => 'MCP 初體驗', 'slug' => 'mcp', 'body' => "第一段\n\n我進來這件公司", 'status' => 'draft',
        ]);
    }

    private function tag(string $name): Tag
    {
        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => $name, 'slug' => 'tag-'.$tag->id]);

        return $tag;
    }

    private function history(string $type, int $id, array $query = []): string
    {
        return $this->get(route('admin.revisions.index', ['type' => $type, 'id' => $id] + $query))->assertOk()->getContent();
    }

    public function test_every_save_records_a_version_with_who_and_the_change(): void
    {
        $post = $this->article();
        $this->travel(15)->minutes();
        app(PostService::class)->update($post, ['body' => "第一段\n\n我進來這間公司"]);
        $this->travel(15)->minutes();
        app(PostService::class)->updateStatus($post->fresh(), Post::STATUS_PUBLISHED);

        $revs = $post->revisions()->orderBy('id')->get();
        $this->assertSame(['create', 'edit', 'status'], $revs->pluck('kind')->all());
        $this->assertSame(['建立草稿', '編輯內文', '草稿 → 已發布'], $revs->pluck('summary')->all());
        $this->assertSame([1, 1], [$revs[1]->chars_added, $revs[1]->chars_removed]);
        $this->assertSame($this->admin->id, $revs[1]->user_id);
    }

    public function test_nothing_changed_means_no_new_version_and_quick_edits_are_folded(): void
    {
        $post = $this->article();
        app(PostService::class)->update($post, ['body' => "第一段\n\n我進來這件公司"]);
        $this->assertSame(1, $post->revisions()->count());

        $this->travel(15)->minutes();
        app(PostService::class)->update($post, ['body' => "第一段\n\n改一"]);
        $this->travel(2)->minutes();
        app(PostService::class)->update($post, ['body' => "第一段\n\n改二"]);

        $this->assertSame(2, $post->revisions()->count(), 'autosave-style edits within 10 minutes become one version');
        $this->assertSame("第一段\n\n改二", $post->revisions()->latest('id')->first()->snapshot['body']);
    }

    public function test_history_page_lists_versions_by_day_with_counts(): void
    {
        $post = $this->article();
        $this->travel(1)->day();
        app(PostService::class)->update($post, ['body' => "第一段\n\n我進來這間公司，多寫一點"]);

        $html = $this->history('posts', $post->id);

        $this->assertStringContainsString('版本紀錄 · 2', $html);
        $this->assertStringContainsString('今天 11/28', $html);
        $this->assertStringContainsString('昨天 11/27', $html);
        $this->assertMatchesRegularExpression('/編輯內文<\/span>\s*<span[^>]*>你 · 14:00<\/span>\s*<\/span>\s*<span[^>]*>\+6 −1<\/span>/', $html);
        $this->assertStringContainsString('href="'.route('admin.revisions.index', ['type' => 'posts', 'id' => $post->id]).'" data-history-link', $this->get(route('admin.posts.edit', $post))->getContent());
    }

    public function test_selected_version_shows_only_changed_paragraphs_with_marks_and_field_changes(): void
    {
        $post = $this->article();
        $this->travel(15)->minutes();
        app(PostService::class)->update($post, ['body' => "第一段\n\n我進來這間公司"]);

        $html = $this->history('posts', $post->id);

        $this->assertSame(1, substr_count($html, 'data-changed-paragraph'));
        $this->assertStringContainsString('我進來這<del>件</del><ins>間</ins>公司', $html);
        $this->assertStringContainsString('第 2 段', $html);
        $this->assertStringContainsString('欄位：標題、slug、標籤、分類、狀態 皆未變更', $html);

        $tag = $this->tag('人工智慧');
        $this->travel(15)->minutes();
        app(PostService::class)->update($post->fresh(), ['title' => '新標題', 'tag_ids' => [$tag->id]]);
        $html = $this->history('posts', $post->id);
        $this->assertStringContainsString('<span class="text-ink">標題</span> MCP 初體驗 → 新標題', $html);
        $this->assertStringContainsString('<span class="text-ink">標籤</span> （無） → #人工智慧', $html);
        // Changing the title also regenerates the slug.
        $this->assertStringContainsString('修改標題、修改網址、新增標籤 #人工智慧', $html);
    }

    public function test_filters_for_ai_and_status_changes(): void
    {
        $post = $this->article();
        $this->travel(15)->minutes();
        app(PostService::class)->updateStatus($post, Post::STATUS_PUBLISHED);

        // A change made through an AI connection counts as AI (EnsureAiConnection calls actAsAi).
        $this->travel(15)->minutes();
        app(RevisionService::class)->actAsAi('Claude', $this->admin->id);
        app(PostService::class)->update($post->fresh(), ['body' => "第一段\n\n我進來這間公司"]);
        app()->forgetScopedInstances();

        $ai = $post->revisions()->latest('id')->first();
        $this->assertSame([ContentRevision::ACTOR_AI, 'Claude'], [$ai->actor, $ai->actor_name]);

        $onlyAi = $this->history('posts', $post->id, ['filter' => 'ai']);
        $this->assertSame(1, substr_count($onlyAi, 'data-revision='));
        $this->assertStringContainsString('透過 API／AI 連線 · Claude', $onlyAi);

        $onlyStatus = $this->history('posts', $post->id, ['filter' => 'status']);
        $this->assertSame(1, substr_count($onlyStatus, 'data-revision='));
        $this->assertStringContainsString('草稿 → 已發布', $onlyStatus);
    }

    public function test_compare_with_any_older_version(): void
    {
        $post = $this->article();
        foreach (['二', '三'] as $word) {
            $this->travel(15)->minutes();
            app(PostService::class)->update($post->fresh(), ['body' => "第一段\n\n版本{$word}"]);
        }
        [$first, , $third] = $post->revisions()->orderBy('id')->get()->all();

        $html = $this->history('posts', $post->id, ['rev' => $third->id, 'compare' => $first->id]);

        $this->assertStringContainsString('<del>我進來這件公司</del><ins>版本三</ins>', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$first->id.'"\s+selected>/', $html);
        $this->assertStringContainsString('<option value=""', $html, 'previous version is the default target');
    }

    public function test_restore_before_a_version_brings_the_content_back_as_a_new_version(): void
    {
        $tag = $this->tag('生活');
        $post = $this->article(['tag_ids' => [$tag->id]]);
        $this->travel(15)->minutes();
        app(PostService::class)->update($post, ['title' => 'AI 改壞的標題', 'body' => '全部被改掉', 'tag_ids' => []]);
        $bad = $post->revisions()->latest('id')->first();

        $this->post(route('admin.revisions.restore', ['type' => 'posts', 'id' => $post->id, 'revision' => $bad->id]))
            ->assertRedirect()->assertSessionHas('success');

        $post->refresh();
        $this->assertSame(['MCP 初體驗', "第一段\n\n我進來這件公司"], [$post->title, $post->body]);
        $this->assertSame([$tag->id], $post->tags()->pluck('tags.id')->all());
        $latest = $post->revisions()->latest('id')->first();
        $this->assertSame('restore', $latest->kind);
        $this->assertStringStartsWith('還原成 ', $latest->summary);
        $this->assertSame(3, $post->revisions()->count());
        $this->assertSame(Post::STATUS_DRAFT, $post->status, 'restoring never changes the status');
    }

    public function test_the_first_version_cannot_be_restored_before(): void
    {
        $post = $this->article();
        $first = $post->revisions()->first();

        $this->assertStringNotContainsString('data-restore', $this->history('posts', $post->id));
        $this->post(route('admin.revisions.restore', ['type' => 'posts', 'id' => $post->id, 'revision' => $first->id]))->assertStatus(422);
    }

    public function test_content_from_before_version_history_gets_a_baseline(): void
    {
        $post = Post::create(['post_group_id' => PostGroup::create()->id, 'locale' => 'zh', 'slug' => 'old', 'title' => '舊文', 'body' => '原本', 'status' => 'published', 'last_modified_at' => now()]);

        app(PostService::class)->update($post, ['body' => '修改後']);

        $revs = $post->revisions()->orderBy('id')->get();
        $this->assertSame(['開始記錄版本前的內容', '編輯內文'], $revs->pluck('summary')->all());
        $this->assertSame('原本', $revs[0]->snapshot['body']);
    }

    public function test_tweets_and_pages_keep_versions_too(): void
    {
        $tweet = app(TweetService::class)->create(['locale' => 'zh', 'body' => '碎念一', 'status' => 'published']);
        $this->travel(15)->minutes();
        app(TweetService::class)->update($tweet, ['body' => '碎念二']);
        $this->assertStringContainsString('碎念<del>一</del><ins>二</ins>', $this->history('tweets', $tweet->id));

        $page = app(PageService::class)->create(['locale' => 'zh', 'title' => '關於', 'slug' => 'about', 'body' => '你好']);
        $this->travel(15)->minutes();
        app(PageService::class)->update($page, ['body' => '大家好']);
        $this->assertSame(2, $page->revisions()->count());
        $this->assertStringContainsString('<ins>大家</ins>好', $this->history('pages', $page->id));
    }

    public function test_versions_survive_the_trash_and_can_still_be_restored(): void
    {
        $post = $this->article();
        $this->travel(15)->minutes();
        app(PostService::class)->update($post, ['body' => '改壞了']);
        $bad = $post->revisions()->latest('id')->first();
        app(PostService::class)->softDeleteGroup($post);

        $html = $this->history('posts', $post->id);
        $this->assertStringContainsString('在垃圾桶', $html);
        $this->assertStringContainsString('data-restore', $html);

        $this->post(route('admin.revisions.restore', ['type' => 'posts', 'id' => $post->id, 'revision' => $bad->id]))->assertRedirect();
        $this->assertSame("第一段\n\n我進來這件公司", Post::withTrashed()->find($post->id)->body);

        $trash = $this->get(route('admin.posts.index', ['status' => 'trashed']))->getContent();
        $this->assertStringContainsString('data-history-link', $trash);
    }

    public function test_scheduled_publishing_is_recorded_as_the_system(): void
    {
        $post = $this->article();
        $post->forceFill(['scheduled_at' => now()->subMinute()])->save();

        app(PostService::class)->publishDue();

        $rev = $post->revisions()->latest('id')->first();
        $this->assertSame([ContentRevision::ACTOR_SYSTEM, '排程發布', 'status'], [$rev->actor, $rev->actor_name, $rev->kind]);
    }

    public function test_history_rejects_unknown_types_and_foreign_revisions(): void
    {
        $a = $this->article();
        $b = $this->article(['slug' => 'other']);
        $this->travel(15)->minutes();
        app(PostService::class)->update($b, ['body' => 'x']);

        $this->get('/admin/users/1/revisions')->assertNotFound();
        $this->post(route('admin.revisions.restore', ['type' => 'posts', 'id' => $a->id, 'revision' => $b->revisions()->latest('id')->first()->id]))->assertNotFound();

        auth()->logout();
        $this->get(route('admin.revisions.index', ['type' => 'posts', 'id' => $a->id]))->assertRedirect();
    }
}
