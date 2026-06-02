<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AbilityMiddlewareTest extends TestCase
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

    public function test_passes_with_required_ability(): void
    {
        Sanctum::actingAs($this->admin(), ['posts:read']);

        $this->getJson('/api/_probe')->assertOk();
    }

    public function test_forbidden_without_required_ability(): void
    {
        Sanctum::actingAs($this->admin(), ['tags:read']);

        $this->getJson('/api/_probe')->assertForbidden();
    }
}
