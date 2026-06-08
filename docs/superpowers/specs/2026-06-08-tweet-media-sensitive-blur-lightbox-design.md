# Tweet 媒體:敏感模糊 + 全螢幕檢視

日期:2026-06-08

## 目標

兩個面向公開 Tweet 媒體的功能:

1. **敏感模糊(馬賽克)**:作者可逐張把 Tweet 媒體標記為「敏感」。公開頁面預設將這些媒體模糊處理並蓋上提示,使用者點擊後才揭露,避免直接看到可能引起不適的內容。
2. **全螢幕檢視**:使用者點擊 Tweet 的圖片時,以全螢幕疊層檢視原圖。

範圍僅限 **Tweet 媒體**(`tweet.media` JSON 陣列),不含 Markdown 內文圖片與文章封面。

## 資料模型

`tweets.media` 為 JSON 陣列(已存在,`array` cast)。每個項目目前為 `{ path, type, alt }`,新增可選欄位:

- `sensitive`:boolean,預設 `false`。標記該媒體是否需要模糊。

JSON 欄位,無需 migration。舊資料缺少此鍵時視為 `false`。

## Admin 編輯器

`resources/views/admin/tweets/edit.blade.php`(`tweetMediaUpload` Alpine 元件,`resources/js/app.js`):

- `tweetMediaUpload` 的 `items` 對應與初始化納入 `sensitive`(預設 `false`)。
- 每個媒體縮圖角落加一個「敏感」toggle(checkbox / 小按鈕),綁定 `item.sensitive`。
- 新增 hidden input `media[${i}][sensitive]`,送出 `item.sensitive ? '1' : '0'`(或省略未勾選者)。

驗證(四個檔皆加 `media.*.sensitive => nullable|boolean`):

- `app/Http/Requests/Admin/Tweet/StoreRequest.php`
- `app/Http/Requests/Admin/Tweet/UpdateRequest.php`
- `app/Http/Requests/Api/Tweet/StoreRequest.php`
- `app/Http/Requests/Api/Tweet/UpdateRequest.php`

注意:後端儲存時需把 `sensitive` 正規化為 boolean(避免存成字串 `"1"`),保持 media 陣列乾淨。

## 公開顯示

`resources/views/components/tweet-card.blade.php` — 三種版型(1 / 2 / 3+ 媒體)都套用。

每張媒體包進一個輕量 wrapper,行為:

- **一般 image**:點擊 → 開啟共用全螢幕 lightbox。
- **sensitive image**:預設套用重度 `blur()` 並蓋上中央提示「敏感內容 · 點擊顯示」。
  - 第一次點擊 → 揭露(移除模糊與提示)。
  - 揭露後再點擊 → 開啟 lightbox(同一般 image)。
- **sensitive video**:預設模糊 + 提示;揭露後使用原生 `controls`(不進 lightbox)。
- **一般 video**:維持現狀(原生 controls)。

reveal 狀態不持久化:每次頁面載入重置為模糊(符合「真的想看再點開」)。

## 前端邏輯(`resources/js/app.js`)

- **Lightbox**:Alpine store(`Alpine.store('lightbox', …)`),單一 fixed 全螢幕疊層,在 `layouts/public.blade.php` 注入一次。
  - `open(src, alt)`:設定來源並顯示。
  - 關閉:點背景 / 按 Esc / 點 ✕。
  - 圖片以 `max-w`/`max-h` 限制在視窗內、置中、`object-contain`。
  - 開啟時鎖背景捲動(`overflow: hidden`),關閉時還原。
- **每張媒體 reveal**:用 inline Alpine `x-data="{ revealed: false }"`(或極小元件)控制模糊 class 與點擊分流。

## CSS(`resources/css/app.css`)

- 模糊樣式(例:`filter: blur(18px)`)與覆蓋提示層樣式。
- lightbox 疊層樣式(背景遮罩、置中、關閉鈕)。
- 模糊狀態下游標 `cursor: pointer`,提示可點。

## 測試

- **後端**:延伸既有 Tweet media 測試(參考 `TweetAdminMediaTest`),驗證 `sensitive` 能存入並正規化為 boolean、可清除;API Store/Update 同樣接受 `sensitive`。
- **前端/視圖**:Blade 渲染測試確認 sensitive 媒體輸出帶有模糊 wrapper / 提示標記;一般媒體帶有 lightbox 觸發屬性。
- 互動(reveal / lightbox 開關)為 Alpine 行為,以視圖屬性存在性驗證,不做瀏覽器端 E2E。

## 非目標(YAGNI)

- 不處理 Markdown 內文圖片、文章封面。
- 不做整篇 Tweet 等級的敏感開關(僅逐張)。
- 不持久化使用者已揭露的偏好。
- lightbox 不做縮放/輪播,只單張全螢幕檢視。
