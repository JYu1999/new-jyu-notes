# JYu's Blog — Database Schema (MVP)

> Database: PostgreSQL 16 (via Laravel Sail)
> ORM: Eloquent (Laravel 13)
> Default app locale: `zh`
> Supported locales: `zh`, `en`, `ja`, `vi`, `id`

---

## 0. Design conventions

- **Multi-language strategy**: Translation group。Post / Tweet 各自有 `*_groups` 錨點表，主表每筆代表「某語言的一個版本」，同一篇文章的不同語言透過 `*_group_id` 串聯。
- **Tags / Categories**：語言中立實體 + `*_translations` 子表存逐語言名稱／slug／描述。
- **Soft delete**：Post / Tweet 使用 Laravel 標準 `deleted_at` 軟刪。Tag / Category 不軟刪（直接 hard delete，並級聯刪除 translations 與 pivot）。
- **Timestamps**：所有主表都有 `created_at` / `updated_at` 兩個 timestamp 欄位（Laravel 預設）。
- **Naming**：snake_case、複數表名、`id` 為主鍵、外鍵以 `<table_singular>_id` 命名。
- **Locale 欄位**：`varchar(5)`，存 `zh` / `en` / `ja` / `vi` / `id`。應用層用 enum 驗證。
- **Slug**：每語言獨立 UNIQUE，例如 `zh` 的 `slug='2025-review'` 與 `en` 的 `slug='2025-review'` 可以共存（不同 locale）。
- **Charset / Collation**：PostgreSQL 預設 UTF-8。
- **時區**：`timestamp WITH TIME ZONE`（Laravel 預設 `timestampsTz`）。

---

## 1. `users`

Laravel 標準 `users` 表 + `role` 欄位。MVP 階段只允許 `admin` 一種角色，但保留欄位以便未來擴充。

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `name` | varchar(255) | NOT NULL | |
| `email` | varchar(255) | NOT NULL, UNIQUE | |
| `email_verified_at` | timestamp | NULL | |
| `password` | varchar(255) | NOT NULL | bcrypt hash |
| `role` | varchar(20) | NOT NULL, default `'admin'` | enum: `admin`（MVP 唯一值） |
| `remember_token` | varchar(100) | NULL | |
| `created_at` | timestampTz | | |
| `updated_at` | timestampTz | | |

**Indexes**: UNIQUE(`email`)

---

## 2. `post_groups` / `tweet_groups`

純粹做為「同一內容的多語言翻譯」之錨點。每新建一篇文章就建立一筆 group。

### 2.1 `post_groups`

| Column | Type | Notes |
|---|---|---|
| `id` | bigserial PK | |
| `created_at` | timestampTz | |
| `updated_at` | timestampTz | |

### 2.2 `tweet_groups`

結構同上。

> **設計理由**：以獨立表存 group ID，可保證 FK 完整性、方便日後加 group-level metadata（例如 canonical_locale）。

---

## 3. `posts`

每筆代表「特定語言版本的一篇 Post」。

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `post_group_id` | bigint | FK → `post_groups.id`, ON DELETE CASCADE, NOT NULL | |
| `locale` | varchar(5) | NOT NULL | `zh` / `en` / `ja` / `vi` / `id` |
| `slug` | varchar(255) | NOT NULL | |
| `title` | varchar(255) | NOT NULL | |
| `excerpt` | text | NULL | 卡片預覽用摘要 |
| `body` | text | NOT NULL | Markdown（含已轉換的 HTML inline） |
| `cover_image_path` | varchar(500) | NULL | 對應 `media.path`；非 FK，以字串保存便於遷移 |
| `status` | varchar(20) | NOT NULL, default `'draft'` | `draft` / `published` / `hidden` |
| `is_featured` | boolean | NOT NULL, default `false` | 首頁置頂用 |
| `views_count` | integer | NOT NULL, default `0` | denormalized counter |
| `published_at` | timestampTz | NULL | 首次發布的時間（用於前台排序） |
| `last_modified_at` | timestampTz | NOT NULL | 內容最後一次修改（與 `updated_at` 分開以便保留排序語意） |
| `author_id` | bigint | FK → `users.id`, NULL | nullable 以便匯入舊資料時可不指派 |
| `deleted_at` | timestampTz | NULL | Soft delete |
| `created_at` | timestampTz | | |
| `updated_at` | timestampTz | | |

**Indexes**

- `UNIQUE (post_group_id, locale) WHERE deleted_at IS NULL` — 一個 group 同語言只能有一個版本
- `UNIQUE (locale, slug) WHERE deleted_at IS NULL` — 同語言 slug 不可重複
- `INDEX (locale, status, published_at DESC)` — 前台時間排序
- `INDEX (locale, status, last_modified_at DESC)` — 「最後更新」排序
- `INDEX (locale, status, views_count DESC)` — 「觀看次數」排序
- `INDEX (status, is_featured) WHERE deleted_at IS NULL` — 首頁置頂查詢

