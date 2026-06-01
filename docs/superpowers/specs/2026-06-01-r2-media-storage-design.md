# R2 媒體儲存重構 — 設計文件

日期：2026-06-01
分支：master

## 背景與問題

專案要部署到 Laravel Cloud（Starter plan），自訂網域 `jyu1999.com` 在 Cloudflare。
一次性的 `php artisan blog:import-from-hugo` 會匯入約 1.2G 的舊 Hugo 媒體。

目前媒體儲存有兩個阻礙：

1. **媒體寫死存在本機 `public` 磁碟**（`storage/app/public`）。Laravel Cloud 的本機檔案系統是
   ephemeral：每次部署被清空，且 web compute 與 queue worker 不共用磁碟。匯入的 1.2G 媒體無法持久、無法穩定提供。
2. **`/storage/...` 這個 URL 慣例散布全 app**，不只 `MediaService`：
   - 文章 / 分類封面、推文媒體：`asset('storage/' . $path)`
   - 文章內文：import 把 `/storage/{path}` 字串直接烤進 DB 內容
   - 媒體庫：`Media::url()` → `Storage::disk('public')->url()`
   - 後台編輯預覽（Alpine JS）：`'/storage/' + path`

   因此只改 `MediaService` 不足以搬遷，會造成部分圖在 R2、部分仍指向本機的破圖狀態。

## 目標

- 媒體改存 Cloudflare R2（S3 相容），透過自訂網域 `media.jyu1999.com` 對外提供。
- app 產生媒體 URL 的地方統一成單一來源，本機開發與生產用同一套程式。
- 一次性遷移：本機執行 import，媒體寫入 R2、資料寫入 Cloud Postgres；Laravel Cloud 不執行 import。

## 非目標（YAGNI）

- 不做圖片縮圖 / 轉檔 pipeline。
- 不做媒體存取權限控管（bucket 走公開讀取 + 自訂網域）。
- 不做歷史內文「相對路徑 + render 時改寫」的機制（見下方決策）。

## 採用途徑：在 app 層集中產生媒體 URL（途徑 A）

新增 `media_url($path)` helper，背後讀「媒體磁碟」的 `url` 設定，全 app 改用它。

- 本機開發：`MEDIA_DISK=public` → `media_url()` 產出 `/storage/...`，行為不變。
- 生產 / import：`MEDIA_DISK=s3`(R2) → `media_url()` 產出 `https://media.jyu1999.com/...`。

優點：單一真實來源、本機與生產同套程式、未來換 CDN 只改一個 env。
（已否決途徑 B：用 Cloudflare Worker 讓 `/storage/*` 透明指向 R2 — 綁死基礎設施、脆弱、本機/生產行為不一致。）

## 設計細節

### 1. 設定

- 新增 `config/media.php`：
  ```php
  return ['disk' => env('MEDIA_DISK', 'public')];
  ```
- `config/filesystems.php` 既有的 `s3` disk 直接重用為 R2（不需改檔，靠 env）：
  - `AWS_ENDPOINT` → R2 endpoint
  - `AWS_URL=https://media.jyu1999.com`（決定 `Storage::disk('s3')->url()` 的 base）
  - `AWS_USE_PATH_STYLE_ENDPOINT=true`

### 2. 統一 URL helper

- 新增 `app/Support/helpers.php`，以 composer `autoload.files` 載入：
  ```php
  if (! function_exists('media_url')) {
      function media_url(string $path): string {
          return \Illuminate\Support\Facades\Storage::disk(config('media.disk', 'public'))->url($path);
      }
  }
  ```
- `composer.json` 加入 `"autoload": { "files": ["app/Support/helpers.php"] }`，需跑 `composer dump-autoload`。

### 3. 程式改動

| 位置 | 現況 | 改為 |
|------|------|------|
| `MediaService::upload` | `$file->store(..., 'public')` | `$file->store(..., config('media.disk'))` |
| `MediaService::delete` | `Storage::disk('public')->delete()` | `Storage::disk(config('media.disk'))->delete()` |
| `MediaService::registerLocalFile` | `Storage::disk('public')->put()` | `Storage::disk(config('media.disk'))->put()` |
| `Media::url()` | `Storage::disk('public')->url($this->path)` | `media_url($this->path)` |
| `ImportFromHugo::buildAssetRewrites` | `$rewrites[$filename] = '/storage/' . $media->path` | `$rewrites[$filename] = media_url($media->path)` |
| `ImportFromHugo::buildAssetRewrites`（dry-run 分支） | `"/storage/{$storageSubdir}/{$filename}"` | `media_url("{$storageSubdir}/{$filename}")` |
| blade：`post-card`、`tweet-card`、`categories/index` 等 | `asset('storage/' . $path)` | `media_url($path)` |
| 後台 Alpine 預覽（`posts/edit`、`pages/edit`、`categories/index`） | `'/storage/' + path` | 由 blade 注入媒體 base URL 給 JS 串接 |

