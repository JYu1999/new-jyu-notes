# 後台媒體上傳 UX 優化 — Design

日期：2026-06-07
狀態：已確認

## 問題

後台在 Tweets / Posts 加媒體不直覺：

1. **Tweet**：後端已支援 `media` JSON 陣列（最多 4 個 image/video + alt，驗證規則在 `app/Http/Requests/Admin/Tweet/StoreRequest.php` 與 `UpdateRequest.php`），但 `admin/tweets/edit.blade.php` 完全沒有媒體 UI，等於無法從後台加媒體。
2. **Post 內文**：要插圖得先去 `/admin/media` 上傳、手動複製路徑、再自己寫 `![](path)`，流程斷裂。

## 範圍

- ✅ Tweet 表單媒體上傳元件（Twitter 風格）
- ✅ Post / Page 的 Markdown textarea 插圖增強（拖放、貼上、按鈕）
- ❌ 不做共用媒體庫 picker（之後若常需重用舊圖再升級）
- ❌ 不動編輯器本體（Notion 式 WYSIWYG 另案）
- ❌ 後端零改動：`POST /admin/media` 已支援 jpg/jpeg/png/webp/gif/mp4/webm ≤10MB，回傳 `{ id, url, path, mime_type, width, height }`

## 設計

### 1. `tweetMediaUpload` 元件

位置：`admin/tweets/edit.blade.php` body textarea 正下方。Alpine 元件加在 `resources/js/app.js`，與既有 `coverUpload`（`app.js:82`）同風格。

- **State**：`items: [{ path, type, alt }]`，初始值 `old('media', $tweet->media ?? [])`，驗證失敗後可還原。
- **UI**：
  - 媒體 grid（2 欄）：每格縮圖（`type === 'video'` 用 `<video>` 預覽）、右上角「✕」移除、下方 alt text 小輸入框。
  - 未滿 4 個時顯示虛線上傳格：點擊選檔（可多選）+ 拖放。
  - `type` 由 MIME 自動判斷（`video/*` → `video`，其餘 → `image`）。
- **上傳**：`POST /admin/media`；上傳中該格顯示 spinner；失敗顯示錯誤並移除該格。
- **送出**：`x-for` 渲染 hidden inputs `media[i][path]` / `media[i][type]` / `media[i][alt]` 隨表單 POST。
- 上傳進行中 disable 儲存按鈕，避免送出半成品。
- 前端擋超過 4 個（後端驗證仍是最後防線）。

### 2. `markdownMediaInsert` 元件

位置：`admin/posts/edit.blade.php` 與 `admin/pages/edit.blade.php` 的 body textarea。

- **三種觸發**：
  1. textarea 上方工具列「插入媒體」按鈕（開檔案選擇器）
  2. 拖放檔案到 textarea（顯示 highlight 邊框提示）
  3. 貼上剪貼簿圖片（截圖直接 Ctrl+V）
- **流程**：游標位置先插占位文字 `![上傳中…]()` → 上傳完成替換為：
  - 圖片：`![](完整URL)`（用 response 的 `url`；DB body 為標準 Markdown，前台 `league/commonmark` 直接渲染）
  - 影片：`<video class="local-video" controls src="URL" preload="metadata"></video>`（與 `ShortcodeConverter::convertLocalVideo` 產出的前台樣式一致）
  - 失敗：移除占位文字並顯示錯誤。

### 3. 錯誤處理

沿用 `coverUpload` 模式：元件內 `error` state 顯示紅字訊息；fetch 非 2xx 即丟錯。

## 測試

- 後端零改動 → 既有 feature tests 不得壞。
- 補「Tweet 表單送出 media 陣列正確持久化」feature test（若已存在則略過）。
- 前端：`sail npm run build` + 瀏覽器手動驗證 — 拖放、貼上、4 格上限、alt 持久化、驗證失敗後 old() 還原、影片預覽。
- 已知 pre-existing：`ExampleTest` 失敗與本案無關。

## 不做的事（YAGNI）

- 媒體拖曳排序（新增順序即顯示順序，之後有需要再加）
- 上傳進度條（10MB 上限，spinner 即可）
- 圖片裁切 / 壓縮