---

## 4. `tweets`

短文，每筆代表特定語言版本。

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tweet_group_id` | bigint | FK → `tweet_groups.id`, ON DELETE CASCADE, NOT NULL | |
| `locale` | varchar(5) | NOT NULL | |
| `body` | text | NOT NULL | 短內文 Markdown |
| `media` | jsonb | NULL | 圖片／影片陣列，格式：`[{"path":"...","type":"image|video","alt":"..."}]` |
| `status` | varchar(20) | NOT NULL, default `'draft'` | `draft` / `published` / `hidden` |
| `published_at` | timestampTz | NULL | |
| `author_id` | bigint | FK → `users.id`, NULL | |
| `deleted_at` | timestampTz | NULL | |
| `created_at` | timestampTz | | |
| `updated_at` | timestampTz | | |

**Indexes**

- `UNIQUE (tweet_group_id, locale) WHERE deleted_at IS NULL`
- `INDEX (locale, status, published_at DESC)` — 時間軸主排序
- `INDEX (status, published_at DESC) WHERE deleted_at IS NULL` — 全站時間軸

> **沒有 slug**：Tweet 不需要 SEO friendly URL，前台用 ID 或 group_id 即可。

---

## 5. `tags` / `tag_translations`

### 5.1 `tags`

語言中立實體。

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `color` | varchar(7) | NULL | hex `#b2543b`，前台 badge 用 |
| `created_at` | timestampTz | | |
| `updated_at` | timestampTz | | |

### 5.2 `tag_translations`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tag_id` | bigint | FK → `tags.id`, ON DELETE CASCADE, NOT NULL | |
| `locale` | varchar(5) | NOT NULL | |
| `name` | varchar(100) | NOT NULL | |
| `slug` | varchar(120) | NOT NULL | |

**Indexes**

- `UNIQUE (tag_id, locale)`
- `UNIQUE (locale, slug)`

---

## 6. `categories` / `category_translations`

### 6.1 `categories`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `cover_image_path` | varchar(500) | NULL | |
| `sort_method` | varchar(20) | NOT NULL, default `'date_desc'` | `manual` / `date_desc` / `date_asc` |
| `created_at` | timestampTz | | |
| `updated_at` | timestampTz | | |

### 6.2 `category_translations`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `category_id` | bigint | FK → `categories.id`, ON DELETE CASCADE, NOT NULL | |
| `locale` | varchar(5) | NOT NULL | |
| `name` | varchar(100) | NOT NULL | |
| `slug` | varchar(120) | NOT NULL | |
| `description` | text | NULL | |

**Indexes**

- `UNIQUE (category_id, locale)`
- `UNIQUE (locale, slug)`

---

## 7. Pivot tables

### 7.1 `post_tag`

| Column | Type | Constraints |
|---|---|---|
| `post_id` | bigint | FK → `posts.id`, ON DELETE CASCADE |
| `tag_id` | bigint | FK → `tags.id`, ON DELETE CASCADE |

PRIMARY KEY (`post_id`, `tag_id`)
INDEX (`tag_id`, `post_id`)

### 7.2 `tweet_tag`

| Column | Type | Constraints |
|---|---|---|
| `tweet_id` | bigint | FK → `tweets.id`, ON DELETE CASCADE |
| `tag_id` | bigint | FK → `tags.id`, ON DELETE CASCADE |

PRIMARY KEY (`tweet_id`, `tag_id`)
INDEX (`tag_id`, `tweet_id`)

### 7.3 `category_post`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `category_id` | bigint | FK → `categories.id`, ON DELETE CASCADE | |
| `post_id` | bigint | FK → `posts.id`, ON DELETE CASCADE | |
| `order_in_category` | integer | NULL | 手動排序時使用 |

PRIMARY KEY (`category_id`, `post_id`)
INDEX (`post_id`, `category_id`)
INDEX (`category_id`, `order_in_category`) — 取手動排序時的排序鍵

> **設計理由**：tag / category 是「跨語言」實體；pivot 是用「特定語言的 Post」關聯到「跨語言 Tag」。實務上同 group 各語言會掛相同 tags（由 admin 編輯時同步），但結構上允許不同語言掛不同 tags 的彈性。

---

## 8. `media`

