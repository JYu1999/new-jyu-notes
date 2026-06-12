<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillPostReferencesTest extends TestCase
{
    use RefreshDatabase;

    /** 直接用 Post::create 建立，繞過 PostService 的存檔同步，模擬「既有資料尚未有 reference」。 */
    private function rawPost(string $slug, string $body, string $status = 'published'): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create()->id,
            'locale' => 'zh',
            'slug' => $slug,
            'title' => $slug,
            'body' => $body,
            'status' => $status,
            'last_modified_at' => now(),
        ]);
    }

    public function test_backfill_populates_references_for_existing_posts(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '看 [這篇](/zh/posts/target)');

        $this->assertSame(0, DB::table('post_references')->count());

        $this->artisan('posts:backfill-references')->assertExitCode(0);

        $this->assertSame(1, DB::table('post_references')->count());
    }

    public function test_backfill_is_idempotent(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '[x](/zh/posts/target)');

        $this->artisan('posts:backfill-references')->assertExitCode(0);
        $this->artisan('posts:backfill-references')->assertExitCode(0);

        $this->assertSame(1, DB::table('post_references')->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '[x](/zh/posts/target)');

        $this->artisan('posts:backfill-references --dry-run')->assertExitCode(0);

        $this->assertSame(0, DB::table('post_references')->count());
    }

    public function test_unresolvable_link_is_reported_as_anomaly(): void
    {
        $this->rawPost('source', '[ghost](/zh/posts/does-not-exist)');

        // Two separate artisan() calls are needed because Mockery's doWrite
        // interception only fires one callback per matched call when multiple
        // expectsOutputToContain expectations overlap on the same output line.
        $this->artisan('posts:backfill-references --dry-run')
            ->assertExitCode(0)
            ->expectsOutputToContain('找不到對應文章');

        $this->artisan('posts:backfill-references --dry-run')
            ->assertExitCode(0)
            ->expectsOutputToContain('zh/does-not-exist');
    }
}
