<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::get('/me', MeController::class)->middleware('auth:sanctum');
