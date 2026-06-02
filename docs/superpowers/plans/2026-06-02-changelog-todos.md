# Changelog & Todos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A lightweight todo manager (open/done + priority) whose completed, flagged items render a public English `/changelog` grouped by date — manageable from both the admin panel and a token-scoped API.

**Architecture:** A single `todos` table backs everything. `TodoService` owns the completion-timestamp rule and the changelog grouping query. Admin CRUD follows the existing `Admin\*Controller` + FormRequest + Blade pattern. The public `/changelog` route lives outside the `{locale}` group (English-only). The API reuses P2's Sanctum `ability:` middleware with a new `todos` resource in `config/abilities.php`, and the authenticated API routes gain `throttle` rate limiting.

**Tech Stack:** Laravel 13, PHP 8.3, Laravel Sail (run everything via `./vendor/bin/sail`), Postgres test DB (`testing`), Sanctum (from P2), Tailwind/Alpine.

---

## File Structure

**Create:**
- `database/migrations/<ts>_create_todos_table.php`
- `app/Models/Todo.php`
- `app/Services/TodoService.php`
- `app/Http/Controllers/Admin/TodoController.php`
- `app/Http/Requests/Admin/Todo/StoreRequest.php`, `UpdateRequest.php`
- `resources/views/admin/todos/index.blade.php`
- `app/Http/Controllers/Public/ChangelogController.php`
- `resources/views/public/changelog.blade.php`
- `app/Http/Controllers/Api/TodoController.php`
- `app/Http/Requests/Api/Todo/StoreRequest.php`, `UpdateRequest.php`
- Test files (per task).

**Modify:**
- `config/abilities.php` — add `todos`.
- `tests/Feature/AbilitiesTest.php` — count 21 → 25.
- `routes/web.php` — admin todos routes + public `/changelog` route.
- `resources/views/layouts/admin.blade.php` — sidebar nav.
- `resources/views/layouts/public.blade.php` — navbar Changelog link.
- `routes/api.php` — throttle group + todos routes.

---

### Task 1: `todos` migration + Todo model

**Files:**
- Create: `database/migrations/<ts>_create_todos_table.php`
- Create: `app/Models/Todo.php`
- Test: `tests/Feature/TodoModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TodoModelTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=TodoModelTest`
Expected: FAIL — `Class "App\Models\Todo" not found`.

- [ ] **Step 3: Create the migration**

Run: `./vendor/bin/sail artisan make:migration create_todos_table`

Then replace the generated file's body so `up()` reads:

```php
    public function up(): void
    {
        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->string('status', 10)->default('open');
            $table->boolean('show_in_changelog')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'show_in_changelog', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todos');
    }
```

- [ ] **Step 4: Create the model**

Create `app/Models/Todo.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Todo extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'show_in_changelog',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'show_in_changelog' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=TodoModelTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/Todo.php tests/Feature/TodoModelTest.php
git commit -m "feat: todos table and Todo model"
```

---

### Task 2: TodoService (completion rule + changelog grouping)

**Files:**
- Create: `app/Services/TodoService.php`
- Test: `tests/Feature/TodoServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TodoServiceTest.php`:

```php
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
        // flagged + done on two different days
        $may19 = $this->service()->create(['title' => 'A Feature', 'status' => Todo::STATUS_DONE, 'show_in_changelog' => true]);
        DB::table('todos')->where('id', $may19->id)->update(['completed_at' => '2026-05-19 10:00:00']);

        $may18 = $this->service()->create(['title' => 'C Feature', 'status' => Todo::STATUS_DONE, 'show_in_changelog' => true]);
        DB::table('todos')->where('id', $may18->id)->update(['completed_at' => '2026-05-18 09:00:00']);

        // done but NOT flagged → excluded
        $this->service()->create(['title' => 'Internal chore', 'status' => Todo::STATUS_DONE, 'show_in_changelog' => false]);
        // flagged but NOT done → excluded
        $this->service()->create(['title' => 'Planned', 'status' => Todo::STATUS_OPEN, 'show_in_changelog' => true]);

        $groups = $this->service()->changelogGrouped();

        $this->assertSame(['2026-05-19', '2026-05-18'], $groups->keys()->all());
        $this->assertSame('A Feature', $groups['2026-05-19']->first()->title);
        $this->assertCount(1, $groups['2026-05-18']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=TodoServiceTest`
