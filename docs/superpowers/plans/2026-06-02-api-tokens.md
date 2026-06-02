# API Token Permission System (P2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin mint short-lived, multi-use, scoped API tokens (Laravel Sanctum) in the admin panel, authenticate API requests via `Authorization: Bearer`, and verify a token's identity/abilities through `GET /api/me`.

**Architecture:** Sanctum personal access tokens carry `resource:action` abilities (e.g. `posts:create`) and an `expires_at`. A single source of truth (`config/abilities.php`) defines the ability matrix; an `Abilities` helper flattens/validates it. An `ApiTokenService` mints/revokes tokens with ability validation. `routes/api.php` is guarded by `auth:sanctum` plus Sanctum's `ability` middleware. An admin "API Tokens" page creates (showing plaintext once), lists, and revokes tokens.

**Tech Stack:** Laravel 13, PHP 8.3, Laravel Sanctum, PHPUnit 12, Laravel Sail (run everything via `./vendor/bin/sail`), Postgres (test DB = the `testing` database in the pgsql container — ensure `.env` has `DB_CONNECTION=pgsql`).

> **Scope (P2 only):** auth/authorization foundation + token admin UI + `GET /api/me`. The actual CRUD endpoints for posts/tweets/categories/tags/media are **P3** and are NOT in this plan.

---

## File Structure

**Create:**
- `config/abilities.php` — the resource→actions ability matrix (single source of truth).
- `app/Support/Abilities.php` — flatten matrix to `resource:action` strings + validity check.
- `app/Services/ApiTokenService.php` — mint/revoke tokens with ability validation.
- `app/Http/Controllers/Api/MeController.php` — `GET /api/me`.
- `app/Http/Controllers/Admin/ApiTokenController.php` — admin index/store/destroy.
- `app/Http/Requests/Admin/ApiToken/StoreRequest.php` — validate create-token form.
- `resources/views/admin/tokens/index.blade.php` — list + create form + one-time plaintext.
- `routes/api.php` — created by `install:api`, then edited for `/me` (+ test-only probe route).
- Test files (one per task, listed in tasks).

**Modify:**
- `app/Models/User.php` — add `HasApiTokens` trait.
- `bootstrap/app.php` — register Sanctum `ability`/`abilities` middleware aliases (and `api:` routing, added by `install:api`).
- `routes/web.php` — admin group: token routes.
- `resources/views/layouts/admin.blade.php` — sidebar nav item.
- `routes/console.php` — schedule `sanctum:prune-expired`.

---

### Task 1: Ability matrix config + `Abilities` helper

**Files:**
- Create: `config/abilities.php`
- Create: `app/Support/Abilities.php`
- Test: `tests/Feature/AbilitiesTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AbilitiesTest.php`:

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

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=AbilitiesTest`
Expected: FAIL — `Class "App\Support\Abilities" not found`.

- [ ] **Step 3: Create the config**

Create `config/abilities.php`:

```php
<?php

/*
| Single source of truth for API token abilities. Ability strings are
| "resource:action". Used by the token admin UI (checkbox grid), the
| ApiTokenService validation, and the Agent Skill docs (P4).
*/
return [
    'posts'      => ['read', 'create', 'update', 'delete', 'publish'],
    'tweets'     => ['read', 'create', 'update', 'delete', 'publish'],
    'categories' => ['read', 'create', 'update', 'delete'],
    'tags'       => ['read', 'create', 'update', 'delete'],
    'media'      => ['read', 'create', 'delete'],
];
```

- [ ] **Step 4: Create the helper**

Create `app/Support/Abilities.php`:

```php
<?php

namespace App\Support;

class Abilities
{
    /** The resource => [actions] matrix. */
    public static function matrix(): array
    {
        return config('abilities');
    }

    /** Flattened list of valid "resource:action" ability strings. */
    public static function all(): array
    {
        $out = [];
        foreach (self::matrix() as $resource => $actions) {
            foreach ($actions as $action) {
                $out[] = "{$resource}:{$action}";
            }
        }

        return $out;
    }

