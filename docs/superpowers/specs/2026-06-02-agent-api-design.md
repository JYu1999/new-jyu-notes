# Agent API（P3）— 設計文件

日期：2026-06-02
分支：feature/agent-api-posts-tweets（批 1）

## 背景

延續 P2（Sanctum token + abilities）與 Todos/Changelog 的 API，開放一套完整的內容管理 API，
讓本地 AI Agent 能以 scoped token 執行後台的內容操作：posts、碎念（tweets）、categories、tags、media。
落實使用者情境 2-3：agent 用「能建草稿但不能發布」的 token 翻譯文章、上傳封面、建立翻譯版草稿，
最後由使用者自己發布。

現況：每個資源都有專屬 Service（`PostService`/`TweetService`/`CategoryService`/`TagService`/`MediaService`），
含 `create/update/delete(或 softDelete)/updateStatus/createTranslation` 等方法——API 控制器是薄包，直接複用，不重複邏輯。
P2 的 ability middleware（`ability:<x>`）與 `config/abilities.php` 矩陣已就緒；`/api/todos` 已示範此模式。
尚無 API Resource 類別。

## 目標

- posts、tweets、categories、tags、media 的 CRUD API，每端點以 `ability:<resource>:<action>` 把關。
- 發布為**獨立端點**、需 `*:publish`；一般 update **不能**改狀態——agent 只能建草稿，發布由 owner。
- 翻譯：在既有 group 下建立新 locale 草稿。
- 媒體：multipart 上傳，path 共用。
- 統一回應（API Resource + 分頁）、統一錯誤、rate limiting。

## 非目標（YAGNI）

- restore（軟刪還原）端點——軟刪足夠，需要再加。
- 公開讀取 API（這套是 token-only 的管理 API）。
- pages 資源的 API（本期不含；需要再開）。
- Webhook、批次匯入、GraphQL。

## 已確認決策

1. **分三批**（一份 spec，三個 plan/merge）：① posts + tweets ② categories + tags ③ media。
2. **發布獨立端點**（`POST /api/{resource}/{id}/publish`，需 `*:publish`）；`PATCH update` 不接受 status/published_at。
3. 引入 **API Resource 層**，list 端點**分頁**；**讀取回傳所有狀態**（含 draft/hidden）。
4. API **不開 restore**。

## API 共通慣例

- **認證**：`auth:sanctum`；路由群組套 `throttle:60,1`（沿用既有 `routes/api.php` 群組）。
- **授權**：每條路由掛 `ability:<resource>:<action>`，字串出自 `config/abilities.php`（單一真實來源）。
- **回應**：成功一律經 API Resource → `{ "data": ... }`；
  - 單筆：`{ "data": {...} }`
  - 列表：Laravel paginator → `{ "data": [...], "links": {...}, "meta": {...} }`
  - 建立：201；更新：200；刪除：204（空 body，`noContent()`）；publish/translation：200 回該資源。
- **錯誤**：401（未認證）/ 403（缺 ability）/ 404（找不到，route-model-binding）/ 422（驗證，JSON）。
- **讀取範圍**：管理 API，index/show 回傳所有狀態（不套公開的 published-only scope）。
- **驗證**：每資源用獨立的 API FormRequest（`app/Http/Requests/Api/<Resource>/StoreRequest|UpdateRequest`），
  規則對齊既有 admin FormRequest；UpdateRequest 用 `sometimes` 支援 partial PATCH（同 todos 的修正）。
- **控制器**：`app/Http/Controllers/Api/<Resource>Controller`，薄包，注入既有 Service。

## 資源端點與權限

### posts（批 1）
| 方法 | 路徑 | ability | 行為 |
|---|---|---|---|
| GET | `/api/posts` | posts:read | 分頁列出（所有狀態），可選 `?locale=` 篩選 |
| GET | `/api/posts/{post}` | posts:read | 單筆 |
| POST | `/api/posts` | posts:create | 建立（`PostService::create`）。可帶 `post_group_id` 建翻譯、`tag_ids`/`category_ids`。狀態固定為 draft（不接受 status） |
| PATCH | `/api/posts/{post}` | posts:update | 更新內容/metadata（title, slug, excerpt, body, cover_image_path, is_featured, tag_ids, category_ids）。**不接受 status / published_at** |
| DELETE | `/api/posts/{post}` | posts:delete | 軟刪（`PostService::softDelete`） |
| POST | `/api/posts/{post}/translations` | posts:create | `{locale}` → `PostService::createTranslation` 在同 group 建該 locale 草稿 |
| POST | `/api/posts/{post}/publish` | posts:publish | `PostService::updateStatus($post, STATUS_PUBLISHED)`（設 published + published_at） |

