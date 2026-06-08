# Tweet 媒體敏感模糊 + 全螢幕檢視 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 讓作者逐張把 Tweet 媒體標記為「敏感」並在公開頁面模糊處理(點擊揭露),且點擊圖片可全螢幕檢視。

**Architecture:** 在既有 `tweets.media` JSON 陣列每項加 `sensitive` boolean(無 migration)。後端在 TweetService 正規化、四個 FormRequest 加驗證。前端在 admin 編輯器加 toggle;公開以 Blade wrapper + Alpine(模糊 reveal + 共用 lightbox store)呈現。

**Tech Stack:** Laravel (Sail/pgsql 測試 DB)、Blade、Alpine.js、Tailwind、Vite。所有 php/artisan/composer 指令一律用 `./vendor/bin/sail`。

> 注意:`tests/Feature/ExampleTest.php` 有既有的 pre-existing 失敗,與本功能無關,不需理會。

---

### Task 1: 後端 — media `sensitive` 驗證與正規化

**Files:**
- Modify: `app/Http/Requests/Admin/Tweet/StoreRequest.php`
- Modify: `app/Http/Requests/Admin/Tweet/UpdateRequest.php`
- Modify: `app/Http/Requests/Api/Tweet/StoreRequest.php`
- Modify: `app/Http/Requests/Api/Tweet/UpdateRequest.php`
- Modify: `app/Services/TweetService.php`
- Test: `tests/Feature/Admin/TweetAdminMediaTest.php`

- [ ] **Step 1: 寫失敗測試** — 在 `TweetAdminMediaTest.php` 末尾(class 內最後一個方法後)加入:

```php
    public function test_update_persists_and_normalizes_sensitive_flag(): void
    {
        $admin = $this->admin();
        $tweet = app(TweetService::class)->create([
            'body' => 'hello', 'locale' => 'zh', 'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('admin.tweets.update', $tweet), [
            'body' => 'hello',
            'status' => 'draft',
            'media' => [
                ['path' => 'uploads/2026/06/a.jpg', 'type' => 'image', 'alt' => '', 'sensitive' => '1'],
                ['path' => 'uploads/2026/06/b.jpg', 'type' => 'image', 'alt' => ''],
            ],
        ])->assertRedirect(route('admin.tweets.edit', $tweet));

        $tweet->refresh();
        // 字串 "1" 正規化為 true;未提供 sensitive 預設 false
        $this->assertTrue($tweet->media[0]['sensitive']);
        $this->assertFalse($tweet->media[1]['sensitive']);
    }
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `./vendor/bin/sail test --filter test_update_persists_and_normalizes_sensitive_flag`
Expected: FAIL — `media[0]['sensitive']` 為字串 `"1"` 而非 `true`(`assertTrue` 失敗),或 key 不存在。

- [ ] **Step 3: 四個 FormRequest 加驗證規則** — 在每個檔的 `rules()` 陣列中,緊接 `'media.*.alt' => ...` 那行之後加入:

```php
            'media.*.sensitive' => 'nullable|boolean',
```

(四個檔:`Admin/Tweet/StoreRequest.php`、`Admin/Tweet/UpdateRequest.php`、`Api/Tweet/StoreRequest.php`、`Api/Tweet/UpdateRequest.php` 皆相同那一行。)

- [ ] **Step 4: TweetService 加正規化** — 在 `app/Services/TweetService.php` class 內(例如 `create` 方法上方)新增私有方法:

```php
    /**
     * 將 media 陣列每項的 sensitive 正規化為 boolean(預設 false),保留其餘欄位。
     */
    private function normalizeMedia(?array $media): ?array
    {
        if ($media === null) {
            return null;
        }

        return array_map(function (array $item) {
            $item['sensitive'] = filter_var($item['sensitive'] ?? false, FILTER_VALIDATE_BOOLEAN);

            return $item;
        }, $media);
    }
```

- [ ] **Step 5: create / update 套用正規化**

在 `create()` 把:
```php
                'media' => $data['media'] ?? null,
```
改為:
```php
                'media' => $this->normalizeMedia($data['media'] ?? null),
```

在 `update()` 把:
```php
            if (array_key_exists('media', $data)) {
                $updateData['media'] = $data['media'];
            }
```
改為:
```php
            if (array_key_exists('media', $data)) {
                $updateData['media'] = $this->normalizeMedia($data['media']);
            }
```

- [ ] **Step 6: 跑測試確認通過(含既有 media 測試不回歸)**

Run: `./vendor/bin/sail test tests/Feature/Admin/TweetAdminMediaTest.php`
Expected: PASS(全部 4 個測試)。

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Admin/Tweet/StoreRequest.php app/Http/Requests/Admin/Tweet/UpdateRequest.php app/Http/Requests/Api/Tweet/StoreRequest.php app/Http/Requests/Api/Tweet/UpdateRequest.php app/Services/TweetService.php tests/Feature/Admin/TweetAdminMediaTest.php
git commit -m "feat: tweet media sensitive flag validation + normalization"
```

