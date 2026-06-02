# API Token 權限系統（P2）— 設計文件

日期：2026-06-02
分支：feature/api-tokens

## 背景

網站要開放一套 API，讓 AI Agent（例如本地 Claude Code）能執行後台的內容操作
（文章、碎念、分類、標籤、媒體的 CRUD）。因為交給 Agent 使用有安全疑慮，使用方式是：
**使用者在後台產生一個有期限、可設定操作範圍的 Token，Agent 帶著這個 Token 才能呼叫 API。**

這是整條 API 鏈（P2 Token 系統 → P3 API 端點 → P4 Agent Skill）的第一個子專案，
負責「認證與授權地基」。

目前狀態：session 登入 + `role:admin` middleware 保護 `/admin`；沒有 `routes/api.php`；
未安裝 Sanctum/Passport。

## 目標

- 後台可產生短期有效、可多次使用、可設權限範圍的 API Token。
- 認證走 `Authorization: Bearer <token>`，以 Laravel Sanctum 實作。
- 權限為「資源 × 動作」細粒度，且「發布」獨立於一般編輯。
- 提供 `GET /api/me` 讓 Agent 驗證自己的身分與可用權限，並讓 P2 能端到端測試。

## 非目標（YAGNI / 留給後續子專案）

- **實際的 CRUD 端點**（posts/tweets/categories/tags/media）→ P3。
- **Agent Skill 文件** → P4。
- IP 白名單、每秒流量限制、OAuth flow、多使用者/團隊權限 → 目前不需要。

## 決策摘要（已與使用者確認）

1. **生命週期**：短期有效、可多次使用。產生時設一個到期時間，期限內不限次數呼叫；過期或手動撤銷即失效。
2. **權限粒度**：資源 × 動作（Read/Create/Update/Delete），且 **Publish 獨立**。
3. **技術**：Laravel Sanctum（personal access tokens，原生支援 abilities 與 `expires_at`）。

## 權限矩陣（abilities）

Ability 字串格式為 `resource:action`。完整清單：

| 資源 | read | create | update | delete | publish |
|---|---|---|---|---|---|
| posts | `posts:read` | `posts:create` | `posts:update` | `posts:delete` | `posts:publish` |
| tweets | `tweets:read` | `tweets:create` | `tweets:update` | `tweets:delete` | `tweets:publish` |
| categories | `categories:read` | `categories:create` | `categories:update` | `categories:delete` | — |
| tags | `tags:read` | `tags:create` | `tags:update` | `tags:delete` | — |
| media | `media:read` | `media:create` | — | `media:delete` | — |

- categories / tags 沒有「發布」概念。
- media 檔案不可改內容，故無 `update`。
- 這份清單集中在**單一真實來源** `config/abilities.php`（回傳結構化的資源→動作陣列），
  供：後台勾選格渲染、Token 產生時的權限驗證、之後 P4 skill 文件引用。

## 架構與元件

### 1. Sanctum 安裝與資料模型

- 安裝 `laravel/sanctum`（與 Laravel 13 相容版本），發佈 config 與 `personal_access_tokens` migration。
- `App\Models\User` 加 `Laravel\Sanctum\HasApiTokens` trait。
- Token 一律屬於產生它的 admin user。
- Token 明碼僅在產生當下回傳一次；資料庫只存 SHA-256 hash（Sanctum 既有行為）。
- `personal_access_tokens` 內含 `abilities`（JSON）、`expires_at`、`last_used_at`。

### 2. 權限來源 `config/abilities.php`

```php
return [
    'posts'      => ['read', 'create', 'update', 'delete', 'publish'],
    'tweets'     => ['read', 'create', 'update', 'delete', 'publish'],
    'categories' => ['read', 'create', 'update', 'delete'],
    'tags'       => ['read', 'create', 'update', 'delete'],
    'media'      => ['read', 'create', 'delete'],
];
```

提供一個 helper（`App\Support\Abilities`）把它攤平成 `['posts:read', 'posts:create', ...]`
與「驗證某 ability 字串是否合法」的方法。

### 3. Token 產生服務

`App\Services\ApiTokenService`：
- `create(User $user, string $name, array $abilities, ?CarbonInterface $expiresAt): array`
  - 驗證 `$abilities` 全部屬於合法清單（否則丟例外）。
  - 呼叫 `$user->createToken($name, $abilities, $expiresAt)`。
  - 回傳 `['plainText' => ..., 'token' => PersonalAccessToken]`（明碼供 UI 顯示一次）。
