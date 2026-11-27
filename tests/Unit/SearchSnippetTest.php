<?php

namespace Tests\Unit;

use App\Services\SearchService;
use PHPUnit\Framework\TestCase;

class SearchSnippetTest extends TestCase
{
    public function test_marks_first_match_with_context(): void
    {
        $s = SearchService::snippet('一年多以前，我就想在公司導入監控系統。', '監控');

        $this->assertSame(['pre' => '一年多以前，我就想在公司導入', 'hit' => '監控', 'post' => '系統。'], $s);
    }

    public function test_is_case_insensitive_and_keeps_original_case(): void
    {
        $this->assertSame('Agent', SearchService::snippet('We use an AI Agent daily', 'agent')['hit']);
    }

    public function test_adds_ellipsis_when_text_is_cut(): void
    {
        $text = str_repeat('a', 100).'KEY'.str_repeat('b', 200);
        $s = SearchService::snippet($text, 'key', 10, 20);

        $this->assertSame('…'.str_repeat('a', 10), $s['pre']);
        $this->assertSame(str_repeat('b', 20).'…', $s['post']);
    }

    public function test_returns_null_without_match_or_needle(): void
    {
        $this->assertNull(SearchService::snippet('hello', 'xyz'));
        $this->assertNull(SearchService::snippet('hello', ''));
    }

    public function test_collapses_whitespace(): void
    {
        $this->assertSame('a b ', SearchService::snippet("a\n\n  b  KEY", 'key')['pre']);
    }
}