---

### Task 2: Admin 編輯器 — 敏感 toggle

**Files:**
- Modify: `resources/js/app.js` (`tweetMediaUpload` 元件)
- Modify: `resources/views/admin/tweets/edit.blade.php`

- [ ] **Step 1: `tweetMediaUpload` 納入 sensitive** — 在 `resources/js/app.js` 的 `tweetMediaUpload`:

把初始 `items` 對應:
```js
        items: (initial || []).map((m) => ({ path: m.path, type: m.type, alt: m.alt ?? '' })),
```
改為:
```js
        items: (initial || []).map((m) => ({ path: m.path, type: m.type, alt: m.alt ?? '', sensitive: !!m.sensitive })),
```

把 `uploadOne` 中 push 的物件:
```js
                this.items.push({
                    path: data.path,
                    type: data.mime_type.startsWith('video/') ? 'video' : 'image',
                    alt: '',
                });
```
改為:
```js
                this.items.push({
                    path: data.path,
                    type: data.mime_type.startsWith('video/') ? 'video' : 'image',
                    alt: '',
                    sensitive: false,
                });
```

- [ ] **Step 2: 編輯器加 toggle + hidden input** — 在 `resources/views/admin/tweets/edit.blade.php` 媒體縮圖的 `<template x-for>` 內,於移除按鈕(`@click="remove(i)"` 那顆 `✕`)之後、`alt` 文字框之前,加入敏感 toggle 按鈕:

```blade
                            <button type="button" @click="item.sensitive = !item.sensitive"
                                :class="item.sensitive ? 'bg-danger text-white' : 'bg-paper/90 text-ink-3'"
                                class="absolute top-1 left-1 border border-line rounded px-2 py-0.5 text-xs hover:opacity-90"
                                :title="item.sensitive ? '已標記為敏感（點擊取消）' : '標記為敏感內容'">
                                <span x-text="item.sensitive ? '🔞 敏感' : '標記敏感'"></span>
                            </button>
```

並在三個既有 hidden input(`media[${i}][path|type|alt]`)之後加入:

```blade
                            <input type="hidden" :name="`media[${i}][sensitive]`" :value="item.sensitive ? '1' : '0'">
```

- [ ] **Step 3: 建置前端**

Run: `./vendor/bin/sail npm run build`
Expected: 建置成功,無錯誤。

- [ ] **Step 4: 手動驗證(說明,不需自動化)**

開啟任一 tweet 編輯頁,確認每個媒體縮圖左上角有「標記敏感／🔞 敏感」按鈕可切換;切換後儲存,重新整理頁面該狀態保留(對應 Task 1 已存入 DB)。

- [ ] **Step 5: Commit**

```bash
git add resources/js/app.js resources/views/admin/tweets/edit.blade.php
git commit -m "feat: sensitive toggle per media item in tweet composer"
```

---

### Task 3: 公開前端 — 共用 lightbox + 模糊樣式

**Files:**
- Modify: `resources/js/app.js` (新增 lightbox store)
- Modify: `resources/views/layouts/public.blade.php` (注入 lightbox 疊層 + Alpine store init)
- Modify: `resources/css/app.css` (模糊 / lightbox 樣式)

- [ ] **Step 1: app.js 註冊 lightbox store** — 在 `resources/js/app.js` `Alpine.start();` 之前加入:

```js
/**
 * Shared fullscreen image lightbox. A single overlay lives in the public
 * layout; any image can open it via $store.lightbox.open(src, alt).
 */
document.addEventListener('alpine:init', () => {
    Alpine.store('lightbox', {
        open: false,
        src: '',
        alt: '',
        show(src, alt = '') {
            this.src = src;
            this.alt = alt;
            this.open = true;
            document.documentElement.style.overflow = 'hidden';
        },
        close() {
            this.open = false;
            this.src = '';
            document.documentElement.style.overflow = '';
        },
    });
});
```

- [ ] **Step 2: layout 注入 lightbox 疊層** — 在 `resources/views/layouts/public.blade.php` 的 `</body>` 之前(footer 之後)加入:

