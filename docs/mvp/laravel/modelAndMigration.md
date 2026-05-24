# JYu's Blog — Models & Migrations (MVP)

> Laravel: 13.x
> PHP: 8.5
> 對應 schema：`docs/mvp/db/schema.md`

---

## 0. 設計規章

- **命名空間**：所有 model 放於 `App\Models\`。
- **Model 規範**：
  - `protected $fillable` 明確列出，**不使用 `$guarded = []`**（避免 mass assignment 風險）。
  - 必須的型別轉換寫在 `$casts`（datetime / boolean / array / jsonb）。
  - 多語言關聯透過 helper method 提供：`$post->translation('en')` 取同 group 的英文版、`$post->siblings()` 取同 group 的其他語言版本、`$post->allTranslations()` 取全部。
  - Post / Tweet 使用 `Illuminate\Database\Eloquent\SoftDeletes`。
- **Slug 產生策略**：手動於 Service 層 (`SlugGenerator::forPost($title, $locale)`)，**不使用第三方 sluggable 套件**。中日韓字元 → transliterate / fallback 至 `post-{group_id}`。
- **Tag / Category 同步策略**：Admin 編輯一個 Post 的 tags / categories 時，同 group 的所有 locale 版本會自動共用相同的 tag/category 關聯。實作於 `PostService::syncTagsAcrossGroup()` / `PostService::syncCategoriesAcrossGroup()`。
- **Migration 命名**：`YYYY_MM_DD_HHMMSS_<verb>_<table>_table.php`，依下方順序排列。
- **欄位順序**：FK → enum / status → core data → media → flags / counters → timestamps → soft delete。
- **Index 命名**：Laravel 自動命名（`<table>_<col>_index`、`<table>_<col1>_<col2>_unique`）；複雜的 partial / multi-column index 用 raw `DB::statement` 在 migration 內建立。
- **Enum constants**：寫在對應 model 的 const，避免散落字串（例如 `Post::STATUS_DRAFT`）。

---

## 1. Migration 執行順序

| # | Migration file | Notes |
|---|---|---|
| 1 | `2026_01_01_000001_create_users_table.php` | Laravel 預設 + 新增 `role` 欄位 |
| 2 | `2026_01_01_000002_create_password_reset_tokens_table.php` | Laravel 預設 |
| 3 | `2026_01_01_000003_create_sessions_table.php` | Laravel 預設 |
| 4 | `2026_01_01_000004_create_cache_table.php` | Laravel 預設 |
| 5 | `2026_01_01_000005_create_jobs_table.php` | Laravel 預設（jobs + job_batches + failed_jobs） |
| 6 | `2026_01_02_000001_create_media_table.php` | depends on `users` |
| 7 | `2026_01_02_000002_create_post_groups_table.php` | |
| 8 | `2026_01_02_000003_create_tweet_groups_table.php` | |
| 9 | `2026_01_02_000004_create_posts_table.php` | depends on `post_groups`, `users` |
| 10 | `2026_01_02_000005_create_tweets_table.php` | depends on `tweet_groups`, `users` |
| 11 | `2026_01_02_000006_create_tags_table.php` | |
| 12 | `2026_01_02_000007_create_tag_translations_table.php` | depends on `tags` |
| 13 | `2026_01_02_000008_create_categories_table.php` | |
| 14 | `2026_01_02_000009_create_category_translations_table.php` | depends on `categories` |
| 15 | `2026_01_02_000010_create_post_tag_table.php` | depends on `posts`, `tags` |
| 16 | `2026_01_02_000011_create_tweet_tag_table.php` | depends on `tweets`, `tags` |
| 17 | `2026_01_02_000012_create_category_post_table.php` | depends on `categories`, `posts` |
| 18 | `2026_01_02_000013_create_post_view_logs_table.php` | depends on `posts` |

---

## 2. Model 詳述

### 2.1 `App\Models\User`

繼承 Laravel 預設 `Authenticatable`，新增 `role` 欄位與 helper。

```php
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    const ROLE_ADMIN = 'admin';

    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function posts(): HasMany   { return $this->hasMany(Post::class, 'author_id'); }
    public function tweets(): HasMany  { return $this->hasMany(Tweet::class, 'author_id'); }
    public function media(): HasMany   { return $this->hasMany(Media::class, 'uploaded_by'); }
}
```

**Migration `create_users_table`**：在 Laravel 預設基礎上加入：

```php
$table->string('role', 20)->default('admin')->after('password');
```

---

### 2.2 `App\Models\PostGroup`

```php
class PostGroup extends Model
{
    use HasFactory;

