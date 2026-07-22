# JYU-132: File-Based Changelog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the DB-backed `todos` changelog system with a single Markdown file (`CHANGELOG.md`) — AI inserts a line into the correct date section as part of the same PR as the code change, with no API/token/database involved.

**Architecture:** `Public\ChangelogController` parses `CHANGELOG.md` at request time (regex over `## YYYY-MM-DD` date headings and their `- ` bullets, re-sorted newest-date-first, bullets kept in file order within a date) instead of querying the `todos` table. The Blade view is unchanged. The legacy Todo admin UI, API, and ability are removed entirely.

**Tech Stack:** Laravel 12 (PHP 8.5), Pest/PHPUnit (`php artisan test`), Blade, Pint for style.

## Global Constraints

- Spec: [docs/superpowers/specs/2026-07-22-jyu-132-file-based-changelog-design.md](../specs/2026-07-22-jyu-132-file-based-changelog-design.md)
- Single file: `CHANGELOG.md` at the project root. **Not** a directory of fragment files (revised from the original one-file-per-entry design after further discussion — see the spec's Non-Goals section for why).
- Internal format: `## YYYY-MM-DD` heading (ISO date, ambiguity-free) followed by one or more `- ` bullet lines, blank line between date sections, newest date first.
- Content: plain text English, one bullet per changelog-worthy change.
- The controller must **re-sort date sections newest-first regardless of physical file order**, and must **preserve bullet order as written within a section** — this is a deliberate defensive measure against AI mis-placing a new section (see spec's Risks section).
- Content outside any `## YYYY-MM-DD` section, or malformed date headings, must be silently ignored (must not break the page).
- No CI enforcement/safety-net is being added in this plan (explicitly out of scope per spec).
- `todos` table is dropped via a **new** migration (`dropIfExists`); the original `create_todos_table` migration file is **not** deleted (preserves migration history).
- Run `./vendor/bin/sail artisan test` and `./vendor/bin/sail pint --test` before every commit in this plan — this repo's CI (`ci.yml`) runs both.

---

### Task 1: File-based `/changelog` rendering

**Files:**
- Create: `config/changelog.php`
- Modify: `app/Http/Controllers/Public/ChangelogController.php`
- Modify (rewrite): `tests/Feature/ChangelogPageTest.php`
- No change needed: `resources/views/public/changelog.blade.php` (already reads `$item->title` and `$date` — a `(object) ['title' => ...]` shape satisfies it unchanged)

**Interfaces:**
- Consumes: nothing from earlier tasks (first task).
- Produces: `ChangelogController::index()` renders `public.changelog` with `groups` = `Collection<string /* Y-m-d */, Collection<int, object{title: string}>>`, sorted date-descending, bullets in file order within a date. `config('changelog.path')` = absolute path to the single Markdown file (a **file**, not a directory — later tasks rely on this exact config key and semantics).

- [ ] **Step 1: Write the failing test (replace the whole file)**

Replace `tests/Feature/ChangelogPageTest.php` with:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    private string $changelogFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->changelogFile = storage_path('framework/testing/changelog-'.uniqid().'.md');
        config(['changelog.path' => $this->changelogFile]);
    }

    protected function tearDown(): void
    {
        File::delete($this->changelogFile);

        parent::tearDown();
    }

    private function putContent(string $content): void
    {
        File::put($this->changelogFile, $content);
    }

    public function test_changelog_shows_grouped_entries(): void
    {
        $this->putContent(<<<MD
        ## 2026-05-19
        - A Feature

        ## 2026-05-18
        - C Feature
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('May 19, 2026')
            ->assertSee('A Feature')
            ->assertSee('May 18, 2026')
            ->assertSee('C Feature');
    }

    public function test_orders_newest_date_first_even_if_file_lists_them_out_of_order(): void
    {
        $this->putContent(<<<MD
        ## 2026-05-18
        - Old Entry

        ## 2026-05-19
        - New Entry
        MD);

        $content = $this->get('/changelog')->getContent();

        $this->assertTrue(strpos($content, 'New Entry') < strpos($content, 'Old Entry'));
    }

    public function test_same_day_entries_preserve_file_order(): void
    {
        $this->putContent(<<<MD
        ## 2026-05-19
        - First Entry
        - Second Entry
        MD);

        $content = $this->get('/changelog')->getContent();

        $this->assertTrue(strpos($content, 'First Entry') < strpos($content, 'Second Entry'));
    }

    public function test_ignores_content_outside_a_date_heading_and_malformed_headers(): void
    {
        $this->putContent(<<<MD
        Random preamble text that is not a bullet.

        ## not-a-date
        - Should not appear

        ## 2026-05-19
        - Valid Entry
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('Valid Entry')
            ->assertDontSee('Should not appear')
            ->assertDontSee('Random preamble');
    }

    public function test_empty_state(): void
    {
        $this->get('/changelog')->assertOk()->assertSee('No entries yet.');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --filter=ChangelogPageTest`
Expected: FAIL — the controller still queries `todos` via `TodoService`, so none of the fixture content in `$this->changelogFile` is read (e.g. `test_changelog_shows_grouped_entries` fails because "May 19, 2026" / "A Feature" are never rendered).

- [ ] **Step 3: Add the config file**

Create `config/changelog.php`:

```php
<?php

return [
    'path' => base_path('CHANGELOG.md'),
];
```

- [ ] **Step 4: Rewrite the controller**

Replace `app/Http/Controllers/Public/ChangelogController.php` with:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(): View
    {
        return view('public.changelog', [
            'groups' => $this->changelogGrouped(),
        ]);
    }

    /**
     * Parse CHANGELOG.md into date-grouped entries. Date sections are
     * re-sorted newest-first regardless of their physical order in the
     * file; bullets within a section keep the order they were written in.
     *
     * @return Collection<string, Collection<int, object{title: string}>>
     */
    private function changelogGrouped(): Collection
    {
        $path = config('changelog.path');

        if (! File::exists($path)) {
            return collect();
        }

        $groups = collect();
        $currentDate = null;

        foreach (preg_split('/\R/', File::get($path)) as $line) {
            $line = rtrim($line);

            if (preg_match('/^##\s+(\d{4}-\d{2}-\d{2})\s*$/', $line, $match) === 1) {
                $currentDate = $match[1];
                $groups->put($currentDate, $groups->get($currentDate, collect()));

                continue;
            }

            if ($currentDate !== null && preg_match('/^-\s+(.+)$/', $line, $match) === 1) {
                $groups->put($currentDate, $groups->get($currentDate)->push((object) [
                    'title' => trim($match[1]),
                ]));
            }
        }

        return $groups->sortKeysDesc();
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/sail artisan test --filter=ChangelogPageTest`
Expected: PASS (all 5 tests).

- [ ] **Step 6: Run the full suite and style check**

Run: `./vendor/bin/sail artisan test && ./vendor/bin/sail pint --test`
Expected: PASS. (`TodoServiceTest`, `Admin/TodoAdminTest`, etc. still reference the old `Todo`/`TodoService` classes and must still pass unchanged at this point — they are only removed in Task 3.)

- [ ] **Step 7: Commit**

```bash
git add config/changelog.php app/Http/Controllers/Public/ChangelogController.php tests/Feature/ChangelogPageTest.php
git commit -m "JYU-132: /changelog renders from CHANGELOG.md instead of the todos table"
```

---

### Task 2: Migrate the 17 existing changelog entries

**Files:**
- Create: `CHANGELOG.md`

**Interfaces:**
- Consumes: Task 1's `ChangelogController` (to visually verify the migrated content renders correctly) and `config('changelog.path')` semantics (must point at `base_path('CHANGELOG.md')` in the non-test environment, which Task 1 Step 3 already set up).
- Produces: the real `CHANGELOG.md` content that Task 3's removal of the `todos` table makes safe (nothing left depends on the DB for this page after this task).

- [ ] **Step 1: Create `CHANGELOG.md`**

Create `CHANGELOG.md` at the project root with exactly this content:

```markdown
## 2026-07-12
- Fix homepage layout collapsing when a tweet contains a very long URL

## 2026-07-11
- Add offsite backup system (Postgres + R2 to B2)
- Add Google Analytics (GA4) to public pages
- Fix bullet and numbered list rendering(Author: yi-rong)

## 2026-07-04
- Add estimated reading time to articles(Author: yi-rong)

## 2026-06-16
- Wide images no longer break article layout on mobile
- Posts and tweets can now @-mention each other, with backlinks on both

## 2026-06-12
- Post @-mentions and backlinks

## 2026-06-08
- Fullscreen image viewer
- Sensitive image blur on tweets

## 2026-06-07
- Tag colors: admin picker + public tinted chips
- YouTube paste-to-embed in admin editor
- Admin media upload UX improvements

## 2026-06-02
- Faster images — media now served via Cloudflare R2
- New: API access with scoped, expiring tokens (for automation)
- New: lightweight todo / roadmap manager in the admin panel
- New: public changelog page
```

- [ ] **Step 2: Verify locally against the real file**

Run: `./vendor/bin/sail artisan serve` (or use the existing `sail up` dev server), then open `http://localhost/changelog` in a browser.
Expected: all 17 entries appear, grouped by date (July 12 → June 2, newest first), matching what was read from `https://jyu1999.com/changelog` earlier in the design discussion. Compare line-by-line against the content above.

- [ ] **Step 3: Commit**

```bash
git add CHANGELOG.md
git commit -m "JYU-132: migrate 17 existing changelog entries into CHANGELOG.md"
```

---

### Task 3: Remove the legacy Todo system

**Files:**
- Delete: `app/Models/Todo.php`
- Delete: `app/Services/TodoService.php`
- Delete: `app/Http/Controllers/Admin/TodoController.php`
- Delete: `app/Http/Controllers/Api/TodoController.php`
- Delete: `app/Http/Requests/Admin/Todo/StoreRequest.php`
- Delete: `app/Http/Requests/Admin/Todo/UpdateRequest.php`
- Delete: `app/Http/Requests/Api/Todo/StoreRequest.php`
- Delete: `app/Http/Requests/Api/Todo/UpdateRequest.php`
- Delete: `resources/views/admin/todos/index.blade.php`
- Delete: `tests/Feature/TodoServiceTest.php`
- Delete: `tests/Feature/TodoModelTest.php`
- Delete: `tests/Feature/Admin/TodoViewTest.php`
- Delete: `tests/Feature/Admin/TodoAdminTest.php`
- Delete: `tests/Feature/Api/TodoApiTest.php`
- Create: `database/migrations/2026_07_22_000001_drop_todos_table.php`
- Modify: `routes/web.php:112-116` (remove the "Todos" admin route block)
- Modify: `routes/api.php:8` and `routes/api.php:15-20` (remove the `TodoController` import and the 5 API routes)
- Modify: `config/abilities.php:14` (remove the `todos` entry)
- Modify: `resources/views/layouts/admin.blade.php:60` (remove the sidebar "Todos" item)
- Modify: `tests/Feature/AbilitiesTest.php` (drop the `todos:*` assertions and update the total count)

**Interfaces:**
- Consumes: nothing from Task 1/2 code — this task is safe to run only because Task 1 already moved `ChangelogController` off `TodoService` (verified: `grep -rn "TodoService\|Todo::" app/Http/Controllers/Public/` returns nothing after Task 1).
- Produces: nothing consumed by later tasks (last task in this plan).

- [ ] **Step 1: Delete the legacy files**

```bash
git rm app/Models/Todo.php
git rm app/Services/TodoService.php
git rm app/Http/Controllers/Admin/TodoController.php
git rm app/Http/Controllers/Api/TodoController.php
git rm app/Http/Requests/Admin/Todo/StoreRequest.php app/Http/Requests/Admin/Todo/UpdateRequest.php
git rm app/Http/Requests/Api/Todo/StoreRequest.php app/Http/Requests/Api/Todo/UpdateRequest.php
git rm resources/views/admin/todos/index.blade.php
git rm tests/Feature/TodoServiceTest.php tests/Feature/TodoModelTest.php
git rm tests/Feature/Admin/TodoViewTest.php tests/Feature/Admin/TodoAdminTest.php
git rm tests/Feature/Api/TodoApiTest.php
```

- [ ] **Step 2: Remove the admin Todo routes**

In `routes/web.php`, delete lines 112-116:

```php
        // Todos
        Route::get('todos', [Admin\TodoController::class, 'index'])->name('todos.index');
        Route::post('todos', [Admin\TodoController::class, 'store'])->name('todos.store');
        Route::put('todos/{todo}', [Admin\TodoController::class, 'update'])->name('todos.update');
        Route::delete('todos/{todo}', [Admin\TodoController::class, 'destroy'])->name('todos.destroy');
```

so the block ends with the `tokens.destroy` route immediately followed by the closing `});`.

- [ ] **Step 3: Remove the API Todo routes and import**

In `routes/api.php`, delete line 8:

```php
use App\Http\Controllers\Api\TodoController;
```

and delete lines 15-19 (plus the blank line that follows them, so `Route::get('/posts', ...)` directly follows `Route::get('/me', MeController::class);` with a single blank line between):

```php
    Route::get('/todos', [TodoController::class, 'index'])->middleware('ability:todos:read');
    Route::post('/todos', [TodoController::class, 'store'])->middleware('ability:todos:create');
    Route::get('/todos/{todo}', [TodoController::class, 'show'])->middleware('ability:todos:read');
    Route::patch('/todos/{todo}', [TodoController::class, 'update'])->middleware('ability:todos:update');
    Route::delete('/todos/{todo}', [TodoController::class, 'destroy'])->middleware('ability:todos:delete');
```

- [ ] **Step 4: Remove the `todos` ability**

In `config/abilities.php`, delete line 14:

```php
    'todos' => ['read', 'create', 'update', 'delete'],
```

- [ ] **Step 5: Remove the admin sidebar entry**

In `resources/views/layouts/admin.blade.php`, delete line 60:

```php
                        ['route' => 'admin.todos.index', 'label' => 'Todos', 'group' => 'todos'],
```

- [ ] **Step 6: Update `AbilitiesTest`**

Replace `tests/Feature/AbilitiesTest.php` with:

```php
<?php

namespace Tests\Feature;

use App\Support\Abilities;
use Tests\TestCase;

class AbilitiesTest extends TestCase
{
    public function test_all_flattens_matrix_to_resource_action_strings(): void
    {
        $all = Abilities::all();

        $this->assertContains('posts:read', $all);
        $this->assertContains('posts:publish', $all);
        $this->assertContains('media:create', $all);
        // media has no update; categories/tags have no publish
        $this->assertNotContains('media:update', $all);
        $this->assertNotContains('tags:publish', $all);
        $this->assertNotContains('categories:publish', $all);

        $this->assertNotContains('todos:read', $all);

        // 5 + 5 + 4 + 4 + 3 = 21 abilities
        $this->assertCount(21, $all);
    }

    public function test_is_valid_checks_membership(): void
    {
        $this->assertTrue(Abilities::isValid('posts:create'));
        $this->assertFalse(Abilities::isValid('media:update'));
        $this->assertFalse(Abilities::isValid('nonsense:foo'));
    }
}
```

- [ ] **Step 7: Add the drop-table migration**

Create `database/migrations/2026_07_22_000001_drop_todos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('todos');
    }

    public function down(): void
    {
        // Intentionally not restored — the todos table and its data
        // are fully superseded by CHANGELOG.md (see
        // docs/superpowers/specs/2026-07-22-jyu-132-file-based-changelog-design.md).
    }
};
```

- [ ] **Step 8: Run the full suite and style check**

Run: `./vendor/bin/sail artisan test && ./vendor/bin/sail pint --test`
Expected: PASS. No test file should reference `Todo`, `TodoService`, or `todos:*` abilities anymore — confirm with:

Run: `grep -rln "Todo\b" app tests resources/views routes config --include="*.php" --include="*.blade.php"`
Expected: no output (empty).

- [ ] **Step 9: Run the migration locally**

Run: `./vendor/bin/sail artisan migrate`
Expected: `2026_07_22_000001_drop_todos_table` runs and reports success; `./vendor/bin/sail artisan tinker --execute="dd(Schema::hasTable('todos'));"` prints `false`.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "JYU-132: remove legacy todos table, admin UI, and API in favor of CHANGELOG.md"
```
