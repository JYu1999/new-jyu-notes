# Agent API — Phase 1 (Posts + Tweets) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Token-scoped REST API for posts and tweets (碎念) — CRUD + same-group translation + a separate publish endpoint — reusing the existing services, so an agent can build drafts but only an owner (a token with `*:publish`) can publish.

**Architecture:** Thin `Api\*Controller`s delegate to existing `PostService`/`TweetService`. API `FormRequest`s deliberately OMIT `status`/`published_at` so `validated()` can never publish via update — publishing is a dedicated `*:publish`-gated endpoint. Output goes through new `JsonResource` classes; index endpoints paginate. All routes sit in the existing `auth:sanctum` + `throttle:60,1` group in `routes/api.php`, each with `ability:<resource>:<action>`.

**Tech Stack:** Laravel 13, Sanctum (P2), PHPUnit, Laravel Sail (`./vendor/bin/sail`), Postgres `testing` DB.

> **Scope:** Phase 1 of the Agent API spec (`docs/superpowers/specs/2026-06-02-agent-api-design.md`). Categories+tags (Phase 2) and media (Phase 3) are separate plans. The abilities matrix already contains `posts:*` and `tweets:*` — no matrix change.

---

## File Structure

**Create:**
- `app/Http/Resources/PostResource.php`, `app/Http/Resources/TweetResource.php`
- `app/Http/Requests/Api/Post/StoreRequest.php`, `UpdateRequest.php`
- `app/Http/Requests/Api/Tweet/StoreRequest.php`, `UpdateRequest.php`
- `app/Http/Controllers/Api/PostController.php`, `app/Http/Controllers/Api/TweetController.php`
- Tests: `tests/Feature/Api/PostApiTest.php`, `tests/Feature/Api/TweetApiTest.php`

**Modify:**
- `routes/api.php` — add posts + tweets routes inside the existing `auth:sanctum`+`throttle` group.

**Reuse (no change):** `PostService`, `TweetService` (create/update/softDelete/updateStatus/createTranslation), `Post`/`Tweet` models (STATUS_* constants, tags/categories relations).

---

### Task 1: Posts API — resource, requests, CRUD controller, routes

**Files:**
- Create: `app/Http/Resources/PostResource.php`
- Create: `app/Http/Requests/Api/Post/StoreRequest.php`, `UpdateRequest.php`
- Create: `app/Http/Controllers/Api/PostController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/PostApiTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/PostApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makePost(string $status = Post::STATUS_DRAFT, string $locale = 'zh', string $slug = 'hello'): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create([])->id,
            'locale' => $locale, 'slug' => $slug, 'title' => 'Hello', 'body' => 'Body',
            'status' => $status, 'last_modified_at' => now(),
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/posts')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['posts:create']); // lacks read
        $this->getJson('/api/posts')->assertForbidden();
    }

    public function test_index_lists_all_statuses_paginated(): void
    {
        $this->makePost(Post::STATUS_DRAFT, 'zh', 'draft-one');
        $this->makePost(Post::STATUS_PUBLISHED, 'en', 'pub-one');
        Sanctum::actingAs($this->user(), ['posts:read']);

        $res = $this->getJson('/api/posts')->assertOk();
        $res->assertJsonStructure(['data' => [['id', 'locale', 'slug', 'title', 'status', 'tag_ids']], 'links', 'meta']);
        $this->assertCount(2, $res->json('data')); // includes the draft
    }

    public function test_create_makes_a_draft(): void
    {
        Sanctum::actingAs($this->user(), ['posts:create']);

        $res = $this->postJson('/api/posts', [
            'locale' => 'zh', 'title' => 'New', 'body' => 'Content',
        ])->assertCreated();

        $res->assertJsonPath('data.status', Post::STATUS_DRAFT);
        $this->assertDatabaseHas('posts', ['title' => 'New', 'status' => 'draft']);
    }

    public function test_update_cannot_change_status(): void
    {
        $post = $this->makePost(Post::STATUS_DRAFT);
        Sanctum::actingAs($this->user(), ['posts:update']);

        // Even if a client sends status, it must be ignored (no posts:publish here).
        $this->patchJson("/api/posts/{$post->id}", [
            'title' => 'Edited', 'status' => 'published',
        ])->assertOk();

        $fresh = $post->fresh();
        $this->assertSame('Edited', $fresh->title);
        $this->assertSame(Post::STATUS_DRAFT, $fresh->status); // still draft
    }

    public function test_delete_soft_deletes(): void
    {
        $post = $this->makePost();
        Sanctum::actingAs($this->user(), ['posts:delete']);

        $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=PostApiTest`
