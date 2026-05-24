# JYu's Blog — Routes & Controllers (MVP)

> Laravel: 13.x · PHP 8.5
> 前端：Blade + Alpine.js + Tailwind（SSR；不使用 Inertia / Livewire）
> 搜尋：PostgreSQL full-text search
> 對應文件：`docs/mvp/db/schema.md`, `docs/mvp/laravel/modelAndMigration.md`

---

## 0. 設計規章

### 0.1 分層職責

```
Routes (web.php)
    ↓
Middleware (auth, admin, locale, track-view)
    ↓
Controller (HTTP 概念：parse request、return view)
    ↓
FormRequest (validation only)
    ↓
Service (business logic：交易、跨資源協調)
    ↓
Repository (純查詢：複雜的 query builder / scope 組合)
    ↓
Model (relationships, scopes, helpers)
    ↓
DB (PostgreSQL)
```

**規則**

- Controller 不直接操作 Model（除了最簡單的 `findOrFail`）；商業邏輯一律走 Service。
- Service 不接收 `Request` 物件，只接收已驗證的 DTO array / scalar。Controller 負責把 `FormRequest::validated()` 轉成參數。
- Repository 只放查詢、不放寫入；寫入由 Service 直接呼叫 Model `save() / create()` 完成。
- Model scope 只放可重用的 query 片段；複合查詢組合（例如「列表 + 過濾 + 排序 + 分頁」）放在 Repository。

### 0.2 命名空間

```
App\Http\Controllers\Public\*       # 前台
App\Http\Controllers\Admin\*        # 後台
App\Http\Controllers\Auth\*         # 登入登出
App\Http\Requests\Public\*
App\Http\Requests\Admin\*
App\Http\Requests\Auth\*
App\Http\Middleware\*
App\Services\*
App\Repositories\*
App\Support\*           # 工具：SlugGenerator, MarkdownRenderer, ShortcodeConverter
```

### 0.3 URL 與 Locale 策略

- **Locale 一律放 URL prefix**：`/{locale}/...`，`locale ∈ {zh, en, ja, vi, id}`。
- 訪問 `/` → middleware 偵測（cookie → Accept-Language → fallback `zh`）→ 302 到對應 `/{locale}`。
- Admin 路由不帶 locale prefix；admin 介面內可切換編輯中的 locale。
- 切換語言：`POST /locale/{locale}`（CSRF 保護）→ 設 cookie + 嘗試重導到同 group 對應語言版本的 URL，找不到則回首頁。

### 0.4 路由命名

- 全域命名為 `<area>.<resource>.<action>`：
  - `public.home`, `public.posts.index`, `public.posts.show`, `public.tweets.index`, `public.tweets.show`
  - `public.tags.show`, `public.categories.show`, `public.search`
  - `auth.login.show`, `auth.login.store`, `auth.logout`
  - `admin.dashboard`, `admin.posts.index`, `admin.posts.edit`, `admin.posts.update`, ...

---

## 1. Routes 完整定義（routes/web.php）

