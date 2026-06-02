# Agent API — Phase 2 (Categories + Tags) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Token-scoped REST CRUD for categories and tags (each with nested `translations[]`), reusing the existing `CategoryService`/`TagService`, following the conventions established in Phase 1.

**Architecture:** Thin `Api\CategoryController`/`Api\TagController` delegate to the existing services. New `CategoryResource`/`TagResource` output the model + its translations. Routes join the existing `auth:sanctum`+`throttle:60,1` group in `routes/api.php`, each gated by `ability:<resource>:<action>`. Delete is a HARD delete (mirrors admin). No publish/translation sub-endpoints (categories/tags have neither).

**Tech Stack:** Laravel 13, Sanctum, PHPUnit, Laravel Sail (`./vendor/bin/sail`), Postgres `testing` DB.

> **Scope:** Phase 2 of `docs/superpowers/specs/2026-06-02-agent-api-design.md`. Abilities `categories:*` and `tags:*` already exist in `config/abilities.php` — no matrix change. Media is Phase 3.

---

## File Structure

**Create:**
- `app/Http/Resources/CategoryResource.php`, `app/Http/Resources/TagResource.php`
- `app/Http/Requests/Api/Category/StoreRequest.php`, `UpdateRequest.php`
- `app/Http/Requests/Api/Tag/StoreRequest.php`, `UpdateRequest.php`
- `app/Http/Controllers/Api/CategoryController.php`, `app/Http/Controllers/Api/TagController.php`
- Tests: `tests/Feature/Api/CategoryApiTest.php`, `tests/Feature/Api/TagApiTest.php`

**Modify:** `routes/api.php` (add routes to the existing group).

**Reuse:** `CategoryService` (create/update/delete), `TagService` (create/update/delete). `Category` (SORT_* consts, `translations()`), `Tag` (`translations()`). Note: `update()` on both services does a full translations replace (deletes locales not in the incoming set) and `delete()` is a hard delete.

---

### Task 1: Categories API — resource, requests, CRUD controller, routes

**Files:**
- Create: `app/Http/Resources/CategoryResource.php`
- Create: `app/Http/Requests/Api/Category/StoreRequest.php`, `UpdateRequest.php`
- Create: `app/Http/Controllers/Api/CategoryController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/CategoryApiTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/CategoryApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeCategory(string $name = 'Tech'): Category
    {
        return app(CategoryService::class)->create([
            'sort_method' => Category::SORT_DATE_DESC,
            'translations' => [['locale' => 'en', 'name' => $name, 'slug' => null, 'description' => null]],
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['categories:create']);
        $this->getJson('/api/categories')->assertForbidden();
    }

    public function test_index_paginated_with_translations(): void
    {
        $this->makeCategory('Tech');
        Sanctum::actingAs($this->user(), ['categories:read']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'sort_method', 'translations' => [['locale', 'name', 'slug']]]], 'links', 'meta'])
            ->assertJsonPath('data.0.translations.0.name', 'Tech');
    }

    public function test_create_with_translations(): void
    {
        Sanctum::actingAs($this->user(), ['categories:create']);

        $this->postJson('/api/categories', [
            'sort_method' => 'date_desc',
            'translations' => [
                ['locale' => 'en', 'name' => 'Travel'],
                ['locale' => 'zh', 'name' => '旅遊'],
            ],
        ])->assertCreated()->assertJsonPath('data.translations.1.name', '旅遊');

        $this->assertDatabaseHas('category_translations', ['name' => 'Travel', 'locale' => 'en']);
    }

    public function test_update_replaces_translations(): void
    {
        $category = $this->makeCategory('Old');
        Sanctum::actingAs($this->user(), ['categories:update']);

        $this->patchJson("/api/categories/{$category->id}", [
            'translations' => [['locale' => 'en', 'name' => 'New']],
        ])->assertOk()->assertJsonPath('data.translations.0.name', 'New');

        $this->assertDatabaseHas('category_translations', ['name' => 'New']);
        $this->assertDatabaseMissing('category_translations', ['name' => 'Old']);
    }

    public function test_delete_removes_category(): void
    {
        $category = $this->makeCategory();
        Sanctum::actingAs($this->user(), ['categories:delete']);

        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=CategoryApiTest`
Expected: FAIL — `/api/categories` routes not defined.

- [ ] **Step 3: Create the resource**

Create `app/Http/Resources/CategoryResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cover_image_path' => $this->cover_image_path,
            'sort_method' => $this->sort_method,
            'translations' => $this->translations->map(fn ($t) => [
                'locale' => $t->locale,
                'name' => $t->name,
                'slug' => $t->slug,
                'description' => $t->description,
            ])->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 4: Create the form requests**

Create `app/Http/Requests/Api/Category/StoreRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:categories:create
    }

    public function rules(): array
    {
        return [
            'cover_image_path' => 'nullable|string|max:500',
            'sort_method' => 'sometimes|in:manual,date_desc,date_asc',
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|in:zh,en,ja,vi,id|distinct',
            'translations.*.name' => 'required|string|max:100',
            'translations.*.slug' => ['nullable', 'string', 'max:120', 'not_regex:~[/\\\\?#&\s]~'],
            'translations.*.description' => 'nullable|string|max:1000',
        ];
    }
}
```

Create `app/Http/Requests/Api/Category/UpdateRequest.php` (translations `sometimes` for partial; when present, full-replace by the service):

```php
<?php