Expected: FAIL — `Class "App\Services\TodoService" not found`.

- [ ] **Step 3: Create the service**

Create `app/Services/TodoService.php`:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=TodoServiceTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/TodoService.php tests/Feature/TodoServiceTest.php
git commit -m "feat: TodoService completion rule and changelog grouping"
```

---

### Task 3: Add `todos` to the abilities matrix

**Files:**
- Modify: `config/abilities.php`
- Modify: `tests/Feature/AbilitiesTest.php`

- [ ] **Step 1: Update the AbilitiesTest expectations (failing first)**

In `tests/Feature/AbilitiesTest.php`, in `test_all_flattens_matrix_to_resource_action_strings`, add assertions and bump the count. Add after the existing `assertContains`/`assertNotContains` lines:

```php
        $this->assertContains('todos:read', $all);
        $this->assertContains('todos:delete', $all);
        $this->assertNotContains('todos:publish', $all);
```

And change the count assertion from:

```php
        // 5 + 5 + 4 + 4 + 3 = 21 abilities
        $this->assertCount(21, $all);
```

to:

```php
        // 5 + 5 + 4 + 4 + 3 + 4 = 25 abilities
        $this->assertCount(25, $all);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=AbilitiesTest`
Expected: FAIL — `todos:read` not present; count is 21 not 25.

- [ ] **Step 3: Add `todos` to the matrix**

In `config/abilities.php`, add a `todos` entry to the returned array (after `media`):

```php
    'todos'      => ['read', 'create', 'update', 'delete'],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=AbilitiesTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/abilities.php tests/Feature/AbilitiesTest.php
git commit -m "feat: add todos resource to ability matrix"
```

---

### Task 4: Admin Todo CRUD (controller + requests + routes)

**Files:**
- Create: `app/Http/Requests/Admin/Todo/StoreRequest.php`, `UpdateRequest.php`
- Create: `app/Http/Controllers/Admin/TodoController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/TodoAdminTest.php`

> The `index` view is created in Task 5. The tests here assert redirects / 403 and do NOT render the index view.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/TodoAdminTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=TodoAdminTest`
Expected: FAIL — `Route [admin.todos.store] not defined`.

- [ ] **Step 3: Create the form requests**

Create `app/Http/Requests/Admin/Todo/StoreRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin\Todo;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in([Todo::PRIORITY_LOW, Todo::PRIORITY_MEDIUM, Todo::PRIORITY_HIGH])],
            'status' => ['required', Rule::in([Todo::STATUS_OPEN, Todo::STATUS_DONE])],
            'show_in_changelog' => ['boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['show_in_changelog' => $this->boolean('show_in_changelog')]);
    }
}
```

Create `app/Http/Requests/Admin/Todo/UpdateRequest.php` with identical contents except the class name `UpdateRequest` (repeat the full file):

```php
<?php

namespace App\Http\Requests\Admin\Todo;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in([Todo::PRIORITY_LOW, Todo::PRIORITY_MEDIUM, Todo::PRIORITY_HIGH])],
            'status' => ['required', Rule::in([Todo::STATUS_OPEN, Todo::STATUS_DONE])],
            'show_in_changelog' => ['boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['show_in_changelog' => $this->boolean('show_in_changelog')]);
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Admin/TodoController.php`:

```php
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
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, inside the admin group (after the `// API Tokens` block, before the group's closing `});`), add:

