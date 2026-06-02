<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Todo\StoreRequest;
use App\Http\Requests\Admin\Todo\UpdateRequest;
use App\Models\Todo;
use App\Services\TodoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TodoController extends Controller
{
    public function index(): View
    {
        return view('admin.todos.index', [
            'todos' => Todo::query()->orderByRaw("status = 'done'")->latest()->get(),
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
