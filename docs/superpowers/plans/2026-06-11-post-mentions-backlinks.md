# 文章互連:`@` 提及 + Backlink Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 讓作者在後台內文用 `@關鍵字` 即時搜尋並插入文章連結,且被提及的文章在公開頁顯示反向 backlink。

**Architecture:** 新增 `post_references`(source→target)邊表;一個純函式 `PostReferenceExtractor` 從 body 抽取 `/{locale}/posts/{slug}` 內部連結,`PostService` 在存檔時同步邊表;Admin 新增 JSON 搜尋端點供前端 `@` 下拉使用;公開頁查 backlink 以卡片清單呈現;一支 artisan 指令對既有文章做一次性 backfill。

**Tech Stack:** Laravel 11 (PHP 8.x)、PostgreSQL、Blade、Alpine.js、Vite、Tailwind。測試用 `./vendor/bin/sail artisan test`(pgsql 測試 DB)。

---

## File Structure

- **Create** `database/migrations/2026_06_11_000001_create_post_references_table.php` — 邊表 schema。
- **Create** `app/Support/PostReferenceExtractor.php` — 從 body 抽取內部文章連結(純函式,易測)。
- **Modify** `app/Models/Post.php` — 新增 `outgoingReferences()` / `backlinks()` 關聯。
- **Modify** `app/Services/PostService.php` — 新增 `syncReferences()`,並於 `create()` / `update()` 呼叫。
- **Modify** `app/Http/Controllers/Admin/PostController.php` — 新增 `search()` JSON 端點。
- **Modify** `routes/web.php` — 新增 `admin.posts.search` 路由。
- **Modify** `resources/js/app.js` — `markdownMediaInsert` 併入 `mentionBehavior`(`@` 偵測 + 搜尋 + 插入)。
- **Modify** `resources/views/admin/posts/edit.blade.php` — textarea 事件綁定 + 下拉面板;`x-data` 傳入 locale/postId。
- **Modify** `app/Http/Controllers/Public/PostController.php` — `show()` 載入 backlinks。
- **Modify** `resources/views/public/posts/show.blade.php` — 「被以下文章提及」區塊。
- **Modify** `lang/{zh,en,ja,vi,id}/public.php` — 新增 `mentioned_in` 字串。
- **Create** `app/Console/Commands/BackfillPostReferences.php` — `posts:backfill-references` 指令。
- **Create** 測試:`tests/Unit/PostReferenceExtractorTest.php`、`tests/Feature/PostReferenceSyncTest.php`、`tests/Feature/Public/PostBacklinkDisplayTest.php`、`tests/Feature/Admin/PostSearchTest.php`、`tests/Feature/BackfillPostReferencesTest.php`。

> 註:本專案無 PostFactory,測試以 `app(PostService::class)->create([...])` 或 `Post::create([...])` 建立資料(比照 `tests/Feature/PostModifiedTimestampTest.php`)。

---

## Task 1: 邊表 migration

**Files:**
- Create: `database/migrations/2026_06_11_000001_create_post_references_table.php`

- [ ] **Step 1: 寫 migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('target_post_id')->constrained('posts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['source_post_id', 'target_post_id']);
            $table->index('target_post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_references');
    }
};
```

- [ ] **Step 2: 跑 migration 驗證**

Run: `./vendor/bin/sail artisan migrate`
Expected: 顯示 `2026_06_11_000001_create_post_references_table .... DONE`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_11_000001_create_post_references_table.php
git commit -m "feat: post_references table for inter-post links"
```

---

## Task 2: PostReferenceExtractor(抽取器,純函式 TDD)

**Files:**
- Create: `app/Support/PostReferenceExtractor.php`
- Test: `tests/Unit/PostReferenceExtractorTest.php`

- [ ] **Step 1: 寫失敗測試**