集中管理上傳的檔案。

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `path` | varchar(500) | NOT NULL, UNIQUE | storage 內相對路徑，如 `posts/2025/abc.png` |
| `mime_type` | varchar(100) | NOT NULL | |
| `size` | bigint | NOT NULL | bytes |
| `width` | integer | NULL | 圖片寬度（影片填 frame 寬度，否則 null） |
| `height` | integer | NULL | |
| `original_filename` | varchar(255) | NULL | |
| `uploaded_by` | bigint | FK → `users.id`, NULL | |
| `created_at` | timestampTz | | |
| `updated_at` | timestampTz | | |

**Indexes**: UNIQUE(`path`)

> **設計理由**：把所有檔案放在一個 table 統一管理；`posts.cover_image_path` / `tweets.media[].path` 不做 FK（為了匯入時的彈性），但保證 `media.path` 是唯一的，可由 path 反查 metadata。

---

## 9. `post_view_logs`

去重用的觀看記錄。

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigserial | PK | |
| `post_id` | bigint | FK → `posts.id`, ON DELETE CASCADE | |
| `fingerprint` | varchar(64) | NOT NULL | `sha256(ip + user_agent + daily_salt)` |
| `viewed_at` | timestampTz | NOT NULL | |

**Indexes**

- `INDEX (post_id, fingerprint, viewed_at DESC)` — 30 分鐘 dedup 查詢主鍵

**邏輯**

```text
on GET /post/{slug}:
  fp = sha256(ip + user_agent + daily_salt)
  recent_exists = SELECT 1 FROM post_view_logs
                  WHERE post_id = $id AND fingerprint = $fp
                    AND viewed_at >= now() - interval '30 minutes'
  if not recent_exists:
    INSERT INTO post_view_logs(post_id, fingerprint, viewed_at) VALUES (...)
    UPDATE posts SET views_count = views_count + 1 WHERE id = $id
```

可選：每日排程清理 30 分鐘以前的 log（保留近 24h 即可）。

> **隱私說明**：不存原始 IP，只存 sha256；`daily_salt` 防止跨日比對。

---

## 10. Laravel 標準表

由 Laravel `php artisan migrate` 預設產生，這裡列出僅供完整性參考：

- `sessions`
- `password_reset_tokens`
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`

---

## 11. Entity-Relationship 概覽

```text
users (1) ────< (0..N) posts.author_id
users (1) ────< (0..N) tweets.author_id
users (1) ────< (0..N) media.uploaded_by

post_groups (1) ────< (1..5) posts        [locale ∈ {zh,en,ja,vi,id}]
tweet_groups (1) ────< (1..5) tweets

posts (N) >────< (N) tags          via post_tag
tweets (N) >────< (N) tags         via tweet_tag
posts (N) >────< (N) categories    via category_post (含 order_in_category)

tags (1) ────< (0..5) tag_translations          [locale]
categories (1) ────< (0..5) category_translations [locale]

posts (1) ────< (0..N) post_view_logs
```

---

## 12. 從舊 Hugo Blog 遷移的對應

| Hugo 結構 | 新系統 |
|---|---|
| `content/posts/<slug>/index.md` | `posts` (locale=`zh`) + create `post_groups` row |
| `content/posts/<slug>/index.en.md` | `posts` (locale=`en`)，共用同一 `post_group_id` |
| `content/posts/<slug>/index.ja.md` | `posts` (locale=`ja`) |
| `content/tweets/<slug>/index*.md` | `tweets` 同上 |
| front matter `title` | `posts.title` |
| front matter `date` | `posts.published_at` |
| front matter `lastmod` | `posts.last_modified_at`（若無則 fallback 至 `date`） |
| front matter `description` | `posts.excerpt` |
| front matter `tags` | 透過 `tag_translations.name` 查 tag，掛到 `post_tag` |
| front matter `categories` | 透過 `category_translations.slug` 查 category，掛到 `category_post` |
| front matter `series` + `series_order` | 視同 category（同名建一個 category）；`series_order` → `category_post.order_in_category`；`categories.sort_method = 'manual'` |
| front matter `draft: true` | `posts.status = 'draft'` |
| `featured.png` | 上傳 → `media`，path 寫入 `posts.cover_image_path` |
| 內文 `{{< alert >}}` 等 shortcode | 在匯入時用 Markdown converter 轉成標準 HTML（詳見 Step 6 設計） |
| 內文行內圖片（如 `image1.png`） | 上傳 → `media`，body 內 `<img src>` 改寫為新 path |

---

## 13. 未在 MVP 實作的延伸欄位 / 表（記錄保留）

設計稿中出現但 PRD 未要求、暫不實作：

- 文章 Like / Bookmark / Share 功能 → 未建表
- Newsletter 訂閱 → 未建表
- 留言 → 未建表
- 文章版本歷史 / Revision → 未建表

如未來需要再以新 migration 補上。
