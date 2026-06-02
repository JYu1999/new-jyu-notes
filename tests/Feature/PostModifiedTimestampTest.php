<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostModifiedTimestampTest extends TestCase
{
    use RefreshDatabase;

    private const PAST = '2020-01-01 00:00:00';

    private function service(): PostService
    {
        return app(PostService::class);
    }

    /** Create a post via the service, then pin last_modified_at to a known past value. */
    private function makePost(): Post
    {
        $post = $this->service()->create([
            'locale' => 'zh',
            'title' => 'Original Title',
            'body' => 'Original body.',
            'status' => Post::STATUS_DRAFT,
        ]);

        DB::table('posts')->where('id', $post->id)->update(['last_modified_at' => self::PAST]);

        return $post->fresh();
    }

    private function modifiedAt(Post $post): string
    {
        return $post->fresh()->last_modified_at->format('Y-m-d H:i:s');
    }

    public function test_editing_content_bumps_last_modified_at(): void
    {
        $post = $this->makePost();

        $this->service()->update($post, ['body' => 'Rewritten body.']);

        $this->assertNotSame(self::PAST, $this->modifiedAt($post));
    }

    public function test_toggling_is_featured_does_not_bump_last_modified_at(): void
    {
        $post = $this->makePost();

        $this->service()->update($post, [
            'title' => $post->title,
            'body' => $post->body,
            'is_featured' => true,
        ]);

        $this->assertSame(self::PAST, $this->modifiedAt($post));
        $this->assertTrue($post->fresh()->is_featured);
    }

    public function test_changing_status_via_update_does_not_bump_last_modified_at(): void
    {
        $post = $this->makePost();

        $this->service()->update($post, [
            'title' => $post->title,
            'body' => $post->body,
            'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->assertSame(self::PAST, $this->modifiedAt($post));
        $this->assertSame(Post::STATUS_PUBLISHED, $post->fresh()->status);
    }

    public function test_changing_only_tags_does_not_bump_last_modified_at(): void
    {
        $post = $this->makePost();
        $tag = Tag::create(['color' => 'aabbcc']);

        $this->service()->update($post, [
            'title' => $post->title,
            'body' => $post->body,
            'tag_ids' => [$tag->id],
        ]);

        $this->assertSame(self::PAST, $this->modifiedAt($post));
        $this->assertTrue($post->fresh()->tags->contains($tag->id));
    }

    public function test_update_status_action_does_not_bump_last_modified_at(): void
    {
        $post = $this->makePost();

        $this->service()->updateStatus($post, Post::STATUS_PUBLISHED);

        $fresh = $post->fresh();
        $this->assertSame(self::PAST, $this->modifiedAt($post));
        $this->assertSame(Post::STATUS_PUBLISHED, $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }
}
