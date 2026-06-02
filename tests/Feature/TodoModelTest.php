<?php

namespace Tests\Feature;

use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_and_casts(): void
    {
        $todo = Todo::create(['title' => 'First feature']);
        $fresh = $todo->fresh();

        $this->assertSame(Todo::STATUS_OPEN, $fresh->status);
        $this->assertSame(Todo::PRIORITY_MEDIUM, $fresh->priority);
        $this->assertFalse($fresh->show_in_changelog);   // boolean cast
        $this->assertNull($fresh->completed_at);
    }
}
