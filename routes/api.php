<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::get('/me', MeController::class)->middleware('auth:sanctum');

if (app()->environment('testing')) {
    Route::get('/_probe', fn () => response()->json(['ok' => true]))
        ->middleware(['auth:sanctum', 'ability:posts:read']);
}
