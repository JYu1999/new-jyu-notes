<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostReferenceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PostService
    {
        return app(PostService::class);
    }

    private function makeTarget(string $slug = 'target-post'): Post
    {
        return $this->service()->create([
            'locale' => 'zh',
            'slug' => $slug,
            'title' => 'Target',
            'body' => 'Target body.',
            'status' => Post::STATUS_PUBLISHED,
        ]);
    }

    public function test_creating_post_with_internal_link_records_reference(): void
    {
        $target = $this->makeTarget();

        $source = $this->service()->create([
            'locale' => 'zh',
            'title' => 'Source',
            'body' => "看這篇 [連結](/zh/posts/{$target->slug}).",
            'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->assertTrue($source->outgoingReferences->contains($target->id));
        $this->assertTrue($target->fresh()->backlinks->contains($source->id));
    }

    public function test_removing_link_on_update_removes_reference(): void
    {
        $target = $this->makeTarget();
        $source = $this->service()->create([
            'locale' => 'zh',
            'title' => 'Source',
            'body' => "[x](/zh/posts/{$target->slug})",
            'status' => Post::STATUS_PUBLISHED,
        ]);
        $this->assertCount(1, $source->outgoingReferences);

        $this->service()->update($source, ['body' => '已經沒有連結了。']);

        $this->assertCount(0, $source->fresh()->outgoingReferences);
    }

    public function test_self_link_is_ignored(): void
    {
        $post = $this->service()->create([
            'locale' => 'zh',
            'slug' => 'me',
            'title' => 'Me',
            'body' => '指向自己 [self](/zh/posts/me)',
            'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->assertCount(0, $post->fresh()->outgoingReferences);
    }

    public function test_unresolvable_link_is_ignored(): void
    {
        $source = $this->service()->create([
            'locale' => 'zh',
            'title' => 'Source',
            'body' => '[ghost](/zh/posts/does-not-exist)',
            'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->assertCount(0, $source->outgoingReferences);
    }
}