```blade
    {{-- Shared fullscreen image lightbox --}}
    <div x-data
        x-show="$store.lightbox.open"
        x-cloak
        @keydown.escape.window="$store.lightbox.close()"
        @click="$store.lightbox.close()"
        class="fixed inset-0 z-[100] bg-black/90 flex items-center justify-center p-4 cursor-zoom-out"
        style="display: none;">
        <button type="button" @click.stop="$store.lightbox.close()"
            class="absolute top-4 right-4 text-white/80 hover:text-white text-3xl leading-none">&times;</button>
        <img :src="$store.lightbox.src" :alt="$store.lightbox.alt"
            @click.stop
            class="max-w-full max-h-full object-contain rounded">
    </div>
```

- [ ] **Step 3: CSS 加模糊與覆蓋層樣式** — 在 `resources/css/app.css` 末尾加入:

```css
/* Tweet 敏感媒體模糊 + 揭露提示 */
.sensitive-media { position: relative; cursor: pointer; }
.sensitive-media img,
.sensitive-media video { filter: blur(18px); transform: scale(1.05); transition: filter 0.2s ease; }
.sensitive-media .sensitive-overlay {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 0.25rem; text-align: center;
    background: rgba(0, 0, 0, 0.35); color: #fff; font-size: 0.8rem; letter-spacing: 0.05em;
    border-radius: 6px; pointer-events: none;
}
/* lightbox 可點圖游標 */
.tweet-media-clickable { cursor: zoom-in; }
```

- [ ] **Step 4: 建置前端**

Run: `./vendor/bin/sail npm run build`
Expected: 建置成功,無錯誤。

- [ ] **Step 5: Commit**

```bash
git add resources/js/app.js resources/views/layouts/public.blade.php resources/css/app.css
git commit -m "feat: shared fullscreen lightbox store + sensitive media styles"
```

---

### Task 4: 公開 tweet-card — 模糊揭露 + lightbox 觸發

**Files:**
- Modify: `resources/views/components/tweet-card.blade.php`
- Test: `tests/Feature/Public/TweetCardSensitiveTest.php` (Create)

- [ ] **Step 1: 寫失敗測試** — 建立 `tests/Feature/Public/TweetCardSensitiveTest.php`:

```php
<?php

namespace Tests\Feature\Public;

use App\Models\User;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TweetCardSensitiveTest extends TestCase
{
    use RefreshDatabase;

    private function publishedTweet(array $media): \App\Models\Tweet
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'a@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);

        return app(TweetService::class)->create([
            'body' => 'hello world', 'locale' => 'zh', 'author_id' => $admin->id,
            'status' => 'published', 'published_at' => now()->subDay(),
            'media' => $media,
        ]);
    }

    public function test_sensitive_image_renders_blur_wrapper(): void
    {
        $tweet = $this->publishedTweet([
            ['path' => 'uploads/a.jpg', 'type' => 'image', 'alt' => 'x', 'sensitive' => true],
        ]);

        $this->get(route('public.tweets.show', ['zh', $tweet->id]))
            ->assertOk()
            ->assertSee('sensitive-media', false)
            ->assertSee('敏感內容', false);
    }

    public function test_plain_image_is_lightbox_clickable_without_blur(): void
    {
        $tweet = $this->publishedTweet([
            ['path' => 'uploads/b.jpg', 'type' => 'image', 'alt' => 'y', 'sensitive' => false],
        ]);

        $html = $this->get(route('public.tweets.show', ['zh', $tweet->id]))
            ->assertOk()
            ->assertSee('tweet-media-clickable', false)
            ->getContent();

        $this->assertStringNotContainsString('sensitive-media', $html);
    }
}
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `./vendor/bin/sail test tests/Feature/Public/TweetCardSensitiveTest.php`
Expected: FAIL — 找不到 `sensitive-media` / `tweet-media-clickable`(tweet-card 尚未輸出)。

- [ ] **Step 3: tweet-card 重構媒體渲染為共用片段** — 在 `resources/views/components/tweet-card.blade.php` 的 `@php ... @endphp` 區塊末端(`$mediaCount` 計算之後)加入一個可重用的渲染閉包,讓三種版型共用,避免重複:

```php
    // 單一媒體項目的渲染:image 一律可開 lightbox;sensitive 先模糊、點擊揭露。
    // $imgClass 由各版型傳入控制尺寸。
    $renderMedia = function (array $m, string $imgClass) {
        $type = $m['type'] ?? 'image';
        $sensitive = ! empty($m['sensitive']);
        $src = media_url($m['path']);
        $alt = $m['alt'] ?? '';
        ob_start();
        ?>
        <div x-data="{ revealed: <?= $sensitive ? 'false' : 'true' ?> }"
            :class="!revealed ? 'sensitive-media' : ''"
            @if($sensitive)
                @click="!revealed ? (revealed = true) : <?= $type === 'image' ? '$store.lightbox.show(\''.e($src).'\', \''.e($alt).'\')' : 'null' ?>"
            @elseif($type === 'image')
                @click="$store.lightbox.show('<?= e($src) ?>', '<?= e($alt) ?>')"
            @endif
            class="<?= $type === 'image' ? 'tweet-media-clickable' : '' ?> relative">
            <?php if ($type === 'image'): ?>
                <img src="<?= e($src) ?>" alt="<?= e($alt) ?>" class="<?= $imgClass ?>">
            <?php else: ?>
                <video src="<?= e($src) ?>" <?php if (! $sensitive): ?>controls<?php endif; ?> class="<?= $imgClass ?>"></video>
            <?php endif; ?>
            <?php if ($sensitive): ?>
                <div class="sensitive-overlay" x-show="!revealed">
                    <span>⚠️ 敏感內容</span>
                    <span>點擊顯示</span>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    };
