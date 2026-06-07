<?php

namespace App\Support;

/**
 * Convert Hugo shortcodes to standard HTML / Markdown for storage in DB.
 *
 * Supported shortcodes (from the legacy Hugo blog):
 * - {{< alert "circle-info" >}}...{{< /alert >}} or {{< alert >}}...{{< /alert >}}
 * - {{< youtubeLite id="..." label="..." >}}
 * - {{< youtube id="..." >}} or {{< youtube ... >}}
 * - {{< local-video "filename.mp4" "description" >}}
 * - {{< carousel aspectRatio="..." interval="..." >}}...{{< /carousel >}}
 * - {{< x user="..." id="..." >}}
 * - {{< typeit ... >}}...{{< /typeit >}}
 * - {{< article link="/posts/foo" >}}
 */
class ShortcodeConverter
{
    /**
     * Optional map of asset path rewrites: from-old-path => new-public-path.
     */
    public array $assetRewrites = [];

    public function convert(string $content): string
    {
        $content = $this->convertAlerts($content);
        $content = $this->convertYoutube($content);
        $content = $this->convertLocalVideo($content);
        $content = $this->convertCarousel($content);
        $content = $this->convertTweetEmbed($content);
        $content = $this->convertTypeit($content);
        $content = $this->convertArticleLink($content);
        $content = $this->rewriteAssets($content);

        return $content;
    }

    private function convertAlerts(string $content): string
    {
        // Match {{< alert "type" >}}...{{< /alert >}}
        $content = preg_replace_callback(
            '/\{\{<\s*alert\s*(?:"([^"]*)")?\s*>\}\}(.*?)\{\{<\s*\/alert\s*>\}\}/s',
            function ($m) {
                $type = $m[1] ?? 'info';
                $body = trim($m[2]);
                $class = match (true) {
                    str_contains($type, 'circle-info'), str_contains($type, 'info') => 'alert-info',
                    str_contains($type, 'warning') => 'alert-warning',
                    str_contains($type, 'danger'), str_contains($type, 'error') => 'alert-danger',
                    default => 'alert-info',
                };
                return "\n\n<aside class=\"alert {$class}\">\n\n{$body}\n\n</aside>\n\n";
            },
            $content
        );

        return $content;
    }

    public function convertYoutube(string $content): string
    {
        // {{< youtubeLite id="abc" label="..." >}} or {{< youtube id="abc" start="125" >}}
        $content = preg_replace_callback(
            '/\{\{<\s*(?:youtubeLite|youtube)\s+([^>]+)\s*>\}\}/',
            function ($m) {
                $attrs = $this->parseAttrs($m[1]);
                $id = $attrs['id'] ?? '';
                if ($id === '') return $m[0];
                $src = 'https://www.youtube.com/embed/' . htmlspecialchars($id);
                $start = $attrs['start'] ?? '';
                if ($start !== '' && ctype_digit($start)) {
                    $src .= '?start=' . $start;
                }
                return "\n\n" . '<div class="youtube-embed"><iframe src="' . $src
                    . '" frameborder="0" allowfullscreen loading="lazy"></iframe></div>' . "\n\n";
            },
            $content
        );

        return $content;
    }

    private function convertLocalVideo(string $content): string
    {
        // {{< local-video "filename.mp4" "description" >}}
        $content = preg_replace_callback(
            '/\{\{<\s*local-video\s+"([^"]+)"\s*(?:"([^"]*)")?\s*>\}\}/',
            function ($m) {
                $file = $m[1];
                return "\n\n<video class=\"local-video\" controls src=\"{$file}\" preload=\"metadata\"></video>\n\n";
            },
            $content
        );

        return $content;
    }

    private function convertCarousel(string $content): string
    {
        // {{< carousel ... >}}...{{< /carousel >}}
        $content = preg_replace_callback(
            '/\{\{<\s*carousel\s*([^>]*)\s*>\}\}(.*?)\{\{<\s*\/carousel\s*>\}\}/s',
            function ($m) {
                $body = trim($m[2]);
                return "\n\n<div class=\"carousel\">\n\n{$body}\n\n</div>\n\n";
            },
            $content
        );

        return $content;
    }

    private function convertTweetEmbed(string $content): string
    {
        // {{< x user="..." id="..." >}}
        $content = preg_replace_callback(
            '/\{\{<\s*x\s+([^>]+)\s*>\}\}/',
            function ($m) {
                $attrs = $this->parseAttrs($m[1]);
                $user = $attrs['user'] ?? '';
                $id = $attrs['id'] ?? '';
                if ($user === '' || $id === '') return $m[0];
                $url = "https://x.com/{$user}/status/{$id}";
                return "\n\n<blockquote class=\"x-embed\"><a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">{$url}</a></blockquote>\n\n";
            },
            $content
        );

        return $content;
    }

    private function convertTypeit(string $content): string
    {
        // {{< typeit ... >}}...{{< /typeit >}} → keep the inner text only
        $content = preg_replace(
            '/\{\{<\s*typeit\s*[^>]*\s*>\}\}(.*?)\{\{<\s*\/typeit\s*>\}\}/s',
            '$1',
            $content
        );

        return $content;
    }

    private function convertArticleLink(string $content): string
    {
        // {{< article link="/posts/foo" >}} → <a href="/posts/foo">/posts/foo</a>
        $content = preg_replace_callback(
            '/\{\{<\s*article\s+([^>]+)\s*>\}\}/',
            function ($m) {
                $attrs = $this->parseAttrs($m[1]);
                $link = $attrs['link'] ?? '';
                if ($link === '') return $m[0];
                return "<a href=\"{$link}\">{$link}</a>";
            },
            $content
        );

        return $content;
    }

    private function rewriteAssets(string $content): string
    {
        foreach ($this->assetRewrites as $from => $to) {
            $quoted = preg_quote($from, '#');

            // Markdown image: ![alt](optional/path/<filename>)  →  ![alt](<newUrl>)
            $content = preg_replace(
                '#(\!\[[^\]]*\]\()(?:[^()\s]*?/)?' . $quoted . '(\))#',
                '$1' . $to . '$2',
                $content
            );

            // HTML attribute: src="optional/path/<filename>" or href="..."
            $content = preg_replace(
                '#(\b(?:src|href)\s*=\s*")(?:[^"\s]*?/)?' . $quoted . '(")#',
                '$1' . $to . '$2',
                $content
            );
        }
        return $content;
    }

    /**
     * Parse `key="value" key2="value2"` style attributes.
     */
    private function parseAttrs(string $attrs): array
    {
        $result = [];
        if (preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $attrs, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $result[$pair[1]] = $pair[2];
            }
        }
        return $result;
    }
}