```php
use App\Http\Controllers\{Public, Admin, Auth};

// =============================================================
// 全域 redirect: / → /{detected-locale}
// =============================================================
Route::get('/', function () {
    return redirect()->to('/' . app()->getLocale());
})->name('public.root');

// =============================================================
// Locale switch endpoint（不帶 locale prefix）
// =============================================================
Route::post('/locale/{locale}', [Public\LocaleController::class, 'switch'])
    ->where('locale', 'zh|en|ja|vi|id')
    ->name('public.locale.switch');

// =============================================================
// Auth routes（無 locale prefix；admin login）
// =============================================================
Route::middleware('guest')->group(function () {
    Route::get('/login',  [Auth\LoginController::class, 'show'])->name('auth.login.show');
    Route::post('/login', [Auth\LoginController::class, 'store'])->name('auth.login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [Auth\LoginController::class, 'destroy'])->name('auth.logout');
});

// =============================================================
// Admin routes（auth + admin role）
// =============================================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // ---- Posts ----
    Route::get('posts',                   [Admin\PostController::class, 'index'])->name('posts.index');
    Route::get('posts/create',            [Admin\PostController::class, 'create'])->name('posts.create');
    Route::post('posts',                  [Admin\PostController::class, 'store'])->name('posts.store');
    Route::get('posts/{post}/edit',       [Admin\PostController::class, 'edit'])->name('posts.edit');
    Route::put('posts/{post}',            [Admin\PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}',         [Admin\PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('posts/{id}/restore',     [Admin\PostController::class, 'restore'])
        ->withTrashed()->name('posts.restore');
    Route::patch('posts/{post}/status',   [Admin\PostController::class, 'updateStatus'])->name('posts.status');
    Route::post('posts/{post}/translation', [Admin\PostController::class, 'createTranslation'])
        ->name('posts.create-translation');

    // ---- Tweets ----
    Route::get('tweets',               [Admin\TweetController::class, 'index'])->name('tweets.index');
    Route::get('tweets/create',        [Admin\TweetController::class, 'create'])->name('tweets.create');
    Route::post('tweets',              [Admin\TweetController::class, 'store'])->name('tweets.store');
    Route::get('tweets/{tweet}/edit',  [Admin\TweetController::class, 'edit'])->name('tweets.edit');
    Route::put('tweets/{tweet}',       [Admin\TweetController::class, 'update'])->name('tweets.update');
    Route::delete('tweets/{tweet}',    [Admin\TweetController::class, 'destroy'])->name('tweets.destroy');
    Route::post('tweets/{id}/restore', [Admin\TweetController::class, 'restore'])
        ->withTrashed()->name('tweets.restore');
    Route::patch('tweets/{tweet}/status', [Admin\TweetController::class, 'updateStatus'])->name('tweets.status');
    Route::post('tweets/{tweet}/translation', [Admin\TweetController::class, 'createTranslation'])
        ->name('tweets.create-translation');

    // ---- Tags ----
    Route::get('tags',             [Admin\TagController::class, 'index'])->name('tags.index');
    Route::post('tags',            [Admin\TagController::class, 'store'])->name('tags.store');
    Route::put('tags/{tag}',       [Admin\TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}',    [Admin\TagController::class, 'destroy'])->name('tags.destroy');

    // ---- Categories ----
    Route::get('categories',                 [Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories',                [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}',      [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}',   [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    // ---- Media ----
    Route::get('media',          [Admin\MediaController::class, 'index'])->name('media.index');
    Route::post('media',         [Admin\MediaController::class, 'store'])->name('media.store');
    Route::delete('media/{id}',  [Admin\MediaController::class, 'destroy'])->name('media.destroy');
});

// =============================================================
// Public routes（locale prefix）
// =============================================================
Route::prefix('{locale}')
    ->where(['locale' => 'zh|en|ja|vi|id'])
    ->middleware('set-locale')
    ->name('public.')
    ->group(function () {

        Route::get('/', [Public\HomeController::class, 'index'])->name('home');

        Route::get('posts',                  [Public\PostController::class, 'index'])->name('posts.index');
        Route::get('posts/{slug}',           [Public\PostController::class, 'show'])
            ->middleware('track-post-view')
            ->name('posts.show');

        Route::get('tweets',                 [Public\TweetController::class, 'index'])->name('tweets.index');
        Route::get('tweets/{id}',            [Public\TweetController::class, 'show'])->name('tweets.show');

        Route::get('tags/{slug}',            [Public\TagController::class, 'show'])->name('tags.show');
        Route::get('categories/{slug}',      [Public\CategoryController::class, 'show'])->name('categories.show');

        Route::get('search',                 [Public\SearchController::class, 'index'])->name('search');
    });
```

---

## 2. Middleware

### 2.1 `App\Http\Middleware\SetLocale`

從 URL `{locale}` 參數設定 `app()->setLocale($locale)`，並覆寫前後台呼叫的 locale。

```php
public function handle(Request $request, Closure $next): Response
{
    $locale = $request->route('locale');
    if (in_array($locale, Post::SUPPORTED_LOCALES, true)) {
        app()->setLocale($locale);
        cookie()->queue('locale', $locale, 60 * 24 * 365);
    }
    return $next($request);
}
```

### 2.2 `App\Http\Middleware\DetectLocale`

用於根 `/` 路由與 admin 進入時設定 locale。優先順序：cookie → Accept-Language → config('app.locale')。

```php
public function handle(Request $request, Closure $next): Response
{
    $locale = $request->cookie('locale')
        ?? collect($request->getLanguages())->first(fn($l) =>
            in_array(substr($l, 0, 2), Post::SUPPORTED_LOCALES))
        ?? config('app.locale');

    app()->setLocale($locale);
    return $next($request);
}
```

註冊於 `bootstrap/app.php` 的 global middleware。

### 2.3 `App\Http\Middleware\EnsureUserRole`

