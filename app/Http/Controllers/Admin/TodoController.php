<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Todo\StoreRequest;
use App\Http\Requests\Admin\Todo\UpdateRequest;
use App\Models\Todo;
use App\Services\TodoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * @deprecated Since JYU-132, changelog is generated from CHANGELOG.md instead
 * of this system. Kept functional for historical data only — not deleted
 * because the table was never fully audited for content beyond what the
 * public changelog displayed. See docs/superpowers/specs/2026-08-04-jyu-132-file-based-changelog-design.md.
 */
class TodoController extends Controller
{
    public function index(): View
    {
        return view('admin.todos.index', [
            'todos' => Todo::query()
                ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [Todo::STATUS_DONE])
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreRequest $request, TodoService $service): RedirectResponse
    {
        $service->create($request->validated());

        return redirect()->route('admin.todos.index')->with('success', 'Todo 已建立');
    }

    public function update(Todo $todo, UpdateRequest $request, TodoService $service): RedirectResponse
    {
        $service->update($todo, $request->validated());

        return redirect()->route('admin.todos.index')->with('success', 'Todo 已更新');
    }

    public function destroy(Todo $todo, TodoService $service): RedirectResponse
    {
        $service->delete($todo);

        return redirect()->route('admin.todos.index')->with('success', 'Todo 已刪除');
    }
}
