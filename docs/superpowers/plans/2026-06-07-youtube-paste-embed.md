# YouTube 貼上 Embed 詢問 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在後台編輯器貼上 YouTube 連結時彈窗詢問「Embed 影片 / 保留連結」，Embed 則插入 `{{< youtube id="..." >}}` shortcode 並在渲染時轉成 iframe。

**Architecture:** 前端在既有 Alpine 元件 `markdownMediaInsert` 的 paste handler 加 URL 偵測（pure function 抽到 `resources/js/youtube.js`），彈窗為 blade partial。後端把 `ShortcodeConverter::convertYoutube()` 改 public 並支援 `start` 屬性，`MarkdownRenderer::render()` 在 CommonMark 前 pre-pass 轉換（只轉 youtube，不跑完整 converter）。

**Tech Stack:** Laravel 13 + PHPUnit（跑測試一律用 `./vendor/bin/sail`）、Alpine.js 3、Vite、Tailwind 4。

**Spec:** `docs/superpowers/specs/2026-06-07-youtube-paste-embed-design.md`

**注意事項（本 repo 慣例）：**
- 所有 composer / artisan / npm 指令透過 `./vendor/bin/sail` 執行（需先 `./vendor/bin/sail up -d`）。
- 已知 pre-existing 失敗：`Tests\Unit\ExampleTest` 或 `Tests\Feature\ExampleTest` 可能失敗，與本案無關，不要嘗試修。
- 測試 DB 是 pgsql（sail 內建），feature test 直接繼承 `Tests\TestCase` 即可，本案測試不碰 DB。

---

## File Structure

| 檔案 | 動作 | 職責 |
|---|---|---|
| `tests/Feature/YoutubeShortcodeTest.php` | Create | 後端轉換的所有測試 |
| `app/Support/ShortcodeConverter.php` | Modify (L61-78) | `convertYoutube()` 改 public + `start` 屬性 |
| `app/Support/MarkdownRenderer.php` | Modify | render 前 pre-pass youtube shortcode |
| `resources/js/youtube.js` | Create | `parseYoutubeUrl()` / `youtubeShortcode()` pure functions |
| `resources/js/app.js` | Modify (L194-259) | `markdownMediaInsert` 加 ytPrompt 偵測 / embed / 關閉 |
| `resources/views/admin/partials/youtube-embed-prompt.blade.php` | Create | 彈窗 markup（兩個編輯頁共用） |
| `resources/views/admin/posts/edit.blade.php` | Modify (L58-75) | wrapper 加 `relative`、textarea 加 `@input`、include partial |
| `resources/views/admin/pages/edit.blade.php` | Modify (L47-64) | 同上 |

---

### Task 1: ShortcodeConverter — `convertYoutube()` 改 public + `start` 屬性

**Files:**
- Test: `tests/Feature/YoutubeShortcodeTest.php`（新建）
- Modify: `app/Support/ShortcodeConverter.php:61-78`

- [ ] **Step 1: 寫失敗測試**

建立 `tests/Feature/YoutubeShortcodeTest.php`：

