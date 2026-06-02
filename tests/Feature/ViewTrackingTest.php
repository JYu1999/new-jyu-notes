<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostGroup;
use App\Services\ViewTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ViewTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        $group = PostGroup::create([]);

        return Post::create([
            'post_group_id' => $group->id,
            'locale' => 'zh',
            'slug' => 'hello-world',
            'title' => 'Hello',
            'body' => 'Body',
            'status' => Post::STATUS_PUBLISHED,
            'views_count' => 5,
            'last_modified_at' => now(),
        ]);
    }

    public function test_tracking_increments_views_without_touching_updated_at(): void
    {
        $post = $this->makePost();

        // Pin updated_at to a known past value without going through Eloquent.
        $past = '2020-01-01 00:00:00';
        DB::table('posts')->where('id', $post->id)->update(['updated_at' => $past]);

        app(ViewTrackingService::class)->track($post, '203.0.113.7', 'PHPUnit-Agent');

        $fresh = Post::findOrFail($post->id);
        $this->assertSame(6, $fresh->views_count);
        $this->assertSame($past, $fresh->updated_at->format('Y-m-d H:i:s'));
    }
}
