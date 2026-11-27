<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Reader's light/dark choice, stored in the `theme` cookie by the toggle button.
 * The cookie is written from JS, so it is excluded from cookie encryption
 * (see bootstrap/app.php) and validated here instead.
 */
class ThemePreference
{
    public const COOKIE = 'theme';

    public const THEMES = ['light', 'dark'];

    /** The explicit choice, or null when the reader never picked one (follow the OS). */
    public static function fromRequest(Request $request): ?string
    {
        return self::normalize($request->cookie(self::COOKIE));
    }

    public static function normalize(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::THEMES, true) ? $value : null;
    }
}
