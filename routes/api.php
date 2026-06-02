<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\TodoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/me', MeController::class);

    Route::get('/todos', [TodoController::class, 'index'])->middleware('ability:todos:read');
    Route::post('/todos', [TodoController::class, 'store'])->middleware('ability:todos:create');
    Route::get('/todos/{todo}', [TodoController::class, 'show'])->middleware('ability:todos:read');
    Route::patch('/todos/{todo}', [TodoController::class, 'update'])->middleware('ability:todos:update');
    Route::delete('/todos/{todo}', [TodoController::class, 'destroy'])->middleware('ability:todos:delete');
});

if (app()->environment('testing')) {
    Route::get('/_probe', fn () => response()->json(['ok' => true]))
        ->middleware(['auth:sanctum', 'ability:posts:read']);
}
