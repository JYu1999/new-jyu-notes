<?php

namespace App\Http\Middleware;

use App\Models\Post;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->route('locale');

        if (in_array($locale, Post::SUPPORTED_LOCALES, true)) {
            app()->setLocale($locale);

            // Persist cookie for 1 year so subsequent visits to / redirect correctly
            cookie()->queue(cookie(
                'locale',
                $locale,
                60 * 24 * 365,
                '/',
                null,
                false,
                false,
                false,
                'lax'
            ));
        }

        return $next($request);
    }
}