```php
<?php

namespace Tests\Unit;

use App\Support\PostReferenceExtractor;
use PHPUnit\Framework\TestCase;

class PostReferenceExtractorTest extends TestCase
{
    private function extract(string $body): array
    {
        return (new PostReferenceExtractor())->extract($body);
    }

    public function test_matches_markdown_link(): void
    {
        $out = $this->extract('參考 [這篇](/zh/posts/unexpected-risks) 很讚');
        $this->assertSame([['locale' => 'zh', 'slug' => 'unexpected-risks']], $out);
    }

    public function test_matches_html_href_and_trailing_slash(): void
    {
        $out = $this->extract('<a href="/en/posts/two-years-work-reflection/">x</a>');
        $this->assertSame([['locale' => 'en', 'slug' => 'two-years-work-reflection']], $out);
    }

    public function test_matches_absolute_url_path_only(): void
    {
        $out = $this->extract('see https://jyu1999.com/ja/posts/mcp-first-experience here');
        $this->assertSame([['locale' => 'ja', 'slug' => 'mcp-first-experience']], $out);
    }

    public function test_ignores_storage_image_paths_and_junk(): void
    {
        $body = '![x](/storage/imports/posts/foo/image.png) and /posts/{{ and /posts/bar/1.JPG';
        $this->assertSame([], $this->extract($body));
    }

    public function test_dedupes_repeated_links(): void
    {
        $body = '[a](/zh/posts/foo) [b](/zh/posts/foo)';
        $this->assertSame([['locale' => 'zh', 'slug' => 'foo']], $this->extract($body));
    }
}
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `./vendor/bin/sail artisan test --filter=PostReferenceExtractorTest`
Expected: FAIL,`Class "App\Support\PostReferenceExtractor" not found`

- [ ] **Step 3: 實作抽取器**

```php
<?php

namespace App\Support;

class PostReferenceExtractor
{
    /**
     * 從 body 抽取內部文章連結,回傳去重後的 (locale, slug) 陣列。
     *
     * 比對 /{locale}/posts/{slug}:要求 locale 前綴 + 單段 slug + 可選結尾斜線。
     * 因此自動排除 /storage/imports/posts/.../image.png 與 /posts/{{ 這類非文章連結。
     *
     * @return array<int, array{locale: string, slug: string}>
     */
    public function extract(string $body): array
    {
        $pattern = '#/(zh|en|ja|vi|id)/posts/([A-Za-z0-9_-]+)/?#';

        if (! preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $result = [];
        $seen = [];
        foreach ($matches as $m) {
            $key = $m[1].'/'.$m[2];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = ['locale' => $m[1], 'slug' => $m[2]];
        }

        return $result;
    }
}
```

- [ ] **Step 4: 跑測試確認通過**

Run: `./vendor/bin/sail artisan test --filter=PostReferenceExtractorTest`
Expected: PASS(5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/PostReferenceExtractor.php tests/Unit/PostReferenceExtractorTest.php
git commit -m "feat: PostReferenceExtractor parses internal post links"
```

---

## Task 3: Post 關聯 + PostService 存檔同步

**Files:**
- Modify: `app/Models/Post.php`
- Modify: `app/Services/PostService.php`
- Test: `tests/Feature/PostReferenceSyncTest.php`

- [ ] **Step 1: 寫失敗測試**

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostReferenceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PostService
    {
        return app(PostService::class);
    }

    private function makeTarget(string $slug = 'target-post'): Post
    {
        return $this->service()->create([
            'locale' => 'zh',
            'slug' => $slug,
            'title' => 'Target',
            'body' => 'Target body.',
            'status' => Post::STATUS_PUBLISHED,
        ]);
    }