```php
        // Todos
        Route::get('todos', [Admin\TodoController::class, 'index'])->name('todos.index');
        Route::post('todos', [Admin\TodoController::class, 'store'])->name('todos.store');
        Route::put('todos/{todo}', [Admin\TodoController::class, 'update'])->name('todos.update');
        Route::delete('todos/{todo}', [Admin\TodoController::class, 'destroy'])->name('todos.destroy');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=TodoAdminTest`
Expected: PASS (5 tests). None render the index view — store/update/destroy assert redirects, bad-priority asserts a validation error, non-admin gets 403 from `role:admin` before the view renders.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Admin/Todo app/Http/Controllers/Admin/TodoController.php routes/web.php tests/Feature/Admin/TodoAdminTest.php
git commit -m "feat: admin todo CRUD controller, requests and routes"
```

---

### Task 5: Admin Todo view + sidebar nav

**Files:**
- Create: `resources/views/admin/todos/index.blade.php`
- Modify: `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/Admin/TodoViewTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/TodoViewTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=TodoViewTest`
Expected: FAIL — view `admin.todos.index` not found.

- [ ] **Step 3: Create the view**

Create `resources/views/admin/todos/index.blade.php`:

```blade
@extends('layouts.admin')

@section('title', 'Todos')

@section('content')
<header class="mb-6">
    <h1 class="font-serif text-2xl font-semibold">Todos</h1>
    <p class="text-sm text-ink-3 mt-1">完成並勾選「顯示於 Changelog」的項目會出現在公開 changelog。</p>
</header>

