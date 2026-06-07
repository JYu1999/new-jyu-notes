# 後台媒體上傳 UX 優化 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tweet 表單加上 Twitter 風格媒體上傳元件（最多 4 個 image/video + alt）；Post / Page 的 Markdown textarea 支援按鈕/拖放/貼上插入媒體。

**Architecture:** 後端零改動（`POST /admin/media` 與 Tweet `media` 驗證/持久化均已存在）。新增兩個 Alpine.js 元件到 `resources/js/app.js`（與既有 `coverUpload` 同風格），並修改三個 Blade view。媒體以 hidden inputs 隨既有表單送出。

**Tech Stack:** Laravel 13 (Sail)、Blade、Alpine.js 3、Tailwind 4、Vite。**所有 composer/artisan/php/npm 指令一律透過 `./vendor/bin/sail` 執行。**

**Spec:** `docs/superpowers/specs/2026-06-07-admin-media-upload-ux-design.md`

---

## 背景知識（執行前必讀）

- 上傳端點：`POST /admin/media`（`app/Http/Controllers/Admin/MediaController.php:20`），接受 `jpg,jpeg,png,webp,gif,mp4,webm` ≤10MB，回傳 JSON `{ id, url, path, mime_type, width, height }`。需要 header `X-CSRF-TOKEN` 與 `Accept: application/json`。
- Tweet 後端：`tweets.media` 是 JSONB array cast（`app/Models/Tweet.php`），驗證規則在 `app/Http/Requests/Admin/Tweet/UpdateRequest.php`（`media` nullable array max:4；每項 `path`/`type:image,video`/`alt`）。`TweetService::update`（`app/Services/TweetService.php:35`）用 `array_key_exists('media', $data)` 決定要不要更新 media —— **所以「清空媒體」必須讓表單送出 `media` key**：空的 `<input type="hidden" name="media" value="">` 經 `ConvertEmptyStringsToNull` middleware 變成 `null`，通過 `nullable` 驗證後把欄位清掉。
- 預覽 URL：`layouts/admin.blade.php:7` 有 `<meta name="media-base" content="...">`，既有 `coverUpload`（`resources/js/app.js:82`）以 `mediaBase + '/' + path` 組預覽網址。
- Post/Page 的 `body` 是標準 Markdown 存 DB，前台用 commonmark 渲染；影片要插 `<video class="local-video" controls src="..." preload="metadata"></video>`（與前台 `ShortcodeConverter::convertLocalVideo` 產出的樣式一致）。
- Tweet **沒有** model factory（database/factories 只有 UserFactory）；測試中用 `app(TweetService::class)->create([...])` 建立。
- 測試 DB 是 pgsql；`tests/Feature/ExampleTest.php` 有一個 pre-existing failure，與本案無關，不要嘗試修。

---

### Task 1: Tweet media 持久化 feature test（characterization）

前端依賴兩個後端行為：(a) media 陣列持久化、(b) 空字串 `media` 清空欄位。先用測試鎖住這兩個契約。

**Files:**
- Create: `tests/Feature/Admin/TweetAdminMediaTest.php`

- [ ] **Step 1: 寫測試**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TweetAdminMediaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_update_persists_media_array(): void
    {
        $admin = $this->admin();
        $tweet = app(TweetService::class)->create([
            'body' => 'hello', 'locale' => 'zh', 'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('admin.tweets.update', $tweet), [
            'body' => 'hello',
            'status' => 'draft',
            'media' => [
                ['path' => 'uploads/2026/06/a.jpg', 'type' => 'image', 'alt' => '一張圖'],
                ['path' => 'uploads/2026/06/b.mp4', 'type' => 'video', 'alt' => ''],
            ],
        ])->assertRedirect(route('admin.tweets.edit', $tweet));

        $tweet->refresh();
        $this->assertCount(2, $tweet->media);
        $this->assertSame('uploads/2026/06/a.jpg', $tweet->media[0]['path']);
        $this->assertSame('一張圖', $tweet->media[0]['alt']);
        $this->assertSame('video', $tweet->media[1]['type']);
    }

    public function test_update_with_empty_media_string_clears_media(): void
    {
        $admin = $this->admin();
        $tweet = app(TweetService::class)->create([
            'body' => 'hello', 'locale' => 'zh', 'author_id' => $admin->id,
            'media' => [['path' => 'uploads/2026/06/a.jpg', 'type' => 'image']],
        ]);

        // 模擬前端清空所有媒體後的 hidden input：media=""
        // ConvertEmptyStringsToNull 會轉成 null → 通過 nullable → 清空欄位
        $this->actingAs($admin)->put(route('admin.tweets.update', $tweet), [
            'body' => 'hello',
            'status' => 'draft',
            'media' => '',
        ])->assertRedirect(route('admin.tweets.edit', $tweet));

        $this->assertEmpty($tweet->refresh()->media);
    }

    public function test_update_without_media_key_keeps_existing_media(): void
    {
        $admin = $this->admin();
        $tweet = app(TweetService::class)->create([
            'body' => 'hello', 'locale' => 'zh', 'author_id' => $admin->id,
            'media' => [['path' => 'uploads/2026/06/a.jpg', 'type' => 'image']],
        ]);

        $this->actingAs($admin)->put(route('admin.tweets.update', $tweet), [
            'body' => 'updated',
            'status' => 'draft',
        ])->assertRedirect(route('admin.tweets.edit', $tweet));

        $this->assertCount(1, $tweet->refresh()->media);
    }
}
```

- [ ] **Step 2: 跑測試**

Run: `./vendor/bin/sail artisan test --filter=TweetAdminMediaTest`
Expected: 3 個全 PASS（後端已實作，這是 characterization test，鎖住前端依賴的契約）。若 `clears_media` 失敗，停下來回報——那代表 spec 對後端行為的假設錯了，不要直接改 service。

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Admin/TweetAdminMediaTest.php
git commit -m "test: lock tweet media persistence contract for admin form"
```

