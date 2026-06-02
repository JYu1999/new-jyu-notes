<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenServiceTest extends TestCase
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

    public function test_create_persists_abilities_and_expiry(): void
    {
        $user = $this->admin();
        $expires = now()->addHours(8);

        $new = app(ApiTokenService::class)->create($user, 'translate-job', ['posts:read', 'media:create'], $expires);

        $this->assertNotEmpty($new->plainTextToken);
        $token = $user->fresh()->tokens()->first();
        $this->assertSame(['posts:read', 'media:create'], $token->abilities);
        $this->assertSame($expires->format('Y-m-d H:i'), $token->expires_at->format('Y-m-d H:i'));
    }

    public function test_create_rejects_invalid_ability(): void
    {
        $user = $this->admin();

        $this->expectException(\InvalidArgumentException::class);
        app(ApiTokenService::class)->create($user, 'bad', ['media:update'], now()->addHour());
    }

    public function test_revoke_deletes_the_token(): void
    {
        $user = $this->admin();
        $new = app(ApiTokenService::class)->create($user, 't', ['posts:read'], now()->addHour());
        $id = $new->accessToken->id;

        app(ApiTokenService::class)->revoke($user, $id);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
