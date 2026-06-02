# Changelog & Todos（輕量專案管理）— 設計文件

日期：2026-06-02
分支：feature/changelog-todos

## 背景

使用者想要一個產品式的公開 **Changelog**（「我做了什麼新功能就放上去」），顯示在公開導覽列。
背後設計成一個**簡易型專案管理工具**：可 CRUD TODO、設優先級，完成後記錄完成時間，
勾選後即納入公開 changelog。TODO 同時要能在**後台**與**API**（給本地 AI Agent）操作，
且納入既有的 API Token 權限系統（P2）。

這是一個獨立子專案，排在 **P3（Agent API）之前**。它一次涵蓋「後台 CRUD + 公開頁 + API + token 權限」，
也是 P3 該模式的試金石。

## 目標

- 後台可 CRUD TODO（title、描述、優先級、狀態、是否納入 changelog）。
- 完成 TODO 記錄 `completed_at`。
- 公開 `/changelog` 頁：顯示「已完成且勾選納入」的項目，依完成日期分組、新到舊。英文單一語言。
- 公開導覽列加入 Changelog 連結。
- TODO 的 CRUD 同時提供 API，掛上 P2 的 ability middleware；abilities 矩陣新增 `todos` 資源。
- 補上 P2 最終審查建議的 API rate limiting。

## 非目標（YAGNI）

- 多語言 changelog（明確只要英文）。
- 看板拖拉、子任務、指派、留言、標籤等完整 PM 功能。
- TODO 的軟刪除/版本歷史。
- 公開頁的分頁（初期資料量小；如需要再加）。

## 決策摘要（已與使用者確認）

1. **Changelog 可見性**：只有 `show_in_changelog = true` 且 `status = done` 的 TODO 才公開。
2. **狀態**：最簡兩態 `open` / `done`。轉 `done` 設 `completed_at`，轉回 `open` 清空。
3. **優先級**：`low` / `medium` / `high`，預設 `medium`。
4. **Changelog 位置**：`/changelog`，在 locale 前綴之外，純英文。
5. **`show_in_changelog` 預設 false**（逐筆勾選才公開）。
6. **API 資源命名**：`todos`，動作 read/create/update/delete。

## 資料模型：`todos` 表

| 欄位 | 型別 | 說明 |
|---|---|---|
| `id` | bigint PK | |
| `title` | string(255) | 英文；即 changelog 顯示的那一行 |
| `description` | text nullable | 內部備註，不顯示於 changelog |
| `priority` | string(10) | `low` / `medium` / `high`，預設 `medium` |
| `status` | string(10) | `open` / `done`，預設 `open` |
| `show_in_changelog` | boolean | 預設 `false` |
| `completed_at` | timestamp nullable | 轉 done 設為 now()，轉回 open 清空 |
| `created_at` / `updated_at` | timestamps | |

`App\Models\Todo` 常數：`STATUS_OPEN`、`STATUS_DONE`、`PRIORITY_LOW/MEDIUM/HIGH`。
casts：`show_in_changelog => boolean`、`completed_at => datetime`。

## 元件

### 1. TodoService（`app/Services/TodoService.php`）
- `create(array $data): Todo`
- `update(Todo $todo, array $data): Todo`
- `delete(Todo $todo): void`
- 完成邏輯集中於此：當 `status` 從非 done → `done` 時設 `completed_at = now()`；
  從 `done` → `open` 時設 `completed_at = null`。其他更新不動 `completed_at`。
- changelog 查詢：`changelogGrouped(): Collection` —
  回傳 `status=done` 且 `show_in_changelog=true` 的 todos，依 `completed_at` 的「日期」分組，
  外層日期新到舊、組內 `completed_at` 新到舊。

### 2. 後台
- 路由（admin 群組，`auth` + `role:admin`）：
  - `GET /admin/todos`（index）、`POST /admin/todos`（store）、
    `PUT /admin/todos/{todo}`（update）、`DELETE /admin/todos/{todo}`（destroy）。
- `Admin\TodoController` + `StoreRequest`/`UpdateRequest`（驗證 title 必填、priority/status enum、
  show_in_changelog boolean、description 可空）。
- 視圖 `admin/todos/index.blade.php`：列表（標題、優先級、狀態、是否在 changelog、完成時間）+
  新增/編輯表單。沿用既有 admin 視圖樣式（bg-card/border-line/...）。