    protected $fillable = [];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** 取得指定語言版本（包含 soft deleted？預設不含） */
    public function postByLocale(string $locale): ?Post
    {
        return $this->posts()->where('locale', $locale)->first();
    }
}
```

**Migration**：

```php
Schema::create('post_groups', function (Blueprint $t) {
    $t->id();
    $t->timestampsTz();
});
```

---

### 2.3 `App\Models\TweetGroup`

結構同 PostGroup。

---

### 2.4 `App\Models\Post`

```php
class Post extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT     = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_HIDDEN    = 'hidden';

    const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_HIDDEN,
    ];

    const SUPPORTED_LOCALES = ['zh', 'en', 'ja', 'vi', 'id'];

    protected $fillable = [
        'post_group_id', 'locale', 'slug', 'title', 'excerpt', 'body',
        'cover_image_path', 'status', 'is_featured',
        'published_at', 'last_modified_at', 'author_id',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'views_count' => 'integer',
        'published_at' => 'datetime',
        'last_modified_at' => 'datetime',
    ];

    // ===== Relationships =====
    public function group(): BelongsTo     { return $this->belongsTo(PostGroup::class, 'post_group_id'); }
    public function author(): BelongsTo    { return $this->belongsTo(User::class, 'author_id'); }
    public function tags(): BelongsToMany  { return $this->belongsToMany(Tag::class, 'post_tag'); }
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post')
                    ->withPivot('order_in_category');
    }
    public function viewLogs(): HasMany    { return $this->hasMany(PostViewLog::class); }

    // ===== Translation helpers =====
    public function siblings(): HasMany
    {
        return $this->hasMany(Post::class, 'post_group_id', 'post_group_id')
                    ->where('id', '!=', $this->id);
    }

    public function translation(string $locale): ?Post
    {
        if ($locale === $this->locale) return $this;
        return Post::where('post_group_id', $this->post_group_id)
                   ->where('locale', $locale)
                   ->first();
    }

    public function allTranslations(): Collection
    {
        return Post::where('post_group_id', $this->post_group_id)->get();
    }

    // ===== Scopes =====
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale);
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured', true);
    }

    // ===== Sort helpers =====
    public function scopeSortBy(Builder $q, string $sort): Builder
    {
        return match ($sort) {
            'views'    => $q->orderByDesc('views_count'),
            'updated'  => $q->orderByDesc('last_modified_at'),
            default    => $q->orderByDesc('published_at'),  // 'published' / 預設
        };
    }
}
```

**Migration `create_posts_table`**：

```php
Schema::create('posts', function (Blueprint $t) {
    $t->id();
    $t->foreignId('post_group_id')->constrained()->cascadeOnDelete();
    $t->string('locale', 5);
    $t->string('slug', 255);
    $t->string('title', 255);
    $t->text('excerpt')->nullable();
    $t->text('body');
    $t->string('cover_image_path', 500)->nullable();
    $t->string('status', 20)->default('draft');
    $t->boolean('is_featured')->default(false);
    $t->integer('views_count')->default(0);
    $t->timestampTz('published_at')->nullable();
    $t->timestampTz('last_modified_at');
    $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
    $t->timestampsTz();
    $t->softDeletesTz();

    $t->index(['locale', 'status', 'published_at'], 'posts_locale_status_published_idx');
    $t->index(['locale', 'status', 'last_modified_at'], 'posts_locale_status_modified_idx');
    $t->index(['locale', 'status', 'views_count'], 'posts_locale_status_views_idx');
});