@if($errors->any())
    <div class="bg-danger-soft border border-danger rounded-md p-3 mb-6 text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid lg:grid-cols-[1fr_340px] gap-6">
    {{-- List --}}
    <div class="space-y-2">
        @forelse($todos as $todo)
            <div class="bg-card border border-line rounded-md p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="text-sm">
                        <div class="font-medium {{ $todo->status === \App\Models\Todo::STATUS_DONE ? 'line-through text-ink-3' : '' }}">
                            {{ $todo->title }}
                        </div>
                        @if($todo->description)
                            <div class="text-ink-3 text-xs mt-1">{{ $todo->description }}</div>
                        @endif
                        <div class="text-ink-3 text-xs mt-1 flex gap-2 flex-wrap">
                            <span class="font-mono uppercase">{{ $todo->priority }}</span>
                            <span>· {{ $todo->status }}</span>
                            @if($todo->show_in_changelog)<span>· changelog ✓</span>@endif
                            @if($todo->completed_at)<span>· {{ $todo->completed_at->format('Y-m-d') }}</span>@endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.todos.destroy', $todo) }}"
                        onsubmit="return confirm('刪除？')">
                        @csrf @method('DELETE')
                        <button class="text-danger hover:underline text-xs">刪除</button>
                    </form>
                </div>

                {{-- Inline edit form --}}
                <form method="POST" action="{{ route('admin.todos.update', $todo) }}" class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    @csrf @method('PUT')
                    <input type="text" name="title" value="{{ $todo->title }}" required
                        class="col-span-2 bg-paper border border-line rounded px-2 py-1">
                    <select name="priority" class="bg-paper border border-line rounded px-2 py-1">
                        @foreach([\App\Models\Todo::PRIORITY_LOW, \App\Models\Todo::PRIORITY_MEDIUM, \App\Models\Todo::PRIORITY_HIGH] as $p)
                            <option value="{{ $p }}" @selected($todo->priority === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="bg-paper border border-line rounded px-2 py-1">
                        @foreach([\App\Models\Todo::STATUS_OPEN, \App\Models\Todo::STATUS_DONE] as $s)
                            <option value="{{ $s }}" @selected($todo->status === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                    <label class="col-span-2 inline-flex items-center gap-2">
                        <input type="checkbox" name="show_in_changelog" value="1" @checked($todo->show_in_changelog)>
                        <span>顯示於 Changelog</span>
                    </label>
                    <button class="col-span-2 bg-paper-2 border border-line rounded px-2 py-1 hover:border-accent">儲存</button>
                </form>
            </div>
        @empty
            <p class="text-ink-3 text-sm py-6">尚無 Todo。</p>
        @endforelse
    </div>

    {{-- Create form --}}
    <aside class="bg-card border border-line rounded-md p-4">
        <h2 class="text-xs text-ink-3 font-mono uppercase tracking-wide mb-3">新增 Todo</h2>
        <form method="POST" action="{{ route('admin.todos.store') }}" class="space-y-3 text-sm">
            @csrf
            <div>
                <label class="block text-xs text-ink-3 mb-1">標題（英文，會顯示在 changelog）</label>
                <input type="text" name="title" value="{{ old('title') }}" required
                    class="w-full bg-paper border border-line rounded px-2 py-1.5 focus:border-accent focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-ink-3 mb-1">描述（內部備註，可空）</label>
                <textarea name="description" rows="2"
                    class="w-full bg-paper border border-line rounded px-2 py-1.5 focus:border-accent focus:outline-none">{{ old('description') }}</textarea>
            </div>
            <div>
                <label class="block text-xs text-ink-3 mb-1">優先級</label>
                <select name="priority" class="w-full bg-paper border border-line rounded px-2 py-1.5">
                    <option value="low">low</option>
                    <option value="medium" selected>medium</option>
                    <option value="high">high</option>
                </select>
            </div>
            <input type="hidden" name="status" value="open">
            <label class="inline-flex items-center gap-2 text-xs">
                <input type="checkbox" name="show_in_changelog" value="1">
                <span>顯示於 Changelog</span>
            </label>
            <button class="w-full bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink font-medium">新增</button>
        </form>
    </aside>
</div>
@endsection
```

- [ ] **Step 4: Add the sidebar nav item**

In `resources/views/layouts/admin.blade.php`, inside the `$items` array (after the `API Tokens` entry), add:

```php
                        ['route' => 'admin.todos.index', 'label' => 'Todos', 'group' => 'todos'],
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter="TodoViewTest|TodoAdminTest"`
Expected: PASS (all admin todo tests green).

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/todos/index.blade.php resources/views/layouts/admin.blade.php tests/Feature/Admin/TodoViewTest.php
git commit -m "feat: admin todo list/create/edit UI and sidebar nav"
```

---

### Task 6: Public `/changelog` page + navbar link

**Files:**
- Create: `app/Http/Controllers/Public/ChangelogController.php`
- Create: `resources/views/public/changelog.blade.php`
- Modify: `routes/web.php` (route OUTSIDE the `{locale}` group)
- Modify: `resources/views/layouts/public.blade.php` (navbar link)
- Test: `tests/Feature/ChangelogPageTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ChangelogPageTest.php`:

```php
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
        // done but not flagged
        app(TodoService::class)->create(['title' => 'Secret chore', 'status' => Todo::STATUS_DONE, 'show_in_changelog' => false]);
        // flagged but open
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=ChangelogPageTest`
Expected: FAIL — `/changelog` 404.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Public/ChangelogController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\TodoService;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(TodoService $service): View
    {
        return view('public.changelog', [
            'groups' => $service->changelogGrouped(),
        ]);
    }
}
```

- [ ] **Step 4: Register the route (outside the locale group)**

In `routes/web.php`, add this ABOVE the `Route::prefix('{locale}')...` group (so the literal `/changelog` is matched before the locale wildcard), near the other top-level routes:

```php
use App\Http\Controllers\Public\ChangelogController;

Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');
```

(Place the `use` with the other controller imports at the top of the file. If `App\Http\Controllers\Public` is already imported as an alias/group, match the file's existing import style.)

- [ ] **Step 5: Create the view**

Create `resources/views/public/changelog.blade.php`:

```blade
@extends('layouts.public')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-16">
    <h1 class="font-serif text-3xl font-semibold mb-2">Changelog</h1>
    <p class="text-ink-3 text-sm mb-10">What's new on the site.</p>

    @forelse($groups as $date => $items)
        <section class="mb-10">
            <h2 class="font-mono text-sm text-ink-3 uppercase tracking-wide mb-3 border-b border-line pb-2">
                {{ \Illuminate\Support\Carbon::parse($date)->format('F j, Y') }}
            </h2>
            <ul class="space-y-2">
                @foreach($items as $item)
                    <li class="flex gap-2 text-ink-2">
                        <span class="text-accent">–</span>
                        <span>{{ $item->title }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-ink-3">No entries yet.</p>
    @endforelse
</div>
@endsection
```

- [ ] **Step 6: Add the navbar link**

In `resources/views/layouts/public.blade.php`, inside the desktop `<nav class="hidden md:flex ...">` block (after the Search link, before `</nav>`), add:

```blade
                <a href="{{ route('changelog') }}" class="hover:text-accent {{ request()->routeIs('changelog') ? 'text-accent font-medium' : 'text-ink-2' }}">
                    Changelog
                </a>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=ChangelogPageTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Public/ChangelogController.php resources/views/public/changelog.blade.php routes/web.php resources/views/layouts/public.blade.php tests/Feature/ChangelogPageTest.php
git commit -m "feat: public /changelog page and navbar link"
```

---

### Task 7: Todo API (CRUD) + rate limiting

**Files:**
- Create: `app/Http/Controllers/Api/TodoController.php`
- Create: `app/Http/Requests/Api/Todo/StoreRequest.php`, `UpdateRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/TodoApiTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/TodoApiTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=TodoApiTest`
Expected: FAIL — `/api/todos` routes not defined (404/401 mismatches).

- [ ] **Step 3: Create the API form requests**

Create `app/Http/Requests/Api/Todo/StoreRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\Todo;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated by auth:sanctum + ability middleware
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in([Todo::PRIORITY_LOW, Todo::PRIORITY_MEDIUM, Todo::PRIORITY_HIGH])],
            'status' => ['required', Rule::in([Todo::STATUS_OPEN, Todo::STATUS_DONE])],
            'show_in_changelog' => ['boolean'],
        ];
    }
}
```

Create `app/Http/Requests/Api/Todo/UpdateRequest.php` (full file, class `UpdateRequest`):

```php
<?php

