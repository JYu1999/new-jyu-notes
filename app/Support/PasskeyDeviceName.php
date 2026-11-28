<?php

namespace App\Support;

/**
 * Default name for a new passkey (JYU-566): the device and browser it was added
 * on, e.g. 「Mac · Safari」. Only a starting point — it can be renamed.
 */
class PasskeyDeviceName
{
    private const DEVICES = [
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Android' => 'Android',
        'Macintosh' => 'Mac',
        'Windows' => 'Windows',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    // Order matters: Edge and Chrome-on-iOS also say "Safari", Chrome says "Safari" too.
    private const BROWSERS = [
        'Edg' => 'Edge',
        'Firefox' => 'Firefox',
        'FxiOS' => 'Firefox',
        'CriOS' => 'Chrome',
        'Chrome' => 'Chrome',
        'Safari' => 'Safari',
    ];

    public static function fromUserAgent(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;
        $parts = array_filter([self::match($userAgent, self::DEVICES), self::match($userAgent, self::BROWSERS)]);

        return count($parts) === 2 ? implode(' · ', $parts) : 'Passkey';
    }

    /** @param  array<string, string>  $tokens */
    private static function match(string $userAgent, array $tokens): ?string
    {
        foreach ($tokens as $token => $label) {
            if (str_contains($userAgent, $token)) {
                return $label;
            }
        }

        return null;
    }
}