Expected: FAIL — `/api/posts` routes not defined.

- [ ] **Step 3: Create the resource**

Create `app/Http/Resources/PostResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_group_id' => $this->post_group_id,
            'locale' => $this->locale,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'cover_image_path' => $this->cover_image_path,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at,
            'last_modified_at' => $this->last_modified_at,
            'tag_ids' => $this->tags->pluck('id')->all(),
            'category_ids' => $this->categories->pluck('id')->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 4: Create the form requests**

Create `app/Http/Requests/Api/Post/StoreRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\Post;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:posts:create
    }

    public function rules(): array
    {
        return [
            'post_group_id' => 'nullable|integer|exists:post_groups,id',
            'locale' => 'required|string|in:zh,en,ja,vi,id',
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', 'not_regex:~[/\\\\?#&\s]~'],
            'excerpt' => 'nullable|string|max:1000',
            'body' => 'required|string',
            'cover_image_path' => 'nullable|string|max:500',
            'is_featured' => 'sometimes|boolean',
            'tag_ids' => 'array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'category_ids' => 'array',
            'category_ids.*' => 'integer|exists:categories,id',
            'categories_order' => 'array',
        ];
    }
}
```

Create `app/Http/Requests/Api/Post/UpdateRequest.php` (note: NO `status`/`published_at`/`locale`; all `sometimes` for partial PATCH):

```php
<?php

