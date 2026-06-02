<?php

namespace Tests\Feature;

use App\Models\Todo;
use App\Services\TodoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TodoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TodoService
    {
        return app(TodoService::class);
    }

    public function test_completing_sets_completed_at(): void
    {
        $todo = $this->service()->create(['title' => 'A']);
        $this->assertNull($todo->completed_at);

        $this->service()->update($todo, ['status' => Todo::STATUS_DONE]);

        $this->assertNotNull($todo->fresh()->completed_at);
    }

    public function test_reopening_clears_completed_at(): void
    {
        $todo = $this->service()->create(['title' => 'A', 'status' => Todo::STATUS_DONE]);
        $this->assertNotNull($todo->fresh()->completed_at);

        $this->service()->update($todo, ['status' => Todo::STATUS_OPEN]);

        $this->assertNull($todo->fresh()->completed_at);
    }

    public function test_editing_done_todo_keeps_completed_at(): void
    {
        $todo = $this->service()->create(['title' => 'A', 'status' => Todo::STATUS_DONE]);
        $original = $todo->fresh()->completed_at;

        $this->service()->update($todo, ['title' => 'A renamed']);

        $this->assertEquals(
            $original->format('Y-m-d H:i:s'),
            $todo->fresh()->completed_at->format('Y-m-d H:i:s')
        );
    }

    public function test_changelog_grouped_only_done_and_flagged_newest_first(): void
    {
        $may19 = $this->service()->create(['title' => 'A Feature', 'status' => Todo::STATUS_DONE, 'show_in_changelog' => true]);
        DB::table('todos')->where('id', $may19->id)->update(['completed_at' => '2026-05-19 10:00:00']);

        $may18 = $this->service()->create(['title' => 'C Feature', 'status' => Todo::STATUS_DONE, 'show_in_changelog' => true]);
        DB::table('todos')->where('id', $may18->id)->update(['completed_at' => '2026-05-18 09:00:00']);

        $this->service()->create(['title' => 'Internal chore', 'status' => Todo::STATUS_DONE, 'show_in_changelog' => false]);
        $this->service()->create(['title' => 'Planned', 'status' => Todo::STATUS_OPEN, 'show_in_changelog' => true]);

        $groups = $this->service()->changelogGrouped();

        $this->assertSame(['2026-05-19', '2026-05-18'], $groups->keys()->all());
        $this->assertSame('A Feature', $groups['2026-05-19']->first()->title);
        $this->assertCount(1, $groups['2026-05-18']);
    }
}
