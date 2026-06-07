# Tweets YouTube 貼上 Embed 詢問 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** tweets 編輯器貼上 YouTube 連結時出現與 posts/pages 相同的「Embed 影片 / 保留連結」彈窗。

**Architecture:** 把 YouTube 偵測/嵌入邏輯從 `markdownMediaInsert` 抽成 module-level 共用物件 `youtubePasteBehavior`（spread 進元件），新增輕量 `youtubePastePrompt()` Alpine 元件掛在 tweets body textarea（嵌套於 `tweetMediaUpload` scope 內）。彈窗 partial 因 state/方法名一致原封不動重用。後端零改動（tweet-card 已用 MarkdownRenderer 渲染、`prose-blog` CSS 已適用 — 已實測）。

**Tech Stack:** Alpine.js 3、Vite（`./vendor/bin/sail npm run build`）、Blade。

**Spec:** `docs/superpowers/specs/2026-06-07-tweets-youtube-paste-embed-design.md`

**注意事項（本 repo 慣例）：**
- npm/artisan 一律透過 `./vendor/bin/sail`（需 `./vendor/bin/sail up -d`）
- 已知 pre-existing 失敗：ExampleTest，與本案無關，不要修
- 註解風格：JSDoc/區塊註解英文、行內邏輯註解繁中

---

## File Structure

| 檔案 | 動作 | 職責 |
|---|---|---|
| `resources/js/app.js` | Modify (L190-288) | 抽 `youtubePasteBehavior` 共用物件、`markdownMediaInsert` 改用之、新增 `window.youtubePastePrompt` |
| `resources/views/admin/tweets/edit.blade.php` | Modify (L45-48) | body textarea 外包 `youtubePastePrompt()` wrapper + include 彈窗 partial |

---

### Task 1: `app.js` 抽共用 behavior + 新增 `youtubePastePrompt` 元件

**Files:**
- Modify: `resources/js/app.js:190-288`（`markdownMediaInsert` 一帶）

repo 無 JS 測試框架（既定決議）；本 task 是行為不變的重構 + 新元件，驗證靠 build 通過 + Task 3 手動驗證（posts 回歸 + tweets 新功能）。

- [ ] **Step 1: 在 `markdownMediaInsert` 的 JSDoc 之前插入共用物件**

在 `window.markdownMediaInsert = function () {` 上方的 JSDoc 區塊**之前**，插入：

```js
/**
 * Shared YouTube paste-detection behavior, spread into Alpine components.
 * Expects x-ref="body" (textarea) in scope; pairs with the
 * admin/partials/youtube-embed-prompt blade partial
 * (ytPrompt / embedYoutube / dismissYtPrompt 名稱須一致).
 */
const youtubePasteBehavior = {
    ytPrompt: null, // { url, id, start } — 待確認的 YouTube embed
    detectYoutubePaste(event) {
        const text = event.clipboardData?.getData('text/plain') ?? '';
        const parsed = parseYoutubeUrl(text);
        if (!parsed) return; // 一般文字貼上不攔截
        // 不 preventDefault：照常貼上原始連結（保留原生 undo）。
        // 貼上本身會觸發 textarea 的 input 事件，須等它過了再開彈窗，
        // 否則 @input 的 dismiss 會立刻把彈窗關掉。
        setTimeout(() => {
            this.ytPrompt = { url: text.trim(), ...parsed };
        }, 0);
    },
    embedYoutube() {
        if (!this.ytPrompt) return;
        const ta = this.$refs.body;
        const idx = ta.value.lastIndexOf(this.ytPrompt.url);
        if (idx !== -1) {
            const code = youtubeShortcode(this.ytPrompt);
            ta.value = ta.value.slice(0, idx) + code + ta.value.slice(idx + this.ytPrompt.url.length);
            const pos = idx + code.length;
            ta.setSelectionRange(pos, pos);
            ta.focus();
        }
        this.ytPrompt = null;
    },
    dismissYtPrompt() {
        this.ytPrompt = null;
    },
};

/**
 * YouTube paste prompt for plain textareas without media upload
 * (e.g. the tweet composer). Expects x-ref="body" in scope.
 */
window.youtubePastePrompt = function () {
    return {
        ...youtubePasteBehavior,
        handlePaste(event) {
            this.detectYoutubePaste(event);
        },
    };
};
```

- [ ] **Step 2: `markdownMediaInsert` 改用共用物件**

(a) 回傳物件開頭加 spread、移除自帶的 `ytPrompt` state：