- `revoke(User $user, int $tokenId): void`：刪除該 user 的指定 token。
- 列表直接用 `$user->tokens()`（Sanctum 關聯）。

### 4. 路由與權限檢查

- 新增 `routes/api.php`（在 `bootstrap/app.php` 註冊 api routing）。
- 認證：`auth:sanctum`。
- 逐項權限：用 Sanctum 內建的 `abilities` / `ability` middleware（例如未來 P3 的
  `Route::post('posts', ...)->middleware('ability:posts:create')`），**不自製 middleware**。
- P2 先提供：
  - `GET /api/me`（僅 `auth:sanctum`）→ 回 `{ user: {id,name,email}, abilities: [...], expires_at }`。
  - 一條**測試用**受權限保護的路由（例如 `GET /api/_probe` 掛 `ability:posts:read`），
    僅供測試 ability middleware。此路由只在測試環境註冊，不在生產對外曝露（見實作計畫）。

### 5. 後台「API Tokens」管理介面

- 路由群組 `admin` 下新增：
  - `GET /admin/tokens`（index：列出現有 token——名稱、權限摘要、到期、最後使用時間）。
  - `POST /admin/tokens`（store：產生）。
  - `DELETE /admin/tokens/{id}`（destroy：撤銷）。
- 側邊欄選單加入「API Tokens」。
- 產生表單：名稱（必填）、到期（預設選項 + 自訂）、權限矩陣勾選格（依 `config/abilities.php` 渲染）。
- 產生後在頁面**明碼顯示一次**（可複製按鈕），離開即無法再看到。
- 列表每列有撤銷按鈕。

### 6. 到期

- 產生時可選預設期限：**1 小時 / 8 小時 / 24 小時 / 7 天**，加「自訂日期時間」。預設 8 小時。
- 後端把選擇換算成 `expires_at` 傳給 `createToken`。
- 過期 token 由 Sanctum 自動拒絕。
- 加一個 artisan 指令 + 既有 scheduler 定期清除過期 token（`sanctum:prune-expired` 即可，Sanctum 內建）。

## 資料流（對應使用者情境 2-3）

1. 後台 → API Tokens → 產生一個「翻譯任務」token：勾 `posts:read/create`、`media:read/create`、
   （不勾 `posts:publish`），到期 8 小時 → 複製明碼。
2. 把明碼交給本地 Agent。
3. Agent `GET /api/me` 確認可用權限。
4. （P3 完成後）Agent 讀原文、上傳封面、建立翻譯版草稿——`posts:publish` 沒勾，所以**無法發布**。
5. 使用者自行在後台確認並發布。

## 錯誤處理

- 無 token / 無效 / 過期 → `401 Unauthorized`。
- 有 token 但缺對應 ability → `403 Forbidden`（Sanctum ability middleware 行為）。
- API 回應一律 JSON（`routes/api.php` 群組，例外轉 JSON）。
- 後台產生時 abilities 不合法 → 表單驗證錯誤。

## 測試策略

- `ApiTokenService`：abilities 寫入正確、非法 ability 被拒、`expires_at` 正確設定、revoke 生效。
- `Abilities` helper：攤平清單正確、合法性檢查正確。
- `GET /api/me`：無 token → 401；有效 token → 回正確 user 與 abilities；過期 token → 401。
- ability middleware：以一條測試路由驗證——帶 `posts:read` 能過、缺則 403。
- 後台 Token 管理：admin 能產生（明碼回傳一次）、列出、撤銷；非 admin / 未登入被擋。

## 安全

- 只有 admin 能進後台產生 token（沿用 `role:admin`）。
- 明碼一次性顯示，DB 只存 hash。
- 短期限 + 隨時可撤銷 + 最小權限（只勾必要 abilities）。
- 生產走 HTTPS。

## 風險與緩解

- **Sanctum 與 Laravel 13 版本相容性**：安裝時挑相容版本；若 `personal_access_tokens` migration 與既有 pgsql 設定有出入，於實作計畫處理。
- **權限矩陣與 P3 端點對齊**：P3 每條路由掛的 ability 必須出自此矩陣；`config/abilities.php` 為唯一來源可降低漂移。
- **測試用 probe 路由外洩**：probe 路由僅在測試環境註冊，不在生產 `routes/api.php` 對外曝露。
