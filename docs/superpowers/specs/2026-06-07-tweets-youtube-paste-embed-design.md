# Tweets 貼上 YouTube 連結詢問 Embed — Design

日期：2026-06-07
狀態：已確認

## 問題

posts/pages 編輯器已有「貼上 YouTube 連結 → 詢問 Embed」功能（見 `2026-06-07-youtube-paste-embed-design.md`），tweets 編輯器沒有。tweets 的 body textarea 在 `tweetMediaUpload` Alpine scope 內，沒有掛 `markdownMediaInsert`，因此貼上偵測不生效。

## 現況確認（已實測）

- **後端零缺口**：`tweet-card.blade.php` 用 `MarkdownRenderer->render()` 渲染 body，youtube shortcode pre-pass 已生效；body wrapper 有 `prose-blog` class，`.youtube-embed` 16:9 CSS 直接適用
- **渲染位置正確**：embed 出現在 shortcode 所在位置（非固定最上方），單篇頁與列表頁皆已用本機測試 tweet 截圖驗證（local tweet id 104）

## 範圍

- ✅ tweets 編輯頁 body textarea 的貼上偵測 + 詢問彈窗（行為與 posts/pages 完全一致）
- ✅ 重構：把 YouTube 偵測/嵌入邏輯從 `markdownMediaInsert` 抽成共用 behavior（DRY）
- ❌ 後端零改動（渲染與 CSS 已生效）
- ❌ 不動 `tweetMediaUpload` 的媒體上傳邏輯
- ❌ 不處理 tweets textarea 的檔案貼上（現況本來就沒有，不在本案範圍）

## 設計

### 1. `resources/js/app.js` 重構 + 新元件

- 抽共用物件 `youtubePasteBehavior`（module-level const，非 window）：
  - state：`ytPrompt: null`
  - `detectYoutubePaste(event)`：現行 `handlePaste` 的文字偵測分支（parseYoutubeUrl → setTimeout 設 ytPrompt）
  - `embedYoutube()`、`dismissYtPrompt()`：原樣搬移
- `markdownMediaInsert`：spread `...youtubePasteBehavior`，`handlePaste` 保留檔案分支，文字部分改呼叫 `this.detectYoutubePaste(event)`。行為不變。
- 新增 `window.youtubePastePrompt = () => ({ ...youtubePasteBehavior, handlePaste(event) { this.detectYoutubePaste(event); } })` — 給沒有媒體上傳需求的 textarea 用。

### 2. `admin/tweets/edit.blade.php` 接線

body textarea 外包一層（嵌套在 `tweetMediaUpload` scope 內，Alpine 支援嵌套 x-data）：

```blade
<div class="relative" x-data="youtubePastePrompt()">
    <textarea name="body" ... x-ref="body"
        @paste="handlePaste($event)"
        @input="dismissYtPrompt()">...</textarea>
    @include('admin.partials.youtube-embed-prompt')
</div>
```

既有彈窗 partial 因 state/方法名一致（`ytPrompt`/`embedYoutube`/`dismissYtPrompt`），原封不動重用。

### 3. 行為（與 posts/pages 一致）

- 貼上單一 YouTube URL（watch / youtu.be / shorts，含 t=）→ 照常貼入 + 彈窗
- Embed → URL 替換成 `{{< youtube id="..." [start="N"] >}}`；保留連結 / Esc / 點外面 / 繼續打字 → 關閉
- tweet `maxlength=2000` 不受影響（shortcode 與 URL 長度相當）

## 測試

- 後端零改動 → 既有 PHP 測試不得壞（已知 pre-existing：ExampleTest 失敗與本案無關）
- `parseYoutubeUrl` node 驗證不變（pure function 未動）
- 重構後 `markdownMediaInsert` 行為不變：`sail npm run build` + 手動驗證 posts 編輯頁貼上流程仍正常
- tweets 手動驗證：貼上 → 彈窗 → Embed 替換 → 儲存 → 前台 tweet 卡片播放影片

## 不做的事（YAGNI）

- tweets textarea 的圖片貼上上傳（另案，如有需求）
- 共用 behavior 進一步抽到獨立檔案（目前兩個使用者，留在 app.js 即可）