```js
window.markdownMediaInsert = function () {
    return {
        ...youtubePasteBehavior,
        uploading: 0,
        error: null,
        dragging: false,
        pick() {
```

（即：刪除原本的 `ytPrompt: null, // { url, id, start } — 待確認的 YouTube embed` 那一行，改在最上方 spread。）

(b) `handlePaste` 的文字分支改呼叫共用方法，整個方法替換為：

```js
        handlePaste(event) {
            const files = Array.from(event.clipboardData?.files ?? []);
            if (files.length) {
                event.preventDefault();
                this.handleFiles(files);
                return;
            }
            this.detectYoutubePaste(event);
        },
```

(c) 刪除 `markdownMediaInsert` 物件內的 `embedYoutube()` 與 `dismissYtPrompt()` 兩個方法（位於 `replaceText` 之後、物件結尾 `};` 之前）— 已由 spread 提供。

- [ ] **Step 3: Build**

Run: `./vendor/bin/sail npm run build`
Expected: Vite build 成功，無錯誤

- [ ] **Step 4: Commit**

```bash
git add resources/js/app.js
git commit -m "refactor: extract shared youtubePasteBehavior + youtubePastePrompt component"
```

---

### Task 2: tweets 編輯頁接線

**Files:**
- Modify: `resources/views/admin/tweets/edit.blade.php:45-48`

- [ ] **Step 1: body textarea 外包 wrapper**

把現在的：

```blade
            <div>
                <textarea name="body" rows="8" maxlength="2000" placeholder="今天想分享什麼…"
                    class="w-full bg-card border border-line rounded-md p-4 font-serif text-base focus:border-accent focus:outline-none" required>{{ old('body', $tweet->body) }}</textarea>
            </div>
```

替換為（嵌套 x-data — Alpine 支援，內層 scope 蓋掉外層 `tweetMediaUpload` 的同名屬性查找；`tweetMediaUpload` 沒有 `ytPrompt`/`handlePaste` 等名稱，無衝突）：

```blade
            <div class="relative" x-data="youtubePastePrompt()">
                <textarea name="body" rows="8" maxlength="2000" placeholder="今天想分享什麼…" x-ref="body"
                    @paste="handlePaste($event)"
                    @input="dismissYtPrompt()"
                    class="w-full bg-card border border-line rounded-md p-4 font-serif text-base focus:border-accent focus:outline-none" required>{{ old('body', $tweet->body) }}</textarea>
                @include('admin.partials.youtube-embed-prompt')
            </div>
```

- [ ] **Step 2: Build（確認 blade 無語法問題可由下一步手動驗證涵蓋，此步僅確保 assets 最新）**

Run: `./vendor/bin/sail npm run build`
Expected: 成功

- [ ] **Step 3: Commit**

```bash
git add resources/views/admin/tweets/edit.blade.php
git commit -m "feat: youtube paste embed prompt in tweet composer"
```

---

### Task 3: 驗證

**Files:** 無（驗證 + 可能微調）

- [ ] **Step 1: PHP 測試全綠（後端零改動的回歸確認）**

Run: `./vendor/bin/sail artisan test`
Expected: 全綠（除已知 ExampleTest）

- [ ] **Step 2: 瀏覽器手動驗證**

| # | 操作 | 預期 |
|---|---|---|
| 1 | tweets 編輯頁貼上 `https://youtu.be/dQw4w9WgXcQ?t=90` | 連結照常貼入 + 彈窗 |
| 2 | 點「Embed 影片」 | 替換成 `{{< youtube id="dQw4w9WgXcQ" start="90" >}}` |
| 3 | 儲存 → 前台 `/zh/tweets` 與單篇頁 | 影片 16:9 inline 播放、位置跟著 shortcode |
| 4 | 貼上 → Esc / 點外面 / 保留連結 / 繼續打字 | 彈窗關閉、連結原樣 |
| 5 | **posts 編輯頁回歸**：貼 YouTube 連結 → Embed | 行為與重構前一致 |
| 6 | **posts 編輯頁回歸**：貼上截圖檔案 | 上傳插入 markdown 仍正常 |

（本機已有測試 tweet id 104 可拿來看前台渲染；Playwright 截圖 harness 在 `/tmp/yt-verify` 可重用。）

- [ ] **Step 3: 有微調則 commit**

```bash
git add -A && git commit -m "fix: tweets youtube paste polish from manual verification"
```

（無微調則跳過。）