namespace App\Http\Requests\Api\Post;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:posts:update
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'not_regex:~[/\\\\?#&\s]~'],
            'excerpt' => 'sometimes|nullable|string|max:1000',
            'body' => 'sometimes|required|string',
            'cover_image_path' => 'sometimes|nullable|string|max:500',
            'is_featured' => 'sometimes|boolean',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'category_ids' => 'sometimes|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'categories_order' => 'sometimes|array',
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Api/PostController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Post\StoreRequest;
use App\Http\Requests\Api\Post\UpdateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Post::query()->with(['tags', 'categories'])->latest();

        if ($request->filled('locale')) {
            $query->where('locale', $request->string('locale'));
        }

        return PostResource::collection($query->paginate(20));
    }

    public function show(Post $post): PostResource
    {
        return new PostResource($post->load(['tags', 'categories']));
    }

    public function store(StoreRequest $request, PostService $service): PostResource
    {
        $post = $service->create($request->validated()); // no status => draft
        $resource = new PostResource($post->load(['tags', 'categories']));

        return $resource;
    }

    public function update(Post $post, UpdateRequest $request, PostService $service): PostResource
    {
        $post = $service->update($post, $request->validated()); // validated() has no status
        return new PostResource($post->load(['tags', 'categories']));
    }

    public function destroy(Post $post, PostService $service): Response
    {
        $service->softDelete($post);
        return response()->noContent();
    }
}
```

> `store` returns a `PostResource`; Laravel sends 200 by default. The test asserts 201, so set the status in the route layer is awkward — instead, return the resource with an explicit response. Adjust `store` to:
> ```php
> public function store(StoreRequest $request, PostService $service): \Illuminate\Http\JsonResponse
> {
>     $post = $service->create($request->validated());
>     return (new PostResource($post->load(['tags', 'categories'])))
>         ->response()
>         ->setStatusCode(201);
> }
> ```
> Use this 201 form for `store` (replacing the `PostResource` return above).

- [ ] **Step 6: Register routes**

In `routes/api.php`, add `use App\Http\Controllers\Api\PostController;` at the top, and inside the existing `Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () { ... })` block (alongside `/me` and `/todos`), add:

```php
    Route::get('/posts', [PostController::class, 'index'])->middleware('ability:posts:read');
    Route::post('/posts', [PostController::class, 'store'])->middleware('ability:posts:create');
    Route::get('/posts/{post}', [PostController::class, 'show'])->middleware('ability:posts:read');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->middleware('ability:posts:update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->middleware('ability:posts:delete');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=PostApiTest`
Expected: PASS (6 tests). In particular `test_update_cannot_change_status` passes because `UpdateRequest` has no `status` key, so `$request->validated()` never contains it and `PostService::update` leaves status untouched.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Resources/PostResource.php app/Http/Requests/Api/Post routes/api.php app/Http/Controllers/Api/PostController.php tests/Feature/Api/PostApiTest.php
git commit -m "feat: posts API CRUD with ability scoping and resource output"
```

---

### Task 2: Posts API — translations + publish endpoints

**Files:**
- Modify: `app/Http/Controllers/Api/PostController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/PostPublishTranslationTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/PostPublishTranslationTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostPublishTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makePost(): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create([])->id,
            'locale' => 'zh', 'slug' => 'hello', 'title' => 'Hello', 'body' => 'Body',
            'status' => Post::STATUS_DRAFT, 'last_modified_at' => now(),
        ]);
    }

    public function test_publish_forbidden_without_publish_ability(): void
    {
        $post = $this->makePost();
        Sanctum::actingAs($this->user(), ['posts:update']); // not publish

        $this->postJson("/api/posts/{$post->id}/publish")->assertForbidden();
        $this->assertSame(Post::STATUS_DRAFT, $post->fresh()->status);
    }

    public function test_publish_with_ability_sets_published(): void
    {
        $post = $this->makePost();
        Sanctum::actingAs($this->user(), ['posts:publish']);

        $this->postJson("/api/posts/{$post->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', Post::STATUS_PUBLISHED);

        $fresh = $post->fresh();
        $this->assertSame(Post::STATUS_PUBLISHED, $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_translation_creates_same_group_draft(): void
    {
        $post = $this->makePost();
        Sanctum::actingAs($this->user(), ['posts:create']);

        $res = $this->postJson("/api/posts/{$post->id}/translations", ['locale' => 'en'])
            ->assertCreated();

        $res->assertJsonPath('data.locale', 'en');
        $res->assertJsonPath('data.post_group_id', $post->post_group_id);
        $res->assertJsonPath('data.status', Post::STATUS_DRAFT);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=PostPublishTranslationTest`
Expected: FAIL — the `/publish` and `/translations` routes don't exist (404/405).

- [ ] **Step 3: Add controller methods**

In `app/Http/Controllers/Api/PostController.php`, add these two methods (the `Request` import already exists):

```php
    public function storeTranslation(Post $post, Request $request, PostService $service): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['locale' => 'required|string|in:zh,en,ja,vi,id']);
        $translation = $service->createTranslation($post, $data['locale']);

        return (new PostResource($translation->load(['tags', 'categories'])))
            ->response()
            ->setStatusCode(201);
    }

    public function publish(Post $post, PostService $service): PostResource
    {
        $service->updateStatus($post, Post::STATUS_PUBLISHED);
        return new PostResource($post->fresh()->load(['tags', 'categories']));
    }
```

- [ ] **Step 4: Register routes**

In `routes/api.php`, inside the same authenticated group, add (after the posts CRUD routes):

```php
    Route::post('/posts/{post}/translations', [PostController::class, 'storeTranslation'])->middleware('ability:posts:create');
    Route::post('/posts/{post}/publish', [PostController::class, 'publish'])->middleware('ability:posts:publish');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=PostPublishTranslationTest`
Expected: PASS (3 tests) — publish gated by `posts:publish` (403 with only `posts:update`), translation creates an `en` draft in the same group.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/PostController.php routes/api.php tests/Feature/Api/PostPublishTranslationTest.php
git commit -m "feat: posts API translation and publish endpoints (publish ability-gated)"
```

---

### Task 3: Tweets API — resource, requests, CRUD controller, routes

**Files:**
- Create: `app/Http/Resources/TweetResource.php`
- Create: `app/Http/Requests/Api/Tweet/StoreRequest.php`, `UpdateRequest.php`
- Create: `app/Http/Controllers/Api/TweetController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/TweetApiTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/TweetApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TweetApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeTweet(string $status = Tweet::STATUS_DRAFT, string $locale = 'zh'): Tweet
    {
        return Tweet::create([
            'tweet_group_id' => TweetGroup::create([])->id,
            'locale' => $locale, 'body' => 'A note', 'status' => $status,
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/tweets')->assertUnauthorized();
    }

    public function test_read_requires_ability(): void
    {
        Sanctum::actingAs($this->user(), ['tweets:create']);
        $this->getJson('/api/tweets')->assertForbidden();
    }

    public function test_index_paginated_all_statuses(): void
    {
        $this->makeTweet(Tweet::STATUS_DRAFT);
        $this->makeTweet(Tweet::STATUS_PUBLISHED, 'en');
        Sanctum::actingAs($this->user(), ['tweets:read']);

        $res = $this->getJson('/api/tweets')->assertOk();
        $res->assertJsonStructure(['data' => [['id', 'locale', 'body', 'status', 'media', 'tag_ids']], 'links', 'meta']);
        $this->assertCount(2, $res->json('data'));
    }

    public function test_create_makes_a_draft(): void
    {
        Sanctum::actingAs($this->user(), ['tweets:create']);

        $this->postJson('/api/tweets', ['locale' => 'zh', 'body' => 'Hello note'])
            ->assertCreated()
            ->assertJsonPath('data.status', Tweet::STATUS_DRAFT);

        $this->assertDatabaseHas('tweets', ['body' => 'Hello note', 'status' => 'draft']);
    }

    public function test_update_cannot_change_status(): void
    {
        $tweet = $this->makeTweet(Tweet::STATUS_DRAFT);
        Sanctum::actingAs($this->user(), ['tweets:update']);

        $this->patchJson("/api/tweets/{$tweet->id}", ['body' => 'Edited', 'status' => 'published'])
            ->assertOk();

        $fresh = $tweet->fresh();
        $this->assertSame('Edited', $fresh->body);
        $this->assertSame(Tweet::STATUS_DRAFT, $fresh->status);
    }

    public function test_delete_soft_deletes(): void
    {
        $tweet = $this->makeTweet();
        Sanctum::actingAs($this->user(), ['tweets:delete']);

        $this->deleteJson("/api/tweets/{$tweet->id}")->assertNoContent();
        $this->assertSoftDeleted('tweets', ['id' => $tweet->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=TweetApiTest`
Expected: FAIL — `/api/tweets` routes not defined.

- [ ] **Step 3: Create the resource**

Create `app/Http/Resources/TweetResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TweetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tweet_group_id' => $this->tweet_group_id,
            'locale' => $this->locale,
            'body' => $this->body,
            'media' => $this->media ?? [],
            'status' => $this->status,
            'published_at' => $this->published_at,
            'tag_ids' => $this->tags->pluck('id')->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 4: Create the form requests**

Create `app/Http/Requests/Api/Tweet/StoreRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\Tweet;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:tweets:create
    }

    public function rules(): array
    {
        return [
            'tweet_group_id' => 'nullable|integer|exists:tweet_groups,id',
            'locale' => 'required|string|in:zh,en,ja,vi,id',
            'body' => 'required|string|max:2000',
            'media' => 'nullable|array|max:4',
            'media.*.path' => 'required_with:media|string|max:500',
            'media.*.type' => 'required_with:media|in:image,video',
            'media.*.alt' => 'nullable|string|max:200',
            'tag_ids' => 'array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];
    }
}
```

Create `app/Http/Requests/Api/Tweet/UpdateRequest.php` (NO `status`/`published_at`/`locale`; partial):

```php
<?php

namespace App\Http\Requests\Api\Tweet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:tweets:update
    }

    public function rules(): array
    {
        return [
            'body' => 'sometimes|required|string|max:2000',
            'media' => 'sometimes|nullable|array|max:4',
            'media.*.path' => 'required_with:media|string|max:500',
            'media.*.type' => 'required_with:media|in:image,video',
            'media.*.alt' => 'nullable|string|max:200',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Api/TweetController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tweet\StoreRequest;
use App\Http\Requests\Api\Tweet\UpdateRequest;
use App\Http\Resources\TweetResource;
use App\Models\Tweet;
use App\Services\TweetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TweetController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Tweet::query()->with(['tags'])->latest();

        if ($request->filled('locale')) {
            $query->where('locale', $request->string('locale'));
        }

        return TweetResource::collection($query->paginate(20));
    }

    public function show(Tweet $tweet): TweetResource
    {
        return new TweetResource($tweet->load(['tags']));
    }

    public function store(StoreRequest $request, TweetService $service): JsonResponse
    {
        $tweet = $service->create($request->validated()); // no status => draft

        return (new TweetResource($tweet->load(['tags'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Tweet $tweet, UpdateRequest $request, TweetService $service): TweetResource
    {
        $tweet = $service->update($tweet, $request->validated()); // validated() has no status
        return new TweetResource($tweet->load(['tags']));
    }

    public function destroy(Tweet $tweet, TweetService $service): Response
    {
        $service->softDelete($tweet);
        return response()->noContent();
    }
}
```

- [ ] **Step 6: Register routes**

In `routes/api.php`, add `use App\Http\Controllers\Api\TweetController;` at the top, and inside the authenticated group add:

```php
    Route::get('/tweets', [TweetController::class, 'index'])->middleware('ability:tweets:read');
    Route::post('/tweets', [TweetController::class, 'store'])->middleware('ability:tweets:create');
    Route::get('/tweets/{tweet}', [TweetController::class, 'show'])->middleware('ability:tweets:read');
    Route::patch('/tweets/{tweet}', [TweetController::class, 'update'])->middleware('ability:tweets:update');
    Route::delete('/tweets/{tweet}', [TweetController::class, 'destroy'])->middleware('ability:tweets:delete');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=TweetApiTest`
Expected: PASS (6 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Resources/TweetResource.php app/Http/Requests/Api/Tweet routes/api.php app/Http/Controllers/Api/TweetController.php tests/Feature/Api/TweetApiTest.php
git commit -m "feat: tweets API CRUD with ability scoping and resource output"
```

---

### Task 4: Tweets API — translations + publish endpoints

**Files:**
- Modify: `app/Http/Controllers/Api/TweetController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/TweetPublishTranslationTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/TweetPublishTranslationTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TweetPublishTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeTweet(): Tweet
    {
        return Tweet::create([
            'tweet_group_id' => TweetGroup::create([])->id,
            'locale' => 'zh', 'body' => 'A note', 'status' => Tweet::STATUS_DRAFT,
        ]);
    }

    public function test_publish_forbidden_without_publish_ability(): void
    {
        $tweet = $this->makeTweet();
        Sanctum::actingAs($this->user(), ['tweets:update']);

        $this->postJson("/api/tweets/{$tweet->id}/publish")->assertForbidden();
        $this->assertSame(Tweet::STATUS_DRAFT, $tweet->fresh()->status);
    }

    public function test_publish_with_ability_sets_published(): void
    {
        $tweet = $this->makeTweet();
        Sanctum::actingAs($this->user(), ['tweets:publish']);

        $this->postJson("/api/tweets/{$tweet->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', Tweet::STATUS_PUBLISHED);

        $this->assertNotNull($tweet->fresh()->published_at);
    }

    public function test_translation_creates_same_group_draft(): void
    {
        $tweet = $this->makeTweet();
        Sanctum::actingAs($this->user(), ['tweets:create']);

        $this->postJson("/api/tweets/{$tweet->id}/translations", ['locale' => 'en'])
            ->assertCreated()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.tweet_group_id', $tweet->tweet_group_id)
            ->assertJsonPath('data.status', Tweet::STATUS_DRAFT);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=TweetPublishTranslationTest`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Add controller methods**

In `app/Http/Controllers/Api/TweetController.php`, add:

```php
    public function storeTranslation(Tweet $tweet, Request $request, TweetService $service): JsonResponse
    {
        $data = $request->validate(['locale' => 'required|string|in:zh,en,ja,vi,id']);
        $translation = $service->createTranslation($tweet, $data['locale']);

        return (new TweetResource($translation->load(['tags'])))
            ->response()
            ->setStatusCode(201);
    }

    public function publish(Tweet $tweet, TweetService $service): TweetResource
    {
        $service->updateStatus($tweet, Tweet::STATUS_PUBLISHED);
        return new TweetResource($tweet->fresh()->load(['tags']));
    }
```

- [ ] **Step 4: Register routes**

In `routes/api.php`, inside the authenticated group, add:

```php
    Route::post('/tweets/{tweet}/translations', [TweetController::class, 'storeTranslation'])->middleware('ability:tweets:create');
    Route::post('/tweets/{tweet}/publish', [TweetController::class, 'publish'])->middleware('ability:tweets:publish');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=TweetPublishTranslationTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Run the full suite**

Run: `./vendor/bin/sail artisan test`
Expected: All pass EXCEPT the pre-existing `Tests\Feature\ExampleTest` (GET / 302). Confirm no regressions (P2 token tests, todos, changelog all green). Report counts.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/TweetController.php routes/api.php tests/Feature/Api/TweetPublishTranslationTest.php
git commit -m "feat: tweets API translation and publish endpoints (publish ability-gated)"
```

---

## Self-Review

**Spec coverage (Phase 1 = posts + tweets):**
- posts CRUD with ability gating + pagination + read-all-statuses → Task 1 ✓
- posts: update cannot change status (publish boundary) → Task 1 `test_update_cannot_change_status` (UpdateRequest omits status) ✓
- posts: soft delete → Task 1 ✓
- posts: translations (same-group draft) + publish (ability-gated) → Task 2 ✓
- tweets CRUD + pagination + read-all → Task 3 ✓
- tweets: update cannot change status → Task 3 ✓
- tweets: soft delete → Task 3 ✓
- tweets: translations + publish → Task 4 ✓
- API Resource layer → PostResource/TweetResource (Tasks 1, 3) ✓
- ability strings match matrix (`posts:*`, `tweets:*`) → Tasks 1-4 routes ✓
- partial PATCH via `sometimes` → Tasks 1, 3 UpdateRequests ✓
- 201/200/204 status codes, `{data}` envelope → controllers ✓
(Categories/tags/media are Phase 2/3 — out of scope here.)

**Placeholder scan:** No TBD/TODO. The Task 1 Step 5 note explicitly replaces the `store` body with the 201 form — full code given, not a placeholder.

**Type/name consistency:** `PostResource`/`TweetResource` keys consistent with test `assertJsonStructure`/`assertJsonPath` (`tag_ids`, `status`, `locale`, `post_group_id`/`tweet_group_id`, `media`). Service methods used exactly as they exist: `create`/`update`/`softDelete`/`updateStatus(model, STATUS_PUBLISHED)`/`createTranslation(model, locale)`. Route names/abilities consistent (`ability:posts:create` for translations — same as spec). `store` returns 201 via `->response()->setStatusCode(201)` in both controllers; `update` returns resource (200); `destroy` returns `noContent()` (204).

**Notes for executor:**
- Line numbers are pre-change; match on quoted code. `.env` must have `DB_CONNECTION=pgsql` (testing DB).
- All new routes go INSIDE the existing `Route::middleware(['auth:sanctum','throttle:60,1'])->group(...)` in `routes/api.php` — do not create a second group; add imports at the top of the file.
- `PostService::create` defaults status to draft when `$data` has no `status`; the API StoreRequest intentionally omits `status`, which is what enforces "agents create drafts only."