別名為 `role`，參數化：`Route::middleware('role:admin')`。

```php
public function handle(Request $request, Closure $next, string $role): Response
{
    if (! $request->user() || $request->user()->role !== $role) abort(403);
    return $next($request);
}
```

### 2.4 `App\Http\Middleware\TrackPostView`

掛在 `posts/{slug}` 上，response 發出後背景記錄 view（async via `terminate()`）。

```php
public function handle(Request $request, Closure $next): Response
{
    return $next($request);
}

public function terminate(Request $request, Response $response): void
{
    if ($response->getStatusCode() !== 200) return;
    $post = $request->route('post'); // 由 Controller 設入
    if ($post instanceof Post) {
        app(ViewTrackingService::class)->track(
            $post,
            $request->ip(),
            $request->userAgent() ?? '',
        );
    }
}
```

---

## 3. Public Controllers

### 3.1 `Public\HomeController`

```php
public function index(PostRepository $posts, TweetRepository $tweets, TagRepository $tags): View
{
    $locale = app()->getLocale();
    return view('public.home', [
        'featuredPosts' => $posts->featured($locale, limit: 4),
        'recentTweets'  => $tweets->recent($locale, limit: 4),
        'popularTags'   => $tags->popular($locale, limit: 12),
    ]);
}
```

### 3.2 `Public\PostController`

```php
public function index(PostListRequest $req, PostRepository $repo): View
{
    $locale = app()->getLocale();
    $params = $req->validated();

    $posts = $repo->paginate(
        locale:   $locale,
        sort:     $params['sort'] ?? 'published',       // 'updated' | 'views' | 'published'
        tag:      $params['tag'] ?? null,
        category: $params['category'] ?? null,
        perPage:  12,
    );

    return view('public.posts.index', [
        'posts'      => $posts,
        'categories' => app(CategoryRepository::class)->all($locale),
        'tags'       => app(TagRepository::class)->all($locale),
    ]);
}

public function show(string $locale, string $slug, PostRepository $repo, Request $request): View
{
    $post = $repo->findPublishedBySlug($locale, $slug);
    abort_if(!$post, 404);

    // 給 TrackPostView middleware 使用
    $request->route()->setParameter('post', $post);

    return view('public.posts.show', [
        'post'         => $post,
        'translations' => $post->allTranslations()->keyBy('locale'),
        'seriesNav'    => app(PostService::class)->seriesNavigation($post),
    ]);
}
```

### 3.3 `Public\TweetController`

```php
public function index(TweetRepository $repo): View
{
    $tweets = $repo->paginate(app()->getLocale(), perPage: 20);
    return view('public.tweets.index', ['tweets' => $tweets]);
}

public function show(string $locale, int $id, TweetRepository $repo): View
{
    $tweet = $repo->findPublished($locale, $id);
    abort_if(!$tweet, 404);
    return view('public.tweets.show', ['tweet' => $tweet]);
}
```

### 3.4 `Public\TagController` / `Public\CategoryController`

```php
// TagController::show
public function show(string $locale, string $slug, TagRepository $tagRepo, PostRepository $postRepo, TweetRepository $tweetRepo): View
{
    $tag = $tagRepo->findBySlug($locale, $slug);
    abort_if(!$tag, 404);

    return view('public.tags.show', [
        'tag'    => $tag,
        'posts'  => $postRepo->byTag($tag, $locale, perPage: 12),
        'tweets' => $tweetRepo->byTag($tag, $locale, perPage: 20),
    ]);
}

// CategoryController::show
public function show(string $locale, string $slug, CategoryRepository $catRepo, PostRepository $postRepo): View
{
    $category = $catRepo->findBySlug($locale, $slug);
    abort_if(!$category, 404);

    return view('public.categories.show', [
        'category' => $category,
        'posts'    => $postRepo->byCategory($category, $locale, perPage: 12),
    ]);
}
```

### 3.5 `Public\SearchController`

```php
public function index(SearchRequest $req, SearchService $search): View
{
    $query = $req->validated()['q'] ?? '';
    $type  = $req->validated()['type'] ?? 'all';   // all | post | tweet
    $locale = app()->getLocale();

    $results = $query === ''
        ? ['posts' => collect(), 'tweets' => collect()]
        : $search->fullText($query, $locale, $type);

    return view('public.search', [
        'q'       => $query,
        'type'    => $type,
        'results' => $results,
    ]);
}
```

### 3.6 `Public\LocaleController`

