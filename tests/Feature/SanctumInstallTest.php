<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_token_with_abilities(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'a@b.c',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]);

        $new = $user->createToken('test', ['posts:read']);

        $this->assertNotEmpty($new->plainTextToken);
        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertTrue($new->accessToken->can('posts:read'));
        $this->assertFalse($new->accessToken->can('posts:delete'));
    }
}
