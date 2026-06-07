# 貼上 YouTube 連結詢問 Embed — Design

日期：2026-06-07
狀態：已確認

## 問題

在後台編輯器貼 YouTube 連結時，只能以純文字連結呈現。希望貼上時能選擇「Embed 影片」讓觀看者直接在文章內播放，或「保留連結」維持原樣。

關鍵限制：`ShortcodeConverter` 目前**只在 Hugo 匯入時執行**（`app/Console/Commands/ImportFromHugo.php`），渲染時只跑 `MarkdownRenderer`。因此在編輯器插入 `{{< youtube id="..." >}}` 必須補上渲染時轉換，否則觀看者只會看到字面文字。

## 範圍

- ✅ Posts / Pages 後台 body textarea 的貼上偵測 + 詢問彈窗
- ✅ 選 Embed 時插入 Hugo shortcode `{{< youtube id="..." >}}`（帶時間點時加 `start="秒數"`）
- ✅ `MarkdownRenderer` 渲染時 pre-pass 轉換 youtube shortcode
- ✅ URL 格式：`youtube.com/watch?v=`、`youtu.be/`、`youtube.com/shorts/`；保留 `t=` 時間點
- ❌ 不支援 `youtube.com/live/`、`youtube.com/embed/` 連結觸發
- ❌ 不在渲染時跑完整 `ShortcodeConverter::convert()`（只 pre-pass youtube，避免改變既有文章語意）
- ❌ 夾在大段文字中的 URL 不觸發（只有貼上內容 trim 後就是單一 YouTube URL 才問）
- ❌ 不處理 playlist（`watch?v=ID&list=...` 只 embed 單支影片）

## 使用流程

1. 在 body textarea 貼上文字
2. 若 trim 後整段是單一 YouTube URL → **照常貼入原始連結**（不 `preventDefault`，保留原生 undo）
3. 彈出小彈窗：「📺 偵測到 YouTube 連結 [Embed 影片] [保留連結]」
4. 選 **Embed** → 剛貼入的 URL 替換成 `{{< youtube id="VIDEO_ID" >}}`，帶 `t=` 則為 `{{< youtube id="..." start="125" >}}`，游標移到 shortcode 後
5. 選 **保留連結** / Esc / 點外面 / 繼續打字 → 彈窗消失，連結保持原樣

## 設計

### 1. 前端：擴充 `markdownMediaInsert`（`resources/js/app.js`）

- **URL 解析**：抽成 pure function `parseYoutubeUrl(text)` → `{ id, start } | null`
  - 支援 `watch?v=ID`、`youtu.be/ID`、`shorts/ID`（含 `www.` / `m.` 前綴與多餘 query 如 `si=`）
  - `t=` 支援 `123`、`123s`、`2m5s`、`1h2m3s`，換算為總秒數；`t=0` 或缺省 → 不帶 `start`
- **`handlePaste` 增強**：無檔案時讀 `clipboardData.getData('text/plain')`，trim 後丟給 `parseYoutubeUrl`；命中時不攔截貼上，記下原文字串並設 `ytPrompt = { url, id, start }` 顯示彈窗
- **彈窗**：Alpine template，絕對定位於 textarea 容器內（textarea 下緣即可），兩顆按鈕 + Esc / click-outside / 繼續輸入即關閉
- **Embed 替換**：用既有 `replaceText(原URL, shortcode)`；替換後游標移至 shortcode 之後
- **連續貼上**：第二次貼上覆蓋前一個 `ytPrompt`（舊的視同放棄）
- **共用 markup**：彈窗抽成 blade partial，`admin/posts/edit.blade.php` 與 `admin/pages/edit.blade.php` 共用

### 2. 後端：渲染時 youtube shortcode pre-pass

- `ShortcodeConverter::convertYoutube()` 改 `public`，擴充 `start` 屬性：
  - `start` 為純數字（`ctype_digit`）→ iframe src 加 `?start=N`
  - 非法 `start` → 忽略該屬性，仍正常 embed
- `MarkdownRenderer::render()` 在丟給 CommonMark 前先呼叫 `convertYoutube()`
- 既有 CSS `.youtube-embed`（16:9、圓角）直接沿用，零改動

## 測試

- **PHP feature/unit tests**：
  - `ShortcodeConverter::convertYoutube()` 含 `start` → iframe src 帶 `?start=N`
  - 非法 `start`（如 `start="abc"`）→ 忽略屬性仍輸出 iframe
  - `MarkdownRenderer::render()` 處理含 `{{< youtube id="..." >}}` 的 markdown → 輸出 `<div class="youtube-embed"><iframe ...>`
  - 既有測試不得壞（已知 pre-existing：`ExampleTest` 失敗與本案無關）
- **JS**：repo 無 JS 測試框架，`parseYoutubeUrl` 抽 pure function 後以瀏覽器手動驗證：
  - `youtu.be/ID?si=xxx&t=90` → id 正確、start=90
  - `watch?v=ID&list=...` → 只取單支影片
  - `shorts/ID`、`t=2m5s` 換算、非 YouTube 連結不觸發、夾在長文字中不觸發
  - Esc / 點外面 / 保留連結皆維持原連結
- `sail npm run build` 通過

## 不做的事（YAGNI）

- 大段文字中的 URL 偵測
- Playlist embed
- 渲染時跑完整 ShortcodeConverter（alert / x / carousel 等留待之後有需求再開）
- 預覽縮圖（彈窗只顯示文字按鈕）
