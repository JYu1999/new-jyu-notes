<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagViewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_index_renders_color_picker_instead_of_text_input(): void
    {
        $tag = Tag::create(['color' => '#3f5e7a']);
        $tag->translations()->create(['locale' => 'zh', 'name' => '技術', 'slug' => 'tech']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.tags.index'))
            ->assertOk()
            // hidden input carries the value (create form + edit form)
            ->assertSee('type="hidden" name="color"', false)
            // preset swatch buttons exist (accent terracotta is in the palette)
            ->assertSee("color = '#b2543b'", false)
            // native custom picker present
            ->assertSee('type="color"', false);

        // the old free-text hex input is gone
        $this->assertStringNotContainsString('type="text" name="color"', $response->getContent());
    }
}