// Partial unique indexes（PostgreSQL）
DB::statement('CREATE UNIQUE INDEX posts_group_locale_unique ON posts (post_group_id, locale) WHERE deleted_at IS NULL');
DB::statement('CREATE UNIQUE INDEX posts_locale_slug_unique ON posts (locale, slug) WHERE deleted_at IS NULL');
DB::statement('CREATE INDEX posts_status_featured_idx ON posts (status, is_featured) WHERE deleted_at IS NULL');
```

---

### 2.5 `App\Models\Tweet`

```php
class Tweet extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT     = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_HIDDEN    = 'hidden';

    const SUPPORTED_LOCALES = Post::SUPPORTED_LOCALES;

    protected $fillable = [
        'tweet_group_id', 'locale', 'body', 'media',
        'status', 'published_at', 'author_id',
    ];

    protected $casts = [
        'media' => 'array',           // JSONB ↔ array
        'published_at' => 'datetime',
    ];

    public function group(): BelongsTo    { return $this->belongsTo(TweetGroup::class, 'tweet_group_id'); }
    public function author(): BelongsTo   { return $this->belongsTo(User::class, 'author_id'); }
    public function tags(): BelongsToMany { return $this->belongsToMany(Tag::class, 'tweet_tag'); }

    public function siblings(): HasMany
    {
        return $this->hasMany(Tweet::class, 'tweet_group_id', 'tweet_group_id')
                    ->where('id', '!=', $this->id);
    }

    public function translation(string $locale): ?Tweet
    {
        if ($locale === $this->locale) return $this;
        return Tweet::where('tweet_group_id', $this->tweet_group_id)
                    ->where('locale', $locale)
                    ->first();
    }

    public function scopePublished(Builder $q): Builder { return $q->where('status', self::STATUS_PUBLISHED); }
    public function scopeLocale(Builder $q, string $l): Builder { return $q->where('locale', $l); }
}
```

**Migration `create_tweets_table`**：

```php
Schema::create('tweets', function (Blueprint $t) {
    $t->id();
    $t->foreignId('tweet_group_id')->constrained()->cascadeOnDelete();
    $t->string('locale', 5);
    $t->text('body');
    $t->jsonb('media')->nullable();
    $t->string('status', 20)->default('draft');
    $t->timestampTz('published_at')->nullable();
    $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
    $t->timestampsTz();
    $t->softDeletesTz();

    $t->index(['locale', 'status', 'published_at'], 'tweets_locale_status_published_idx');
});

DB::statement('CREATE UNIQUE INDEX tweets_group_locale_unique ON tweets (tweet_group_id, locale) WHERE deleted_at IS NULL');
DB::statement('CREATE INDEX tweets_status_published_idx ON tweets (status, published_at DESC) WHERE deleted_at IS NULL');
```

---

### 2.6 `App\Models\Tag` / `App\Models\TagTranslation`

```php
class Tag extends Model
{
    use HasFactory;
    protected $fillable = ['color'];

    public function translations(): HasMany    { return $this->hasMany(TagTranslation::class); }
    public function posts(): BelongsToMany     { return $this->belongsToMany(Post::class, 'post_tag'); }
    public function tweets(): BelongsToMany    { return $this->belongsToMany(Tweet::class, 'tweet_tag'); }

    public function name(string $locale): ?string
    {
        return $this->translations->firstWhere('locale', $locale)?->name
            ?? $this->translations->firstWhere('locale', 'zh')?->name;
    }

    public function slug(string $locale): ?string
    {
        return $this->translations->firstWhere('locale', $locale)?->slug
            ?? $this->translations->firstWhere('locale', 'zh')?->slug;
    }
}

class TagTranslation extends Model
{
    protected $fillable = ['tag_id', 'locale', 'name', 'slug'];
    public $timestamps = false;
    public function tag(): BelongsTo { return $this->belongsTo(Tag::class); }
}
```

**Migrations**：

```php
Schema::create('tags', function (Blueprint $t) {
    $t->id();
    $t->string('color', 7)->nullable();
    $t->timestampsTz();
});

Schema::create('tag_translations', function (Blueprint $t) {
    $t->id();
    $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $t->string('locale', 5);
    $t->string('name', 100);
    $t->string('slug', 120);

    $t->unique(['tag_id', 'locale']);
    $t->unique(['locale', 'slug']);
});
```

---

### 2.7 `App\Models\Category` / `App\Models\CategoryTranslation`

```php
class Category extends Model
{
    use HasFactory;

