<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root redirect to detected locale
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->to('/' . app()->getLocale());
})->name('public.root');

/*
|--------------------------------------------------------------------------
| Locale switching endpoint (no locale prefix)
|--------------------------------------------------------------------------
*/

Route::post('/locale/{locale}', [Public\LocaleController::class, 'switch'])
    ->where('locale', 'zh|en|ja|vi|id')
    ->name('public.locale.switch');

/*
|--------------------------------------------------------------------------
| Authentication (admin login / logout)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('auth.login.show');
    Route::post('/login', [LoginController::class, 'store'])->name('auth.login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('auth.logout');
});

/*
|--------------------------------------------------------------------------
| Admin routes (auth + admin role required)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Posts
        Route::get('posts', [Admin\PostController::class, 'index'])->name('posts.index');
        Route::get('posts/create', [Admin\PostController::class, 'create'])->name('posts.create');
        Route::get('posts/search', [Admin\PostController::class, 'search'])->name('posts.search');
        Route::post('posts', [Admin\PostController::class, 'store'])->name('posts.store');
        Route::get('posts/{post}/edit', [Admin\PostController::class, 'edit'])->name('posts.edit');
        Route::put('posts/{post}', [Admin\PostController::class, 'update'])->name('posts.update');
        Route::delete('posts/{post}', [Admin\PostController::class, 'destroy'])->name('posts.destroy');
        Route::post('posts/{id}/restore', [Admin\PostController::class, 'restore'])->name('posts.restore');
        Route::patch('posts/{post}/status', [Admin\PostController::class, 'updateStatus'])->name('posts.status');
        Route::post('posts/{post}/translation', [Admin\PostController::class, 'createTranslation'])
            ->name('posts.create-translation');

        // Tweets
        Route::get('tweets', [Admin\TweetController::class, 'index'])->name('tweets.index');
        Route::get('tweets/create', [Admin\TweetController::class, 'create'])->name('tweets.create');
        Route::post('tweets', [Admin\TweetController::class, 'store'])->name('tweets.store');
        Route::get('tweets/{tweet}/edit', [Admin\TweetController::class, 'edit'])->name('tweets.edit');
        Route::put('tweets/{tweet}', [Admin\TweetController::class, 'update'])->name('tweets.update');
        Route::delete('tweets/{tweet}', [Admin\TweetController::class, 'destroy'])->name('tweets.destroy');
        Route::post('tweets/{id}/restore', [Admin\TweetController::class, 'restore'])->name('tweets.restore');
        Route::patch('tweets/{tweet}/status', [Admin\TweetController::class, 'updateStatus'])->name('tweets.status');
        Route::post('tweets/{tweet}/translation', [Admin\TweetController::class, 'createTranslation'])
            ->name('tweets.create-translation');

        // Tags
        Route::get('tags', [Admin\TagController::class, 'index'])->name('tags.index');
        Route::post('tags', [Admin\TagController::class, 'store'])->name('tags.store');
        Route::put('tags/{tag}', [Admin\TagController::class, 'update'])->name('tags.update');
        Route::delete('tags/{tag}', [Admin\TagController::class, 'destroy'])->name('tags.destroy');

        // Categories
        Route::get('categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

        // Pages
        Route::get('pages',                     [Admin\PageController::class, 'index'])->name('pages.index');
        Route::get('pages/create',              [Admin\PageController::class, 'create'])->name('pages.create');
        Route::post('pages',                    [Admin\PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{page}/edit',         [Admin\PageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}',              [Admin\PageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}',           [Admin\PageController::class, 'destroy'])->name('pages.destroy');
        Route::post('pages/{id}/restore',       [Admin\PageController::class, 'restore'])->name('pages.restore');
        Route::post('pages/{page}/translation', [Admin\PageController::class, 'createTranslation'])->name('pages.create-translation');

        // Media
        Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
        Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');
        Route::delete('media/{id}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');

        // API Tokens
        Route::get('tokens', [Admin\ApiTokenController::class, 'index'])->name('tokens.index');
        Route::post('tokens', [Admin\ApiTokenController::class, 'store'])->name('tokens.store');
        Route::delete('tokens/{id}', [Admin\ApiTokenController::class, 'destroy'])->name('tokens.destroy');

        // Todos
        Route::get('todos', [Admin\TodoController::class, 'index'])->name('todos.index');
        Route::post('todos', [Admin\TodoController::class, 'store'])->name('todos.store');
        Route::put('todos/{todo}', [Admin\TodoController::class, 'update'])->name('todos.update');
        Route::delete('todos/{todo}', [Admin\TodoController::class, 'destroy'])->name('todos.destroy');
    });

/*
|--------------------------------------------------------------------------
| Changelog (English-only, top-level, outside the locale group)
|--------------------------------------------------------------------------
*/

Route::get('/changelog', [Public\ChangelogController::class, 'index'])->name('changelog');

/*
|--------------------------------------------------------------------------
| Public site (locale prefix)
|--------------------------------------------------------------------------
*/

Route::prefix('{locale}')
    ->where(['locale' => 'zh|en|ja|vi|id'])
    ->middleware('set-locale')
    ->name('public.')
    ->group(function () {
        Route::get('/', [Public\HomeController::class, 'index'])->name('home');

        Route::get('posts', [Public\PostController::class, 'index'])->name('posts.index');
        Route::get('posts/{slug}', [Public\PostController::class, 'show'])
            ->middleware('track-post-view')
            ->name('posts.show');

        Route::get('tweets', [Public\TweetController::class, 'index'])->name('tweets.index');
        Route::get('tweets/{id}', [Public\TweetController::class, 'show'])->name('tweets.show');

        Route::get('tags/{slug}', [Public\TagController::class, 'show'])->name('tags.show');
        Route::get('categories/{slug}', [Public\CategoryController::class, 'show'])->name('categories.show');

        Route::get('search', [Public\SearchController::class, 'index'])->name('search');

        // Static page catch-all (must be LAST so it doesn't shadow other routes above).
        // Matches /{locale}/{slug} where slug is not one of the reserved names above.
        Route::get('{slug}', [Public\PageController::class, 'show'])
            ->where('slug', '(?!posts$|tweets$|tags$|categories$|search$)[A-Za-z0-9_\-]+')
            ->name('pages.show');
    });
