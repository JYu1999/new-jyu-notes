<?php

use App\Http\Middleware\DetectLocale;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackPostView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            DetectLocale::class,
        ]);

        $middleware->alias([
            'set-locale' => SetLocale::class,
            'role' => EnsureUserRole::class,
            'track-post-view' => TrackPostView::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('auth.login.show'));

        // Trust the nginx reverse proxy (used by ryoluo/sail-ssl)
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
