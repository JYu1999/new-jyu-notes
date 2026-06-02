<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\Api\TweetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/me', MeController::class);

    Route::get('/todos', [TodoController::class, 'index'])->middleware('ability:todos:read');
    Route::post('/todos', [TodoController::class, 'store'])->middleware('ability:todos:create');
    Route::get('/todos/{todo}', [TodoController::class, 'show'])->middleware('ability:todos:read');
    Route::patch('/todos/{todo}', [TodoController::class, 'update'])->middleware('ability:todos:update');
    Route::delete('/todos/{todo}', [TodoController::class, 'destroy'])->middleware('ability:todos:delete');

    Route::get('/posts', [PostController::class, 'index'])->middleware('ability:posts:read');
    Route::post('/posts', [PostController::class, 'store'])->middleware('ability:posts:create');
    Route::get('/posts/{post}', [PostController::class, 'show'])->middleware('ability:posts:read');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->middleware('ability:posts:update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->middleware('ability:posts:delete');

    Route::post('/posts/{post}/translations', [PostController::class, 'storeTranslation'])->middleware('ability:posts:create');
    Route::post('/posts/{post}/publish', [PostController::class, 'publish'])->middleware('ability:posts:publish');

    Route::get('/tweets', [TweetController::class, 'index'])->middleware('ability:tweets:read');
    Route::post('/tweets', [TweetController::class, 'store'])->middleware('ability:tweets:create');
    Route::get('/tweets/{tweet}', [TweetController::class, 'show'])->middleware('ability:tweets:read');
    Route::patch('/tweets/{tweet}', [TweetController::class, 'update'])->middleware('ability:tweets:update');
    Route::delete('/tweets/{tweet}', [TweetController::class, 'destroy'])->middleware('ability:tweets:delete');

    Route::post('/tweets/{tweet}/translations', [TweetController::class, 'storeTranslation'])->middleware('ability:tweets:create');
    Route::post('/tweets/{tweet}/publish', [TweetController::class, 'publish'])->middleware('ability:tweets:publish');

    Route::get('/categories', [CategoryController::class, 'index'])->middleware('ability:categories:read');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('ability:categories:create');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('ability:categories:read');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->middleware('ability:categories:update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('ability:categories:delete');

    Route::get('/tags', [TagController::class, 'index'])->middleware('ability:tags:read');
    Route::post('/tags', [TagController::class, 'store'])->middleware('ability:tags:create');
    Route::get('/tags/{tag}', [TagController::class, 'show'])->middleware('ability:tags:read');
    Route::patch('/tags/{tag}', [TagController::class, 'update'])->middleware('ability:tags:update');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->middleware('ability:tags:delete');
});

if (app()->environment('testing')) {
    Route::get('/_probe', fn () => response()->json(['ok' => true]))
        ->middleware(['auth:sanctum', 'ability:posts:read']);
}
