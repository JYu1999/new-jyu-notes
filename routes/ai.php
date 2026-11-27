<?php

use App\Http\Middleware\EnsureAiConnection;
use App\Http\Middleware\RecordAiConsent;
use App\Mcp\Servers\BlogServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
| AI connections (JYU-554): OAuth for Claude's connector flow, and the MCP server.
| Passport's own routes are disabled (AppServiceProvider) so the approve endpoint
| can record the permissions chosen on the consent screen.
*/

Route::prefix('oauth')->name('passport.')->group(function () {
    Route::post('token', [AccessTokenController::class, 'issueToken'])->middleware('throttle:30,1')->name('token');
    Route::get('authorize', [AuthorizationController::class, 'authorize'])->middleware('web')->name('authorizations.authorize');
    Route::middleware(['web', 'auth'])->group(function () {
        Route::post('authorize', [ApproveAuthorizationController::class, 'approve'])
            ->middleware(RecordAiConsent::class)->name('authorizations.approve');
        Route::delete('authorize', [DenyAuthorizationController::class, 'deny'])->name('authorizations.deny');
    });
});

// Dynamic client registration + OAuth discovery metadata for MCP clients.
Mcp::oauthRoutes();

Mcp::web('/mcp', BlogServer::class)
    ->middleware(['auth:api', EnsureAiConnection::class, 'throttle:120,1'])
    ->name('mcp');