```php
<?php

namespace Tests\Feature;

use App\Support\ShortcodeConverter;
use Tests\TestCase;

class YoutubeShortcodeTest extends TestCase
{
    public function test_youtube_shortcode_renders_iframe(): void
    {
        $out = (new ShortcodeConverter())->convertYoutube('{{< youtube id="dQw4w9WgXcQ" >}}');

        $this->assertStringContainsString(
            '<div class="youtube-embed"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"',
            $out
        );
    }

    public function test_youtube_shortcode_with_start_adds_start_param(): void
    {
        $out = (new ShortcodeConverter())->convertYoutube('{{< youtube id="dQw4w9WgXcQ" start="125" >}}');

        $this->assertStringContainsString(
            'src="https://www.youtube.com/embed/dQw4w9WgXcQ?start=125"',
            $out
        );
    }

    public function test_youtube_shortcode_with_invalid_start_ignores_attribute(): void
    {
        $out = (new ShortcodeConverter())->convertYoutube('{{< youtube id="dQw4w9WgXcQ" start="abc" >}}');

        $this->assertStringContainsString('src="https://www.youtube.com/embed/dQw4w9WgXcQ"', $out);
        $this->assertStringNotContainsString('start=', $out);
    }
}
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `./vendor/bin/sail artisan test --filter=YoutubeShortcodeTest`
Expected: FAIL — `Call to private method ... convertYoutube()`（目前是 private）

- [ ] **Step 3: 實作**

修改 `app/Support/ShortcodeConverter.php` 的 `convertYoutube`（原 L61-78），整個方法替換為：

```php
    public function convertYoutube(string $content): string
    {
        // {{< youtubeLite id="abc" label="..." >}} or {{< youtube id="abc" start="125" >}}
        $content = preg_replace_callback(
            '/\{\{<\s*(?:youtubeLite|youtube)\s+([^>]+)\s*>\}\}/',
            function ($m) {
                $attrs = $this->parseAttrs($m[1]);
                $id = $attrs['id'] ?? '';
                if ($id === '') return $m[0];
                $src = 'https://www.youtube.com/embed/' . htmlspecialchars($id);
                $start = $attrs['start'] ?? '';
                if ($start !== '' && ctype_digit($start)) {
                    $src .= '?start=' . $start;
                }
                return "\n\n" . '<div class="youtube-embed"><iframe src="' . $src
                    . '" frameborder="0" allowfullscreen loading="lazy"></iframe></div>' . "\n\n";
            },
            $content
        );

        return $content;
    }
```

（變更點：`private` → `public`、抽出 `$src` 變數、加 `start` 處理。其餘輸出格式不變，避免影響既有匯入內容的轉換結果。）

- [ ] **Step 4: 跑測試確認通過**

Run: `./vendor/bin/sail artisan test --filter=YoutubeShortcodeTest`
Expected: PASS（3 tests）

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/YoutubeShortcodeTest.php app/Support/ShortcodeConverter.php
git commit -m "feat: public convertYoutube with start attribute support"
```

---

### Task 2: MarkdownRenderer 渲染時 pre-pass youtube shortcode

**Files:**
- Test: `tests/Feature/YoutubeShortcodeTest.php`（追加）
- Modify: `app/Support/MarkdownRenderer.php`

- [ ] **Step 1: 寫失敗測試**

在 `tests/Feature/YoutubeShortcodeTest.php` 追加（記得在檔頭 use 區塊加 `use App\Support\MarkdownRenderer;`）：

```php
    public function test_markdown_renderer_converts_youtube_shortcode(): void
    {
        $md = "前面的文字\n\n{{< youtube id=\"dQw4w9WgXcQ\" >}}\n\n後面的文字";

        $html = app(MarkdownRenderer::class)->render($md);

        $this->assertStringContainsString(
            '<div class="youtube-embed"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"',
            $html
        );
        $this->assertStringContainsString('前面的文字', $html);
        $this->assertStringContainsString('後面的文字', $html);
    }

    public function test_markdown_renderer_passes_start_through(): void
    {
        $html = app(MarkdownRenderer::class)->render('{{< youtube id="dQw4w9WgXcQ" start="90" >}}');

        $this->assertStringContainsString('?start=90', $html);
    }
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `./vendor/bin/sail artisan test --filter=YoutubeShortcodeTest`
Expected: FAIL — 新兩個測試找不到 iframe（shortcode 被當字面文字輸出）

- [ ] **Step 3: 實作**

修改 `app/Support/MarkdownRenderer.php`：

加 property 與初始化（constructor 內最後一行 `$this->converter = ...` 之後加一行）：

```php
    private MarkdownConverter $converter;
    private ShortcodeConverter $shortcodes;
```

constructor 結尾：

```php
        $this->converter = new MarkdownConverter($environment);
        $this->shortcodes = new ShortcodeConverter();