```php
public function switch(string $locale, Request $request, PostService $svc): RedirectResponse
{
    abort_unless(in_array($locale, Post::SUPPORTED_LOCALES, true), 400);

    cookie()->queue('locale', $locale, 60 * 24 * 365);

    // 嘗試從來源 URL 推算對應翻譯
    $referer = $request->header('referer');
    $target  = $svc->equivalentUrlInLocale($referer, $locale) ?? "/{$locale}";
    return redirect($target);
}
```

---

## 4. Admin Controllers

### 4.1 `Admin\DashboardController`

```php
public function index(PostRepository $p, TweetRepository $t): View
{
    return view('admin.dashboard', [
        'stats' => [
            'posts_published'  => $p->countByStatus(Post::STATUS_PUBLISHED),
            'posts_draft'      => $p->countByStatus(Post::STATUS_DRAFT),
            'tweets_published' => $t->countByStatus(Tweet::STATUS_PUBLISHED),
        ],
        'recentPosts'  => $p->recentForAdmin(limit: 5),
        'recentTweets' => $t->recentForAdmin(limit: 5),
    ]);
}
```

### 4.2 `Admin\PostController`

```php
public function index(Admin\Post\IndexRequest $req, PostRepository $repo): View
{
    $params = $req->validated();
    $posts  = $repo->adminPaginate(
        status:    $params['status'] ?? null,         // all | published | draft | hidden | trashed
        locale:    $params['locale'] ?? null,
        search:    $params['q'] ?? null,
        perPage:   20,
    );

    return view('admin.posts.index', [
        'posts'         => $posts,
        'counts'        => $repo->countsByStatus(),     // for tabs
        'currentStatus' => $params['status'] ?? 'all',
    ]);
}

public function create(): View
{
    return view('admin.posts.edit', [
        'post'       => new Post(['status' => 'draft', 'locale' => app()->getLocale()]),
        'tags'       => app(TagRepository::class)->all(),
        'categories' => app(CategoryRepository::class)->all(),
        'mode'       => 'create',
    ]);
}

public function store(Admin\Post\StoreRequest $req, PostService $svc): RedirectResponse
{
    $post = $svc->create($req->validated());
    return redirect()
        ->route('admin.posts.edit', $post)
        ->with('success', '建立成功');
}

public function edit(Post $post): View
{
    return view('admin.posts.edit', [
        'post'         => $post,
        'translations' => $post->allTranslations()->keyBy('locale'),
        'tags'         => app(TagRepository::class)->all(),
        'categories'   => app(CategoryRepository::class)->all(),
        'mode'         => 'edit',
    ]);
}

public function update(Post $post, Admin\Post\UpdateRequest $req, PostService $svc): RedirectResponse
{
    $svc->update($post, $req->validated());
    return redirect()->route('admin.posts.edit', $post)->with('success', '已更新');
}

public function destroy(Post $post, PostService $svc): RedirectResponse
{
    $svc->softDelete($post);
    return redirect()->route('admin.posts.index')->with('success', '已移至垃圾桶');
}

public function restore(int $id, PostService $svc): RedirectResponse
{
    $post = Post::withTrashed()->findOrFail($id);
    $svc->restore($post);
    return redirect()->route('admin.posts.index')->with('success', '已還原');
}

public function updateStatus(Post $post, Admin\Post\UpdateStatusRequest $req, PostService $svc): RedirectResponse
{
    $svc->updateStatus($post, $req->validated()['status']);
    return back()->with('success', '狀態已更新');
}

public function createTranslation(Post $post, Admin\Post\CreateTranslationRequest $req, PostService $svc): RedirectResponse
{
    $newPost = $svc->createTranslation($post, $req->validated()['locale']);
    return redirect()->route('admin.posts.edit', $newPost);
}
```

### 4.3 `Admin\TweetController`

結構同 PostController，方法少了 `category` 與 `is_featured` 相關欄位。

### 4.4 `Admin\TagController`

```php
public function index(TagRepository $repo): View
{
    return view('admin.tags.index', [
        'tags' => $repo->allWithCounts(),  // 含 post_count / tweet_count
    ]);
}

public function store(Admin\Tag\StoreRequest $req, TagService $svc): RedirectResponse
{
    $svc->create($req->validated());
    return back()->with('success', '已建立');
}

public function update(Tag $tag, Admin\Tag\UpdateRequest $req, TagService $svc): RedirectResponse
{
    $svc->update($tag, $req->validated());
    return back()->with('success', '已更新');
}

public function destroy(Tag $tag, TagService $svc): RedirectResponse
{
    $svc->delete($tag);  // detach pivot + delete translations + delete tag
    return back()->with('success', '已刪除');
}
```

