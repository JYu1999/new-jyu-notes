<?php

namespace Tests\Feature\Admin;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoViewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_index_lists_todos_and_has_create_form(): void
    {
        Todo::create(['title' => 'Visible todo title']);

        $this->actingAs($this->admin())
            ->get(route('admin.todos.index'))
            ->assertOk()
            ->assertSee('Visible todo title')
            ->assertSee('name="title"', false)
            ->assertSee('name="show_in_changelog"', false);
    }
}
