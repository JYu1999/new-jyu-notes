<?php

namespace App\Services;

use App\Models\Todo;
use Illuminate\Support\Collection;

class TodoService
{
    public function create(array $data): Todo
    {
        return $this->fillAndSave(new Todo(), $data);
    }

    public function update(Todo $todo, array $data): Todo
    {
        return $this->fillAndSave($todo, $data);
    }

    public function delete(Todo $todo): void
    {
        $todo->delete();
    }

    /**
     * Completed + flagged todos, grouped by completion date (Y-m-d),
     * newest day first and newest-within-day first.
     *
     * @return Collection<string, Collection<int, Todo>>
     */
    public function changelogGrouped(): Collection
    {
        return Todo::query()
            ->where('status', Todo::STATUS_DONE)
            ->where('show_in_changelog', true)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->get()
            ->groupBy(fn (Todo $t) => $t->completed_at->format('Y-m-d'));
    }

    private function fillAndSave(Todo $todo, array $data): Todo
    {
        $wasDone = $todo->status === Todo::STATUS_DONE;

        $todo->fill($data);

        if ($todo->status === Todo::STATUS_DONE && ! $wasDone) {
            $todo->completed_at = now();
        } elseif ($todo->status === Todo::STATUS_OPEN && $wasDone) {
            $todo->completed_at = null;
        }

        $todo->save();

        return $todo;
    }
}
