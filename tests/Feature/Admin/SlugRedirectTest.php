<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\PostSlugRedirect;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JYU-550: changing a published post's slug keeps the old URL working.
 */
class SlugRedirectTest extends TestCase
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
            'locale' => 'zh', 'title' => '文章', 'slug' => 'old-slug', 'body' => '內文', 'status' => 'published',
        ]);
    }

    private function rename(Post $post, string $slug): void
    {
        $this->put(route('admin.posts.update', $post), ['title' => $post->title, 'body' => $post->body, 'slug' => $slug])
            ->assertRedirect(route('admin.posts.edit', $post))->assertSessionHasNoErrors();
    }

    public function test_slug_field_explains_that_old_urls_redirect(): void
    {
        $html = $this->get(route('admin.posts.edit', $this->article()))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="slug"[^>]*>.*?<span data-slug-hint[^>]*>由標題自動產生，發布後修改會保留舊網址轉址<\/span>/s', $html);
    }

    public function test_old_url_of_a_renamed_published_post_redirects_permanently(): void
    {
        $post = $this->article();

        $this->rename($post, 'new-slug');

        $this->get('/zh/posts/old-slug')->assertStatus(301)->assertRedirect(route('public.posts.show', ['zh', 'new-slug']));
        $this->get('/zh/posts/new-slug')->assertOk()->assertSee('文章');
    }

    public function test_renaming_twice_sends_every_old_url_straight_to_the_current_one(): void
    {
        $post = $this->article();

        $this->rename($post, 'second');
        $this->rename($post, 'third');

        $this->get('/zh/posts/old-slug')->assertStatus(301)->assertRedirect(route('public.posts.show', ['zh', 'third']));
        $this->get('/zh/posts/second')->assertStatus(301)->assertRedirect(route('public.posts.show', ['zh', 'third']));
    }

    public function test_changing_the_slug_back_does_not_loop(): void
    {
        $post = $this->article();

        $this->rename($post, 'new-slug');
        $this->rename($post, 'old-slug');

        $this->get('/zh/posts/old-slug')->assertOk();
        $this->get('/zh/posts/new-slug')->assertStatus(301)->assertRedirect(route('public.posts.show', ['zh', 'old-slug']));
        $this->assertSame(['new-slug'], PostSlugRedirect::pluck('old_slug')->all());
    }

    public function test_slug_regenerated_from_a_new_title_also_keeps_the_old_url(): void
    {
        $post = $this->article(['title' => 'Old title', 'slug' => 'old-title']);

        $this->put(route('admin.posts.update', $post), ['title' => 'Brand new title', 'body' => '內文', 'slug' => '']);

        $new = $post->fresh()->slug;
        $this->assertNotSame('old-title', $new);
        $this->get('/zh/posts/old-title')->assertStatus(301)->assertRedirect(route('public.posts.show', ['zh', $new]));
    }

    public function test_drafts_that_were_never_published_leave_no_redirect(): void
    {
        $draft = $this->article(['status' => 'draft']);

        $this->rename($draft, 'new-slug');

        $this->assertSame(0, PostSlugRedirect::count());
        $this->get('/zh/posts/old-slug')->assertNotFound();
    }

    public function test_redirects_only_lead_to_published_posts_in_the_same_language(): void
    {
        $post = $this->article();
        $this->rename($post, 'new-slug');

        $this->get('/en/posts/old-slug')->assertNotFound();

        app(PostService::class)->updateStatus($post->fresh(), Post::STATUS_HIDDEN);
        $this->get('/zh/posts/old-slug')->assertNotFound();
    }

    public function test_a_post_that_takes_an_old_slug_wins_over_the_redirect(): void
    {
        $post = $this->article();
        $this->rename($post, 'new-slug');

        $other = $this->article(['title' => '另一篇', 'slug' => 'old-slug']);

        $this->get('/zh/posts/old-slug')->assertOk()->assertSee('另一篇');
        $this->assertSame(0, PostSlugRedirect::where('old_slug', 'old-slug')->count());
        $this->assertSame('old-slug', $other->slug);
    }

    public function test_internal_links_with_the_old_slug_still_count_as_references(): void
    {
        $post = $this->article();
        $this->rename($post, 'new-slug');

        $citing = $this->article(['title' => '引用者', 'slug' => 'citing', 'body' => '見 [舊連結](/zh/posts/old-slug)']);

        $this->assertSame([$post->id], $citing->outgoingReferences()->pluck('target_id')->all());
    }

    public function test_deleting_a_post_for_good_removes_its_redirects(): void
    {
        $post = $this->article();
        $this->rename($post, 'new-slug');

        $post->forceDelete();

        $this->assertSame(0, PostSlugRedirect::count());
    }
}