    const SORT_MANUAL    = 'manual';
    const SORT_DATE_DESC = 'date_desc';
    const SORT_DATE_ASC  = 'date_asc';

    protected $fillable = ['cover_image_path', 'sort_method'];

    public function translations(): HasMany { return $this->hasMany(CategoryTranslation::class); }
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'category_post')
                    ->withPivot('order_in_category');
    }

    public function name(string $locale): ?string
    {
        return $this->translations->firstWhere('locale', $locale)?->name
            ?? $this->translations->firstWhere('locale', 'zh')?->name;
    }
}

class CategoryTranslation extends Model
{
    protected $fillable = ['category_id', 'locale', 'name', 'slug', 'description'];
    public $timestamps = false;
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
```

**Migrations**：

```php
Schema::create('categories', function (Blueprint $t) {
    $t->id();
    $t->string('cover_image_path', 500)->nullable();
    $t->string('sort_method', 20)->default('date_desc');
    $t->timestampsTz();
});

Schema::create('category_translations', function (Blueprint $t) {
    $t->id();
    $t->foreignId('category_id')->constrained()->cascadeOnDelete();
    $t->string('locale', 5);
    $t->string('name', 100);
    $t->string('slug', 120);
    $t->text('description')->nullable();

    $t->unique(['category_id', 'locale']);
    $t->unique(['locale', 'slug']);
});
```

---

### 2.8 Pivot tables

**`post_tag`**：使用 Laravel 標準寫法，不需要獨立 model（除非要附加欄位）。

```php
Schema::create('post_tag', function (Blueprint $t) {
    $t->foreignId('post_id')->constrained()->cascadeOnDelete();
    $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $t->primary(['post_id', 'tag_id']);
    $t->index(['tag_id', 'post_id']);
});
```

**`tweet_tag`** 結構同上。

**`category_post`**：附加 `order_in_category` 欄位。

```php
Schema::create('category_post', function (Blueprint $t) {
    $t->foreignId('category_id')->constrained()->cascadeOnDelete();
    $t->foreignId('post_id')->constrained()->cascadeOnDelete();
    $t->integer('order_in_category')->nullable();
    $t->primary(['category_id', 'post_id']);
    $t->index(['post_id', 'category_id']);
    $t->index(['category_id', 'order_in_category']);
});
```

---

### 2.9 `App\Models\Media`

```php
class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'path', 'mime_type', 'size', 'width', 'height',
        'original_filename', 'uploaded_by',
    ];

    protected $casts = [
        'size'   => 'integer',
        'width'  => 'integer',
        'height' => 'integer',
    ];

    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
```

**Migration**：

```php
Schema::create('media', function (Blueprint $t) {
    $t->id();
    $t->string('path', 500)->unique();
    $t->string('mime_type', 100);
    $t->bigInteger('size');
    $t->integer('width')->nullable();
    $t->integer('height')->nullable();
    $t->string('original_filename', 255)->nullable();
    $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
    $t->timestampsTz();
});
```

---

### 2.10 `App\Models\PostViewLog`

```php
class PostViewLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['post_id', 'fingerprint', 'viewed_at'];
    protected $casts = ['viewed_at' => 'datetime'];

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }
}
```

**Migration**：

```php
Schema::create('post_view_logs', function (Blueprint $t) {
    $t->id();
    $t->foreignId('post_id')->constrained()->cascadeOnDelete();
    $t->string('fingerprint', 64);
    $t->timestampTz('viewed_at');

    $t->index(['post_id', 'fingerprint', 'viewed_at'], 'post_view_logs_dedup_idx');
});
```

---

## 3. Cross-locale Tag / Category 同步機制

當 Admin 編輯一個 Post 並設定 tags 時，PostService 會將相同的 tag IDs `sync` 到同 group 的所有 locale 版本。Category 同理。

```php
// PostService::syncTagsAcrossGroup
public function syncTagsAcrossGroup(Post $post, array $tagIds): void
{
    DB::transaction(function () use ($post, $tagIds) {
        $siblings = Post::where('post_group_id', $post->post_group_id)->get();
        foreach ($siblings as $p) {
            $p->tags()->sync($tagIds);
        }
    });
}