namespace App\Http\Requests\Api\Todo;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated by auth:sanctum + ability middleware
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in([Todo::PRIORITY_LOW, Todo::PRIORITY_MEDIUM, Todo::PRIORITY_HIGH])],
            'status' => ['required', Rule::in([Todo::STATUS_OPEN, Todo::STATUS_DONE])],
            'show_in_changelog' => ['boolean'],
        ];
    }
}
```

- [ ] **Step 4: Create the API controller**

Create `app/Http/Controllers/Api/TodoController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Todo\StoreRequest;
use App\Http\Requests\Api\Todo\UpdateRequest;
use App\Models\Todo;
use App\Services\TodoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TodoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Todo::query()->latest()->get()]);
    }

    public function show(Todo $todo): JsonResponse
    {
        return response()->json(['data' => $todo]);
    }

    public function store(StoreRequest $request, TodoService $service): JsonResponse
    {
        $todo = $service->create($request->validated());

        return response()->json(['data' => $todo], 201);
    }

    public function update(Todo $todo, UpdateRequest $request, TodoService $service): JsonResponse
    {
        $todo = $service->update($todo, $request->validated());

        return response()->json(['data' => $todo]);
    }

    public function destroy(Todo $todo, TodoService $service): Response
    {
        $service->delete($todo);

        return response()->noContent(); // 204, empty body
    }
}
```

- [ ] **Step 5: Wire routes + throttle**

Replace the contents of `routes/api.php` with (this groups `/me` + todos under `auth:sanctum` + `throttle:60,1`, each todos route adding its ability; the test-only probe stays as-is):

```php
<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\TodoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/me', MeController::class);

    Route::get('/todos', [TodoController::class, 'index'])->middleware('ability:todos:read');
    Route::post('/todos', [TodoController::class, 'store'])->middleware('ability:todos:create');
    Route::get('/todos/{todo}', [TodoController::class, 'show'])->middleware('ability:todos:read');
    Route::patch('/todos/{todo}', [TodoController::class, 'update'])->middleware('ability:todos:update');
    Route::delete('/todos/{todo}', [TodoController::class, 'destroy'])->middleware('ability:todos:delete');
});