```

`render()` 改為：

```php
    public function render(string $markdown): string
    {
        // 編輯器插入的 {{< youtube ... >}} 在渲染時轉成 iframe。
        // 完整的 ShortcodeConverter 只在 Hugo 匯入時跑，這裡僅 pre-pass youtube。
        $markdown = $this->shortcodes->convertYoutube($markdown);

        return (string) $this->converter->convert($markdown);
    }
```

（`ShortcodeConverter` 與 `MarkdownRenderer` 同在 `App\Support` namespace，不需 use。）

- [ ] **Step 4: 跑測試確認通過**

Run: `./vendor/bin/sail artisan test --filter=YoutubeShortcodeTest`
Expected: PASS（5 tests）

- [ ] **Step 5: 跑完整測試確認沒壞別的**

Run: `./vendor/bin/sail artisan test`
Expected: 全綠（除已知 pre-existing 的 ExampleTest 失敗）

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/YoutubeShortcodeTest.php app/Support/MarkdownRenderer.php
git commit -m "feat: render-time youtube shortcode pre-pass in MarkdownRenderer"
```

---

### Task 3: `parseYoutubeUrl` / `youtubeShortcode` pure functions

**Files:**
- Create: `resources/js/youtube.js`

repo 無 JS 測試框架（spec 決議不引入）；`package.json` 是 `"type": "module"`，用 node 一行指令驗證 pure function。

- [ ] **Step 1: 建立 `resources/js/youtube.js`**

```js
/**
 * Parse a pasted string that is exactly one YouTube URL.
 * Supports youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID
 * (optionally with www. / m. host prefix and extra query params like si=).
 * Returns { id, start } or null. `start` is the t= timestamp in total
 * seconds (0 when absent or unparseable).
 */
export function parseYoutubeUrl(text) {
    const trimmed = (text ?? '').trim();
    if (!trimmed || /\s/.test(trimmed)) return null; // 夾在長文字中的 URL 不算

    let url;
    try {
        url = new URL(trimmed);
    } catch {
        return null;
    }
    if (url.protocol !== 'https:' && url.protocol !== 'http:') return null;

    const host = url.hostname.replace(/^(www\.|m\.)/, '');
    let id = null;
    if (host === 'youtu.be') {
        id = url.pathname.slice(1).split('/')[0] || null;
    } else if (host === 'youtube.com') {
        if (url.pathname === '/watch') {
            id = url.searchParams.get('v');
        } else if (url.pathname.startsWith('/shorts/')) {
            id = url.pathname.split('/')[2] || null;
        }
    }
    if (!id || !/^[A-Za-z0-9_-]{11}$/.test(id)) return null;

    return { id, start: parseTimestamp(url.searchParams.get('t')) };
}

// "90" / "90s" / "2m5s" / "1h2m3s" → 總秒數；無法解析 → 0
function parseTimestamp(t) {
    if (!t) return 0;
    if (/^\d+s?$/.test(t)) return parseInt(t, 10);
    const m = t.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/);
    if (!m) return 0;
    return (+(m[1] ?? 0)) * 3600 + (+(m[2] ?? 0)) * 60 + (+(m[3] ?? 0));
}

/** Build the Hugo-style shortcode stored in markdown. */
export function youtubeShortcode({ id, start }) {
    return start > 0
        ? `{{< youtube id="${id}" start="${start}" >}}`
        : `{{< youtube id="${id}" >}}`;
}
```

- [ ] **Step 2: 用 node 驗證（在 repo 根目錄、host 上跑即可，不需 sail）**

