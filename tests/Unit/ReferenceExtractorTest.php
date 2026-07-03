<?php

namespace Tests\Unit;

use App\Support\ReferenceExtractor;
use PHPUnit\Framework\TestCase;

class ReferenceExtractorTest extends TestCase
{
    private function extract(string $body): array
    {
        return (new ReferenceExtractor)->extract($body);
    }

    public function test_matches_post_markdown_link(): void
    {
        $out = $this->extract('參考 [這篇](/zh/posts/unexpected-risks) 很讚');
        $this->assertSame([['type' => 'post', 'locale' => 'zh', 'slug' => 'unexpected-risks']], $out);
    }

    public function test_matches_post_html_href_and_trailing_slash(): void
    {
        $out = $this->extract('<a href="/en/posts/two-years-work-reflection/">x</a>');
        $this->assertSame([['type' => 'post', 'locale' => 'en', 'slug' => 'two-years-work-reflection']], $out);
    }

    public function test_matches_post_absolute_url_path_only(): void
    {
        $out = $this->extract('see https://jyu1999.com/ja/posts/mcp-first-experience here');
        $this->assertSame([['type' => 'post', 'locale' => 'ja', 'slug' => 'mcp-first-experience']], $out);
    }

    public function test_ignores_storage_image_paths_and_junk(): void
    {
        $body = '![x](/storage/imports/posts/foo/image.png) and /posts/{{ and /posts/bar/1.JPG';
        $this->assertSame([], $this->extract($body));
    }

    public function test_dedupes_repeated_post_links(): void
    {
        $body = '[a](/zh/posts/foo) [b](/zh/posts/foo)';
        $this->assertSame([['type' => 'post', 'locale' => 'zh', 'slug' => 'foo']], $this->extract($body));
    }

    public function test_matches_tweet_link_with_numeric_id(): void
    {
        $out = $this->extract('看這則 [推文](/zh/tweets/123)');
        $this->assertSame([['type' => 'tweet', 'id' => 123]], $out);
    }

    public function test_matches_tweet_trailing_slash_and_dedupes(): void
    {
        $out = $this->extract('[a](/en/tweets/45/) [b](/zh/tweets/45)');
        $this->assertSame([['type' => 'tweet', 'id' => 45]], $out);
    }

    public function test_mixed_post_and_tweet_links(): void
    {
        $out = $this->extract('[p](/zh/posts/foo) 與 [t](/zh/tweets/9)');
        $this->assertSame([
            ['type' => 'post', 'locale' => 'zh', 'slug' => 'foo'],
            ['type' => 'tweet', 'id' => 9],
        ], $out);
    }
}
