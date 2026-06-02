<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function nonAdmin(): User
    {
        return User::create([
            'name' => 'User', 'email' => 'user@b.c',
            'password' => bcrypt('x'), 'role' => 'user',
        ]);
    }

    public function test_admin_can_create_token_and_sees_plaintext_once(): void
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)->post(route('admin.tokens.store'), [
            'name' => 'translate-job',
            'abilities' => ['posts:read', 'media:create'],
            'expires_in' => '8h',
        ]);

        $res->assertRedirect(route('admin.tokens.index'));
        $res->assertSessionHas('newToken');
        $this->assertCount(1, $admin->fresh()->tokens);
    }

    public function test_create_rejects_invalid_ability(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tokens.store'), [
            'name' => 'bad',
            'abilities' => ['media:update'],
            'expires_in' => '1h',
        ])->assertSessionHasErrors('abilities.0');

        $this->assertCount(0, $admin->fresh()->tokens);
    }

    public function test_admin_can_revoke_token(): void
    {
        $admin = $this->admin();
        $new = $admin->createToken('t', ['posts:read'], now()->addHour());

        $this->actingAs($admin)
            ->delete(route('admin.tokens.destroy', $new->accessToken->id))
            ->assertRedirect(route('admin.tokens.index'));

        $this->assertCount(0, $admin->fresh()->tokens);
    }

    public function test_non_admin_cannot_access(): void
    {
        $this->actingAs($this->nonAdmin())
            ->get(route('admin.tokens.index'))
            ->assertForbidden();
    }
}
