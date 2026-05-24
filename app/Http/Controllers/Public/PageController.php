<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $locale, string $slug): View
    {
        $page = Page::query()
            ->locale($locale)
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        $availableLocales = $page->allTranslations()
            ->filter(fn ($p) => $p->status === Page::STATUS_PUBLISHED)
            ->pluck('locale')
            ->all();

        return view('public.pages.show', [
            'page' => $page,
            'availableLocales' => $availableLocales,
        ]);
    }
}