```

> 說明:`x-show="!revealed"` 需要 Alpine,已全站載入。揭露後 sensitive image 第二次點擊才開 lightbox;sensitive video 揭露後不開 lightbox(交由使用者另行播放,維持簡單)。注意 video 揭露後沒有原生 controls 的問題 → 見 Step 4 調整。

- [ ] **Step 4: 修正 sensitive video 揭露後可播放** — 將 Step 3 片段中的 video 行改為揭露後動態加上 controls:

把:
```php
                <video src="<?= e($src) ?>" <?php if (! $sensitive): ?>controls<?php endif; ?> class="<?= $imgClass ?>"></video>
```
改為:
```php
                <video src="<?= e($src) ?>" :controls="revealed" class="<?= $imgClass ?>"></video>
```

(`:controls="revealed"` → 非敏感時 `revealed` 初始為 true 即帶 controls;敏感時揭露後才出現 controls。)

- [ ] **Step 5: 三種版型改用 `$renderMedia`** — 將 `tweet-card.blade.php` 既有的媒體區塊(`@if($mediaCount === 1) ... @endif` 整段,即目前約第 54–92 行)替換為:

```blade
    {{-- Media: 1 → full, 2 → side-by-side, 3+ → horizontal scroll-snap --}}
    @if($mediaCount === 1)
        <div class="mt-3">
            {!! $renderMedia($media[0], 'rounded-md w-full h-auto object-cover max-h-96') !!}
        </div>
    @elseif($mediaCount === 2)
        <div class="mt-3 grid grid-cols-2 gap-2">
            @foreach($media as $m)
                {!! $renderMedia($m, 'rounded-md w-full h-48 object-cover') !!}
            @endforeach
        </div>
    @elseif($mediaCount > 2)
        <div class="mt-3 -mx-1 flex gap-2 overflow-x-auto snap-x snap-mandatory pb-2 scrollbar-thin">
            @foreach($media as $m)
                <div class="snap-start flex-shrink-0 w-56 sm:w-64">
                    {!! $renderMedia($m, 'rounded-md w-full h-44 object-cover') !!}
                </div>
            @endforeach
        </div>
    @endif
```

- [ ] **Step 6: 跑測試確認通過**

Run: `./vendor/bin/sail test tests/Feature/Public/TweetCardSensitiveTest.php`
Expected: PASS(兩個測試)。

- [ ] **Step 7: 建置前端並跑完整相關測試**

Run: `./vendor/bin/sail npm run build && ./vendor/bin/sail test tests/Feature/Admin/TweetAdminMediaTest.php tests/Feature/Public/TweetCardSensitiveTest.php`
Expected: 建置成功;測試全 PASS。

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/tweet-card.blade.php tests/Feature/Public/TweetCardSensitiveTest.php
git commit -m "feat: blur + click-to-reveal + fullscreen lightbox for tweet media"
```

---

## Self-Review

**Spec coverage:**
- 資料模型 `sensitive` → Task 1。✓
- Admin toggle + hidden input + 驗證 → Task 1(驗證)+ Task 2(UI)。✓
- 公開模糊揭露(image+video)→ Task 4。✓
- 全螢幕 lightbox → Task 3(store/layout/css)+ Task 4(觸發)。✓
- reveal 不持久化(每次載入 `revealed` 重設)→ Task 4 inline x-data。✓
- 三種版型皆套用 → Task 4 Step 5。✓
- API Store/Update 接受 sensitive → Task 1 Step 3。✓

**Placeholder scan:** 無 TODO/TBD;所有步驟含具體程式碼與指令。✓

**Type/名稱一致性:** `$store.lightbox.show(src, alt)`(store 方法名 `show`,屬性 `open`)— Task 3 定義、Task 4 呼叫一致;CSS class `sensitive-media`/`sensitive-overlay`/`tweet-media-clickable` 在 Task 3 與 Task 4 一致;`normalizeMedia` 在 Task 1 定義並於 create/update 呼叫。✓
