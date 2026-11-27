<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JYU-532: the chosen theme is rendered on <html> by the server, so the very first
 * paint is already dark — no flash of the light theme while JS loads.
 * With no choice, an inline <head> script follows the OS setting before CSS paints.
 */
class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dark_cookie_is_rendered_on_html_element_on_first_response(): void
    {
        // The browser sets this cookie from JS (unencrypted), so it must be readable as-is.
        $html = $this->withUnencryptedCookie('theme', 'dark')->get('/zh')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*\sdata-theme="dark"/', $html);
        $this->assertMatchesRegularExpression('/<meta name="color-scheme" content="dark">/', $html);
    }

    public function test_light_cookie_is_rendered_on_html_element(): void
    {
        $html = $this->withUnencryptedCookie('theme', 'light')->get('/zh')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*\sdata-theme="light"/', $html);
        $this->assertMatchesRegularExpression('/<meta name="color-scheme" content="light">/', $html);
    }

    public function test_without_choice_page_follows_operating_system_setting(): void
    {
        $html = $this->get('/zh')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<html[^>]*\sdata-theme=/', $html);
        $this->assertStringContainsString('<meta name="color-scheme" content="light dark">', $html);
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
    }

    public function test_os_follow_script_runs_before_stylesheet_in_head(): void
    {
        $html = $this->get('/zh')->getContent();

        $script = strpos($html, 'prefers-color-scheme: dark');
        $stylesheet = strpos($html, 'rel="stylesheet"');

        $this->assertNotFalse($script);
        $this->assertNotFalse($stylesheet);
        $this->assertLessThan($stylesheet, $script, 'Theme script must run before the stylesheet is applied.');
    }

    public function test_unknown_cookie_value_is_ignored(): void
    {
        $html = $this->withUnencryptedCookie('theme', '"><script>alert(1)</script>')->get('/zh')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<html[^>]*\sdata-theme=/', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_dark_cookie_applies_to_every_page_type(): void
    {
        $this->withUnencryptedCookie('theme', 'dark');

        foreach (['/zh/posts', '/zh/tweets', '/zh/search', '/changelog', '/login'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<html[^>]*\sdata-theme="dark"/', $html, "{$url} did not render the dark theme");
        }
    }

    public function test_dark_cookie_applies_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)->withUnencryptedCookie('theme', 'dark')->get('/admin')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*\sdata-theme="dark"/', $html);
    }
}