---

### Task 2: `tweetMediaUpload` Alpine 元件 + Tweet 表單 UI

**Files:**
- Modify: `resources/js/app.js`（在 `coverUpload` 定義之後、`infiniteScroll` 之前插入）
- Modify: `resources/views/admin/tweets/edit.blade.php`

- [ ] **Step 1: 在 `app.js` 加入元件**

插入在 `window.coverUpload = ...` 區塊（約 124 行）結尾之後：

```js
/**
 * Tweet media upload widget (Twitter-style, max 4 items).
 * Each item: { path, type: 'image'|'video', alt }.
 * Uses /admin/media endpoint which returns JSON { url, path, mime_type, ... }.
 */
window.tweetMediaUpload = function ({ initial, max = 4 }) {
    return {
        items: (initial || []).map((m) => ({ path: m.path, type: m.type, alt: m.alt ?? '' })),
        uploading: 0,
        error: null,
        get mediaBase() {
            return document.querySelector('meta[name=media-base]')?.content ?? '';
        },
        get full() {
            return this.items.length >= max;
        },
        url(item) {
            return this.mediaBase + '/' + item.path;
        },
        async add(fileList) {
            this.error = null;
            const incoming = Array.from(fileList);
            const files = incoming.slice(0, max - this.items.length - this.uploading);
            if (incoming.length > files.length) {
                this.error = `最多 ${max} 個媒體`;
            }
            await Promise.all(files.map((f) => this.uploadOne(f)));
        },
        async uploadOne(file) {
            this.uploading++;
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
                this.items.push({
                    path: data.path,
                    type: data.mime_type.startsWith('video/') ? 'video' : 'image',
                    alt: '',
                });
            } catch (e) {
                this.error = e.message || '上傳失敗';
            } finally {
                this.uploading--;
            }
        },
        remove(index) {
            this.items.splice(index, 1);
        },
    };
};
```

- [ ] **Step 2: 修改 `admin/tweets/edit.blade.php`**

(a) 把 `x-data` 掛在 `<form>` 上（第 11 行），讓儲存按鈕也能讀到上傳狀態：

```blade
<form method="POST" action="{{ $action }}" class="space-y-6" id="tweet-form"
    x-data="tweetMediaUpload({ initial: @js(old('media', $tweet->media ?? [])) })">
```

(b) 儲存按鈕（第 28 行）加上 disable：

```blade
<button type="submit" :disabled="uploading > 0" :class="uploading > 0 ? 'opacity-50 cursor-wait' : ''"
    class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">儲存</button>
```

(c) 在 body textarea 的 `</div>`（第 46 行）之後、標籤區塊之前插入媒體區：

