# Agent API — Phase 3 (Media) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Token-scoped media API — list, multipart upload, and delete — reusing the existing `MediaService`, so an agent can upload a cover image and reference its `path`.

**Architecture:** A thin `Api\MediaController` delegates to `MediaService` (`paginate`/`upload`/`delete`). A new `MediaResource` exposes id/path/url/metadata. Routes join the existing `auth:sanctum`+`throttle:60,1` group in `routes/api.php`, gated by `ability:media:read|create|delete`. Media has NO `update` ability (files are immutable) and NO publish/translations.

**Tech Stack:** Laravel 13, Sanctum, PHPUnit, Laravel Sail (`./vendor/bin/sail`), Postgres `testing` DB.

> **Scope:** Phase 3 (final batch) of `docs/superpowers/specs/2026-06-02-agent-api-design.md`. Abilities `media:read|create|delete` already exist in `config/abilities.php` (no `media:update`) — no matrix change.

---

## File Structure

**Create:**
- `app/Http/Resources/MediaResource.php`
- `app/Http/Requests/Api/Media/StoreRequest.php`
- `app/Http/Controllers/Api/MediaController.php`
- `tests/Feature/Api/MediaApiTest.php`

**Modify:** `routes/api.php` (add routes to the existing group).

**Reuse:** `MediaService::paginate(int): LengthAwarePaginator`, `upload(UploadedFile, ?User): Media`, `delete(int): void`. `Media` model (`url()`, fillable: path, mime_type, size, width, height, original_filename, uploaded_by). Media has no soft-deletes (hard delete; `MediaService::delete` removes the file from the configured disk then the row).

---

### Task 1: Media API — resource, request, controller, routes

**Files:**
- Create: `app/Http/Resources/MediaResource.php`
- Create: `app/Http/Requests/Api/Media/StoreRequest.php`
- Create: `app/Http/Controllers/Api/MediaController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/MediaApiTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/MediaApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.disk' => 'public']);
        Storage::fake('public');
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeMedia(string $name = 'old.jpg'): Media
    {
        return Media::create([
            'path' => 'uploads/2026/06/'.$name,
            'mime_type' => 'image/jpeg',
            'size' => 123,
            'original_filename' => $name,
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/media')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['media:create']); // lacks read
        $this->getJson('/api/media')->assertForbidden();
    }

    public function test_index_paginated(): void
    {
        $this->makeMedia('a.jpg');
        $this->makeMedia('b.jpg');
        Sanctum::actingAs($this->user(), ['media:read']);

        $this->getJson('/api/media')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'path', 'url', 'mime_type', 'original_filename']], 'links', 'meta'])
            ->assertJsonCount(2, 'data');
    }

    public function test_upload_requires_create_ability(): void
    {
        Sanctum::actingAs($this->user(), ['media:read']); // lacks create
        $this->post('/api/media', ['file' => UploadedFile::fake()->image('x.jpg', 10, 10)], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_upload_stores_file_and_returns_record(): void
    {
        Sanctum::actingAs($this->user(), ['media:create']);

        $res = $this->post('/api/media', [
            'file' => UploadedFile::fake()->image('photo.jpg', 12, 12),
        ], ['Accept' => 'application/json'])->assertCreated();

        $res->assertJsonPath('data.original_filename', 'photo.jpg');
        $path = $res->json('data.path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('media', ['original_filename' => 'photo.jpg']);
    }

    public function test_upload_rejects_non_media_file(): void
    {
        Sanctum::actingAs($this->user(), ['media:create']);

        $this->post('/api/media', [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_delete_requires_ability_and_removes(): void
    {
        $media = $this->makeMedia();
        Sanctum::actingAs($this->user(), ['media:delete']);

        $this->deleteJson("/api/media/{$media->id}")->assertNoContent();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=MediaApiTest`
Expected: FAIL — `/api/media` routes not defined.

- [ ] **Step 3: Create the resource**

Create `app/Http/Resources/MediaResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'url' => $this->url(),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'original_filename' => $this->original_filename,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 4: Create the form request**

Create `app/Http/Requests/Api/Media/StoreRequest.php` (mirrors the admin media rule):

```php
<?php

namespace App\Http\Requests\Api\Media;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:media:create
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,gif,mp4,webm',
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Api/MediaController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Media\StoreRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaController extends Controller
{
    public function index(MediaService $service): AnonymousResourceCollection
    {
        return MediaResource::collection($service->paginate(20));
    }

    public function store(StoreRequest $request, MediaService $service): JsonResponse
    {
        $media = $service->upload($request->file('file'), $request->user());

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Media $media, MediaService $service): Response
    {
        $service->delete($media->id);
        return response()->noContent();
    }
}
```

- [ ] **Step 6: Register routes**

In `routes/api.php`, add `use App\Http\Controllers\Api\MediaController;` at the top, and INSIDE the existing `auth:sanctum`+`throttle` group add:

```php
    Route::get('/media', [MediaController::class, 'index'])->middleware('ability:media:read');
    Route::post('/media', [MediaController::class, 'store'])->middleware('ability:media:create');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->middleware('ability:media:delete');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=MediaApiTest`
Expected: PASS (7 tests).

- [ ] **Step 8: Run the full suite**

Run: `./vendor/bin/sail artisan test`
Expected: All pass EXCEPT the pre-existing `Tests\Feature\ExampleTest` (GET / 302). Confirm no regressions (posts/tweets/categories/tags/todos/changelog/P2). Report counts.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Resources/MediaResource.php app/Http/Requests/Api/Media routes/api.php app/Http/Controllers/Api/MediaController.php tests/Feature/Api/MediaApiTest.php
git commit -m "feat: media API list/upload/delete with ability scoping"
```

---

## Self-Review

**Spec coverage (Phase 3 = media):**
- `GET /api/media` (media:read, paginated via `MediaService::paginate`) → Task 1 ✓
- `POST /api/media` (media:create, multipart upload via `MediaService::upload`) → Task 1 ✓
- `DELETE /api/media/{media}` (media:delete, `MediaService::delete`) → Task 1 ✓
- NO `media:update` (files immutable) — only 3 routes, matching the matrix → Task 1 ✓
- `MediaResource` exposes path + url (so agent can reference cover_image_path) → Task 1 ✓
- ability gating, 401/403/422/201/204 → Task 1 tests ✓
- file validation mirrors admin (max 10MB, image/video mimes) → StoreRequest ✓

**Placeholder scan:** No TBD/TODO; full code in every step.

**Type/name consistency:** `MediaService::paginate(20)`/`upload($file,$user)`/`delete($media->id)` used exactly as they exist. `MediaResource` keys match test assertions (`path`, `url`, `original_filename`). Abilities match `config/abilities.php` (`media` = read/create/delete, no update). Multipart upload tested with `$this->post(..., ['Accept'=>'application/json'])` (postJson can't carry files). `Storage::fake('public')` + `config(['media.disk'=>'public'])` in setUp so `MediaService::upload` (which stores on `config('media.disk')`) writes to the fake disk and `Media::url()` resolves.

**Notes for executor:**
- `.env` must have `DB_CONNECTION=pgsql` (testing DB).
- Routes go INSIDE the existing `Route::middleware(['auth:sanctum','throttle:60,1'])->group(...)`.
- The upload test uses `$this->post(...)` (NOT `postJson`) with an `Accept: application/json` header so the fake file is sent as multipart while errors return JSON (422).
- `DELETE /api/media/{media}` uses route-model binding; the controller passes `$media->id` to `MediaService::delete(int $id)`.