// PostService::syncCategoriesAcrossGroup
public function syncCategoriesAcrossGroup(Post $post, array $categoryIdsWithOrder): void
{
    // $categoryIdsWithOrder = [123 => ['order_in_category' => 1], 456 => ['order_in_category' => null]]
    DB::transaction(function () use ($post, $categoryIdsWithOrder) {
        $siblings = Post::where('post_group_id', $post->post_group_id)->get();
        foreach ($siblings as $p) {
            $p->categories()->sync($categoryIdsWithOrder);
        }
    });
}
```

> 注意：當新增 group 的新語言版本時，新建的 Post 也要立刻 sync 既有 sibling 的 tags / categories。實作於 `PostService::createTranslation()`。

---

## 4. Database Seeders

| Seeder | 用途 |
|---|---|
| `DatabaseSeeder` | 主入口 |
| `AdminUserSeeder` | 從 `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME`) 建立唯一 admin |
| `HugoMigrationSeeder` | 從 `~/hugo-blowfish-blog/content/` 匯入舊資料（詳見 Step 6） |

> `HugoMigrationSeeder` 也可改用 `artisan blog:import-from-hugo` Console Command，方便重跑與除錯。建議**用 Command 實作**，Seeder 內呼叫之。

---

## 5. Model 之間關係 ASCII 圖

```text
              ┌──────────────┐
              │    users     │
              └──────┬───────┘
                     │ author_id
                     ▼
┌─────────────┐   ┌──────────────┐                    ┌─────────────────┐
│ post_groups │◄──│    posts     │──── post_tag ─────►│      tags       │
└─────────────┘   └────┬─────────┘                    └────┬────────────┘
                       │                                   │
                       │ category_post                     │ tag_translations
                       │ (+ order_in_category)             ▼
                       ▼                          ┌──────────────────┐
                ┌─────────────┐                   │ tag_translations │
                │ categories  │                   └──────────────────┘
                └────┬────────┘
                     │ category_translations
                     ▼
              ┌─────────────────────┐
              │ category_translations│
              └─────────────────────┘

┌──────────────┐   ┌──────────────┐                    ┌──────────────┐
│tweet_groups  │◄──│    tweets    │──── tweet_tag ────►│     tags     │
└──────────────┘   └──────────────┘                    └──────────────┘

posts ──< post_view_logs
media ── (referenced by path in posts.cover_image_path / tweets.media JSON)
```

---

## 6. FormRequest 一覽（前置 step，細節留待 Step 4）

| Request | Endpoint | Rules 重點 |
|---|---|---|
| `Admin/Post/StoreRequest` | POST /admin/posts | post_group_id（nullable）、locale required in_array、title required、body required、status in_array、tags array of int、categories array |
| `Admin/Post/UpdateRequest` | PUT /admin/posts/{id} | 同上，多數欄位 nullable |
| `Admin/Tweet/StoreRequest` | POST /admin/tweets | tweet_group_id（nullable）、locale required、body required max 500、media array of {path,type} |
| `Admin/Tweet/UpdateRequest` | PUT /admin/tweets/{id} | 同上 |
| `Admin/Tag/StoreRequest` | POST /admin/tags | translations array required（至少一筆）、每個 translation {locale, name, slug} |
| `Admin/Tag/UpdateRequest` | PUT /admin/tags/{id} | translations array |
| `Admin/Category/StoreRequest` | POST /admin/categories | translations array、sort_method enum |
| `Admin/Category/UpdateRequest` | PUT /admin/categories/{id} | 同上 |
| `Admin/Media/StoreRequest` | POST /admin/media | file required, max size, mime types |
| `Auth/LoginRequest` | POST /login | email required email、password required |

---

## 7. 既未實作但有預留空間的擴充點

- `Post::scopeSearch()` — 之後加入 PostgreSQL full-text search（`to_tsvector`），不需 schema 變動，但會新增 GIN index migration。
- `Category::SORT_VIEWS` — 若日後需要按觀看排序。
- `Post::is_pinned` 用 `is_featured` 替代，未來需要區分時可再加欄位。
