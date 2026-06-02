<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiMeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'a@b.c',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_returns_user_and_abilities(): void
    {
        $user = $this->admin();
        $new = $user->createToken('t', ['posts:read', 'media:create'], now()->addHour());

        $this->withToken($new->plainTextToken)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'a@b.c')
            ->assertJsonPath('abilities', ['posts:read', 'media:create']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->admin();
        $new = $user->createToken('t', ['posts:read'], now()->subMinute());

        $this->withToken($new->plainTextToken)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}
