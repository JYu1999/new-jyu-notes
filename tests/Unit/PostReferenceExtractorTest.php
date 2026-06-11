<?php

namespace Tests\Unit;

use App\Support\PostReferenceExtractor;
use PHPUnit\Framework\TestCase;

class PostReferenceExtractorTest extends TestCase
{
    private function extract(string $body): array
    {
        return (new PostReferenceExtractor())->extract($body);
    }

    public function test_matches_markdown_link(): void
    {
        $out = $this->extract('參考 [這篇](/zh/posts/unexpected-risks) 很讚');
        $this->assertSame([['locale' => 'zh', 'slug' => 'unexpected-risks']], $out);
    }

    public function test_matches_html_href_and_trailing_slash(): void
    {
        $out = $this->extract('<a href="/en/posts/two-years-work-reflection/">x</a>');
        $this->assertSame([['locale' => 'en', 'slug' => 'two-years-work-reflection']], $out);
    }

    public function test_matches_absolute_url_path_only(): void
    {
        $out = $this->extract('see https://jyu1999.com/ja/posts/mcp-first-experience here');
        $this->assertSame([['locale' => 'ja', 'slug' => 'mcp-first-experience']], $out);
    }

    public function test_ignores_storage_image_paths_and_junk(): void
    {
        $body = '![x](/storage/imports/posts/foo/image.png) and /posts/{{ and /posts/bar/1.JPG';
        $this->assertSame([], $this->extract($body));
    }

    public function test_dedupes_repeated_links(): void
    {
        $body = '[a](/zh/posts/foo) [b](/zh/posts/foo)';
        $this->assertSame([['locale' => 'zh', 'slug' => 'foo']], $this->extract($body));
    }
}
