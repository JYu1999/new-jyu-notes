# R2 Media Storage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Centralize all media URL generation behind a `media_url()` helper backed by a configurable media disk, so media can be stored on Cloudflare R2 (served via `media.jyu1999.com`) for Laravel Cloud deployment.

**Architecture:** A new `config/media.php` exposes `media.disk` (env `MEDIA_DISK`, default `public`). A `media_url($path)` helper returns `Storage::disk(config('media.disk'))->url($path)`. Every existing media URL site (`MediaService`, `Media::url()`, the Hugo importer, public Blade views, admin Alpine previews) is switched to this single source. Local dev keeps `MEDIA_DISK=public` so behavior is unchanged; production/import sets `MEDIA_DISK=s3` pointing at R2.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit 12, Laravel Sail (run all artisan/composer via `./vendor/bin/sail`), Flysystem S3 adapter (for R2), Cloudflare R2 + Cloudflare DNS.

---

## File Structure

**Create:**
- `config/media.php` — single config key for the media disk.
- `app/Support/helpers.php` — `media_url()` global helper (composer `autoload.files`).
- `tests/Feature/MediaUrlTest.php` — helper behavior across disks.
- `tests/Feature/MediaServiceTest.php` — service writes to the configured disk.
- `tests/Feature/MediaModelTest.php` — `Media::url()` uses the helper.
- `tests/Feature/ImportMediaRewriteTest.php` — importer bakes the helper URL.
- `tests/Feature/NoHardcodedStorageUrlTest.php` — guard: no `/storage/` literals remain in views.

**Modify:**
- `composer.json` — add `autoload.files`.
- `app/Services/MediaService.php` — 3 hardcoded `'public'` → `config('media.disk')`.
- `app/Models/Media.php` — `url()` → `media_url()`.
- `app/Console/Commands/ImportFromHugo.php` — `buildAssetRewrites()` both branches.
- Public views: `components/post-card.blade.php`, `public/posts/show.blade.php`, `public/pages/show.blade.php`, `components/tweet-card.blade.php`, `admin/categories/index.blade.php`.
- Admin layout + previews: `layouts/admin.blade.php`, `admin/posts/edit.blade.php`, `admin/pages/edit.blade.php`, `admin/categories/index.blade.php`, `resources/js/app.js`.
- `.env.example` — document `MEDIA_DISK` + R2 `AWS_*` keys.

> **Sail note:** All `artisan`/`composer`/`test` commands run through `./vendor/bin/sail`. Ensure the stack is up (`./vendor/bin/sail up -d`) before running tests.

---

### Task 1: Media disk config + `media_url()` helper

**Files:**
- Create: `config/media.php`
- Create: `app/Support/helpers.php`
- Modify: `composer.json` (autoload block, lines 26-32)
- Test: `tests/Feature/MediaUrlTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MediaUrlTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    public function test_media_url_uses_public_disk_by_default(): void
    {
        config(['media.disk' => 'public']);

        $this->assertStringContainsString('/storage/uploads/a.png', media_url('uploads/a.png'));
    }

    public function test_media_url_uses_configured_s3_style_disk(): void
    {
        config([
            'media.disk' => 'r2test',
            'filesystems.disks.r2test' => [
                'driver' => 's3',
                'key' => 'dummy',
                'secret' => 'dummy',
                'region' => 'auto',
                'bucket' => 'dummy-bucket',
                'url' => 'https://media.jyu1999.com',
                'endpoint' => 'https://acct.r2.cloudflarestorage.com',
                'use_path_style_endpoint' => true,
            ],
        ]);

        $this->assertSame('https://media.jyu1999.com/uploads/a.png', media_url('uploads/a.png'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=MediaUrlTest`
Expected: FAIL — `Call to undefined function media_url()`.

- [ ] **Step 3: Create the config file**

Create `config/media.php`:

```php
<?php

return [
    /*
    | The filesystem disk used to store and serve uploaded/imported media.
    | Local dev: "public" (storage/app/public via /storage symlink).
    | Production/import: "s3" (Cloudflare R2, served via media.jyu1999.com).
    */
    'disk' => env('MEDIA_DISK', 'public'),
];
```

- [ ] **Step 4: Create the helper**

Create `app/Support/helpers.php`:

```php
<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('media_url')) {
    /**
     * Public URL for a stored media path, resolved against the configured media disk.
     */
    function media_url(string $path): string
    {
        return Storage::disk(config('media.disk', 'public'))->url($path);
    }
}
```

- [ ] **Step 5: Register the helper in composer autoload**

In `composer.json`, change the `autoload` block (currently lines 26-32) to add a `files` entry:

```json
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        },
        "files": [
            "app/Support/helpers.php"
        ]
    },
```

- [ ] **Step 6: Regenerate the autoloader**

Run: `./vendor/bin/sail composer dump-autoload`
Expected: `Generated optimized autoload files`.

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=MediaUrlTest`
Expected: PASS (2 tests).

- [ ] **Step 8: Commit**

```bash
git add config/media.php app/Support/helpers.php composer.json composer.lock tests/Feature/MediaUrlTest.php
git commit -m "feat: add configurable media disk and media_url() helper"
```

---

### Task 2: `MediaService` writes to the configured disk

**Files:**
- Modify: `app/Services/MediaService.php` (lines 17, 35, 59)
- Test: `tests/Feature/MediaServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MediaServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_stores_on_configured_media_disk(): void
    {
        config(['media.disk' => 'mediafake']);
        Storage::fake('mediafake');

        $service = app(MediaService::class);
        $media = $service->upload(UploadedFile::fake()->image('photo.jpg', 10, 10));

        Storage::disk('mediafake')->assertExists($media->path);
    }

    public function test_register_local_file_stores_on_configured_media_disk(): void
    {
        config(['media.disk' => 'mediafake']);
        Storage::fake('mediafake');

        $source = UploadedFile::fake()->image('legacy.png', 8, 8)->getRealPath();

        $service = app(MediaService::class);
        $media = $service->registerLocalFile($source, 'imports/posts/demo');

        $this->assertNotNull($media);
        Storage::disk('mediafake')->assertExists($media->path);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=MediaServiceTest`
Expected: FAIL — file asserted on `mediafake` but written to `public` (`Unable to find a file at path [...]`).

- [ ] **Step 3: Update `MediaService::upload`**

In `app/Services/MediaService.php`, change line 17:

```php
        $path = $file->store("uploads/{$year}/{$month}", config('media.disk'));
```

- [ ] **Step 4: Update `MediaService::delete`**

Change line 35:

```php
        Storage::disk(config('media.disk'))->delete($media->path);
```

- [ ] **Step 5: Update `MediaService::registerLocalFile`**

Change line 59:

```php
        Storage::disk(config('media.disk'))->put($targetPath, file_get_contents($sourcePath));
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=MediaServiceTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/MediaService.php tests/Feature/MediaServiceTest.php
git commit -m "feat: MediaService reads media disk from config"
```

---

### Task 3: `Media::url()` uses `media_url()`

**Files:**
- Modify: `app/Models/Media.php` (the `url()` method)
- Test: `tests/Feature/MediaModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MediaModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_matches_media_url_helper(): void
    {
        config([
            'media.disk' => 'r2test',
            'filesystems.disks.r2test' => [
                'driver' => 's3',
                'key' => 'dummy',
                'secret' => 'dummy',
                'region' => 'auto',
                'bucket' => 'dummy-bucket',
                'url' => 'https://media.jyu1999.com',
                'endpoint' => 'https://acct.r2.cloudflarestorage.com',
                'use_path_style_endpoint' => true,
            ],
        ]);

        $media = Media::create([
            'path' => 'uploads/2026/06/x.png',
            'mime_type' => 'image/png',
            'size' => 1,
            'original_filename' => 'x.png',
        ]);

        $this->assertSame('https://media.jyu1999.com/uploads/2026/06/x.png', $media->url());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=MediaModelTest`
Expected: FAIL — `url()` uses the hardcoded `public` disk, so the asserted R2 URL does not match.

- [ ] **Step 3: Update `Media::url()`**

In `app/Models/Media.php`, replace the `url()` method body:

```php
    public function url(): string
    {
        return media_url($this->path);
    }
```

The `use Illuminate\Support\Facades\Storage;` import may now be unused — remove it if no other reference remains in the file.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=MediaModelTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Media.php tests/Feature/MediaModelTest.php
git commit -m "feat: Media::url() resolves through media_url() helper"
```

---

### Task 4: Hugo importer bakes the media-disk URL into content

**Files:**
- Modify: `app/Console/Commands/ImportFromHugo.php` (`buildAssetRewrites`, lines 390-405)
- Test: `tests/Feature/ImportMediaRewriteTest.php`

The importer's `buildAssetRewrites()` currently writes `/storage/...` literals into post bodies. Switch both branches (dry-run and real) to `media_url(...)` so imported content points at the configured media disk (R2 in production).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportMediaRewriteTest.php`. It invokes the private `buildAssetRewrites` via reflection against a temp bundle dir containing one image, with the media disk faked:

```php
<?php

namespace Tests\Feature;

use App\Console\Commands\ImportFromHugo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class ImportMediaRewriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewrites_use_media_url_for_configured_disk(): void
    {
        config([
            'media.disk' => 'r2test',
            'filesystems.disks.r2test' => [
                'driver' => 's3',
                'key' => 'dummy',
                'secret' => 'dummy',
                'region' => 'auto',
                'bucket' => 'dummy-bucket',
                'url' => 'https://media.jyu1999.com',
                'endpoint' => 'https://acct.r2.cloudflarestorage.com',
                'use_path_style_endpoint' => true,
            ],
        ]);
        Storage::fake('r2test');

        // Bundle dir with one real image file.
        $bundle = sys_get_temp_dir().'/hugo-bundle-'.uniqid();
        mkdir($bundle, 0777, true);
        $img = imagecreatetruecolor(4, 4);
        imagepng($img, "{$bundle}/pic.png");
        imagedestroy($img);

        $cmd = app(ImportFromHugo::class);
        // The command reads $this->admin inside registerLocalFile via MediaService; seed one.
        $admin = User::factory()->create();
        $ref = new ReflectionClass($cmd);
        $adminProp = $ref->getProperty('admin');
        $adminProp->setAccessible(true);
        $adminProp->setValue($cmd, $admin);

        $method = $ref->getMethod('buildAssetRewrites');
        $method->setAccessible(true);
        /** @var array<string,string> $rewrites */
        $rewrites = $method->invoke($cmd, $bundle, 'imports/posts/demo');

        $this->assertArrayHasKey('pic.png', $rewrites);
        $this->assertStringStartsWith('https://media.jyu1999.com/', $rewrites['pic.png']);

        // cleanup
        @unlink("{$bundle}/pic.png");
        @rmdir($bundle);
    }
}
```

> If `User::factory()` is unavailable, replace it with `User::create(['name' => 'A', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN])`.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=ImportMediaRewriteTest`
Expected: FAIL — rewrite value starts with `/storage/`, not the R2 URL.

- [ ] **Step 3: Update `buildAssetRewrites`**

In `app/Console/Commands/ImportFromHugo.php`, update both branches (lines ~395-401):

```php
            if ($this->option('dry-run')) {
                $rewrites[$filename] = media_url("{$storageSubdir}/{$filename}");
            } else {
                $media = $this->media->registerLocalFile($file, $storageSubdir, $this->admin);
                if ($media) {
                    $rewrites[$filename] = media_url($media->path);
                }
            }
```

> Leave `ingestCoverImage()` returning `$media->path` (a bare relative path) unchanged — cover images are stored as paths and rendered through `media_url()` in Blade (Task 5). Only inline body rewrites become absolute URLs.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=ImportMediaRewriteTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ImportFromHugo.php tests/Feature/ImportMediaRewriteTest.php
git commit -m "feat: importer bakes media_url() into post bodies"
```

---

### Task 5: Public Blade views use `media_url()`

**Files:**
- Modify: `resources/views/components/post-card.blade.php:8`
- Modify: `resources/views/public/posts/show.blade.php:37`
- Modify: `resources/views/public/pages/show.blade.php:10`
- Modify: `resources/views/components/tweet-card.blade.php:59,62,70,73,83,86`
- Modify: `resources/views/admin/categories/index.blade.php:82`
- Test: `tests/Feature/NoHardcodedStorageUrlTest.php`

- [ ] **Step 1: Write the failing guard test**

Create `tests/Feature/NoHardcodedStorageUrlTest.php`. It scans all Blade views and fails if any hardcoded storage URL remains (server-side `asset('storage/...')` or client-side `'/storage/'`):

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class NoHardcodedStorageUrlTest extends TestCase
{
    public function test_no_hardcoded_storage_urls_in_views(): void
    {
        $offenders = [];
        $dir = resource_path('views');
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($rii as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (preg_match("/asset\\(['\"]storage\\//", $contents)
                || str_contains($contents, "'/storage/'")
                || str_contains($contents, '"/storage/"')) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, "Hardcoded storage URLs found in:\n".implode("\n", $offenders));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=NoHardcodedStorageUrlTest`
Expected: FAIL — lists `post-card.blade.php`, `posts/show.blade.php`, `pages/show.blade.php`, `tweet-card.blade.php`, `categories/index.blade.php`, plus the admin Alpine files (fixed in Task 6).

- [ ] **Step 3: Update `post-card.blade.php` line 8**

```blade
            <img src="{{ media_url($post->cover_image_path) }}" alt="" class="w-full h-44 object-cover">
```

- [ ] **Step 4: Update `public/posts/show.blade.php` line 37**

```blade
            <img src="{{ media_url($post->cover_image_path) }}" alt="" class="w-full rounded-lg mb-10 h-auto max-h-[400px] object-cover">
```

- [ ] **Step 5: Update `public/pages/show.blade.php` line 10**

```blade
        <img src="{{ media_url($page->cover_image_path) }}" alt="" class="w-full rounded-lg mb-10 h-auto max-h-[400px] object-cover">
```

> Preserve the existing class list on this line if it differs; only change the `src` attribute.

- [ ] **Step 6: Update `tweet-card.blade.php` (lines 59, 62, 70, 73, 83, 86)**

Replace each `asset('storage/' . $m['path'])` with `media_url($m['path'])`. For example:

```blade
                <img src="{{ media_url($m['path']) }}" alt="{{ $m['alt'] ?? '' }}"
```
```blade
                <video src="{{ media_url($m['path']) }}" controls
```

Apply the same substitution to all six occurrences, keeping each line's surrounding attributes intact.

- [ ] **Step 7: Update `admin/categories/index.blade.php` line 82**

```blade
                    <img src="{{ media_url($cat->cover_image_path) }}" class="w-full h-32 object-cover rounded mb-3">
```

> Lines 24 and 110 in this same file are Alpine `:src="'/storage/' + path"` previews — leave them for Task 6.

- [ ] **Step 8: Run the guard test (will still fail on admin Alpine files)**

Run: `./vendor/bin/sail artisan test --filter=NoHardcodedStorageUrlTest`
Expected: still FAIL, but offenders reduced to only the admin Alpine preview files (`admin/posts/edit.blade.php`, `admin/pages/edit.blade.php`, `admin/categories/index.blade.php`). This confirms Task 5 is complete; Task 6 finishes the guard.

- [ ] **Step 9: Commit**

```bash
git add resources/views/components/post-card.blade.php resources/views/public/posts/show.blade.php resources/views/public/pages/show.blade.php resources/views/components/tweet-card.blade.php resources/views/admin/categories/index.blade.php tests/Feature/NoHardcodedStorageUrlTest.php
git commit -m "feat: public views render media via media_url()"
```

---

### Task 6: Admin Alpine previews use injected media base URL

**Files:**
- Modify: `resources/views/layouts/admin.blade.php` (head, after line 6 `<meta name="csrf-token">`)
- Modify: `resources/js/app.js` (`coverUpload`, lines 82-119)
- Modify: `resources/views/admin/posts/edit.blade.php:102`
- Modify: `resources/views/admin/pages/edit.blade.php:82`
- Modify: `resources/views/admin/categories/index.blade.php:24,110`
- Test: `tests/Feature/NoHardcodedStorageUrlTest.php` (from Task 5)

- [ ] **Step 1: Inject the media base URL into the admin layout head**

In `resources/views/layouts/admin.blade.php`, add a meta tag immediately after the csrf-token meta (line 6):

```blade
    <meta name="media-base" content="{{ rtrim(media_url(''), '/') }}">
```

- [ ] **Step 2: Expose the base URL from `coverUpload`**

In `resources/js/app.js`, update the `coverUpload` factory (starts line 82) to read the base and provide a `previewUrl` getter:

```js
window.coverUpload = function ({ initial }) {
    return {
        path: initial || '',
        uploading: false,
        error: null,
        get mediaBase() {
            return document.querySelector('meta[name=media-base]')?.content ?? '';
        },
        get previewUrl() {
            return this.path ? this.mediaBase + '/' + this.path : '';
        },
        async upload(file) {
            if (!file) return;
            this.error = null;
            this.uploading = true;
            try {
                const fd = new FormData();
                fd.append('file', file);
                const res = await fetch('/admin/media', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                if (!res.ok) {
                    const txt = await res.text();
                    throw new Error('上傳失敗 (' + res.status + '): ' + txt.slice(0, 100));
                }
                const data = await res.json();
                this.path = data.path;
            } catch (e) {
                this.error = e.message || '上傳失敗';
            } finally {
                this.uploading = false;
            }
        },
        clear() {
            this.path = '';
        },
    };
};
```

- [ ] **Step 3: Update `admin/posts/edit.blade.php` line 102**

```blade
                            <img :src="previewUrl" class="rounded max-h-40 w-full object-cover border border-line">
```

- [ ] **Step 4: Update `admin/pages/edit.blade.php` line 82**

```blade
                            <img :src="previewUrl" class="rounded max-h-32 w-full object-cover border border-line">
```

- [ ] **Step 5: Update `admin/categories/index.blade.php` lines 24 and 110**

Line 24:

```blade
                    <img :src="previewUrl" class="rounded max-h-32 w-full object-cover border border-line">
```

Line 110:

```blade
                            <img :src="previewUrl" class="rounded max-h-28 w-full object-cover border border-line">
```

- [ ] **Step 6: Rebuild front-end assets**

Run: `./vendor/bin/sail npm run build`
Expected: Vite build completes without errors.

- [ ] **Step 7: Run the guard test to verify it now passes**

Run: `./vendor/bin/sail artisan test --filter=NoHardcodedStorageUrlTest`
Expected: PASS — no hardcoded storage URLs remain in any view.

- [ ] **Step 8: Commit**

```bash
git add resources/views/layouts/admin.blade.php resources/js/app.js resources/views/admin/posts/edit.blade.php resources/views/admin/pages/edit.blade.php resources/views/admin/categories/index.blade.php
git commit -m "feat: admin previews use injected media base url"
```

---

### Task 7: Document R2 / media env keys in `.env.example`

**Files:**
- Modify: `.env.example` (the AWS block, lines 62-66, and add `MEDIA_DISK`)

- [ ] **Step 1: Update the AWS / media block**

In `.env.example`, replace the AWS block with documented R2 keys and add `MEDIA_DISK`. The existing block is:

```env
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Replace with:

```env
# Media disk: "public" for local dev, "s3" (Cloudflare R2) for production/import.
MEDIA_DISK=public

# Cloudflare R2 (S3-compatible). Leave blank for local dev (MEDIA_DISK=public).
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=
AWS_ENDPOINT=
AWS_URL=
AWS_USE_PATH_STYLE_ENDPOINT=true
```

- [ ] **Step 2: Verify the full test suite passes**

Run: `./vendor/bin/sail artisan test`
Expected: PASS — all tests green (existing + the new media tests).

- [ ] **Step 3: Commit**

```bash
git add .env.example
git commit -m "docs: document MEDIA_DISK and R2 env keys in .env.example"
```

---

## Post-implementation: R2 + migration runbook (manual, not code)

These steps are performed by the operator after the code above is merged. They are documented in the design spec (`docs/superpowers/specs/2026-06-01-r2-media-storage-design.md`) and repeated here for convenience:

1. **Cloudflare R2:** create a bucket, generate an S3 API token (access key + secret), and bind the custom domain `media.jyu1999.com` to the bucket (R2 → Custom Domains; DNS auto-created). Enable public read via the custom domain.
2. **Local import env** (a throwaway `.env` or exported vars — keep secrets out of git):
   ```env
   MEDIA_DISK=s3
   FILESYSTEM_DISK=s3
   AWS_ACCESS_KEY_ID=<R2 token>
   AWS_SECRET_ACCESS_KEY=<R2 secret>
   AWS_DEFAULT_REGION=auto
   AWS_BUCKET=<bucket>
   AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
   AWS_URL=https://media.jyu1999.com
   AWS_USE_PATH_STYLE_ENDPOINT=true
   DB_CONNECTION=pgsql   # remaining DB_* point at the Laravel Cloud Postgres
   ADMIN_EMAIL=jyu@furuke.com
   ADMIN_NAME=JYu
   ADMIN_PASSWORD=<strong password>
   ```
3. Run against the Cloud DB + R2:
   ```bash
   ./vendor/bin/sail artisan migrate --force
   ./vendor/bin/sail artisan db:seed --class=AdminUserSeeder
   ./vendor/bin/sail artisan blog:import-from-hugo
   ```
4. Verify: R2 bucket has files; Cloud DB has rows; post bodies contain `https://media.jyu1999.com/...` image URLs.
5. On Laravel Cloud, set production env `MEDIA_DISK=s3` + the same `AWS_*` (same bucket + domain). `DB_*` is injected by Laravel Cloud.
6. Restore your local `.env` to local-dev values (`MEDIA_DISK=public`, SQLite) afterward.

---

## Self-Review

**Spec coverage:**
- §1 `config/media.php` → Task 1 ✓
- §2 `media_url()` helper + composer autoload → Task 1 ✓
- §3 MediaService (3 sites) → Task 2 ✓; `Media::url()` → Task 3 ✓; importer rewrite (both branches) → Task 4 ✓; public Blade sites → Task 5 ✓; admin Alpine previews → Task 6 ✓
- §4 content bakes absolute URL → Task 4 ✓
- §5/§6 env vars → Task 7 (.env.example) + runbook ✓
- §7 R2/Cloudflare setup → runbook (manual) ✓
- §8 migration runbook → runbook ✓
- Testing strategy (media_url, MediaService, importer) → Tasks 1-4 ✓; the view guard test (Task 5/6) additionally enforces refactor completeness.

**Placeholder scan:** No TBD/TODO; every code step shows full code; the one conditional (`User::factory()` fallback) provides explicit alternative code.

**Type/name consistency:** `media_url(string): string` used identically in Tasks 1, 3, 4, 5, 6 (meta tag). `config('media.disk')` used in Tasks 1, 2. `previewUrl` getter defined in Task 6 Step 2 and referenced in Steps 3-5. `coverUpload` shape preserved (adds `mediaBase`/`previewUrl`, keeps `path`/`upload`/`clear`).

**Note for executor:** Line numbers are from the pre-change file state; if an earlier task shifts lines, match on the quoted code rather than the line number.
```