### 4.5 `Admin\CategoryController`

結構同 TagController；多了 `cover_image_path` 上傳與 `sort_method` 欄位。

### 4.6 `Admin\MediaController`

```php
public function index(MediaService $svc): View
{
    return view('admin.media.index', ['media' => $svc->paginate(perPage: 24)]);
}

public function store(Admin\Media\StoreRequest $req, MediaService $svc): JsonResponse
{
    // 此端點被 admin editor 透過 AJAX 呼叫，回 JSON 便於 inline 插入
    $media = $svc->upload($req->file('file'), $req->user());
    return response()->json([
        'id'   => $media->id,
        'url'  => $media->url(),
        'path' => $media->path,
    ]);
}

public function destroy(int $id, MediaService $svc): JsonResponse
{
    $svc->delete($id);
    return response()->json(['ok' => true]);
}
```

> **例外**：MediaController 因配合 WYSIWYG editor 的內嵌上傳需求，回 JSON 而非 view。其他 Admin endpoint 都回 view 或 redirect。

---

## 5. Auth Controller

### 5.1 `Auth\LoginController`

```php
public function show(): View
{
    return view('auth.login');
}

public function store(Auth\LoginRequest $req): RedirectResponse
{
    $credentials = $req->validated();

    if (! Auth::attempt($credentials, remember: true)) {
        return back()->withErrors(['email' => '帳號或密碼錯誤']);
    }

    $req->session()->regenerate();
    return redirect()->intended(route('admin.dashboard'));
}

public function destroy(Request $req): RedirectResponse
{
    Auth::logout();
    $req->session()->invalidate();
    $req->session()->regenerateToken();
    return redirect()->route('auth.login.show');
}
```

---

## 6. FormRequest 規格

### 6.1 `Auth\LoginRequest`
```php
public function rules(): array {
    return [
        'email'    => 'required|email',
        'password' => 'required|string|min:8',
    ];
}
```

### 6.2 `Admin\Post\StoreRequest`
```php
public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }
public function rules(): array {
    return [
        'post_group_id'    => 'nullable|integer|exists:post_groups,id',
        'locale'           => 'required|string|in:zh,en,ja,vi,id',
        'title'            => 'required|string|max:255',
        'slug'             => 'nullable|string|max:255|regex:/^[a-z0-9\-]+$/',
        'excerpt'          => 'nullable|string|max:500',
        'body'             => 'required|string',
        'cover_image_path' => 'nullable|string|max:500',
        'status'           => 'required|in:draft,published,hidden',
        'is_featured'      => 'sometimes|boolean',
        'published_at'     => 'nullable|date',
        'tag_ids'          => 'array',
        'tag_ids.*'        => 'integer|exists:tags,id',
        'category_ids'     => 'array',
        'category_ids.*'   => 'integer|exists:categories,id',
        'categories_order' => 'array',                 // map: category_id => order
    ];
}
```

### 6.3 `Admin\Post\UpdateRequest`

同 `StoreRequest`，但 `title` / `body` / `status` 加 `sometimes`，移除 `post_group_id`。

### 6.4 `Admin\Post\UpdateStatusRequest`
```php
public function rules(): array {
    return ['status' => 'required|in:draft,published,hidden'];
}
```

### 6.5 `Admin\Post\CreateTranslationRequest`
```php
public function rules(): array {
    return ['locale' => 'required|in:zh,en,ja,vi,id'];
}
```

### 6.6 `Admin\Post\IndexRequest`
```php
public function rules(): array {
    return [
        'status' => 'nullable|in:all,published,draft,hidden,trashed',
        'locale' => 'nullable|in:zh,en,ja,vi,id',
        'q'      => 'nullable|string|max:100',
        'page'   => 'nullable|integer|min:1',
    ];
}
```

### 6.7 `Admin\Tweet\StoreRequest`
```php
public function rules(): array {
    return [
        'tweet_group_id'   => 'nullable|integer|exists:tweet_groups,id',
        'locale'           => 'required|in:zh,en,ja,vi,id',
        'body'             => 'required|string|max:1000',
        'media'            => 'nullable|array|max:4',
        'media.*.path'     => 'required_with:media|string|max:500',
        'media.*.type'     => 'required_with:media|in:image,video',
        'media.*.alt'      => 'nullable|string|max:200',
        'status'           => 'required|in:draft,published,hidden',
        'published_at'     => 'nullable|date',
        'tag_ids'          => 'array',
        'tag_ids.*'        => 'integer|exists:tags,id',
    ];
}
```

