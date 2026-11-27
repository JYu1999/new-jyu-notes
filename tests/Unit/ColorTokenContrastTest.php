<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the design-token palette in resources/css/app.css:
 *  - tokens match the 2026 redesign values
 *  - every text token stays readable (WCAG AA, ≥ 4.5:1) on every surface,
 *    in both light (@theme) and dark ([data-theme='dark']) modes.
 */
class ColorTokenContrastTest extends TestCase
{
    private const SURFACES = ['paper', 'paper-2', 'card'];

    private const TEXT_TOKENS = ['ink', 'ink-2', 'ink-3', 'accent', 'accent-ink'];

    /** @return array<string, string> token name (without --color-) => hex */
    private static function tokens(string $theme): array
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $pattern = $theme === 'light'
            ? '/@theme\s*\{(.*?)\n\}/s'
            : "/\[data-theme='dark'\]\s*\{(.*?)\n\}/s";
        preg_match($pattern, $css, $block);

        preg_match_all('/--color-([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})\s*;/', $block[1] ?? '', $m, PREG_SET_ORDER);

        $tokens = [];
        foreach ($m as [, $name, $hex]) {
            $tokens[$name] = strtolower($hex);
        }

        return $tokens;
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = array_map(function (int $i) use ($hex) {
            $c = hexdec(substr($hex, $i, 2)) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, [0, 2, 4]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function contrast(string $a, string $b): float
    {
        [$hi, $lo] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    public function test_contrast_helper_matches_known_values(): void
    {
        $this->assertEqualsWithDelta(21.0, self::contrast('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, self::contrast('#777777', '#777777'), 0.01);
    }

    public function test_light_tokens_match_redesign_palette(): void
    {
        $t = self::tokens('light');

        $this->assertSame('#a4472f', $t['accent']);
        $this->assertSame('#6b5f53', $t['ink-3']);
        $this->assertSame('#2a221c', $t['ink']);
        $this->assertSame('#4a3f35', $t['ink-2']);
        $this->assertSame('#f6f1e7', $t['paper']);
        $this->assertSame('#ede6d6', $t['paper-2']);
        $this->assertSame('#fbf8f0', $t['card']);
    }

    public function test_dark_theme_overrides_every_text_and_surface_token(): void
    {
        $dark = self::tokens('dark');

        foreach ([...self::TEXT_TOKENS, ...self::SURFACES] as $name) {
            $this->assertArrayHasKey($name, $dark, "Dark theme is missing --color-{$name}");
        }
    }

    public static function themes(): array
    {
        return ['light' => ['light'], 'dark' => ['dark']];
    }

    #[DataProvider('themes')]
    public function test_text_tokens_reach_aa_contrast_on_every_surface(string $theme): void
    {
        $t = self::tokens($theme);

        foreach (self::TEXT_TOKENS as $fg) {
            foreach (self::SURFACES as $bg) {
                $ratio = self::contrast($t[$fg], $t[$bg]);
                $this->assertGreaterThanOrEqual(
                    4.5,
                    $ratio,
                    sprintf('[%s] --color-%s (%s) on --color-%s (%s) is only %.2f:1', $theme, $fg, $t[$fg], $bg, $t[$bg], $ratio),
                );
            }
        }
    }

    #[DataProvider('themes')]
    public function test_inverse_text_tokens_reach_aa_contrast_on_ink(string $theme): void
    {
        $t = self::tokens($theme);

        foreach (['paper', 'inverse-ink-2', 'inverse-ink-3', 'inverse-accent'] as $fg) {
            $this->assertArrayHasKey($fg, $t, "[{$theme}] missing --color-{$fg}");
            $ratio = self::contrast($t[$fg], $t['ink']);
            $this->assertGreaterThanOrEqual(4.5, $ratio, sprintf('[%s] --color-%s on --color-ink is only %.2f:1', $theme, $fg, $ratio));
        }
    }
}
