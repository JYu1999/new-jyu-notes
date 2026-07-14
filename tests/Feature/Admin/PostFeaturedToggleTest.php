<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostFeaturedToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_unchecking_is_featured_in_edit_form_actually_unsets_it(): void
    {
        $post = app(PostService::class)->create([
            'locale' => 'zh',
            'title' => 'Featured Post',
            'body' => 'body',
            'status' => Post::STATUS_PUBLISHED,
            'is_featured' => true,
        ]);

        $this->assertTrue($post->fresh()->is_featured);

        // Simulate the real browser form submission: an unchecked checkbox
        // is simply absent from the payload, exactly like the edit form does.
        $this->actingAs($this->admin())
            ->put(route('admin.posts.update', $post), [
                'title' => $post->title,
                'body' => $post->body,
                'status' => $post->status,
            ])
            ->assertRedirect();

        $this->assertFalse($post->fresh()->is_featured);
    }
}