namespace App\Http\Requests\Api\Category;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:categories:update
    }

    public function rules(): array
    {
        return [
            'cover_image_path' => 'sometimes|nullable|string|max:500',
            'sort_method' => 'sometimes|required|in:manual,date_desc,date_asc',
            'translations' => 'sometimes|array|min:1',
            'translations.*.locale' => 'required|in:zh,en,ja,vi,id|distinct',
            'translations.*.name' => 'required|string|max:100',
            'translations.*.slug' => ['nullable', 'string', 'max:120', 'not_regex:~[/\\\\?#&\s]~'],
            'translations.*.description' => 'nullable|string|max:1000',
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Api/CategoryController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Category\StoreRequest;
use App\Http\Requests\Api\Category\UpdateRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->with('translations')->latest()->paginate(20)
        );
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('translations'));
    }

    public function store(StoreRequest $request, CategoryService $service): JsonResponse
    {
        $category = $service->create($request->validated());

        return (new CategoryResource($category->load('translations')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Category $category, UpdateRequest $request, CategoryService $service): CategoryResource
    {
        $category = $service->update($category, $request->validated());
        return new CategoryResource($category->load('translations'));
    }

    public function destroy(Category $category, CategoryService $service): Response
    {
        $service->delete($category);
        return response()->noContent();
    }
}
```

- [ ] **Step 6: Register routes**

In `routes/api.php`, add `use App\Http\Controllers\Api\CategoryController;` at the top, and INSIDE the existing `auth:sanctum`+`throttle` group add:

```php
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('ability:categories:read');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('ability:categories:create');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('ability:categories:read');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->middleware('ability:categories:update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('ability:categories:delete');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=CategoryApiTest`
Expected: PASS (6 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Resources/CategoryResource.php app/Http/Requests/Api/Category routes/api.php app/Http/Controllers/Api/CategoryController.php tests/Feature/Api/CategoryApiTest.php
git commit -m "feat: categories API CRUD with translations and ability scoping"
```

---

### Task 2: Tags API — resource, requests, CRUD controller, routes

**Files:**
- Create: `app/Http/Resources/TagResource.php`
- Create: `app/Http/Requests/Api/Tag/StoreRequest.php`, `UpdateRequest.php`
- Create: `app/Http/Controllers/Api/TagController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/TagApiTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/TagApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeTag(string $name = 'PHP'): Tag
    {
        return app(TagService::class)->create([
            'color' => '#b2543b',
            'translations' => [['locale' => 'en', 'name' => $name, 'slug' => null]],
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/tags')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['tags:create']);
        $this->getJson('/api/tags')->assertForbidden();
    }

    public function test_index_paginated_with_translations(): void
    {
        $this->makeTag('PHP');
        Sanctum::actingAs($this->user(), ['tags:read']);

        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'color', 'translations' => [['locale', 'name', 'slug']]]], 'links', 'meta'])
            ->assertJsonPath('data.0.translations.0.name', 'PHP');
    }

    public function test_create_with_translations(): void
    {
        Sanctum::actingAs($this->user(), ['tags:create']);

        $this->postJson('/api/tags', [
            'color' => '#123456',
            'translations' => [['locale' => 'en', 'name' => 'Laravel']],
        ])->assertCreated()->assertJsonPath('data.translations.0.name', 'Laravel');

        $this->assertDatabaseHas('tag_translations', ['name' => 'Laravel', 'locale' => 'en']);
    }

    public function test_create_rejects_bad_color(): void
    {
        Sanctum::actingAs($this->user(), ['tags:create']);

        $this->postJson('/api/tags', [
            'color' => 'red',
            'translations' => [['locale' => 'en', 'name' => 'X']],
        ])->assertStatus(422);
    }

    public function test_update_replaces_translations(): void
    {
        $tag = $this->makeTag('Old');
        Sanctum::actingAs($this->user(), ['tags:update']);

        $this->patchJson("/api/tags/{$tag->id}", [
            'translations' => [['locale' => 'en', 'name' => 'New']],
        ])->assertOk()->assertJsonPath('data.translations.0.name', 'New');

        $this->assertDatabaseMissing('tag_translations', ['name' => 'Old']);
    }

    public function test_delete_removes_tag(): void
    {
        $tag = $this->makeTag();
        Sanctum::actingAs($this->user(), ['tags:delete']);

        $this->deleteJson("/api/tags/{$tag->id}")->assertNoContent();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=TagApiTest`
Expected: FAIL — `/api/tags` routes not defined.

- [ ] **Step 3: Create the resource**

Create `app/Http/Resources/TagResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'color' => $this->color,
            'translations' => $this->translations->map(fn ($t) => [
                'locale' => $t->locale,
                'name' => $t->name,
                'slug' => $t->slug,
            ])->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 4: Create the form requests**

Create `app/Http/Requests/Api/Tag/StoreRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\Tag;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:tags:create
    }

    public function rules(): array
    {
        return [
            'color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|in:zh,en,ja,vi,id|distinct',
            'translations.*.name' => 'required|string|max:100',
            'translations.*.slug' => ['nullable', 'string', 'max:120', 'not_regex:~[/\\\\?#&\s]~'],
        ];
    }
}
```

Create `app/Http/Requests/Api/Tag/UpdateRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\Tag;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:tags:update
    }

    public function rules(): array
    {
        return [
            'color' => 'sometimes|nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'translations' => 'sometimes|array|min:1',
            'translations.*.locale' => 'required|in:zh,en,ja,vi,id|distinct',
            'translations.*.name' => 'required|string|max:100',
            'translations.*.slug' => ['nullable', 'string', 'max:120', 'not_regex:~[/\\\\?#&\s]~'],
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Api/TagController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tag\StoreRequest;
use App\Http\Requests\Api\Tag\UpdateRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(
            Tag::query()->with('translations')->latest()->paginate(20)
        );
    }

    public function show(Tag $tag): TagResource
    {
        return new TagResource($tag->load('translations'));
    }

    public function store(StoreRequest $request, TagService $service): JsonResponse
    {
        $tag = $service->create($request->validated());

        return (new TagResource($tag->load('translations')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Tag $tag, UpdateRequest $request, TagService $service): TagResource
    {
        $tag = $service->update($tag, $request->validated());
        return new TagResource($tag->load('translations'));
    }

    public function destroy(Tag $tag, TagService $service): Response
    {
        $service->delete($tag);
        return response()->noContent();
    }
}
```

- [ ] **Step 6: Register routes**

In `routes/api.php`, add `use App\Http\Controllers\Api\TagController;` at the top, and INSIDE the existing group add:

```php
    Route::get('/tags', [TagController::class, 'index'])->middleware('ability:tags:read');
    Route::post('/tags', [TagController::class, 'store'])->middleware('ability:tags:create');
    Route::get('/tags/{tag}', [TagController::class, 'show'])->middleware('ability:tags:read');
    Route::patch('/tags/{tag}', [TagController::class, 'update'])->middleware('ability:tags:update');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->middleware('ability:tags:delete');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=TagApiTest`
Expected: PASS (7 tests).

- [ ] **Step 8: Run the full suite**

Run: `./vendor/bin/sail artisan test`
Expected: All pass EXCEPT the pre-existing `Tests\Feature\ExampleTest` (GET / 302). Confirm no regressions (posts/tweets API, P2 tokens, todos, changelog). Report counts.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Resources/TagResource.php app/Http/Requests/Api/Tag routes/api.php app/Http/Controllers/Api/TagController.php tests/Feature/Api/TagApiTest.php
git commit -m "feat: tags API CRUD with translations and ability scoping"
```

---

## Self-Review

**Spec coverage (Phase 2 = categories + tags):**
- categories CRUD + nested translations + pagination + ability gating → Task 1 ✓
- categories update replaces translations (service behavior) → Task 1 `test_update_replaces_translations` ✓
- categories hard delete → Task 1 ✓
- tags CRUD + translations + color validation + hard delete → Task 2 ✓
- API Resource layer (CategoryResource/TagResource with translations) → Tasks 1, 2 ✓
- ability strings match matrix (`categories:*`, `tags:*`) → routes ✓
- partial PATCH (`translations` sometimes) → UpdateRequests ✓
- 201/200/204 + `{data}` envelope → controllers ✓

**Placeholder scan:** No TBD/TODO; full code in every step.

**Type/name consistency:** `CategoryService`/`TagService` `create(array)`/`update(model,array)`/`delete(model)` used exactly as they exist. Resource keys (`translations` with locale/name/slug[/description]) match test `assertJsonStructure`/`assertJsonPath`. Route abilities match `config/abilities.php` (`categories`/`tags` = read/create/update/delete, no publish). `sort_method` optional on create (service defaults to `date_desc`); `color` regex `^#[0-9a-fA-F]{6}$`.

**Notes for executor:**
- Line numbers pre-change; match on quoted code. `.env` must have `DB_CONNECTION=pgsql`.
- All routes go INSIDE the existing `Route::middleware(['auth:sanctum','throttle:60,1'])->group(...)` — add imports at top.
- `CategoryService::update`/`TagService::update` do a FULL translations replace (delete locales not in the incoming set) — that's the intended behavior the update test asserts.
- Delete is HARD (no soft-deletes on categories/tags) — tests use `assertDatabaseMissing`.