if (app()->environment('testing')) {
    Route::get('/_probe', fn () => response()->json(['ok' => true]))
        ->middleware(['auth:sanctum', 'ability:posts:read']);
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=TodoApiTest`
Expected: PASS (7 tests).

- [ ] **Step 7: Run the full suite**

Run: `./vendor/bin/sail artisan test`
Expected: All pass EXCEPT the known pre-existing `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response` (302 vs 200). Confirm nothing else fails — in particular `ApiMeTest` and `AbilityMiddlewareTest` (the `/me` route moved into the throttle group; it must still work).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/TodoController.php app/Http/Requests/Api/Todo routes/api.php tests/Feature/Api/TodoApiTest.php
git commit -m "feat: todo API CRUD with ability scoping and rate limiting"
```

---

## Self-Review

**Spec coverage:**
- `todos` table + model (fields/casts/constants) → Task 1 ✓
- Completion-timestamp rule (open→done sets, done→open clears, edits keep) → Task 2 (TodoService) ✓
- `changelogGrouped` (done + flagged, by date desc) → Task 2 ✓
- `todos` ability in matrix + AbilitiesTest count update (21→25) → Task 3 ✓
- Admin CRUD (controller/requests/routes) → Task 4 ✓; view + sidebar → Task 5 ✓
- Public `/changelog` outside locale group, English, grouped by date, navbar link, empty state → Task 6 ✓
- API CRUD with `ability:todos:*`, throttle rate limiting → Task 7 ✓
- Testing strategy (service, public page, admin, API ability gating + throttle) → Tasks 2,4,5,6,7 ✓
- Security (admin role-gated; API token+ability; changelog exposes only title) → Tasks 4/5 (role:admin), 7 (ability), 6 (view shows only title) ✓

**Placeholder scan:** No TBD/TODO; every code step is complete. The `UpdateRequest` files are written out in full (not "same as StoreRequest").

**Type/name consistency:** `Todo::STATUS_OPEN/STATUS_DONE`, `PRIORITY_LOW/MEDIUM/HIGH` used consistently (Tasks 1,2,4,5,7). `TodoService::create/update/delete/changelogGrouped` signatures consistent (Tasks 2,4,6,7). Route names `admin.todos.index/store/update/destroy` (Tasks 4,5) and `changelog` (Task 6). Ability strings `todos:read/create/update/delete` (Tasks 3,7) match the matrix. `groups` keyed by `Y-m-d`, view re-parses with Carbon (Tasks 2,6) — consistent. API JSON shape `{data: ...}` consistent (Task 7 controller + tests).

**Notes for executor:**
- Line numbers are pre-change; match on quoted code.
- Ensure `.env` has `DB_CONNECTION=pgsql` so the `testing` Postgres DB is used.
- Task 6 Step 4: the `/changelog` route MUST be registered outside (and the file places it above) the `{locale}` prefix group so the locale wildcard (`zh|en|ja|vi|id`) doesn't shadow it — `changelog` isn't in that allow-list anyway, but ordering keeps it unambiguous.
- Task 7 moves `/me` into the throttle group; `ApiMeTest` must stay green (re-run it in Step 7).
