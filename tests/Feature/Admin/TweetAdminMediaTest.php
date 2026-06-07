<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TweetAdminMediaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_update_persists_media_array(): void
    {
        $admin = $this->admin();
        $tweet = app(TweetService::class)->create([
            'body' => 'hello', 'locale' => 'zh', 'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('admin.tweets.update', $tweet), [
            'body' => 'hello',
            'status' => 'draft',
            'media' => [
                ['path' => 'uploads/2026/06/a.jpg', 'type' => 'image', 'alt' => '一張圖'],
                ['path' => 'uploads/2026/06/b.mp4', 'type' => 'video', 'alt' => ''],
            ],
        ])->assertRedirect(route('admin.tweets.edit', $tweet));

        $tweet->refresh();
        $this->assertCount(2, $tweet->media);
        $this->assertSame('uploads/2026/06/a.jpg', $tweet->media[0]['path']);
        $this->assertSame('一張圖', $tweet->media[0]['alt']);
        $this->assertSame('video', $tweet->media[1]['type']);
    }

    public function test_update_with_empty_media_string_clears_media(): void
    {
        $admin = $this->admin();
        $tweet = app(TweetService::class)->create([
            'body' => 'hello', 'locale' => 'zh', 'author_id' => $admin->id,
            'media' => [['path' => 'uploads/2026/06/a.jpg', 'type' => 'image']],
        ]);

        // 模擬前端清空所有媒體後的 hidden input：media=""
        // ConvertEmptyStringsToNull 會轉成 null → 通過 nullable → 清空欄位
        $this->actingAs($admin)->put(route('admin.tweets.update', $tweet), [
            'body' => 'hello',
            'status' => 'draft',
            'media' => '',
        ])->assertRedirect(route('admin.tweets.edit', $tweet));

        $this->assertEmpty($tweet->refresh()->media);
    }

    public function test_update_without_media_key_keeps_existing_media(): void
    {
        $admin = $this->admin();
        $tweet = app(TweetService::class)->create([
            'body' => 'hello', 'locale' => 'zh', 'author_id' => $admin->id,
            'media' => [['path' => 'uploads/2026/06/a.jpg', 'type' => 'image']],
        ]);

        $this->actingAs($admin)->put(route('admin.tweets.update', $tweet), [
            'body' => 'updated',
            'status' => 'draft',
        ])->assertRedirect(route('admin.tweets.edit', $tweet));

        $this->assertCount(1, $tweet->refresh()->media);
    }
}
