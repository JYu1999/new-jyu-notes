<?php

namespace Tests\Unit;

use App\Support\ThemePreference;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ThemePreferenceTest extends TestCase
{
    public function test_accepts_only_known_themes(): void
    {
        $this->assertSame('dark', ThemePreference::normalize('dark'));
        $this->assertSame('light', ThemePreference::normalize('light'));
        $this->assertNull(ThemePreference::normalize('Dark'));
        $this->assertNull(ThemePreference::normalize('system'));
        $this->assertNull(ThemePreference::normalize(''));
        $this->assertNull(ThemePreference::normalize(null));
        $this->assertNull(ThemePreference::normalize(['dark']));
    }

    public function test_reads_cookie_from_request(): void
    {
        $request = Request::create('/', 'GET', cookies: ['theme' => 'dark']);

        $this->assertSame('dark', ThemePreference::fromRequest($request));
        $this->assertNull(ThemePreference::fromRequest(Request::create('/')));
    }
}