後台 Alpine 注入方式：在 layout 或對應 blade 以
`<script>window.MEDIA_BASE_URL = @js(rtrim(media_url(''), '/'));</script>`
（或 data attribute）提供，Alpine `coverUpload` 改用 `window.MEDIA_BASE_URL + '/' + path`。

### 4. 內文 URL 策略

import 時即把**絕對 R2 URL**（`media_url($media->path)`）烤進文章內容。
理由：`media.jyu1999.com` 是穩定網域，內文圖片網址固定即可。
取捨：若未來更換媒體網域，需跑一次字串替換 migration——對一次性遷移可接受。

### 5. 環境變數（本機執行 import 時用）

```env
MEDIA_DISK=s3
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<R2 token>
AWS_SECRET_ACCESS_KEY=<R2 secret>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=<bucket>
AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
AWS_URL=https://media.jyu1999.com
AWS_USE_PATH_STYLE_ENDPOINT=true

DB_CONNECTION=pgsql        # 其餘 DB_* 指向 Cloud Postgres
ADMIN_EMAIL=jyu@furuke.com
ADMIN_NAME=JYu
ADMIN_PASSWORD=<強密碼>
```

R2 憑證與 DB 連線資訊不進 git，依現有 secrets 習慣處理。

### 6. Cloud 生產環境變數

與本機 import 相同的 `MEDIA_DISK=s3` + `AWS_*`（同一個 R2 bucket 與 `media.jyu1999.com`）。
`DB_*` 由 Laravel Cloud 注入託管 Postgres。

### 7. R2 與 Cloudflare 設定（基礎設施，非程式）

1. Cloudflare 建立 R2 bucket，產生 S3 API token（access key / secret）。
2. R2 bucket 綁定自訂網域 `media.jyu1999.com`（Cloudflare R2 → Custom Domains），DNS 自動建立。
3. 設定 bucket 公開讀取（透過自訂網域）。

### 8. 一次性遷移 runbook（本機執行）

1. 設定上述環境變數（媒體 → R2，DB → Cloud Postgres）。
2. `php artisan migrate --force`（在 Cloud Postgres 上建表）。
3. `php artisan db:seed --class=AdminUserSeeder`（建立 admin）。
4. `php artisan blog:import-from-hugo`（媒體寫入 R2、資料寫入 Cloud DB、內文烤入 R2 URL）。
5. 抽查 R2 是否有檔案、Cloud DB 是否有資料、內文圖片 URL 是否為 `https://media.jyu1999.com/...`。
6. 完成後把本機 `.env` 的 R2/Cloud DB 設定還原回本機開發值（或另存獨立 env）。

## 測試策略

- `media_url()` 單元測試：`MEDIA_DISK=public` 時回傳 `/storage/...`；`MEDIA_DISK=s3`（設 `AWS_URL`）時回傳 `https://media.jyu1999.com/...`。
- `MediaService` 測試：以 `Storage::fake()` 驗證寫入的磁碟為 `config('media.disk')`、`registerLocalFile` 行為不變。
- import 改寫測試：驗證 `buildAssetRewrites` 產出的內文 URL 在 `s3` 設定下為絕對 R2 URL。
- 既有測試需維持通過（本機預設 `MEDIA_DISK=public`，行為應與現況一致）。

## 風險與緩解

- **既有測試假設 `/storage/`**：本機預設 `MEDIA_DISK=public`，`media_url()` 仍產 `/storage/...`，降低破壞面。逐一確認受影響測試。
- **後台 Alpine 預覽**：JS 端需取得媒體 base URL，遺漏會造成上傳後預覽破圖（不影響已存資料）。納入改動清單明確處理。
- **內文絕對 URL 不可逆於 render 層**：已知取捨，換網域需資料層替換。