    public function test_creating_post_with_internal_link_records_reference(): void
    {
        $target = $this->makeTarget();

        $source = $this->service()->create([
            'locale' => 'zh',
            'title' => 'Source',
            'body' => "看這篇 [連結](/zh/posts/{$target->slug}).",
            'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->assertTrue($source->outgoingReferences->contains($target->id));
        $this->assertTrue($target->fresh()->backlinks->contains($source->id));
    }

    public function test_removing_link_on_update_removes_reference(): void
    {
        $target = $this->makeTarget();
        $source = $this->service()->create([
            'locale' => 'zh',
            'title' => 'Source',
            'body' => "[x](/zh/posts/{$target->slug})",
            'status' => Post::STATUS_PUBLISHED,
        ]);
        $this->assertCount(1, $source->outgoingReferences);

        $this->service()->update($source, ['body' => '已經沒有連結了。']);

        $this->assertCount(0, $source->fresh()->outgoingReferences);
    }

    public function test_self_link_is_ignored(): void
    {
        $post = $this->service()->create([
            'locale' => 'zh',
            'slug' => 'me',
            'title' => 'Me',
            'body' => '指向自己 [self](/zh/posts/me)',
            'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->assertCount(0, $post->fresh()->outgoingReferences);
    }

    public function test_unresolvable_link_is_ignored(): void
    {
        $source = $this->service()->create([
            'locale' => 'zh',
            'title' => 'Source',
            'body' => '[ghost](/zh/posts/does-not-exist)',
            'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->assertCount(0, $source->outgoingReferences);
    }
}
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `./vendor/bin/sail artisan test --filter=PostReferenceSyncTest`
Expected: FAIL,`Call to undefined method ... outgoingReferences()`

- [ ] **Step 3: 加 Post 關聯**

在 `app/Models/Post.php` 的 `// ===== Relationships =====` 區塊內(`categories()` 之後)新增:

```php
    public function outgoingReferences(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class, 'post_references', 'source_post_id', 'target_post_id'
        )->withTimestamps();
    }

    public function backlinks(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class, 'post_references', 'target_post_id', 'source_post_id'
        )->withTimestamps();
    }
```

(`BelongsToMany` 已在檔案頂部 `use` 進來,無需新增 import。)

- [ ] **Step 4: 加 PostService::syncReferences 並在 create/update 呼叫**

在 `app/Services/PostService.php` 頂部 `use` 區塊新增:

```php
use App\Support\PostReferenceExtractor;
```

在 class 內新增方法:

```php
    /**
     * 解析 body 內部文章連結,覆寫此文章的 outgoing references。
     */
    public function syncReferences(Post $post): void
    {
        $pairs = (new PostReferenceExtractor())->extract((string) $post->body);

        $targetIds = [];
        foreach ($pairs as $pair) {
            $target = Post::query()
                ->where('locale', $pair['locale'])
                ->where('slug', $pair['slug'])
                ->first();

            if ($target && $target->id !== $post->id) {
                $targetIds[$target->id] = true;
            }
        }

        $post->outgoingReferences()->sync(array_keys($targetIds));
    }
```

在 `create()` 內,`return $post->fresh(...)` 之前加上:

```php
            $this->syncReferences($post);
```

(位置:在 `syncCategoriesAcrossGroup(...)` 區塊之後、`return` 之前。)

在 `update()` 內,同樣於 tag/category sync 之後、`return $post->fresh(...)` 之前加上:

```php
            $this->syncReferences($post);
```

- [ ] **Step 5: 跑測試確認通過**

Run: `./vendor/bin/sail artisan test --filter=PostReferenceSyncTest`
Expected: PASS(4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Models/Post.php app/Services/PostService.php tests/Feature/PostReferenceSyncTest.php
git commit -m "feat: sync post_references on post save"
```

---

## Task 4: Admin 搜尋端點(`@` 用)

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/PostController.php`
- Test: `tests/Feature/Admin/PostSearchTest.php`

- [ ] **Step 1: 寫失敗測試**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePost(array $attrs): Post
    {
        return app(\App\Services\PostService::class)->create(array_merge([
            'locale' => 'zh',
            'title' => 'Untitled',
            'body' => 'b',
            'status' => Post::STATUS_PUBLISHED,
        ], $attrs));
    }

    public function test_search_matches_title_and_returns_url(): void
    {
        $this->makePost(['title' => 'Cloudflare R2 圖床', 'slug' => 'r2-images']);

        $res = $this->actingAs($this->admin())
            ->getJson('/admin/posts/search?q=R2&locale=zh');

        $res->assertOk()
            ->assertJsonFragment(['slug' => 'r2-images', 'url' => '/zh/posts/r2-images']);
    }

    public function test_search_excludes_drafts_and_other_locales_and_self(): void
    {
        $self = $this->makePost(['title' => 'Self R2', 'slug' => 'self']);
        $this->makePost(['title' => 'Draft R2', 'slug' => 'draft', 'status' => Post::STATUS_DRAFT]);
        $this->makePost(['title' => 'EN R2', 'slug' => 'en-r2', 'locale' => 'en']);

        $res = $this->actingAs($this->admin())
            ->getJson("/admin/posts/search?q=R2&locale=zh&exclude={$self->id}");

        $res->assertOk();
        $data = $res->json();
        $slugs = array_column($data, 'slug');
        $this->assertNotContains('draft', $slugs);
        $this->assertNotContains('en-r2', $slugs);
        $this->assertNotContains('self', $slugs);
    }

    public function test_empty_query_returns_empty_array(): void
    {
        $res = $this->actingAs($this->admin())->getJson('/admin/posts/search?q=');
        $res->assertOk()->assertExactJson([]);
    }
}
```

> 註:`User::factory()->create(['role' => 'admin'])` 沿用既有 admin 測試慣例(見 `tests/Feature/Admin/TodoAdminTest.php`)。若該專案 admin 角色欄位不同,比照既有測試調整。

- [ ] **Step 2: 跑測試確認失敗**

Run: `./vendor/bin/sail artisan test --filter=PostSearchTest`
Expected: FAIL(404,路由不存在)

- [ ] **Step 3: 加路由**

在 `routes/web.php` admin 群組,`posts.create` 路由之後加上:

```php
        Route::get('posts/search', [Admin\PostController::class, 'search'])->name('posts.search');
```

- [ ] **Step 4: 加 controller 方法**

在 `app/Http/Controllers/Admin/PostController.php` 頂部 `use` 區塊新增:

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
```

新增方法:

```php
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $locale = (string) $request->query('locale', app()->getLocale());
        $exclude = $request->integer('exclude');
        $like = '%'.$q.'%';

        $results = Post::query()
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('locale', $locale)
            ->when($exclude, fn ($qb) => $qb->where('id', '!=', $exclude))
            ->where(function ($qb) use ($like) {
                $qb->where('title', 'ilike', $like)
                    ->orWhere('slug', 'ilike', $like)
                    ->orWhere('excerpt', 'ilike', $like);
            })
            ->orderByRaw('CASE WHEN title ILIKE ? THEN 0 ELSE 1 END', [$like])
            ->limit(8)
            ->get(['id', 'title', 'slug', 'locale']);

        return response()->json(
            $results->map(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'locale' => $p->locale,
                'url' => "/{$p->locale}/posts/{$p->slug}",
            ])->all()
        );
    }
```

- [ ] **Step 5: 跑測試確認通過**

Run: `./vendor/bin/sail artisan test --filter=PostSearchTest`
Expected: PASS(3 tests)

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/PostController.php tests/Feature/Admin/PostSearchTest.php
git commit -m "feat: admin post search endpoint for @ mentions"
```

---

## Task 5: 編輯器 `@` 提及(Alpine + Blade)

> 本任務為前端 UI,專案無 JS 測試框架,以**瀏覽器手動驗證**為主;後端搜尋已於 Task 4 涵蓋。

**Files:**
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/posts/edit.blade.php`

- [ ] **Step 1: 在 app.js 加 mentionBehavior 並併入 markdownMediaInsert**

在 `resources/js/app.js`,於 `youtubePasteBehavior` 物件**之後**新增:

```javascript
const mentionBehavior = {
    mentionActive: false,
    mentionQuery: '',
    mentionResults: [],
    mentionIndex: 0,
    mentionStart: -1,
    _mentionTimer: null,

    // 取得查詢用 locale:新增頁讀 select,編輯頁用初始值
    mentionLocale() {
        const sel = document.querySelector('select[name=locale]');
        return sel ? sel.value : (this.locale || 'zh');
    },

    // 每次 textarea input 觸發:偵測游標前是否有有效的 @query
    detectMention() {
        const ta = this.$refs.body;
        const pos = ta.selectionStart;
        const text = ta.value.slice(0, pos);
        const at = text.lastIndexOf('@');
        if (at === -1) return this.closeMention();

        // @ 必須位於行首或空白後(避免 email 如 jyu@furuke.com 誤觸)
        const before = at === 0 ? '\n' : text[at - 1];
        if (!/\s/.test(before)) return this.closeMention();

        const query = text.slice(at + 1);
        if (/\s/.test(query)) return this.closeMention(); // 出現空白即結束 mention

        this.mentionStart = at;
        this.mentionQuery = query;
        this.mentionActive = true;
        this.searchMentions(query);
    },

    searchMentions(q) {
        clearTimeout(this._mentionTimer);
        if (q === '') { this.mentionResults = []; return; }
        this._mentionTimer = setTimeout(async () => {
            if (!this.mentionActive) return;
            const params = new URLSearchParams({
                q,
                locale: this.mentionLocale(),
                exclude: this.postId ?? '',
            });
            try {
                const res = await fetch(`/admin/posts/search?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (!res.ok) { this.mentionResults = []; return; }
                this.mentionResults = await res.json();
                this.mentionIndex = 0;
            } catch (e) {
                this.mentionResults = [];
            }
        }, 250);
    },

    onMentionKeydown(e) {
        if (!this.mentionActive || this.mentionResults.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.mentionIndex = (this.mentionIndex + 1) % this.mentionResults.length;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.mentionIndex = (this.mentionIndex - 1 + this.mentionResults.length) % this.mentionResults.length;
        } else if (e.key === 'Enter') {
            e.preventDefault();
            this.pickMention(this.mentionResults[this.mentionIndex]);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            this.closeMention();
        }
    },

    pickMention(item) {
        if (!item) return;
        const ta = this.$refs.body;
        const pos = ta.selectionStart;
        const link = `[${item.title}](${item.url})`;
        ta.value = ta.value.slice(0, this.mentionStart) + link + ta.value.slice(pos);
        const caret = this.mentionStart + link.length;
        ta.setSelectionRange(caret, caret);
        ta.focus();
        this.closeMention();
        ta.dispatchEvent(new Event('input')); // 讓 Alpine 同步 value
    },

    closeMention() {
        this.mentionActive = false;
        this.mentionResults = [];
        this.mentionQuery = '';
        this.mentionStart = -1;
    },
};
```

接著修改 `window.markdownMediaInsert`,讓它接受 `locale` / `postId` 參數並併入 `mentionBehavior`。把原本的:

```javascript
window.markdownMediaInsert = function () {
    return {
        ...youtubePasteBehavior,
        uploading: 0,
```

改為:

```javascript
window.markdownMediaInsert = function ({ locale = 'zh', postId = null } = {}) {
    return {
        ...youtubePasteBehavior,
        ...mentionBehavior,
        locale,
        postId,
        uploading: 0,
```

(其餘 `markdownMediaInsert` 內容不變。)

- [ ] **Step 2: 改 edit.blade.php 的 x-data、textarea 事件與下拉面板**

在 `resources/views/admin/posts/edit.blade.php`,把:

```blade
            <div x-data="markdownMediaInsert()" class="relative">
```

改為:

```blade
            <div x-data="markdownMediaInsert({ locale: @js($post->locale ?: app()->getLocale()), postId: @js($post->id) })" class="relative">
```

把 textarea 的:

```blade
                    @input="dismissYtPrompt()"
```

改為:

```blade
                    @input="dismissYtPrompt(); detectMention()"
                    @keydown="onMentionKeydown($event)"
```

在 `@include('admin.partials.youtube-embed-prompt')` 之後、`</div>` 之前,加入下拉面板:

```blade
                {{-- @ 提及文章搜尋下拉 --}}
                <div x-show="mentionActive && mentionResults.length" x-cloak
                    class="mt-1 bg-card border border-line rounded-md shadow-lg overflow-hidden max-h-64 overflow-y-auto">
                    <template x-for="(item, i) in mentionResults" :key="item.id">
                        <button type="button"
                            @click="pickMention(item)"
                            @mouseenter="mentionIndex = i"
                            :class="i === mentionIndex ? 'bg-paper-2' : ''"
                            class="w-full text-left px-3 py-2 border-b border-line last:border-0 hover:bg-paper-2">
                            <div class="text-sm" x-text="item.title"></div>
                            <div class="text-xs text-ink-3 font-mono" x-text="item.url"></div>
                        </button>
                    </template>
                </div>
```

- [ ] **Step 3: 編譯前端**

Run: `./vendor/bin/sail npm run build`
Expected: Vite build 成功,無錯誤。

- [ ] **Step 4: 瀏覽器手動驗證**

1. 登入後台,開「編輯文章」。
2. 在內文輸入空白後打 `@` 再接關鍵字(例如 `@R2`)→ 下方浮出文章清單。
3. 按 ↑↓ 可移動高亮、Enter 或點擊插入 `[標題](/zh/posts/slug)`,游標停在連結之後。
4. 在 email 字串中(如打 `jyu@`,`@` 前是字母)→ **不**跳出清單。
5. 按 Esc 或打空白 → 清單關閉。

- [ ] **Step 5: Commit**

```bash
git add resources/js/app.js resources/views/admin/posts/edit.blade.php public/build
git commit -m "feat: @ mention autocomplete in post editor"
```

---

## Task 6: 前台 backlink 顯示

**Files:**
- Modify: `app/Http/Controllers/Public/PostController.php`
- Modify: `resources/views/public/posts/show.blade.php`
- Modify: `lang/zh/public.php`, `lang/en/public.php`, `lang/ja/public.php`, `lang/vi/public.php`, `lang/id/public.php`
- Test: `tests/Feature/Public/PostBacklinkDisplayTest.php`

- [ ] **Step 1: 寫失敗測試**

```php
<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostBacklinkDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PostService
    {
        return app(PostService::class);
    }

    public function test_published_source_appears_in_backlinks(): void
    {
        $target = $this->service()->create([
            'locale' => 'zh', 'slug' => 'target', 'title' => '目標文章',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        $this->service()->create([
            'locale' => 'zh', 'slug' => 'source', 'title' => '來源文章',
            'body' => '看 [這篇](/zh/posts/target)', 'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->get('/zh/posts/target')
            ->assertOk()
            ->assertSee('被以下文章提及')
            ->assertSee('來源文章');
    }

    public function test_draft_source_is_hidden(): void
    {
        $target = $this->service()->create([
            'locale' => 'zh', 'slug' => 'target2', 'title' => '目標2',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        $this->service()->create([
            'locale' => 'zh', 'slug' => 'draft-source', 'title' => '草稿來源',
            'body' => '[x](/zh/posts/target2)', 'status' => Post::STATUS_DRAFT,
        ]);

        $this->get('/zh/posts/target2')
            ->assertOk()
            ->assertDontSee('草稿來源');
    }
}
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `./vendor/bin/sail artisan test --filter=PostBacklinkDisplayTest`
Expected: FAIL(找不到「被以下文章提及」)

- [ ] **Step 3: 加 i18n 字串**

在 `lang/zh/public.php` 的 `// Post show` 區塊內(`'next_post' => '下一篇',` 之後)新增:

```php
    'mentioned_in' => '被以下文章提及',
```

在 `lang/en/public.php` 對應位置新增:

```php
    'mentioned_in' => 'Mentioned in',
```

在 `lang/ja/public.php` 對應位置新增:

```php
    'mentioned_in' => 'この記事に言及している記事',
```

在 `lang/vi/public.php` 對應位置新增:

```php
    'mentioned_in' => 'Được nhắc đến trong',
```

在 `lang/id/public.php` 對應位置新增:

```php
    'mentioned_in' => 'Disebutkan dalam',
```

> 若某語言檔沒有 `// Post show` 區塊,放在 `return [` 之後任一處即可,鍵名一致即生效。

- [ ] **Step 4: controller 載入 backlinks**

在 `app/Http/Controllers/Public/PostController.php` 的 `show()` 內,計算 `$availableLocales` 之後、`return view(...)` 之前加上:

```php
        $backlinks = $post->backlinks()
            ->where('posts.status', Post::STATUS_PUBLISHED)
            ->orderByDesc('posts.published_at')
            ->get(['posts.id', 'posts.title', 'posts.slug', 'posts.locale', 'posts.excerpt']);
```

在 `return view('public.posts.show', [...])` 的陣列中加入:

```php
            'backlinks' => $backlinks,
```

頂部若無 `use App\Models\Post;` 則新增之(供 `Post::STATUS_PUBLISHED` 使用)。

- [ ] **Step 5: blade 顯示區塊**

在 `resources/views/public/posts/show.blade.php`,於 Tags 區塊(`@endif` 結束 tags 之後)與 `{{-- Series navigation --}}` 之間,插入:

```blade
        {{-- Backlinks:被以下文章提及 --}}
        @if($backlinks->isNotEmpty())
            <section class="mt-12 pt-6 border-t border-line">
                <div class="text-[10px] uppercase tracking-widest text-ink-3 font-mono mb-4">🔗 {{ __('public.mentioned_in') }}</div>
                <div class="grid gap-3">
                    @foreach($backlinks as $bl)
                        <a href="{{ route('public.posts.show', [$bl->locale, $bl->slug]) }}"
                            class="block p-4 border border-line rounded-lg hover:border-accent transition-colors">
                            <div class="font-serif text-base font-semibold mb-1">{{ $bl->title }}</div>
                            @if($bl->excerpt)
                                <div class="text-xs text-ink-3 line-clamp-2">{{ $bl->excerpt }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
```

- [ ] **Step 6: 跑測試確認通過**

Run: `./vendor/bin/sail artisan test --filter=PostBacklinkDisplayTest`
Expected: PASS(2 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Public/PostController.php resources/views/public/posts/show.blade.php lang/zh/public.php lang/en/public.php lang/ja/public.php lang/vi/public.php lang/id/public.php tests/Feature/Public/PostBacklinkDisplayTest.php
git commit -m "feat: show backlinks on public post page"
```

---

## Task 7: 既有文章 backfill 指令

> **安全性**:此指令**只寫入新的 `post_references` 邊表**,完全不碰 `posts.body`、slug 或任何前台渲染來源。即使結果有誤,公開網站照常運作,最壞情況只是 backlink 多/少,重跑即可修正。指令支援 `--dry-run` 預覽(不寫入),並逐篇印出解析結果與「找不到的連結」異常,讓你複製檢查後再正式執行。

**Files:**
- Create: `app/Console/Commands/BackfillPostReferences.php`
- Test: `tests/Feature/BackfillPostReferencesTest.php`

- [ ] **Step 1: 寫失敗測試**

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillPostReferencesTest extends TestCase
{
    use RefreshDatabase;

    /** 直接用 Post::create 建立,繞過 PostService 的存檔同步,模擬「既有資料尚未有 reference」。 */
    private function rawPost(string $slug, string $body, string $status = 'published'): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create()->id,
            'locale' => 'zh',
            'slug' => $slug,
            'title' => $slug,
            'body' => $body,
            'status' => $status,
            'last_modified_at' => now(),
        ]);
    }

    public function test_backfill_populates_references_for_existing_posts(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '看 [這篇](/zh/posts/target)');

        $this->assertSame(0, DB::table('post_references')->count());

        $this->artisan('posts:backfill-references')->assertExitCode(0);

        $this->assertSame(1, DB::table('post_references')->count());
    }

    public function test_backfill_is_idempotent(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '[x](/zh/posts/target)');

        $this->artisan('posts:backfill-references')->assertExitCode(0);
        $this->artisan('posts:backfill-references')->assertExitCode(0);

        $this->assertSame(1, DB::table('post_references')->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '[x](/zh/posts/target)');

        $this->artisan('posts:backfill-references --dry-run')->assertExitCode(0);

        $this->assertSame(0, DB::table('post_references')->count());
    }
}
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `./vendor/bin/sail artisan test --filter=BackfillPostReferencesTest`
Expected: FAIL(`Command "posts:backfill-references" is not defined.`)

- [ ] **Step 3: 實作指令**

```php
<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostService;
use App\Support\PostReferenceExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPostReferences extends Command
{
    protected $signature = 'posts:backfill-references {--dry-run : 只印出將會發生的變更,不寫入資料庫}';

    protected $description = 'Scan all posts and (re)populate post_references from internal links in their bodies.';

    public function handle(PostService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $extractor = new PostReferenceExtractor();

        $this->info($dryRun ? '== DRY RUN(不會寫入資料庫)==' : '== Backfill post_references ==');

        $scanned = 0;
        $withLinks = 0;
        $totalRefs = 0;
        $unresolvedTotal = 0;

        foreach (Post::all() as $post) {
            $scanned++;
            $pairs = $extractor->extract((string) $post->body);
            if (empty($pairs)) {
                continue;
            }

            $resolved = [];
            $unresolved = [];
            foreach ($pairs as $pair) {
                $target = Post::query()
                    ->where('locale', $pair['locale'])
                    ->where('slug', $pair['slug'])
                    ->first();

                if ($target && $target->id !== $post->id) {
                    $resolved[$target->id] = "{$pair['locale']}/{$pair['slug']}";
                } elseif (! $target) {
                    $unresolved[] = "{$pair['locale']}/{$pair['slug']}";
                }
            }

            $withLinks++;
            $totalRefs += count($resolved);
            $unresolvedTotal += count($unresolved);

            $line = sprintf(
                '[%s/%s] → %d 篇: %s',
                $post->locale,
                $post->slug,
                count($resolved),
                $resolved ? implode(', ', $resolved) : '(無可解析的目標)'
            );
            if ($unresolved) {
                $line .= '  ⚠ 找不到對應文章: '.implode(', ', $unresolved);
            }
            $this->line($line);

            if (! $dryRun) {
                $service->syncReferences($post);
            }
        }

        $this->newLine();
        $this->table(
            ['掃描文章', '含內部連結', '建立 reference', '無法解析(異常)'],
            [[$scanned, $withLinks, $totalRefs, $unresolvedTotal]]
        );

        if ($dryRun) {
            $this->warn('這是 dry-run,未寫入任何資料。確認上方結果無誤後,拿掉 --dry-run 再跑一次正式執行。');
        } else {
            $this->info('完成。post_references 目前共 '.DB::table('post_references')->count().' 筆。');
        }

        return self::SUCCESS;
    }
}
```

每行輸出範例(可直接複製給我檢查):

```
[zh/introducing-monitoring-in-the-company] → 1 篇: zh/unexpected-risks
[en/introducing-monitoring-in-the-company] → 1 篇: en/unexpected-risks
[zh/some-old-post] → 0 篇: (無可解析的目標)  ⚠ 找不到對應文章: zh/renamed-slug
```

`⚠ 找不到對應文章` 代表 body 裡有指向不存在 slug 的連結(通常是該目標曾改過 slug,或本來就是死連結)—— 這是要回報給我看的「異常」。

- [ ] **Step 4: 跑測試確認通過**

Run: `./vendor/bin/sail artisan test --filter=BackfillPostReferencesTest`
Expected: PASS(2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/BackfillPostReferences.php tests/Feature/BackfillPostReferencesTest.php
git commit -m "feat: posts:backfill-references command"
```

---

## Task 8: 全套測試 + 對既有資料跑 backfill

- [ ] **Step 1: 跑完整測試套件**

Run: `./vendor/bin/sail artisan test`
Expected: 全綠,僅 `tests/Unit/ExampleTest.php` 等既有(與本功能無關)案例維持原狀。本功能新增的 5 個測試檔皆 PASS。

- [ ] **Step 2: 先 dry-run 預覽(不寫入)**

Run: `./vendor/bin/sail artisan posts:backfill-references --dry-run`
Expected: 逐篇印出解析結果與摘要表,結尾提示「這是 dry-run,未寫入任何資料」。
**把這段輸出複製給作者(JYu)確認**:檢查解析的目標是否合理、`⚠ 找不到對應文章` 的異常是否在預期內。確認後再進行 Step 3。

- [ ] **Step 3: 正式執行 backfill**

Run: `./vendor/bin/sail artisan posts:backfill-references`
Expected: 同樣印出逐篇結果與摘要表,結尾輸出 `完成。post_references 目前共 N 筆。`
(此指令只寫 `post_references`,不影響前台渲染;若結果有誤可直接重跑覆寫。)

- [ ] **Step 4: 人工抽查**

開 `/zh/posts/unexpected-risks` → 應於底部看到「被以下文章提及」含「…監控導入…」那篇(zh)。確認 en/ja 版頁面顯示對應語言的來源。

---

## Self-Review 註記

- **Spec 覆蓋**:核心模型(Task 1、3)、`@` 提及(Task 4、5)、搜尋端點(Task 4)、連結抽取(Task 2)、前台顯示+i18n(Task 6)、backfill(Task 7)、測試(各任務)、已知限制(無需程式碼,維持設計記載)——皆有對應任務。
- **型別一致**:`syncReferences(Post)`、`outgoingReferences()`/`backlinks()`、搜尋回傳 `{id,title,slug,locale,url}`、Alpine `pickMention/closeMention/detectMention` 命名於各任務間一致。
- **無 placeholder**:每個程式碼步驟皆含完整內容。
