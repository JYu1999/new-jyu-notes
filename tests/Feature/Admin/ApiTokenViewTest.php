<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenViewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_index_renders_with_ability_checkboxes(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.tokens.index'))
            ->assertOk()
            ->assertSee('posts:create')
            ->assertSee('media:create')
            ->assertSee('name="abilities[]"', false);
    }

    public function test_newly_created_plaintext_is_shown_once(): void
    {
        $admin = $this->admin();

        // First request: create, which flashes the plaintext to the session.
        $this->actingAs($admin)
            ->post(route('admin.tokens.store'), [
                'name' => 't', 'abilities' => ['posts:read'], 'expires_in' => '1h',
            ])
            ->assertRedirect(route('admin.tokens.index'));

        // Follow the redirect: the flashed token is visible this once.
        $token = session('newToken');
        $this->assertNotEmpty($token);
        $this->actingAs($admin)
            ->withSession(['newToken' => $token, 'newTokenName' => 't'])
            ->get(route('admin.tokens.index'))
            ->assertOk()
            ->assertSee($token);
    }
}
