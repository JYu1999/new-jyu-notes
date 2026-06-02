<?php

namespace Tests\Feature\Api;

use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeTag(string $name = 'PHP'): Tag
    {
        return app(TagService::class)->create([
            'color' => '#b2543b',
            'translations' => [['locale' => 'en', 'name' => $name, 'slug' => null]],
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/tags')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['tags:create']);
        $this->getJson('/api/tags')->assertForbidden();
    }

    public function test_index_paginated_with_translations(): void
    {
        $this->makeTag('PHP');
        Sanctum::actingAs($this->user(), ['tags:read']);

        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'color', 'translations' => [['locale', 'name', 'slug']]]], 'links', 'meta'])
            ->assertJsonPath('data.0.translations.0.name', 'PHP');
    }

    public function test_create_with_translations(): void
    {
        Sanctum::actingAs($this->user(), ['tags:create']);

        $this->postJson('/api/tags', [
            'color' => '#123456',
            'translations' => [['locale' => 'en', 'name' => 'Laravel']],
        ])->assertCreated()->assertJsonPath('data.translations.0.name', 'Laravel');

        $this->assertDatabaseHas('tag_translations', ['name' => 'Laravel', 'locale' => 'en']);
    }

    public function test_create_rejects_bad_color(): void
    {
        Sanctum::actingAs($this->user(), ['tags:create']);

        $this->postJson('/api/tags', [
            'color' => 'red',
            'translations' => [['locale' => 'en', 'name' => 'X']],
        ])->assertStatus(422);
    }

    public function test_update_replaces_translations(): void
    {
        $tag = $this->makeTag('Old');
        Sanctum::actingAs($this->user(), ['tags:update']);

        $this->patchJson("/api/tags/{$tag->id}", [
            'translations' => [['locale' => 'en', 'name' => 'New']],
        ])->assertOk()->assertJsonPath('data.translations.0.name', 'New');

        $this->assertDatabaseMissing('tag_translations', ['name' => 'Old']);
    }

    public function test_delete_removes_tag(): void
    {
        $tag = $this->makeTag();
        Sanctum::actingAs($this->user(), ['tags:delete']);

        $this->deleteJson("/api/tags/{$tag->id}")->assertNoContent();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }
}
