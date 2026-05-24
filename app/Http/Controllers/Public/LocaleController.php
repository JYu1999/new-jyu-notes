<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(string $locale, Request $request, PostService $service): RedirectResponse
    {
        abort_unless(in_array($locale, Post::SUPPORTED_LOCALES, true), 400);

        cookie()->queue(cookie('locale', $locale, 60 * 24 * 365, '/'));

        $referer = $request->header('referer');
        $target = $service->equivalentUrlInLocale($referer, $locale) ?? "/{$locale}";

        return redirect($target);
    }
}
