<?php

namespace Tests\Feature;

use App\Support\MarkdownRenderer;
use App\Support\ShortcodeConverter;
use Tests\TestCase;

class YoutubeShortcodeTest extends TestCase
{
    public function test_youtube_shortcode_renders_iframe(): void
    {
        $out = (new ShortcodeConverter())->convertYoutube('{{< youtube id="dQw4w9WgXcQ" >}}');

        $this->assertStringContainsString(
            '<div class="youtube-embed"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"',
            $out
        );
    }

    public function test_youtube_shortcode_with_start_adds_start_param(): void
    {
        $out = (new ShortcodeConverter())->convertYoutube('{{< youtube id="dQw4w9WgXcQ" start="125" >}}');

        $this->assertStringContainsString(
            'src="https://www.youtube.com/embed/dQw4w9WgXcQ?start=125"',
            $out
        );
    }

    public function test_youtube_shortcode_with_invalid_start_ignores_attribute(): void
    {
        $out = (new ShortcodeConverter())->convertYoutube('{{< youtube id="dQw4w9WgXcQ" start="abc" >}}');

        $this->assertStringContainsString('src="https://www.youtube.com/embed/dQw4w9WgXcQ"', $out);
        $this->assertStringNotContainsString('start=', $out);
    }

    public function test_markdown_renderer_converts_youtube_shortcode(): void
    {
        $md = "前面的文字\n\n{{< youtube id=\"dQw4w9WgXcQ\" >}}\n\n後面的文字";

        $html = app(MarkdownRenderer::class)->render($md);

        $this->assertStringContainsString(
            '<div class="youtube-embed"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"',
            $html
        );
        $this->assertStringContainsString('前面的文字', $html);
        $this->assertStringContainsString('後面的文字', $html);
    }

    public function test_markdown_renderer_passes_start_through(): void
    {
        $html = app(MarkdownRenderer::class)->render('{{< youtube id="dQw4w9WgXcQ" start="90" >}}');

        $this->assertStringContainsString('?start=90', $html);
    }
}
