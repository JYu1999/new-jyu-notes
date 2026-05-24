<?php

namespace App\Http\Middleware;

use App\Models\Post;
use Closure;
use Illuminate\Http\Request;

class DetectLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = Post::SUPPORTED_LOCALES;

        $cookieLocale = $request->cookie('locale');
        if (in_array($cookieLocale, $supported, true)) {
            app()->setLocale($cookieLocale);
            return $next($request);
        }

        foreach ($request->getLanguages() as $lang) {
            $short = strtolower(substr($lang, 0, 2));
            if (in_array($short, $supported, true)) {
                app()->setLocale($short);
                return $next($request);
            }
        }

        app()->setLocale(config('app.locale'));
        return $next($request);
    }
}