### 6.8 `Admin\Tag\StoreRequest`
```php
public function rules(): array {
    return [
        'color'                       => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
        'translations'                => 'required|array|min:1',
        'translations.*.locale'       => 'required|in:zh,en,ja,vi,id|distinct',
        'translations.*.name'         => 'required|string|max:100',
        'translations.*.slug'         => 'required|string|max:120|regex:/^[a-z0-9\-]+$/',
    ];
}
```

### 6.9 `Admin\Category\StoreRequest`
```php
public function rules(): array {
    return [
        'cover_image_path'              => 'nullable|string|max:500',
        'sort_method'                   => 'required|in:manual,date_desc,date_asc',
        'translations'                  => 'required|array|min:1',
        'translations.*.locale'         => 'required|in:zh,en,ja,vi,id|distinct',
        'translations.*.name'           => 'required|string|max:100',
        'translations.*.slug'           => 'required|string|max:120|regex:/^[a-z0-9\-]+$/',
        'translations.*.description'    => 'nullable|string|max:500',
    ];
}
```

### 6.10 `Admin\Media\StoreRequest`
```php
public function rules(): array {
    return [
        'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,gif,mp4,webm',
    ];
}
```

### 6.11 `Public\PostListRequest`
```php
public function rules(): array {
    return [
        'sort'     => 'nullable|in:published,updated,views',
        'tag'      => 'nullable|integer|exists:tags,id',
        'category' => 'nullable|integer|exists:categories,id',
        'page'     => 'nullable|integer|min:1',
    ];
}
```

### 6.12 `Public\SearchRequest`
```php
public function rules(): array {
    return [
        'q'    => 'nullable|string|max:100',
        'type' => 'nullable|in:all,post,tweet',
    ];
}
```

---

## 7. Service 層職責

| Service | 主要方法 | 職責 |
|---|---|---|
| `PostService` | `create()`, `update()`, `softDelete()`, `restore()`, `updateStatus()`, `createTranslation()`, `syncTagsAcrossGroup()`, `syncCategoriesAcrossGroup()`, `seriesNavigation()`, `equivalentUrlInLocale()` | 文章相關所有寫入；跨 locale tag/category 同步；series 前後文章導覽 |
| `TweetService` | `create()`, `update()`, `softDelete()`, `restore()`, `updateStatus()`, `createTranslation()`, `syncTagsAcrossGroup()` | 短文相關所有寫入 |
| `TagService` | `create()`, `update()`, `delete()` | 含 translations 同步 |
| `CategoryService` | `create()`, `update()`, `delete()` | 含 translations 同步 |
| `MediaService` | `upload()`, `delete()`, `paginate()` | 檔案上傳到 `storage/app/public/`、寫入 DB |
| `SearchService` | `fullText($q, $locale, $type)` | PostgreSQL tsvector 查詢，回傳 `['posts' => Collection, 'tweets' => Collection]` |
| `ViewTrackingService` | `track(Post $p, string $ip, string $ua)` | 計算 fingerprint、查 dedup window、insert log + increment counter |
| `App\Support\SlugGenerator` | `forPost($title, $locale, $postGroupId = null)`, `forTag(...)`, `forCategory(...)` | 從 title 產生 slug，碰到 collision 加 `-2`、`-3`|
| `App\Support\MarkdownRenderer` | `render(string $md): string` | 將 DB body Markdown 渲染為安全 HTML（含 syntax highlight） |
| `App\Support\ShortcodeConverter` | `convert(string $hugoMd): string` | Hugo shortcode → 標準 HTML（migration 用） |

---

## 8. Repository 層職責

| Repository | 主要方法 |
|---|---|
| `PostRepository` | `paginate(locale, sort, tag, category, perPage)`, `featured(locale, limit)`, `findPublishedBySlug(locale, slug)`, `byTag(Tag, locale, perPage)`, `byCategory(Category, locale, perPage)`, `adminPaginate(status, locale, search, perPage)`, `countsByStatus()`, `recentForAdmin(limit)`, `countByStatus($status)` |
| `TweetRepository` | `paginate(locale, perPage)`, `recent(locale, limit)`, `findPublished(locale, id)`, `byTag(Tag, locale, perPage)`, `adminPaginate(...)`, `recentForAdmin()`, `countByStatus()` |
| `TagRepository` | `all($locale = null)`, `allWithCounts()`, `findBySlug(locale, slug)`, `popular(locale, limit)` |
| `CategoryRepository` | `all($locale = null)`, `findBySlug(locale, slug)` |
| `MediaRepository` | `paginate(perPage)` |

