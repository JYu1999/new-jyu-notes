<?php

namespace Tests\Feature\Admin;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoAdminTest extends TestCase
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

    public function test_admin_can_create_todo(): void
    {
        $this->actingAs($this->admin())->post(route('admin.todos.store'), [
            'title' => 'New feature',
            'priority' => Todo::PRIORITY_HIGH,
            'status' => Todo::STATUS_OPEN,
        ])->assertRedirect(route('admin.todos.index'));

        $this->assertDatabaseHas('todos', ['title' => 'New feature', 'priority' => 'high']);
    }

    public function test_marking_done_sets_completed_at(): void
    {
        $todo = Todo::create(['title' => 'X']);

        $this->actingAs($this->admin())->put(route('admin.todos.update', $todo), [
            'title' => 'X',
            'priority' => Todo::PRIORITY_MEDIUM,
            'status' => Todo::STATUS_DONE,
            'show_in_changelog' => '1',
        ])->assertRedirect(route('admin.todos.index'));

        $fresh = $todo->fresh();
        $this->assertSame(Todo::STATUS_DONE, $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertTrue($fresh->show_in_changelog);
    }

    public function test_admin_can_delete_todo(): void
    {
        $todo = Todo::create(['title' => 'X']);

        $this->actingAs($this->admin())
            ->delete(route('admin.todos.destroy', $todo))
            ->assertRedirect(route('admin.todos.index'));

        $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
    }

    public function test_validation_rejects_bad_priority(): void
    {
        $this->actingAs($this->admin())->post(route('admin.todos.store'), [
            'title' => 'X', 'priority' => 'urgent', 'status' => Todo::STATUS_OPEN,
        ])->assertSessionHasErrors('priority');

        $this->assertDatabaseCount('todos', 0);
    }

    public function test_non_admin_cannot_access(): void
    {
        $this->actingAs($this->nonAdmin())
            ->get(route('admin.todos.index'))
            ->assertForbidden();
    }
}
