# GA4 追蹤整合設計 (JYU-43)

## 目標

在 blog 的**公開頁面**整合 Google Analytics 4 (GA4),追蹤真實訪客流量。
只在**正式站 (production)** 且已設定 Measurement ID 時載入,本機開發與後台管理頁面不追蹤。

## 需求

- 只在公開頁面 (`layouts/public.blade.php`) 載入 GA,後台 (`layouts/admin.blade.php`) 完全不載入 → 排除自己編輯文章時的流量。
- 只在 `production` 環境且 `.env` 有設定 `GA_MEASUREMENT_ID` 時輸出追蹤程式碼。
- Measurement ID 透過 `.env` 設定,不進 git。

## 架構

### 1. 設定值 (config)

- `.env`(正式站):`GA_MEASUREMENT_ID=G-XXXXXXXXXX`
- `.env.example`:新增空白範本 `GA_MEASUREMENT_ID=`
- `config/services.php` 新增(照現有第三方服務慣例):
  ```php
  'google_analytics' => [
      'measurement_id' => env('GA_MEASUREMENT_ID'),
  ],
  ```
  透過 config 讀取而非在 Blade 直接 `env()`,因為 `config:cache` 後 `env()` 會回傳 null。

### 2. 追蹤片段 (partial)

新增 `resources/views/partials/google-analytics.blade.php`,內含標準 GA4 `gtag.js`。
整個片段以條件包住,**兩者皆成立才輸出**:

```blade
@if(app()->environment('production') && config('services.google_analytics.measurement_id'))
    {{-- GA4 gtag.js --}}
@endif
```

### 3. 掛載點

在 `layouts/public.blade.php` 的 `<head>` 內、`@vite` 之前 `@include('partials.google-analytics')`。
**不動** `layouts/admin.blade.php` 與 `layouts/auth.blade.php`。

## GA4 帳號建立步驟(使用者操作)

1. 前往 https://analytics.google.com/,用 Google 帳號登入。
2. 「管理」→「建立」→「帳戶」,填帳戶名稱(例如 JYu Notes)。
3. 建立「資源 (Property)」,填名稱、時區(台北)、幣別。
4. 商家資訊填完後,選擇「網站」平台,建立「資料串流 (Data stream)」,輸入 blog 網址 (jyu1999.com)。
5. 建立後即取得 **Measurement ID**,格式為 `G-XXXXXXXXXX`。
6. 將此 ID 填入正式站的 `.env`:`GA_MEASUREMENT_ID=G-XXXXXXXXXX`,重新部署 / `config:cache`。

## 驗證

- 本機 (`local`):即使設定 ID,view source 也不應出現 gtag。
- 正式站設定 ID 後:公開頁 view source 出現 gtag 且含正確 ID;後台頁面不出現。
```

## 範圍外 (YAGNI)

- 自訂事件追蹤(閱讀時間、點擊等)— 之後需要再擴充 partial。
- Cookie consent 橫幅。
