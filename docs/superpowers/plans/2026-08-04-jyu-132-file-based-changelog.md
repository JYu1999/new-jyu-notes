# JYU-132: File-Based Changelog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the DB-backed `todos` changelog *mechanism* with a single Markdown file (`CHANGELOG.md`) — AI inserts a line into the correct date section as part of the same PR as the code change, with no API/token/database involved. The `todos` system itself is marked `@deprecated` in code, not deleted — see the spec's Non-Goals for why.

**Architecture:** `Public\ChangelogController` parses `CHANGELOG.md` at request time (regex over `## YYYY-MM-DD` date headings and their `- ` bullets, re-sorted newest-date-first, bullets kept in file order within a date) instead of querying the `todos` table. The Blade view is unchanged. The legacy Todo model, service, and admin/API controllers gain a `@deprecated` PHPDoc annotation each; nothing else about them changes — routes, database table, ability, admin view, and existing tests all stay exactly as they are.

**Tech Stack:** Laravel 12 (PHP 8.5), Pest/PHPUnit (`php artisan test`), Blade, Pint for style.

## Global Constraints

- Spec: [docs/superpowers/specs/2026-08-04-jyu-132-file-based-changelog-design.md](../specs/2026-08-04-jyu-132-file-based-changelog-design.md)
- Single file: `CHANGELOG.md` at the project root. Not a directory of fragment files.
- Internal format: `## YYYY-MM-DD` heading (ISO date, ambiguity-free) followed by one or more `- ` bullet lines, blank line between date sections, newest date first.
- Content: plain text English, one bullet per changelog-worthy change.
- The controller must **re-sort date sections newest-first regardless of physical file order**, and must **preserve bullet order as written within a section**.
- Content outside any `## YYYY-MM-DD` section, or malformed date headings (including digit-shaped but calendar-invalid dates like `2026-13-01`), must be silently ignored (must not break the page).
- No CI enforcement/safety-net is being added in this plan (explicitly out of scope per spec).
- The `todos` table, its migration, routes, ability entry, admin sidebar item, admin view, and all existing Todo-related tests are **not** touched by this plan beyond the four `@deprecated` docblocks in Task 3 — no deletion, no functional modification.
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
- Produces: `ChangelogController::index()` renders `public.changelog` with `groups` = `Collection<string /* Y-m-d */, Collection<int, object{title: string}>>`, sorted date-descending, bullets in file order within a date. `config('changelog.path')` = absolute path to the single Markdown file (a **file**, not a directory — Task 2 relies on this exact config key and semantics).

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

    public function test_ignores_calendar_invalid_date_headings_and_does_not_crash(): void
    {
        $this->putContent(<<<MD
        ## 2026-13-01
        - Should not appear

        ## 2026-05-19
        - Valid Entry
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('Valid Entry')
            ->assertDontSee('Should not appear');
    }

    public function test_empty_state(): void
    {
        $this->get('/changelog')->assertOk()->assertSee('No entries yet.');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --filter=ChangelogPageTest`
Expected: FAIL — the controller still queries `todos` via `TodoService`, so none of the fixture content in `$this->changelogFile` is read.

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
     * Digit-shaped but calendar-invalid headings (e.g. 2026-13-01) are
     * rejected via checkdate() so they can't reach Carbon::parse() in the
     * view and crash the page.
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

            if (preg_match('/^##\s+(\d{4})-(\d{2})-(\d{2})\s*$/', $line, $match) === 1
                && checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
                $currentDate = $match[1].'-'.$match[2].'-'.$match[3];
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
Expected: PASS (all 6 tests).

- [ ] **Step 6: Run the full suite and style check**

Run: `./vendor/bin/sail artisan test && ./vendor/bin/sail pint --test`
Expected: PASS. (`TodoServiceTest`, `Admin/TodoAdminTest`, etc. still reference `Todo`/`TodoService` and must still pass unchanged — Task 3 only adds docblocks to them, no behavior change.)

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
- Produces: the real `CHANGELOG.md` content that the public `/changelog` page will serve going forward.

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

Run: `./vendor/bin/sail up -d` (if not already running), then open `http://localhost/changelog` in a browser.
Expected: all 17 entries appear, grouped by date (July 12 → June 2, newest first). Compare line-by-line against the content above.

- [ ] **Step 3: Commit**

```bash
git add CHANGELOG.md
git commit -m "JYU-132: migrate 17 existing changelog entries into CHANGELOG.md"
```

---

### Task 3: Mark the legacy Todo system as `@deprecated`

**Files:**
- Modify: `app/Models/Todo.php`
- Modify: `app/Services/TodoService.php`
- Modify: `app/Http/Controllers/Admin/TodoController.php`
- Modify: `app/Http/Controllers/Api/TodoController.php`

**Interfaces:**
- Consumes: nothing from Task 1/2 — this task only adds docblock comments, no behavioral change, no dependency on the new changelog mechanism.
- Produces: nothing consumed by later tasks (last task in this plan). No routes, config, database schema, admin view, or existing test file changes — the deprecation notice is purely a code-comment signal.

- [ ] **Step 1: Add the deprecation docblock to the model**

In `app/Models/Todo.php`, add this docblock immediately above `class Todo extends Model`:

```php
/**
 * @deprecated Since JYU-132, changelog is generated from CHANGELOG.md instead
 * of this system. Kept functional for historical data only — not deleted
 * because the table was never fully audited for content beyond what the
 * public changelog displayed. See docs/superpowers/specs/2026-08-04-jyu-132-file-based-changelog-design.md.
 */
class Todo extends Model
```

- [ ] **Step 2: Add the deprecation docblock to the service**

In `app/Services/TodoService.php`, add the same docblock immediately above `class TodoService`:

```php
/**
 * @deprecated Since JYU-132, changelog is generated from CHANGELOG.md instead
 * of this system. Kept functional for historical data only — not deleted
 * because the table was never fully audited for content beyond what the
 * public changelog displayed. See docs/superpowers/specs/2026-08-04-jyu-132-file-based-changelog-design.md.
 */
class TodoService
```

- [ ] **Step 3: Add the deprecation docblock to the admin controller**

In `app/Http/Controllers/Admin/TodoController.php`, add the same docblock immediately above `class TodoController extends Controller`:

```php
/**
 * @deprecated Since JYU-132, changelog is generated from CHANGELOG.md instead
 * of this system. Kept functional for historical data only — not deleted
 * because the table was never fully audited for content beyond what the
 * public changelog displayed. See docs/superpowers/specs/2026-08-04-jyu-132-file-based-changelog-design.md.
 */
class TodoController extends Controller
```

- [ ] **Step 4: Add the deprecation docblock to the API controller**

In `app/Http/Controllers/Api/TodoController.php`, add the same docblock immediately above `class TodoController extends Controller`:

```php
/**
 * @deprecated Since JYU-132, changelog is generated from CHANGELOG.md instead
 * of this system. Kept functional for historical data only — not deleted
 * because the table was never fully audited for content beyond what the
 * public changelog displayed. See docs/superpowers/specs/2026-08-04-jyu-132-file-based-changelog-design.md.
 */
class TodoController extends Controller
```

- [ ] **Step 5: Run the full suite and style check**

Run: `./vendor/bin/sail artisan test && ./vendor/bin/sail pint --test`
Expected: PASS, with the exact same test count as before this task (docblock-only change — no test file was touched, so `TodoServiceTest`, `TodoModelTest`, `Admin/TodoViewTest`, `Admin/TodoAdminTest`, `Api/TodoApiTest`, and `AbilitiesTest` all continue to pass unmodified).

- [ ] **Step 6: Confirm no other file changed**

Run: `git status --short`
Expected: exactly 4 modified files (`app/Models/Todo.php`, `app/Services/TodoService.php`, `app/Http/Controllers/Admin/TodoController.php`, `app/Http/Controllers/Api/TodoController.php`), nothing else — confirms routes, config, migrations, views, and tests were not touched.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Todo.php app/Services/TodoService.php app/Http/Controllers/Admin/TodoController.php app/Http/Controllers/Api/TodoController.php
git commit -m "JYU-132: mark legacy Todo system as @deprecated in favor of CHANGELOG.md"
```