- 側邊欄 `$items` 新增 `['route' => 'admin.todos.index', 'label' => 'Todos', 'group' => 'todos']`。

### 3. 公開 Changelog 頁
- 路由：`GET /changelog`（**不在** `{locale}` 群組內）→ `Public\ChangelogController@index`，名稱 `changelog`。
- 視圖 `public/changelog.blade.php`：依 `TodoService::changelogGrouped()` 輸出
  日期標題（格式如 `May 19, 2026`，用 `completed_at->format('F j, Y')`）+ 項目 bullet（todo title）。
- 導覽列：`resources/views/layouts/public.blade.php` 桌機 nav 與手機選單各加一個
  `Changelog` 連結，指向 `url('/changelog')`，標籤硬編英文 "Changelog"（不本地化）。
  active 樣式用 `request()->routeIs('changelog')`。

### 4. API
- `config/abilities.php` 新增：`'todos' => ['read', 'create', 'update', 'delete']`。
  （後台 token 權限勾選格會自動長出 todos 列；`Abilities::all()` 數量隨之 +4。）
- `routes/api.php` 新增（皆 `auth:sanctum` + 對應 `ability:` middleware）：
  - `GET /api/todos`（`ability:todos:read`）
  - `POST /api/todos`（`ability:todos:create`）
  - `GET /api/todos/{todo}`（`ability:todos:read`）
  - `PATCH /api/todos/{todo}`（`ability:todos:update`）
  - `DELETE /api/todos/{todo}`（`ability:todos:delete`）
- `Api\TodoController`：回傳 JSON（todo 欄位）；store/update 用 API 專用 FormRequest 驗證
  （與後台同規則，獨立類別避免耦合）；複用 `TodoService` 的完成邏輯。
- **Rate limiting**：對認證後的 API 路由套用 `throttle`（預設 60 次/分，沿用 Laravel `throttle:api`
  或在 `bootstrap/app.php`/`AppServiceProvider` 定義名為 `api` 的 limiter）。`/api/me` 與 `/api/todos/*` 一併納入。

## 資料流（changelog 從 TODO 到公開頁）

1. 後台或 Agent 建立 TODO（`open`）。
2. 完成時把 `status` 設為 `done` → `TodoService` 記 `completed_at`。
3. 勾選 `show_in_changelog`。
4. 公開 `/changelog` 自動依完成日期列出該項。

## 錯誤處理

- API：未認證 401；缺 ability 403；驗證錯誤 422（JSON）；找不到 todo 404。
- 後台：非 admin → 403（既有 `EnsureUserRole`）；驗證錯誤回表單。
- 公開頁：無資料時顯示「No entries yet.」。

## 測試策略

- `TodoService`：open→done 設 completed_at；done→open 清空；單純改 title 不動 completed_at；
  `changelogGrouped` 只含 done+flagged、依日期分組且新到舊。
- 公開頁：`/changelog` 200；顯示已完成+勾選項、依日期分組；未勾選/未完成不出現；空狀態文案。
- 後台：admin 可 CRUD + 標記完成；非 admin 403。
- API：每端點 ability 把關（有權限可用、無權限 403、未認證 401）；CRUD 正確；
  PATCH 設 done 會記 completed_at；rate limit 生效（超限 429）。
- abilities：`Abilities::all()` 含四個 `todos:*`；後台權限格渲染 todos 列。

## 安全

- API 沿用 P2：Bearer token + `auth:sanctum` + 逐端點 `ability:todos:*`。
- 後台沿用 `auth` + `role:admin`。
- Rate limiting 防濫用。
- 公開 changelog 只輸出 `title`（使用者自填的英文），description 與內部欄位不外洩。

## 風險與緩解

- **`/changelog` 與 `{locale}` 路由衝突**：locale 群組限定 `where('locale','zh|en|ja|vi|id')`，
  `changelog` 不符該模式，且置於 locale 群組之外，不會被吃掉。實作時確認註冊順序與限制。
- **abilities 矩陣變動影響 P2 測試**：`AbilitiesTest` 斷言總數，新增 todos 後需同步更新該測試的期望數（21 → 25）。
- **rate limiter 影響既有測試**：測試環境可能需放寬或停用 throttle，避免誤觸；於實作計畫處理。