```blade
            {{-- Media (max 4, Twitter-style) --}}
            <div>
                <label class="block text-xs text-ink-3 mb-2 font-mono uppercase">媒體（最多 4 個）</label>

                <div class="grid grid-cols-2 gap-3">
                    <template x-for="(item, i) in items" :key="item.path">
                        <div class="relative border border-line rounded-md overflow-hidden bg-card">
                            <template x-if="item.type === 'image'">
                                <img :src="url(item)" class="w-full h-32 object-cover">
                            </template>
                            <template x-if="item.type === 'video'">
                                <video :src="url(item)" class="w-full h-32 object-cover" preload="metadata" muted></video>
                            </template>
                            <button type="button" @click="remove(i)"
                                class="absolute top-1 right-1 bg-paper/90 border border-line rounded px-2 py-0.5 text-xs hover:text-danger">✕</button>
                            <input type="text" x-model="item.alt" maxlength="200" placeholder="alt 描述（選填）"
                                class="w-full bg-paper border-t border-line px-2 py-1 text-xs focus:outline-none">
                            <input type="hidden" :name="`media[${i}][path]`" :value="item.path">
                            <input type="hidden" :name="`media[${i}][type]`" :value="item.type">
                            <input type="hidden" :name="`media[${i}][alt]`" :value="item.alt">
                        </div>
                    </template>

                    <template x-if="!full">
                        <label class="cursor-pointer border-2 border-dashed border-line rounded-md h-32 flex items-center justify-center text-xs text-ink-3 hover:border-accent hover:text-accent transition-colors"
                            :class="uploading > 0 ? 'animate-pulse' : ''"
                            @dragover.prevent @drop.prevent="add($event.dataTransfer.files)">
                            <span x-show="uploading === 0">＋ 圖片 / 影片（≤ 10 MB）</span>
                            <span x-show="uploading > 0" x-cloak>上傳中…</span>
                            <input type="file" class="hidden" multiple accept="image/*,video/mp4,video/webm"
                                @change="add($event.target.files); $event.target.value = ''">
                        </label>
                    </template>
                </div>

                {{-- 清空媒體時仍送出 media key，後端才會把欄位清掉（見 TweetAdminMediaTest） --}}
                <template x-if="items.length === 0">
                    <input type="hidden" name="media" value="">
                </template>

                <p x-show="error" x-cloak class="mt-2 text-xs text-danger" x-text="error"></p>
            </div>
```

- [ ] **Step 3: Build 前端**

Run: `./vendor/bin/sail npm run build`
Expected: Vite build 成功，無錯誤。

- [ ] **Step 4: 跑相關測試**

Run: `./vendor/bin/sail artisan test --filter=TweetAdminMediaTest`
Expected: 3 個全 PASS（確認 blade 修改沒弄壞表單流程）。

- [ ] **Step 5: Commit**

```bash
git add resources/js/app.js resources/views/admin/tweets/edit.blade.php
git commit -m "feat: tweet media upload UI in admin form"
```

---

### Task 3: `markdownMediaInsert` 元件 + Post / Page 內文插媒體

**Files:**
- Modify: `resources/js/app.js`（接在 `tweetMediaUpload` 之後插入）
- Modify: `resources/views/admin/posts/edit.blade.php:58-62`（body 欄位區塊）
- Modify: `resources/views/admin/pages/edit.blade.php`（body 欄位區塊，約第 47-51 行，結構與 posts 相同但變數是 `$page`）

- [ ] **Step 1: 在 `app.js` 加入元件**

```js
/**
 * Markdown textarea media insert: toolbar button + drag-drop + paste.
 * Uploads to /admin/media, inserts markdown (image) or <video> tag at cursor.
 * Expects x-ref="body" (textarea) and x-ref="file" (hidden file input) in scope.
 */
window.markdownMediaInsert = function () {
    return {
        uploading: 0,
        error: null,
        dragging: false,
        pick() {
            this.$refs.file.click();
        },
        handleFiles(fileList) {
            this.error = null;
            Array.from(fileList).forEach((f) => this.uploadAndInsert(f));
        },
        handlePaste(event) {
            const files = Array.from(event.clipboardData?.files ?? []);
            if (!files.length) return; // 一般文字貼上不攔截
            event.preventDefault();
            this.handleFiles(files);
        },
        async uploadAndInsert(file) {
            const placeholder = `![上傳中：${file.name}…]()`;
            this.insertAtCursor(placeholder + '\n');
            this.uploading++;
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
                const md = data.mime_type.startsWith('video/')
                    ? `<video class="local-video" controls src="${data.url}" preload="metadata"></video>`
                    : `![](${data.url})`;
                this.replaceText(placeholder, md);
            } catch (e) {
                this.replaceText(placeholder + '\n', '');
                this.error = e.message || '上傳失敗';
            } finally {
                this.uploading--;
            }
        },
        insertAtCursor(text) {
            const ta = this.$refs.body;
            const start = ta.selectionStart ?? ta.value.length;
            const end = ta.selectionEnd ?? start;
            ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
            const pos = start + text.length;
            ta.setSelectionRange(pos, pos);
            ta.focus();
        },
        replaceText(from, to) {
            // String.replace 以字串為 pattern 時是字面取代第一個出現，無 regex 風險
            const ta = this.$refs.body;
            ta.value = ta.value.replace(from, to);
        },
    };
};
```

