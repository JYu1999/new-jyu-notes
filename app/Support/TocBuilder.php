<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Scans rendered post HTML for h2/h3 headings, injects unique anchor IDs,
 * and returns a structured list for rendering a table-of-contents sidebar.
 */
class TocBuilder
{
    /**
     * @return array{html: string, headings: array<int, array{id: string, level: int, text: string}>}
     */
    public static function build(string $html): array
    {
        $headings = [];
        $seen = [];

        $html = preg_replace_callback(
            '#<h([2-3])([^>]*)>(.+?)</h\1>#s',
            function ($m) use (&$headings, &$seen) {
                [, $level, $attrs, $inner] = $m;
                $text = trim(strip_tags($inner));

                // Build a slug; ensure uniqueness within the document.
                $base = Str::slug($text) ?: 'h'.(count($headings) + 1);
                $id = $base;
                $i = 2;
                while (isset($seen[$id])) {
                    $id = $base.'-'.$i++;
                }
                $seen[$id] = true;

                $headings[] = [
                    'id' => $id,
                    'level' => (int) $level,
                    'text' => $text,
                ];

                // Preserve any existing attributes (e.g. class); insert id at the front.
                $attrs = trim((string) $attrs);
                $idAttr = 'id="'.htmlspecialchars($id, ENT_QUOTES).'"';
                $attrStr = $attrs === '' ? ' '.$idAttr : ' '.$idAttr.' '.$attrs;

                return '<h'.$level.$attrStr.'>'.$inner.'</h'.$level.'>';
            },
            $html
        );

        return ['html' => $html, 'headings' => $headings];
    }
}
