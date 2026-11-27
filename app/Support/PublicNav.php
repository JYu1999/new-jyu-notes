<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Http\Request;

/**
 * The public site's main menu (header, mobile pills, footer).
 * One source of truth so every layout shows the same items in the same order.
 */
class PublicNav
{
    public const ABOUT_SLUG = 'about';

    public const FALLBACK_LOCALE = 'zh';

    /** Short labels shown on the language button ("繁中", "EN"…). Full names live in the dropdown. */
    public const LOCALE_NAMES = [
        'zh' => '繁體中文',
        'en' => 'English',
        'ja' => '日本語',
        'vi' => 'Tiếng Việt',
        'id' => 'Bahasa Indonesia',
    ];

    /**
     * @return list<array{key: string, label: string, url: string, active: bool}>
     */
    public static function items(string $locale, Request $request): array
    {
        return [
            [
                'key' => 'posts',
                'label' => __('nav.posts'),
                'url' => route('public.posts.index', $locale),
                'active' => $request->routeIs('public.posts.*'),
            ],
            [
                'key' => 'tweets',
                'label' => __('nav.tweets'),
                'url' => route('public.tweets.index', $locale),
                'active' => $request->routeIs('public.tweets.*'),
            ],
            [
                'key' => 'topics',
                'label' => __('nav.topics'),
                'url' => route('public.topics', $locale),
                'active' => $request->routeIs('public.topics', 'public.tags.*', 'public.categories.*'),
            ],
            [
                'key' => 'about',
                'label' => __('nav.about'),
                'url' => self::aboutUrl($locale),
                'active' => $request->routeIs('public.pages.show') && $request->route('slug') === self::ABOUT_SLUG,
            ],
        ];
    }

    /** The "About" page in this locale, or the Chinese one when it hasn't been translated. */
    public static function aboutUrl(string $locale): string
    {
        $exists = Page::query()->published()->locale($locale)->where('slug', self::ABOUT_SLUG)->exists();

        return route('public.pages.show', [$exists ? $locale : self::FALLBACK_LOCALE, self::ABOUT_SLUG]);
    }
}