- [ ] **Step 2: 修改 `admin/posts/edit.blade.php` 的 body 欄位**

把第 58-62 行的：

```blade
            <div>
                <label class="block text-xs text-ink-3 mb-1 font-mono uppercase tracking-wide">內文 (Markdown)</label>
                <textarea name="body" rows="24"
                    class="w-full bg-card border border-line rounded-md p-4 font-mono text-sm focus:border-accent focus:outline-none leading-relaxed">{{ old('body', $post->body) }}</textarea>
            </div>
```

換成：

```blade
            <div x-data="markdownMediaInsert()">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs text-ink-3 font-mono uppercase tracking-wide">內文 (Markdown)</label>
                    <button type="button" @click="pick()"
                        class="text-xs text-ink-3 hover:text-accent font-mono"
                        x-text="uploading > 0 ? '上傳中…' : '📷 插入媒體'"></button>
                </div>
                <textarea name="body" rows="24" x-ref="body"
                    @dragover.prevent="dragging = true"
                    @dragleave="dragging = false"
                    @drop.prevent="dragging = false; handleFiles($event.dataTransfer.files)"
                    @paste="handlePaste($event)"
                    :class="dragging ? 'border-accent' : ''"
                    class="w-full bg-card border border-line rounded-md p-4 font-mono text-sm focus:border-accent focus:outline-none leading-relaxed">{{ old('body', $post->body) }}</textarea>
                <input type="file" class="hidden" x-ref="file" multiple accept="image/*,video/mp4,video/webm"
                    @change="handleFiles($event.target.files); $event.target.value = ''">
                <p x-show="error" x-cloak class="mt-1 text-xs text-danger" x-text="error"></p>
            </div>
```

- [ ] **Step 3: 對 `admin/pages/edit.blade.php` 做同樣修改**

找到 body 欄位區塊（結構與 posts 相同的 `<textarea name="body" rows="24" ...>{{ old('body', $page->body) }}</textarea>` 包在 `<div>` 裡），套用與 Step 2 完全相同的結構，唯一差異是 `{{ old('body', $page->body) }}`。

- [ ] **Step 4: Build 前端**

Run: `./vendor/bin/sail npm run build`
Expected: Vite build 成功。

- [ ] **Step 5: Commit**

```bash
git add resources/js/app.js resources/views/admin/posts/edit.blade.php resources/views/admin/pages/edit.blade.php
git commit -m "feat: insert media into markdown body via button/drag-drop/paste"
```

---

### Task 4: 回歸測試 + 手動驗證

- [ ] **Step 1: 跑完整測試**

Run: `./vendor/bin/sail artisan test`
Expected: 除了 pre-existing 的 `ExampleTest` failure 之外全 PASS。不要嘗試修 ExampleTest。

- [ ] **Step 2: 手動驗證（瀏覽器）**

啟動：`./vendor/bin/sail up -d` 後以 admin 帳號登入後台。

Tweet（`/admin/tweets/create` 與編輯既有 tweet）：
1. 點虛線格選一張圖 → 出現預覽縮圖
2. 拖放第二張圖到虛線格 → 出現
3. 上傳一個 mp4 → 以 video 預覽呈現
4. 填 alt → 儲存 → 重新整理後 alt 還在
5. 一次選 5 個檔案 → 只收 4 個並顯示「最多 4 個媒體」
6. 移除全部媒體 → 儲存 → 重新整理後媒體確實清空（驗證 hidden `media` input）
7. 上傳中儲存按鈕呈 disabled
8. 故意送出超長 body 觸發驗證錯誤 → 回到表單後媒體仍在（old() 還原）

Post（`/admin/posts/{id}/edit`）：
9. 點「📷 插入媒體」上傳 → 游標處出現 `![](https://...)`
10. 拖放圖片到 textarea → 邊框 highlight、完成後插入
11. 截圖後在 textarea 內 Ctrl+V → 插入
12. 上傳 mp4 → 插入 `<video class="local-video" ...>` tag
13. 貼上純文字 → 正常貼上（不被攔截）

Page（`/admin/pages/{id}/edit`）：
14. 同 9 抽查一項

- [ ] **Step 3: 驗證結果回報**

把上述 14 項的結果列表回報；有失敗項目就修復後重跑該項，再 commit 修復。
