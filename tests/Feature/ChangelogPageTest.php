<?php

namespace Tests\Feature;

use App\Models\Todo;
use App\Services\TodoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    private function doneFlagged(string $title, string $date): void
    {
        $todo = app(TodoService::class)->create([
            'title' => $title, 'status' => Todo::STATUS_DONE, 'show_in_changelog' => true,
        ]);
        DB::table('todos')->where('id', $todo->id)->update(['completed_at' => $date]);
    }

    public function test_changelog_shows_grouped_entries(): void
    {
        $this->doneFlagged('A Feature', '2026-05-19 10:00:00');
        $this->doneFlagged('C Feature', '2026-05-18 10:00:00');

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('May 19, 2026')
            ->assertSee('A Feature')
            ->assertSee('May 18, 2026')
            ->assertSee('C Feature');
    }

    public function test_excludes_unflagged_and_open(): void
    {
        app(TodoService::class)->create(['title' => 'Secret chore', 'status' => Todo::STATUS_DONE, 'show_in_changelog' => false]);
        app(TodoService::class)->create(['title' => 'Planned thing', 'status' => Todo::STATUS_OPEN, 'show_in_changelog' => true]);

        $this->get('/changelog')
            ->assertOk()
            ->assertDontSee('Secret chore')
            ->assertDontSee('Planned thing');
    }

    public function test_empty_state(): void
    {
        $this->get('/changelog')->assertOk()->assertSee('No entries yet.');
    }
}