```bash
node -e "
import('./resources/js/youtube.js').then(({ parseYoutubeUrl, youtubeShortcode }) => {
    const eq = (a, b, msg) => {
        const ja = JSON.stringify(a), jb = JSON.stringify(b);
        if (ja !== jb) { console.error('FAIL', msg, ja, '!==', jb); process.exitCode = 1; }
        else console.log('ok', msg);
    };
    eq(parseYoutubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'), { id: 'dQw4w9WgXcQ', start: 0 }, 'watch');
    eq(parseYoutubeUrl('https://youtu.be/dQw4w9WgXcQ?si=xxx&t=90'), { id: 'dQw4w9WgXcQ', start: 90 }, 'youtu.be + si + t');
    eq(parseYoutubeUrl('https://m.youtube.com/watch?v=dQw4w9WgXcQ&list=PLx'), { id: 'dQw4w9WgXcQ', start: 0 }, 'm. + playlist 忽略');
    eq(parseYoutubeUrl('https://www.youtube.com/shorts/dQw4w9WgXcQ'), { id: 'dQw4w9WgXcQ', start: 0 }, 'shorts');
    eq(parseYoutubeUrl('https://youtu.be/dQw4w9WgXcQ?t=2m5s'), { id: 'dQw4w9WgXcQ', start: 125 }, 't=2m5s');
    eq(parseYoutubeUrl('https://youtu.be/dQw4w9WgXcQ?t=1h2m3s'), { id: 'dQw4w9WgXcQ', start: 3723 }, 't=1h2m3s');
    eq(parseYoutubeUrl('看這個 https://youtu.be/dQw4w9WgXcQ 超讚'), null, '夾在文字中不觸發');
    eq(parseYoutubeUrl('https://example.com/watch?v=dQw4w9WgXcQ'), null, '非 YouTube');
    eq(parseYoutubeUrl('https://www.youtube.com/live/dQw4w9WgXcQ'), null, 'live 不支援（spec 範圍外）');
    eq(parseYoutubeUrl('not a url'), null, '非 URL');
    eq(youtubeShortcode({ id: 'dQw4w9WgXcQ', start: 0 }), '{{< youtube id=\"dQw4w9WgXcQ\" >}}', 'shortcode 無 start');
    eq(youtubeShortcode({ id: 'dQw4w9WgXcQ', start: 125 }), '{{< youtube id=\"dQw4w9WgXcQ\" start=\"125\" >}}', 'shortcode 有 start');
});
"
```

Expected: 全部印 `ok ...`，exit code 0。任一 FAIL → 修 `youtube.js` 再跑。

- [ ] **Step 3: Commit**

```bash
git add resources/js/youtube.js
git commit -m "feat: parseYoutubeUrl/youtubeShortcode pure functions"
```

---

### Task 4: Alpine 貼上偵測 + 彈窗

**Files:**
- Modify: `resources/js/app.js`（檔頭 import 區 + `markdownMediaInsert` L194-259）
- Create: `resources/views/admin/partials/youtube-embed-prompt.blade.php`
- Modify: `resources/views/admin/posts/edit.blade.php:58-75`
- Modify: `resources/views/admin/pages/edit.blade.php:47-64`

- [ ] **Step 1: `app.js` 檔頭加 import**

在 `import Alpine from 'alpinejs';` 之後加：

```js
import { parseYoutubeUrl, youtubeShortcode } from './youtube';
```

- [ ] **Step 2: 擴充 `markdownMediaInsert`**

在 `markdownMediaInsert` 回傳物件中，`dragging: false,` 之後加一個 state：

```js
        ytPrompt: null, // { url, id, start } — 待確認的 YouTube embed
```

把整個 `handlePaste` 方法（原 L206-211）替換為：

```js
        handlePaste(event) {
            const files = Array.from(event.clipboardData?.files ?? []);
            if (files.length) {
                event.preventDefault();
                this.handleFiles(files);
                return;
            }
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
```

在 `replaceText` 方法之後（物件結尾 `};` 之前）加兩個方法：

```js
        embedYoutube() {
            if (!this.ytPrompt) return;
            const ta = this.$refs.body;
            const idx = ta.value.indexOf(this.ytPrompt.url);
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
```

（連續貼兩條連結：第二次 paste 直接覆寫 `ytPrompt`，舊的視同放棄 — 符合 spec。）

- [ ] **Step 3: 建立彈窗 partial**

建立 `resources/views/admin/partials/youtube-embed-prompt.blade.php`：

