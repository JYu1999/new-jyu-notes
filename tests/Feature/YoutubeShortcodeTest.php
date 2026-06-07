<?php

namespace Tests\Feature;

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
}
