<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JYU-549: schedule a post to publish itself later.
 */
class ScheduledPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.display_timezone' => 'Asia/Taipei']);
        $this->travelTo(Carbon::parse('2026-11-27 12:00:00', 'UTC'));
        $admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);
    }

    private function draft(array $attrs = []): Post
    {
        return app(PostService::class)->create($attrs + [
            'locale' => 'zh', 'title' => '草稿', 'slug' => uniqid('p'), 'body' => '內文', 'status' => 'draft',
        ]);
    }

    private function scheduled(string $utc, array $attrs = []): Post
    {
        $post = $this->draft($attrs);
        $post->forceFill(['scheduled_at' => Carbon::parse($utc, 'UTC')])->save();

        return $post;
    }

    public function test_choosing_schedule_in_the_editor_keeps_the_post_a_draft_until_then(): void
    {
        $post = $this->draft();

        // 2026-12-01 09:00 in Taipei = 01:00 UTC. The publish button also sends status=published.
        $this->put(route('admin.posts.update', $post), [
            'title' => '草稿', 'body' => '內文', 'status' => 'published',
            'publish_when' => 'schedule', 'scheduled_at' => '2026-12-01T09:00',
        ])->assertRedirect(route('admin.posts.edit', $post))->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame(Post::STATUS_DRAFT, $post->status);
        $this->assertNull($post->published_at);
        $this->assertTrue($post->scheduled_at->eq(Carbon::parse('2026-12-01 01:00:00', 'UTC')));
        $this->assertTrue($post->isScheduled());
    }

    public function test_a_new_post_can_be_scheduled_when_created(): void
    {
        $this->post(route('admin.posts.store'), [
            'locale' => 'zh', 'title' => '新文章', 'body' => '內文', 'status' => 'published',
            'publish_when' => 'schedule', 'scheduled_at' => '2026-11-28T08:30',
        ])->assertSessionHasNoErrors();

        $post = Post::firstWhere('title', '新文章');
        $this->assertSame(Post::STATUS_DRAFT, $post->status);
        $this->assertTrue($post->scheduled_at->eq(Carbon::parse('2026-11-28 00:30:00', 'UTC')));
    }

    public function test_schedule_needs_a_future_time_and_an_unpublished_post(): void
    {
        $post = $this->draft();
        $base = ['title' => '草稿', 'body' => '內文', 'status' => 'draft', 'publish_when' => 'schedule'];

        $this->put(route('admin.posts.update', $post), $base)->assertSessionHasErrors(['scheduled_at' => '請選擇排程發布的日期與時間']);
        // 2026-11-27 19:00 Taipei = 11:00 UTC, an hour ago.
        $this->put(route('admin.posts.update', $post), $base + ['scheduled_at' => '2026-11-27T19:00'])
            ->assertSessionHasErrors(['scheduled_at' => '排程時間必須在現在之後']);

        $published = $this->draft(['status' => 'published', 'slug' => 'live']);
        $this->put(route('admin.posts.update', $published), [...$base, 'scheduled_at' => '2026-12-01T09:00'])
            ->assertSessionHasErrors(['scheduled_at' => '已發布的文章不能排程']);

        $this->assertNull($post->fresh()->scheduled_at);
        $this->assertSame(Post::STATUS_PUBLISHED, $published->fresh()->status);
    }

    public function test_editor_offers_schedule_in_the_publish_options(): void
    {
        $html = $this->get(route('admin.posts.edit', $this->draft()))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="其他發布選項：排程、設為隱藏"', $html);
        $this->assertMatchesRegularExpression('/data-schedule-option[^>]*>排程…<\/button>/', $html);
        $this->assertMatchesRegularExpression('/發布時間\s*<select name="publish_when"/', $html);
        $this->assertMatchesRegularExpression('/<option value="schedule"\s*>排程…<\/option>/', $html);
        $this->assertMatchesRegularExpression('/type="datetime-local" name="scheduled_at"/', $html);
        $this->assertStringContainsString('min="2026-11-27T20:00"', $html);

        // Published posts can't be scheduled, only hidden.
        $live = $this->get(route('admin.posts.edit', $this->draft(['status' => 'published', 'slug' => 'live'])))->getContent();
        $this->assertStringContainsString('aria-label="其他發布選項：設為隱藏"', $live);
        $this->assertStringNotContainsString('name="publish_when"', $live);
        $this->assertStringNotContainsString('data-schedule-option', $live);
    }

    public function test_editor_shows_a_scheduled_post_in_local_time_with_a_cancel_button(): void
    {
        $post = $this->scheduled('2026-12-01 01:00:00');

        $html = $this->get(route('admin.posts.edit', $post))->getContent();

        $this->assertMatchesRegularExpression('/data-scheduled-chip[^>]*>已排程 · 2026\/12\/01 09:00</', $html);
        $this->assertStringContainsString('value="2026-12-01T09:00"', $html);
        $this->assertMatchesRegularExpression('/<option value="schedule" selected>排程…/', $html);
        $this->assertMatchesRegularExpression('/x-text="publishLabel">排程</', $html);
        $this->assertStringContainsString('form="unschedule-post"', $html);
        $this->assertStringContainsString('action="'.route('admin.posts.unschedule', $post).'"', $html);
    }

    public function test_list_marks_scheduled_posts_with_their_time(): void
    {
        $this->scheduled('2026-12-01 01:00:00', ['title' => '排程中的文章']);
        $this->draft(['title' => '一般草稿']);

        $html = $this->get(route('admin.posts.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-scheduled '));
        $this->assertMatchesRegularExpression('/data-scheduled[^>]*>已排程<\/span>\s*<time[^>]*>12\/01 09:00<\/time>/', $html);
        $this->assertStringContainsString('取消排程', $html);
    }

    public function test_due_posts_are_published_automatically_and_appear_on_the_site(): void
    {
        $due = $this->scheduled('2026-11-27 11:59:00', ['slug' => 'due']);
        $later = $this->scheduled('2026-11-27 12:30:00', ['slug' => 'later']);
        $plainDraft = $this->draft(['slug' => 'plain']);

        $this->get(route('public.posts.show', ['zh', 'due']))->assertNotFound();

        $this->artisan('posts:publish-scheduled')->assertSuccessful();

        $due->refresh();
        $this->assertSame(Post::STATUS_PUBLISHED, $due->status);
        $this->assertTrue($due->published_at->eq(Carbon::parse('2026-11-27 11:59:00', 'UTC')));
        $this->assertNull($due->scheduled_at);
        $this->assertSame(Post::STATUS_DRAFT, $later->fresh()->status);
        $this->assertSame(Post::STATUS_DRAFT, $plainDraft->fresh()->status);
        $this->get(route('public.posts.show', ['zh', 'due']))->assertOk();

        $this->travelTo(Carbon::parse('2026-11-27 12:31:00', 'UTC'));
        $this->artisan('posts:publish-scheduled')->assertSuccessful();
        $this->assertSame(Post::STATUS_PUBLISHED, $later->fresh()->status);
    }

    public function test_publishing_runs_every_minute_on_the_scheduler(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'posts:publish-scheduled'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_cancelling_a_schedule_returns_the_post_to_draft(): void
    {
        $post = $this->scheduled('2026-12-01 01:00:00');

        $this->from(route('admin.posts.edit', $post))
            ->delete(route('admin.posts.unschedule', $post))
            ->assertRedirect(route('admin.posts.edit', $post))
            ->assertSessionHas('success', '已取消排程，文章維持草稿');

        $post->refresh();
        $this->assertNull($post->scheduled_at);
        $this->assertSame(Post::STATUS_DRAFT, $post->status);

        $this->travelTo(Carbon::parse('2026-12-02', 'UTC'));
        $this->artisan('posts:publish-scheduled');
        $this->assertSame(Post::STATUS_DRAFT, $post->fresh()->status);
    }

    public function test_switching_back_to_publish_now_or_changing_status_drops_the_schedule(): void
    {
        $a = $this->scheduled('2026-12-01 01:00:00');
        $this->put(route('admin.posts.update', $a), ['title' => '草稿', 'body' => '內文', 'status' => 'draft', 'publish_when' => 'now', 'scheduled_at' => '2026-12-01T09:00']);
        $this->assertNull($a->fresh()->scheduled_at);

        $b = $this->scheduled('2026-12-01 01:00:00');
        $this->put(route('admin.posts.update', $b), ['title' => '草稿', 'body' => '內文', 'status' => 'published', 'publish_when' => 'now']);
        $this->assertSame([Post::STATUS_PUBLISHED, null], [$b->fresh()->status, $b->fresh()->scheduled_at]);

        $c = $this->scheduled('2026-12-01 01:00:00');
        $this->patch(route('admin.posts.status', $c), ['status' => 'hidden']);
        $this->assertSame([Post::STATUS_HIDDEN, null], [$c->fresh()->status, $c->fresh()->scheduled_at]);
    }

    public function test_autosave_keeps_the_schedule(): void
    {
        $post = $this->scheduled('2026-12-01 01:00:00');

        $this->postJson(route('admin.posts.autosave'), [
            'post_id' => $post->id, 'title' => '草稿', 'body' => '改一下', 'publish_when' => 'now',
        ])->assertOk();

        $this->assertTrue($post->fresh()->isScheduled());
    }

    public function test_only_admins_can_cancel_a_schedule(): void
    {
        $post = $this->scheduled('2026-12-01 01:00:00');
        auth()->logout();

        $this->delete(route('admin.posts.unschedule', $post))->assertRedirect();
        $this->assertNotNull($post->fresh()->scheduled_at);
    }
}