```blade
{{-- YouTube 貼上偵測彈窗 — 須置於 x-data="markdownMediaInsert()" 且 class 含 relative 的容器內 --}}
<div x-show="ytPrompt" x-cloak
    @keydown.escape.window="dismissYtPrompt()"
    @click.outside="dismissYtPrompt()"
    class="absolute bottom-3 left-3 z-10 flex items-center gap-3 bg-card border border-line rounded-md shadow-lg px-3 py-2 text-sm">
    <span class="text-ink-3">📺 偵測到 YouTube 連結</span>
    <button type="button" @click="embedYoutube()"
        class="bg-accent text-white px-2.5 py-1 rounded text-xs font-medium hover:bg-accent-ink">Embed 影片</button>
    <button type="button" @click="dismissYtPrompt()"
        class="text-xs text-ink-3 hover:text-accent">保留連結</button>
</div>
```

- [ ] **Step 4: 接到兩個編輯頁**

`resources/views/admin/posts/edit.blade.php`（L58-75 區塊）做三個小改動：

1. `<div x-data="markdownMediaInsert()">` → `<div x-data="markdownMediaInsert()" class="relative">`
2. textarea 的 `@paste="handlePaste($event)"` 下一行加 `@input="dismissYtPrompt()"`
3. `<p x-show="error" ...>` 那行之後、`</div>` 之前加：

```blade
                @include('admin.partials.youtube-embed-prompt')
```

`resources/views/admin/pages/edit.blade.php`（L47-64 區塊）做完全相同的三個改動（該頁結構與 posts 相同：同樣的 `x-data` div、同樣的 textarea 屬性、同樣的 error `<p>`）。

- [ ] **Step 5: Build**

Run: `./vendor/bin/sail npm run build`
Expected: Vite build 成功，無錯誤

- [ ] **Step 6: Commit**

```bash
git add resources/js/app.js resources/views/admin/partials/youtube-embed-prompt.blade.php resources/views/admin/posts/edit.blade.php resources/views/admin/pages/edit.blade.php
git commit -m "feat: youtube paste detection prompt with embed shortcode insert"
```

---

### Task 5: 手動驗證 + 收尾

**Files:** 無新檔案（驗證 + 可能的微調）

- [ ] **Step 1: 啟動環境**

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run build
```

- [ ] **Step 2: 瀏覽器手動驗證（後台 /admin 任一文章編輯頁）**

逐項確認：

| # | 操作 | 預期 |
|---|---|---|
| 1 | 貼上 `https://www.youtube.com/watch?v=dQw4w9WgXcQ` | 連結照常貼入 + 彈窗出現 |
| 2 | 點「Embed 影片」 | URL 被替換成 `{{< youtube id="dQw4w9WgXcQ" >}}`，游標在其後 |
| 3 | 貼上 `https://youtu.be/dQw4w9WgXcQ?si=x&t=90` → Embed | 替換成 `{{< youtube id="dQw4w9WgXcQ" start="90" >}}` |
| 4 | 貼上連結 → 點「保留連結」 | 彈窗關閉，連結原樣 |
| 5 | 貼上連結 → 按 Esc | 同上 |
| 6 | 貼上連結 → 點編輯器外面 | 同上 |
| 7 | 貼上連結 → 繼續打字 | 彈窗消失，連結原樣 |
| 8 | 貼上含連結的長句（前後有字） | 不彈窗 |
| 9 | 貼上截圖（剪貼簿圖片） | 既有上傳流程不受影響 |
| 10 | Embed 後儲存 → 前台預覽 | 文章內出現可播放的 YouTube 影片（16:9） |
| 11 | 帶 `start` 的文章前台播放 | 影片從該秒數開始 |
| 12 | pages 編輯頁重複 1-2 | 行為一致 |

- [ ] **Step 3: 最終全測試**

Run: `./vendor/bin/sail artisan test`
Expected: 全綠（除已知 ExampleTest）

- [ ] **Step 4: 有微調則 commit**

```bash
git add -A && git commit -m "fix: youtube paste embed polish from manual verification"
```

（無微調則跳過。）