    public static function isValid(string $ability): bool
    {
        return in_array($ability, self::all(), true);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=AbilitiesTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add config/abilities.php app/Support/Abilities.php tests/Feature/AbilitiesTest.php
git commit -m "feat: ability matrix config and Abilities helper"
```

---

### Task 2: Install Sanctum, add HasApiTokens, register ability middleware

**Files:**
- Modify: `app/Models/User.php`
- Modify: `bootstrap/app.php`
- Create (by command): `routes/api.php`, Sanctum config + `personal_access_tokens` migration
- Test: `tests/Feature/SanctumInstallTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SanctumInstallTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_token_with_abilities(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'a@b.c',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]);

        $new = $user->createToken('test', ['posts:read']);

        $this->assertNotEmpty($new->plainTextToken);
        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertTrue($new->accessToken->can('posts:read'));
        $this->assertFalse($new->accessToken->can('posts:delete'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=SanctumInstallTest`
Expected: FAIL — `Call to undefined method App\Models\User::createToken()` (HasApiTokens not present).

- [ ] **Step 3: Install the API scaffolding (Sanctum)**

Run: `./vendor/bin/sail artisan install:api --no-interaction`

This installs `laravel/sanctum`, publishes the `personal_access_tokens` migration, creates `routes/api.php` (with a default `/user` route), registers `api:` routing in `bootstrap/app.php`, and runs migrations.

> **Fallback if `install:api` prompts/hangs or fails:** run these instead —
> `./vendor/bin/sail composer require laravel/sanctum` ;
> `./vendor/bin/sail artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` ;
> `./vendor/bin/sail artisan migrate` ;
> create `routes/api.php` with `<?php use Illuminate\Support\Facades\Route;` ;
> and in `bootstrap/app.php` add `api: __DIR__.'/../routes/api.php',` to the `->withRouting(...)` call.

- [ ] **Step 4: Add HasApiTokens to the User model**

In `app/Models/User.php`, add the trait. Add the import near the other `use` statements:

```php
use Laravel\Sanctum\HasApiTokens;
```

And add `HasApiTokens` to the class's `use` traits line (it already uses `HasFactory, Notifiable` — append it), e.g.:

```php
    use HasApiTokens, HasFactory, Notifiable;
```

(Match the existing trait list in the file; just add `HasApiTokens`.)

- [ ] **Step 5: Register Sanctum ability middleware aliases**

In `bootstrap/app.php`, inside the `$middleware->alias([...])` array, add two entries so per-route ability checks work:

```php
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=SanctumInstallTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Models/User.php bootstrap/app.php routes/api.php config/sanctum.php database/migrations composer.json composer.lock tests/Feature/SanctumInstallTest.php
git commit -m "feat: install Sanctum, add HasApiTokens and ability middleware aliases"
```

---

### Task 3: ApiTokenService (mint/revoke with validation)

**Files:**
- Create: `app/Services/ApiTokenService.php`
- Test: `tests/Feature/ApiTokenServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ApiTokenServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'a@b.c',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_create_persists_abilities_and_expiry(): void
    {
        $user = $this->admin();
        $expires = now()->addHours(8);

        $new = app(ApiTokenService::class)->create($user, 'translate-job', ['posts:read', 'media:create'], $expires);

        $this->assertNotEmpty($new->plainTextToken);
        $token = $user->fresh()->tokens()->first();
        $this->assertSame(['posts:read', 'media:create'], $token->abilities);
        $this->assertSame($expires->format('Y-m-d H:i'), $token->expires_at->format('Y-m-d H:i'));
    }

    public function test_create_rejects_invalid_ability(): void
    {
        $user = $this->admin();

        $this->expectException(\InvalidArgumentException::class);
        app(ApiTokenService::class)->create($user, 'bad', ['media:update'], now()->addHour());
    }

    public function test_revoke_deletes_the_token(): void
    {
        $user = $this->admin();
        $new = app(ApiTokenService::class)->create($user, 't', ['posts:read'], now()->addHour());
        $id = $new->accessToken->id;

        app(ApiTokenService::class)->revoke($user, $id);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=ApiTokenServiceTest`
Expected: FAIL — `Class "App\Services\ApiTokenService" not found`.

- [ ] **Step 3: Create the service**

Create `app/Services/ApiTokenService.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Support\Abilities;
use Carbon\CarbonInterface;
use Laravel\Sanctum\NewAccessToken;

class ApiTokenService
{
    /**
     * Mint a personal access token after validating every ability.
     *
     * @param  string[]  $abilities
     * @throws \InvalidArgumentException when an ability is not in the matrix
     */
    public function create(User $user, string $name, array $abilities, ?CarbonInterface $expiresAt = null): NewAccessToken
    {
        foreach ($abilities as $ability) {
            if (! Abilities::isValid($ability)) {
                throw new \InvalidArgumentException("Invalid ability: {$ability}");
            }
        }

        return $user->createToken($name, $abilities, $expiresAt);
    }

    public function revoke(User $user, int $tokenId): void
    {
        $user->tokens()->whereKey($tokenId)->delete();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=ApiTokenServiceTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/ApiTokenService.php tests/Feature/ApiTokenServiceTest.php
git commit -m "feat: ApiTokenService mints and revokes scoped tokens"
```

---

### Task 4: `GET /api/me` verify endpoint

**Files:**
- Create: `app/Http/Controllers/Api/MeController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ApiMeTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ApiMeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiMeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'a@b.c',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_returns_user_and_abilities(): void
    {
        $user = $this->admin();
        $new = $user->createToken('t', ['posts:read', 'media:create'], now()->addHour());

        $this->withToken($new->plainTextToken)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'a@b.c')
            ->assertJsonPath('abilities', ['posts:read', 'media:create']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->admin();
        $new = $user->createToken('t', ['posts:read'], now()->subMinute());

        $this->withToken($new->plainTextToken)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=ApiMeTest`
Expected: FAIL — `/api/me` route not defined (404), so assertions fail.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Api/MeController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email']),
            'abilities' => $token->abilities ?? [],
            'expires_at' => $token->expires_at,
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/api.php`, add (keep any existing `use` lines; add this import and route):

```php
use App\Http\Controllers\Api\MeController;

Route::get('/me', MeController::class)->middleware('auth:sanctum');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=ApiMeTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/MeController.php routes/api.php tests/Feature/ApiMeTest.php
git commit -m "feat: GET /api/me returns identity, abilities and expiry"
```

---

### Task 5: Ability middleware enforcement (test-only probe route)

**Files:**
- Modify: `routes/api.php` (add a probe route registered only under testing)
- Test: `tests/Feature/AbilityMiddlewareTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AbilityMiddlewareTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AbilityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'a@b.c',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_passes_with_required_ability(): void
    {
        Sanctum::actingAs($this->admin(), ['posts:read']);

        $this->getJson('/api/_probe')->assertOk();
    }

    public function test_forbidden_without_required_ability(): void
    {
        Sanctum::actingAs($this->admin(), ['tags:read']);

        $this->getJson('/api/_probe')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=AbilityMiddlewareTest`
Expected: FAIL — `/api/_probe` not defined (404, not 200/403).

- [ ] **Step 3: Add the test-only probe route**

In `routes/api.php`, append (the `environment('testing')` guard keeps it out of production):

```php
if (app()->environment('testing')) {
    Route::get('/_probe', fn () => response()->json(['ok' => true]))
        ->middleware(['auth:sanctum', 'ability:posts:read']);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=AbilityMiddlewareTest`
Expected: PASS (2 tests) — `posts:read` token gets 200, `tags:read` token gets 403.

- [ ] **Step 5: Commit**

```bash
git add routes/api.php tests/Feature/AbilityMiddlewareTest.php
git commit -m "test: verify Sanctum ability middleware gates routes"
```

---

### Task 6: Admin token controller + routes + form request

**Files:**
- Create: `app/Http/Controllers/Admin/ApiTokenController.php`
- Create: `app/Http/Requests/Admin/ApiToken/StoreRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/ApiTokenAdminTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/ApiTokenAdminTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenAdminTest extends TestCase
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

    public function test_admin_can_create_token_and_sees_plaintext_once(): void
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)->post(route('admin.tokens.store'), [
            'name' => 'translate-job',
            'abilities' => ['posts:read', 'media:create'],
            'expires_in' => '8h',
        ]);

        $res->assertRedirect(route('admin.tokens.index'));
        $res->assertSessionHas('newToken');
        $this->assertCount(1, $admin->fresh()->tokens);
    }

    public function test_create_rejects_invalid_ability(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tokens.store'), [
            'name' => 'bad',
            'abilities' => ['media:update'],
            'expires_in' => '1h',
        ])->assertSessionHasErrors('abilities.0');

        $this->assertCount(0, $admin->fresh()->tokens);
    }

    public function test_admin_can_revoke_token(): void
    {
        $admin = $this->admin();
        $new = $admin->createToken('t', ['posts:read'], now()->addHour());

        $this->actingAs($admin)
            ->delete(route('admin.tokens.destroy', $new->accessToken->id))
            ->assertRedirect(route('admin.tokens.index'));

        $this->assertCount(0, $admin->fresh()->tokens);
    }

    public function test_non_admin_cannot_access(): void
    {
        $this->actingAs($this->nonAdmin())
            ->get(route('admin.tokens.index'))
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=ApiTokenAdminTest`
Expected: FAIL — route `admin.tokens.index` not defined (`Route [admin.tokens.index] not defined`).

- [ ] **Step 3: Create the form request**

Create `app/Http/Requests/Admin/ApiToken/StoreRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin\ApiToken;

use App\Support\Abilities;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already behind auth + role:admin
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(Abilities::all())],
            'expires_in' => ['required', Rule::in(['1h', '8h', '24h', '7d', 'custom'])],
            'expires_at' => ['required_if:expires_in,custom', 'nullable', 'date', 'after:now'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Admin/ApiTokenController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApiToken\StoreRequest;
use App\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.tokens.index', [
            'tokens' => $request->user()->tokens()->latest()->get(),
        ]);
    }

    public function store(StoreRequest $request, ApiTokenService $service): RedirectResponse
    {
        $data = $request->validated();

        $expiresAt = match ($data['expires_in']) {
            '1h' => now()->addHour(),
            '8h' => now()->addHours(8),
            '24h' => now()->addDay(),
            '7d' => now()->addWeek(),
            'custom' => Carbon::parse($data['expires_at']),
        };

        $new = $service->create($request->user(), $data['name'], $data['abilities'], $expiresAt);

        return redirect()
            ->route('admin.tokens.index')
            ->with('newToken', $new->plainTextToken)
            ->with('newTokenName', $data['name']);
    }

    public function destroy(Request $request, int $id, ApiTokenService $service): RedirectResponse
    {
        $service->revoke($request->user(), $id);

        return redirect()->route('admin.tokens.index')->with('status', 'Token 已撤銷');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, inside the existing `admin` group (after the `// Media` block, before the closing `});`), add:

```php
        // API Tokens
        Route::get('tokens', [Admin\ApiTokenController::class, 'index'])->name('tokens.index');
        Route::post('tokens', [Admin\ApiTokenController::class, 'store'])->name('tokens.store');
        Route::delete('tokens/{id}', [Admin\ApiTokenController::class, 'destroy'])->name('tokens.destroy');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=ApiTokenAdminTest`
Expected: PASS (4 tests). None of these tests render the `admin.tokens.index` view — they assert redirects (store/destroy), a validation redirect-back (invalid ability), and a `403` from the `role:admin` middleware (non-admin, blocked before the view renders). The index view itself is built and tested in Task 7.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/ApiTokenController.php app/Http/Requests/Admin/ApiToken/StoreRequest.php routes/web.php tests/Feature/Admin/ApiTokenAdminTest.php
git commit -m "feat: admin API token controller, routes and validation"
```

---

### Task 7: Admin token UI (view + sidebar nav)

**Files:**
- Create: `resources/views/admin/tokens/index.blade.php`
- Modify: `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/Admin/ApiTokenViewTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/ApiTokenViewTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenViewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_index_renders_with_ability_checkboxes(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.tokens.index'))
            ->assertOk()
            ->assertSee('posts:create')
            ->assertSee('media:create')
            ->assertSee('name="abilities[]"', false);
    }

    public function test_newly_created_plaintext_is_shown_once(): void
    {
        $admin = $this->admin();

        // First request: create, which flashes the plaintext to the session.
        $this->actingAs($admin)
            ->post(route('admin.tokens.store'), [
                'name' => 't', 'abilities' => ['posts:read'], 'expires_in' => '1h',
            ])
            ->assertRedirect(route('admin.tokens.index'));

        // Follow the redirect: the flashed token is visible this once.
        $token = session('newToken');
        $this->assertNotEmpty($token);
        $this->actingAs($admin)
            ->withSession(['newToken' => $token, 'newTokenName' => 't'])
            ->get(route('admin.tokens.index'))
            ->assertOk()
            ->assertSee($token);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=ApiTokenViewTest`
Expected: FAIL — view `admin.tokens.index` not found.

- [ ] **Step 3: Create the view**

Create `resources/views/admin/tokens/index.blade.php`:

```blade
@extends('layouts.admin')

@section('title', 'API Tokens')

@section('content')
<header class="mb-6">
    <h1 class="font-serif text-2xl font-semibold">API Tokens</h1>
    <p class="text-sm text-ink-3 mt-1">產生給 AI Agent 使用的 API token，可設期限與權限範圍。</p>
</header>

@if(session('newToken'))
    <div class="bg-accent-soft border border-accent rounded-md p-4 mb-6">
        <p class="text-sm font-medium mb-2">「{{ session('newTokenName') }}」的 token（只會顯示這一次，請立即複製）：</p>
        <div class="flex items-center gap-2">
            <code class="flex-1 bg-paper border border-line rounded px-3 py-2 text-xs break-all">{{ session('newToken') }}</code>
            <button type="button"
                onclick="navigator.clipboard.writeText('{{ session('newToken') }}')"
                class="bg-accent text-white px-3 py-2 rounded text-sm">複製</button>
        </div>
    </div>
@endif

@if($errors->any())
    <div class="bg-danger-soft border border-danger rounded-md p-3 mb-6 text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid lg:grid-cols-[1fr_360px] gap-6">
    {{-- Existing tokens --}}
    <div class="space-y-3">
        <h2 class="text-xs text-ink-3 font-mono uppercase tracking-wide">現有 Token</h2>
        @forelse($tokens as $token)
            <div class="bg-card border border-line rounded-md p-4 flex items-start justify-between gap-3">
                <div class="text-sm">
                    <div class="font-medium">{{ $token->name }}</div>
                    <div class="text-ink-3 text-xs mt-1 font-mono break-all">{{ implode(', ', $token->abilities ?? []) }}</div>
                    <div class="text-ink-3 text-xs mt-1">
                        到期：{{ $token->expires_at?->format('Y-m-d H:i') ?? '永不' }}
                        · 最後使用：{{ $token->last_used_at?->diffForHumans() ?? '未使用' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.tokens.destroy', $token->id) }}"
                    onsubmit="return confirm('撤銷這個 token？')">
                    @csrf @method('DELETE')
                    <button class="text-danger hover:underline text-sm">撤銷</button>
                </form>
            </div>
        @empty
            <p class="text-ink-3 text-sm py-6">尚無 token。</p>
        @endforelse
    </div>

    {{-- Create form --}}
    <aside class="bg-card border border-line rounded-md p-4">
        <h2 class="text-xs text-ink-3 font-mono uppercase tracking-wide mb-3">產生新 Token</h2>
        <form method="POST" action="{{ route('admin.tokens.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs text-ink-3 mb-1">名稱</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm focus:border-accent focus:outline-none">
            </div>

            <div>
                <label class="block text-xs text-ink-3 mb-1">到期</label>
                <select name="expires_in" x-data x-on:change="$refs.customWrap.style.display = ($event.target.value === 'custom' ? 'block' : 'none')"
                    class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm">
                    <option value="1h">1 小時</option>
                    <option value="8h" selected>8 小時</option>
                    <option value="24h">24 小時</option>
                    <option value="7d">7 天</option>
                    <option value="custom">自訂…</option>
                </select>
                <div x-ref="customWrap" style="display:none" class="mt-2">
                    <input type="datetime-local" name="expires_at"
                        class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs text-ink-3 mb-2">權限</label>
                <div class="space-y-3">
                    @foreach(\App\Support\Abilities::matrix() as $resource => $actions)
                        <div>
                            <div class="text-xs font-mono uppercase text-ink-2 mb-1">{{ $resource }}</div>
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                @foreach($actions as $action)
                                    <label class="inline-flex items-center gap-1 text-xs cursor-pointer">
                                        <input type="checkbox" name="abilities[]" value="{{ $resource }}:{{ $action }}">
                                        <span>{{ $action }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <button class="w-full bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">
                產生 Token
            </button>
        </form>
    </aside>
</div>
@endsection
```

- [ ] **Step 4: Add the sidebar nav item**

In `resources/views/layouts/admin.blade.php`, inside the `$items` array (after the `media` entry), add:

```php
                        ['route' => 'admin.tokens.index', 'label' => 'API Tokens', 'group' => 'tokens'],
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/sail artisan test --filter="ApiTokenViewTest|ApiTokenAdminTest"`
Expected: PASS (all of Task 6's and Task 7's tests now green).

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/tokens/index.blade.php resources/views/layouts/admin.blade.php tests/Feature/Admin/ApiTokenViewTest.php
git commit -m "feat: admin API token UI with ability grid and one-time secret"
```

---

### Task 8: Schedule pruning of expired tokens

**Files:**
- Modify: `routes/console.php`
- Test: `tests/Feature/PruneExpiredTokensTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PruneExpiredTokensTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_is_scheduled(): void
    {
        $events = app(Schedule::class)->events();
        $found = collect($events)->contains(
            fn ($e) => str_contains($e->command ?? '', 'sanctum:prune-expired')
        );

        $this->assertTrue($found, 'sanctum:prune-expired should be scheduled');
    }

    public function test_prune_deletes_long_expired_tokens(): void
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
        $new = $user->createToken('t', ['posts:read'], now()->subDays(10));
        // Make it clearly older than the prune window.
        DB::table('personal_access_tokens')->where('id', $new->accessToken->id)
            ->update(['expires_at' => now()->subDays(10)]);

        $this->artisan('sanctum:prune-expired --hours=24')->assertExitCode(0);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=PruneExpiredTokensTest`
Expected: FAIL on `test_prune_command_is_scheduled` (not scheduled yet). The deletion test may already pass (Sanctum ships the command).

- [ ] **Step 3: Schedule the command**

In `routes/console.php`, add at the end (ensure the `use` is present):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();
```

(If the file already imports `Schedule`, don't duplicate the `use`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=PruneExpiredTokensTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Run the full suite**

Run: `./vendor/bin/sail artisan test`
Expected: All pass except the known pre-existing `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response` (302 vs 200) — unrelated to this work.

- [ ] **Step 6: Commit**

```bash
git add routes/console.php tests/Feature/PruneExpiredTokensTest.php
git commit -m "feat: schedule daily pruning of expired API tokens"
```

---

## Self-Review

**Spec coverage:**
- Sanctum install + HasApiTokens + personal_access_tokens → Task 2 ✓
- `config/abilities.php` single source + helper → Task 1 ✓
- Ability matrix (publish separate; media no update; categories/tags no publish) → Task 1 config + AbilitiesTest assertions ✓
- ApiTokenService (validate abilities, expiry, revoke) → Task 3 ✓
- routes/api.php + auth:sanctum + ability middleware → Tasks 2 (aliases), 4 (auth), 5 (ability) ✓
- `GET /api/me` (user, abilities, expires_at) → Task 4 ✓
- Admin Tokens UI: index/store/destroy, ability grid, one-time plaintext, sidebar → Tasks 6, 7 ✓
- Expiry presets (1h/8h/24h/7d/custom, default 8h) → Task 6 controller + Task 7 form ✓
- Prune expired via scheduler → Task 8 ✓
- Security (admin-only via role:admin, hash-only storage, one-time plaintext) → Tasks 6/7 + Sanctum default ✓
- Test-only probe route not exposed in production → Task 5 (`environment('testing')` guard) ✓

**Placeholder scan:** No TBD/TODO; every code step has complete code. Task 6 Step 6 explicitly explains the expected partial-failure (missing view) and the option to add a placeholder — not a placeholder in the plan itself.

**Type/name consistency:** `Abilities::all()/matrix()/isValid()` consistent across Tasks 1,3,6. `ApiTokenService::create(User,string,array,?CarbonInterface)`/`revoke(User,int)` consistent across Tasks 3,6. Route names `admin.tokens.index/store/destroy` consistent across Tasks 6,7. Ability strings (`posts:read`, `media:create`, `media:update` invalid) consistent across Tasks 1,3,4,5,6. `newToken`/`newTokenName` session keys consistent across Tasks 6,7. Middleware aliases `ability`/`abilities` defined Task 2, used Task 5.

**Note for executor:** Line numbers are pre-change; match on quoted code, not line numbers. Ensure `.env` has `DB_CONNECTION=pgsql` (+ Sail pgsql host/creds) so the `testing` Postgres database is used — the schema migrations are Postgres-specific and will not build on SQLite.
