<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeCategory(string $name = 'Tech'): Category
    {
        return app(CategoryService::class)->create([
            'sort_method' => Category::SORT_DATE_DESC,
            'translations' => [['locale' => 'en', 'name' => $name, 'slug' => null, 'description' => null]],
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['categories:create']);
        $this->getJson('/api/categories')->assertForbidden();
    }

    public function test_index_paginated_with_translations(): void
    {
        $this->makeCategory('Tech');
        Sanctum::actingAs($this->user(), ['categories:read']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'sort_method', 'translations' => [['locale', 'name', 'slug']]]], 'links', 'meta'])
            ->assertJsonPath('data.0.translations.0.name', 'Tech');
    }

    public function test_create_with_translations(): void
    {
        Sanctum::actingAs($this->user(), ['categories:create']);

        $this->postJson('/api/categories', [
            'sort_method' => 'date_desc',
            'translations' => [
                ['locale' => 'en', 'name' => 'Travel'],
                ['locale' => 'zh', 'name' => '旅遊'],
            ],
        ])->assertCreated()->assertJsonPath('data.translations.1.name', '旅遊');

        $this->assertDatabaseHas('category_translations', ['name' => 'Travel', 'locale' => 'en']);
    }

    public function test_update_replaces_translations(): void
    {
        $category = $this->makeCategory('Old');
        Sanctum::actingAs($this->user(), ['categories:update']);

        $this->patchJson("/api/categories/{$category->id}", [
            'translations' => [['locale' => 'en', 'name' => 'New']],
        ])->assertOk()->assertJsonPath('data.translations.0.name', 'New');

        $this->assertDatabaseHas('category_translations', ['name' => 'New']);
        $this->assertDatabaseMissing('category_translations', ['name' => 'Old']);
    }

    public function test_delete_removes_category(): void
    {
        $category = $this->makeCategory();
        Sanctum::actingAs($this->user(), ['categories:delete']);

        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
