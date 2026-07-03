<?php

namespace Tests\Feature\Api;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TodoApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/todos')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['todos:create']); // lacks read
        $this->getJson('/api/todos')->assertForbidden();
    }

    public function test_list_with_ability(): void
    {
        Todo::create(['title' => 'A']);
        Sanctum::actingAs($this->user(), ['todos:read']);

        $this->getJson('/api/todos')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'A');
    }

    public function test_create_with_ability(): void
    {
        Sanctum::actingAs($this->user(), ['todos:create']);

        $this->postJson('/api/todos', [
            'title' => 'From agent', 'priority' => 'high', 'status' => 'open',
        ])->assertCreated()->assertJsonPath('data.title', 'From agent');

        $this->assertDatabaseHas('todos', ['title' => 'From agent']);
    }

    public function test_update_to_done_sets_completed_at(): void
    {
        $todo = Todo::create(['title' => 'A']);
        Sanctum::actingAs($this->user(), ['todos:update']);

        $this->patchJson("/api/todos/{$todo->id}", [
            'title' => 'A', 'priority' => 'medium', 'status' => 'done', 'show_in_changelog' => true,
        ])->assertOk();

        $this->assertNotNull($todo->fresh()->completed_at);
    }

    public function test_partial_update_changes_only_provided_fields(): void
    {
        $todo = Todo::create(['title' => 'Original', 'priority' => 'low']);
        Sanctum::actingAs($this->user(), ['todos:update']);

        // PATCH only status — title/priority must be left intact, completed_at set.
        $this->patchJson("/api/todos/{$todo->id}", ['status' => 'done'])
            ->assertOk();

        $fresh = $todo->fresh();
        $this->assertSame('Original', $fresh->title);
        $this->assertSame('low', $fresh->priority);
        $this->assertSame('done', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_delete_with_ability(): void
    {
        $todo = Todo::create(['title' => 'A']);
        Sanctum::actingAs($this->user(), ['todos:delete']);

        $this->deleteJson("/api/todos/{$todo->id}")->assertNoContent();
        $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
    }

    public function test_responses_carry_rate_limit_headers(): void
    {
        Sanctum::actingAs($this->user(), ['todos:read']);

        $this->getJson('/api/todos')->assertHeader('X-RateLimit-Limit', 60);
    }
}