Store 欄位（對齊 admin Post StoreRequest，去除 status/published_at）：
`post_group_id?`, `locale`(required), `title`(required), `slug?`, `excerpt?`, `body`(required),
`cover_image_path?`, `is_featured?`(bool), `tag_ids?[]`, `category_ids?[]`, `categories_order?[]`。

### tweets / 碎念（批 1）
同 posts 一套（`/api/tweets...`，含 `translations` 與 `publish`）。
Store 欄位（對齊 admin Tweet StoreRequest，去除 status/published_at）：
`tweet_group_id?`, `locale`(required), `body`(required, max:2000),
`media?[]`（每項 `{path(required), type(image|video), alt?}`，max 4）, `tag_ids?[]`。
碎念無 is_featured；`publish` 走 `TweetService::updateStatus`。

### categories（批 2）
| 方法 | 路徑 | ability |
|---|---|---|
| GET `/api/categories` | categories:read（分頁） |
| GET `/api/categories/{category}` | categories:read |
| POST `/api/categories` | categories:create |
| PATCH `/api/categories/{category}` | categories:update |
| DELETE `/api/categories/{category}` | categories:delete（硬刪，`CategoryService::delete`） |

Store 欄位（對齊 admin）：`cover_image_path?`, `sort_method?`,
`translations[]{ locale, name(required), slug?, description? }`（min 1，locale 不重複）。

### tags（批 2）
CRUD（read/create/update/delete，硬刪）。Store：`color?`(hex), `translations[]{ locale, name(required), slug? }`。

### media（批 3）
| 方法 | 路徑 | ability |
|---|---|---|
| GET `/api/media` | media:read（分頁，`MediaService::paginate`） |
| POST `/api/media` | media:create（**multipart** `file`，`MediaService::upload($file, $request->user())`，回 `{id, path, url, mime_type, width, height}`） |
| DELETE `/api/media/{id}` | media:delete（`MediaService::delete`） |

## API Resource 類別

`app/Http/Resources/`：`PostResource`、`TweetResource`、`CategoryResource`、`TagResource`、`MediaResource`。
- 只輸出該資源對 agent 有意義的欄位（含關聯：post 的 tags/categories、translations 的 locale/slug；category/tag 的 translations）。
- 不外洩無關內部欄位。posts 含 `status`、`published_at`、`post_group_id`（agent 需知道是否已發布、group 以建翻譯）。

## 資料流（情境 2-3）

1. 你在後台完成中文文章草稿（或經 API 建立）。
2. 後台產生「翻譯任務」token：`posts:read/create/update`、`media:read/create`（**不含 `posts:publish`**）。
3. Agent：`GET /api/posts/{id}` 讀原文 → `POST /api/media` 上傳封面 → `POST /api/posts/{id}/translations {locale:"en"}` 建英文草稿 → `PATCH /api/posts/{enId}` 填譯文與 `cover_image_path`。
4. Agent 想 publish → `POST /api/posts/{enId}/publish` → **403**（token 無 `posts:publish`）。
5. 你確認後在後台或用含 publish 的 token 發布。

## 測試策略

- 每端點：未認證 401、缺 ability 403、有 ability 成功；CRUD 正確；index 分頁與 Resource 形狀。
- **發布隔離**（核心）：`posts:update` token 打 `/publish` → 403；`posts:publish` token → 200 且 status=published、published_at 已設；`PATCH update` 帶 status 不會改變狀態。
- **翻譯**：`/translations` 在同 `post_group_id` 下建出新 locale 草稿。
- posts create/update 正確同步 tag_ids/category_ids。
- media：multipart 上傳成功、回傳 path/url（測試用 `Storage::fake` + 設定 media disk）。
- 既有測試不回歸（P2 abilities、todos、changelog）。

## 安全

- 全部 token-only（`auth:sanctum` + 逐端點 ability），沿用 P2。
- 發布權限隔離確保 agent 不能擅自發布。
- media 上傳沿用既有 `MediaService` 驗證（型別/大小，見 admin Media StoreRequest）。
- rate limiting `throttle:60,1`。

## 風險與緩解

- **abilities 已含全部資源**：`config/abilities.php` 已定義 posts/tweets/categories/tags/media 的動作（P2 既有），P3 不需改矩陣，只是開始「使用」這些 ability 字串。確認端點掛的 ability 與矩陣完全一致。
- **API Resource 引入點**：第一次加 Resource 層；批 1 先為 posts/tweets 建立慣例，批 2/3 沿用。
- **partial PATCH**：UpdateRequest 用 `sometimes`，避免要求全欄位（重蹈 todos 的覆轍）。
- **publish 與 update 的邊界**：update 必須完全忽略/拒絕 status 欄位，否則 publish 權限形同虛設——以測試守住。