> Repository 主要負責**回傳查詢結果**；Service 主要負責**寫入 / 跨表協調**。

---

## 9. PostgreSQL Full-text Search 補充

`SearchService::fullText()` 使用 PostgreSQL 內建 tsvector。

### 9.1 額外 migration（在 base schema 之後）

```php
// 在 posts / tweets 表加上 generated 欄位 + GIN index
DB::statement("
    ALTER TABLE posts ADD COLUMN search_vector tsvector
    GENERATED ALWAYS AS (
        setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
        setweight(to_tsvector('simple', coalesce(excerpt, '')), 'B') ||
        setweight(to_tsvector('simple', coalesce(body, '')), 'C')
    ) STORED
");
DB::statement("CREATE INDEX posts_search_vector_idx ON posts USING GIN (search_vector)");

DB::statement("
    ALTER TABLE tweets ADD COLUMN search_vector tsvector
    GENERATED ALWAYS AS (to_tsvector('simple', coalesce(body, ''))) STORED
");
DB::statement("CREATE INDEX tweets_search_vector_idx ON tweets USING GIN (search_vector)");
```

> 使用 `'simple'` configuration 是因為 PostgreSQL 預設 dictionary 對 CJK 不友善；對 MVP 已夠用（exact token match）。後續可換成 `pg_bigm` 或 ElasticSearch / Meilisearch。

### 9.2 查詢

```php
public function fullText(string $q, string $locale, string $type = 'all'): array
{
    $tsq = "websearch_to_tsquery('simple', ?)";
    $posts = $type !== 'tweet'
        ? Post::query()
            ->whereRaw("search_vector @@ {$tsq}", [$q])
            ->where('locale', $locale)
            ->published()
            ->orderByRaw("ts_rank(search_vector, {$tsq}) DESC", [$q])
            ->limit(20)
            ->get()
        : collect();

    $tweets = $type !== 'post'
        ? Tweet::query()
            ->whereRaw("search_vector @@ {$tsq}", [$q])
            ->where('locale', $locale)
            ->published()
            ->orderByRaw("ts_rank(search_vector, {$tsq}) DESC", [$q])
            ->limit(20)
            ->get()
        : collect();

    return compact('posts', 'tweets');
}
```

---

## 10. Route 一覽總表（含 verb / name / middleware）

| Method | URI | Name | Middleware | Controller@action |
|---|---|---|---|---|
| GET    | `/` | `public.root` | `detect-locale` | redirect to `/{locale}` |
| POST   | `/locale/{locale}` | `public.locale.switch` | | `LocaleController@switch` |
| GET    | `/login` | `auth.login.show` | `guest` | `Auth\LoginController@show` |
| POST   | `/login` | `auth.login.store` | `guest` | `Auth\LoginController@store` |
| POST   | `/logout` | `auth.logout` | `auth` | `Auth\LoginController@destroy` |
| GET    | `/admin` | `admin.dashboard` | `auth`,`role:admin` | `Admin\DashboardController@index` |
| GET    | `/admin/posts` | `admin.posts.index` | `auth`,`role:admin` | `Admin\PostController@index` |
| GET    | `/admin/posts/create` | `admin.posts.create` | `auth`,`role:admin` | `Admin\PostController@create` |
| POST   | `/admin/posts` | `admin.posts.store` | `auth`,`role:admin` | `Admin\PostController@store` |
| GET    | `/admin/posts/{post}/edit` | `admin.posts.edit` | `auth`,`role:admin` | `Admin\PostController@edit` |
| PUT    | `/admin/posts/{post}` | `admin.posts.update` | `auth`,`role:admin` | `Admin\PostController@update` |
| DELETE | `/admin/posts/{post}` | `admin.posts.destroy` | `auth`,`role:admin` | `Admin\PostController@destroy` |
| POST   | `/admin/posts/{id}/restore` | `admin.posts.restore` | `auth`,`role:admin` | `Admin\PostController@restore` |
| PATCH  | `/admin/posts/{post}/status` | `admin.posts.status` | `auth`,`role:admin` | `Admin\PostController@updateStatus` |
| POST   | `/admin/posts/{post}/translation` | `admin.posts.create-translation` | `auth`,`role:admin` | `Admin\PostController@createTranslation` |
| GET    | `/admin/tweets` | `admin.tweets.index` | `auth`,`role:admin` | `Admin\TweetController@index` |
| GET    | `/admin/tweets/create` | `admin.tweets.create` | `auth`,`role:admin` | `Admin\TweetController@create` |
| POST   | `/admin/tweets` | `admin.tweets.store` | `auth`,`role:admin` | `Admin\TweetController@store` |
| GET    | `/admin/tweets/{tweet}/edit` | `admin.tweets.edit` | `auth`,`role:admin` | `Admin\TweetController@edit` |
| PUT    | `/admin/tweets/{tweet}` | `admin.tweets.update` | `auth`,`role:admin` | `Admin\TweetController@update` |
| DELETE | `/admin/tweets/{tweet}` | `admin.tweets.destroy` | `auth`,`role:admin` | `Admin\TweetController@destroy` |
| POST   | `/admin/tweets/{id}/restore` | `admin.tweets.restore` | `auth`,`role:admin` | `Admin\TweetController@restore` |
| PATCH  | `/admin/tweets/{tweet}/status` | `admin.tweets.status` | `auth`,`role:admin` | `Admin\TweetController@updateStatus` |
| POST   | `/admin/tweets/{tweet}/translation` | `admin.tweets.create-translation` | `auth`,`role:admin` | `Admin\TweetController@createTranslation` |
| GET    | `/admin/tags` | `admin.tags.index` | `auth`,`role:admin` | `Admin\TagController@index` |
| POST   | `/admin/tags` | `admin.tags.store` | `auth`,`role:admin` | `Admin\TagController@store` |
| PUT    | `/admin/tags/{tag}` | `admin.tags.update` | `auth`,`role:admin` | `Admin\TagController@update` |
| DELETE | `/admin/tags/{tag}` | `admin.tags.destroy` | `auth`,`role:admin` | `Admin\TagController@destroy` |
| GET    | `/admin/categories` | `admin.categories.index` | `auth`,`role:admin` | `Admin\CategoryController@index` |
| POST   | `/admin/categories` | `admin.categories.store` | `auth`,`role:admin` | `Admin\CategoryController@store` |
| PUT    | `/admin/categories/{category}` | `admin.categories.update` | `auth`,`role:admin` | `Admin\CategoryController@update` |
| DELETE | `/admin/categories/{category}` | `admin.categories.destroy` | `auth`,`role:admin` | `Admin\CategoryController@destroy` |
| GET    | `/admin/media` | `admin.media.index` | `auth`,`role:admin` | `Admin\MediaController@index` |
| POST   | `/admin/media` | `admin.media.store` | `auth`,`role:admin` | `Admin\MediaController@store` |
| DELETE | `/admin/media/{id}` | `admin.media.destroy` | `auth`,`role:admin` | `Admin\MediaController@destroy` |
| GET    | `/{locale}` | `public.home` | `set-locale` | `Public\HomeController@index` |
| GET    | `/{locale}/posts` | `public.posts.index` | `set-locale` | `Public\PostController@index` |
| GET    | `/{locale}/posts/{slug}` | `public.posts.show` | `set-locale`,`track-post-view` | `Public\PostController@show` |
| GET    | `/{locale}/tweets` | `public.tweets.index` | `set-locale` | `Public\TweetController@index` |
| GET    | `/{locale}/tweets/{id}` | `public.tweets.show` | `set-locale` | `Public\TweetController@show` |
| GET    | `/{locale}/tags/{slug}` | `public.tags.show` | `set-locale` | `Public\TagController@show` |
| GET    | `/{locale}/categories/{slug}` | `public.categories.show` | `set-locale` | `Public\CategoryController@show` |
| GET    | `/{locale}/search` | `public.search` | `set-locale` | `Public\SearchController@index` |

---

## 11. 後續 Step 對應

- **Step 5（實作後端）**：按照上述 Controllers / Services / Repositories / FormRequests 結構建檔。
- **Step 6（Hugo 匯入）**：實作 `php artisan blog:import-from-hugo`，使用 `ShortcodeConverter`、`MediaService`、`PostService::create()`。
- **Step 7（前端）**：建立 Blade view 對應上方每個 view（`public.home`, `public.posts.index`, `admin.posts.edit` 等），整合 Tailwind + Alpine.js。
