<?php

namespace App\Support;

/**
 * Paragraph-level text diff for the revision history (JYU-553).
 *
 * The body is split into paragraphs (blank-line separated). Paragraphs are matched
 * with an LCS; a removed paragraph followed by an added one is shown as a single
 * "changed" paragraph with inline <del>/<ins> marks. Inline diffs work on tokens:
 * each CJK character, each latin word, each whitespace run and each other symbol.
 */
class TextDiff
{
    /** Above this many LCS cells we fall back to "replace everything" to stay fast. */
    private const MAX_CELLS = 2_000_000;

    /**
     * @return list<array{type: 'same'|'added'|'removed'|'changed', context: string, html: string}>
     */
    public static function paragraphs(string $old, string $new): array
    {
        $a = self::split($old);
        $b = self::split($new);
        $ops = self::lcsOps($a, $b);

        // Pair each run of removals with the following run of additions as "changed".
        $rows = [];
        $count = count($ops);
        for ($i = 0; $i < $count; $i++) {
            [$op, $text] = $ops[$i];
            if ($op === 'del' && isset($ops[$i + 1]) && $ops[$i + 1][0] === 'ins') {
                $rows[] = ['type' => 'changed', 'old' => $text, 'new' => $ops[$i + 1][1]];
                $i++;

                continue;
            }
            $rows[] = ['type' => ['eq' => 'same', 'del' => 'removed', 'ins' => 'added'][$op], 'old' => $op === 'ins' ? '' : $text, 'new' => $op === 'del' ? '' : $text];
        }

        // Context label: the heading path (in the new text) above each paragraph, or "第 N 段".
        $path = [];
        $n = 0;
        $out = [];
        foreach ($rows as $row) {
            $text = $row['type'] === 'removed' ? $row['old'] : $row['new'];
            if ($row['type'] !== 'removed') {
                $n++;
            }
            $context = $path ? implode(' › ', $path) : '第 '.max(1, $n).' 段';
            if ($row['type'] !== 'removed' && preg_match('/^(#{1,6})\s+(.+)$/m', $text, $m) && ! str_contains($text, "\n")) {
                $level = strlen($m[1]);
                $path = array_slice($path, 0, max(0, $level - 2));
                $path[] = trim($m[2]);
            }
            $out[] = [
                'type' => $row['type'],
                'context' => $context,
                'html' => match ($row['type']) {
                    'changed' => self::inline($row['old'], $row['new']),
                    'added' => '<ins>'.e($row['new']).'</ins>',
                    'removed' => '<del>'.e($row['old']).'</del>',
                    default => e($row['new']),
                },
            ];
        }

        return $out;
    }

    /** Inline token diff as HTML with <del> / <ins>. */
    public static function inline(string $old, string $new): string
    {
        $html = '';
        foreach (self::mergeRuns(self::lcsOps(self::tokens($old), self::tokens($new))) as [$op, $text]) {
            $html .= match ($op) {
                'del' => '<del>'.e($text).'</del>',
                'ins' => '<ins>'.e($text).'</ins>',
                default => e($text),
            };
        }

        return $html;
    }

    /**
     * Characters added and removed between two texts (whitespace not counted).
     *
     * @return array{added: int, removed: int}
     */
    public static function counts(string $old, string $new): array
    {
        $added = 0;
        $removed = 0;
        $ops = self::lcsOps(self::split($old), self::split($new));
        for ($i = 0, $c = count($ops); $i < $c; $i++) {
            [$op, $text] = $ops[$i];
            if ($op === 'del' && isset($ops[$i + 1]) && $ops[$i + 1][0] === 'ins') {
                // A changed paragraph counts only its differing tokens, so fixing
                // one typo isn't reported as rewriting the whole paragraph.
                foreach (self::lcsOps(self::tokens($text), self::tokens($ops[$i + 1][1])) as [$tokOp, $tok]) {
                    $added += $tokOp === 'ins' ? self::length($tok) : 0;
                    $removed += $tokOp === 'del' ? self::length($tok) : 0;
                }
                $i++;
            } elseif ($op === 'ins') {
                $added += self::length($text);
            } elseif ($op === 'del') {
                $removed += self::length($text);
            }
        }

        return ['added' => $added, 'removed' => $removed];
    }

    /** @return list<string> */
    private static function split(string $text): array
    {
        $text = trim(str_replace("\r\n", "\n", $text));

        return $text === '' ? [] : array_values(array_map('trim', preg_split('/\n\s*\n/', $text)));
    }

    /** @return list<string> */
    private static function tokens(string $text): array
    {
        preg_match_all('/\p{Han}|\p{Hiragana}|\p{Katakana}|\p{Hangul}|[\p{L}\p{N}_]+|\s+|./u', $text, $m);

        return $m[0];
    }

    private static function length(string $text): int
    {
        return mb_strlen(preg_replace('/\s+/u', '', $text));
    }

    /**
     * Longest-common-subsequence edit script.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: 'eq'|'del'|'ins', 1: string}>
     */
    private static function lcsOps(array $a, array $b): array
    {
        // Trim the common prefix / suffix first: most edits touch a small region.
        $prefix = [];
        while ($a && $b && $a[0] === $b[0]) {
            $prefix[] = ['eq', array_shift($a)];
            array_shift($b);
        }
        $suffix = [];
        while ($a && $b && end($a) === end($b)) {
            array_unshift($suffix, ['eq', array_pop($a)]);
            array_pop($b);
        }

        $n = count($a);
        $m = count($b);
        if ($n * $m > self::MAX_CELLS) {
            $mid = array_merge(array_map(fn ($x) => ['del', $x], $a), array_map(fn ($x) => ['ins', $x], $b));

            return array_merge($prefix, $mid, $suffix);
        }

        $len = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $len[$i][$j] = $a[$i] === $b[$j] ? $len[$i + 1][$j + 1] + 1 : max($len[$i + 1][$j], $len[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['eq', $a[$i]];
                $i++;
                $j++;
            } elseif ($len[$i + 1][$j] >= $len[$i][$j + 1]) {
                $ops[] = ['del', $a[$i++]];
            } else {
                $ops[] = ['ins', $b[$j++]];
            }
        }
        while ($i < $n) {
            $ops[] = ['del', $a[$i++]];
        }
        while ($j < $m) {
            $ops[] = ['ins', $b[$j++]];
        }

        return array_merge($prefix, $ops, $suffix);
    }

    /**
     * Join consecutive tokens of the same op; within a changed stretch put all
     * deletions before all insertions ("件" → "間" reads as <del>件</del><ins>間</ins>).
     *
     * @param  list<array{0: string, 1: string}>  $ops
     * @return list<array{0: string, 1: string}>
     */
    private static function mergeRuns(array $ops): array
    {
        $out = [];
        $del = '';
        $ins = '';
        $flush = function () use (&$out, &$del, &$ins) {
            if ($del !== '') {
                $out[] = ['del', $del];
            }
            if ($ins !== '') {
                $out[] = ['ins', $ins];
            }
            $del = $ins = '';
        };
        foreach ($ops as [$op, $t]) {
            if ($op === 'eq') {
                $flush();
                if ($out && end($out)[0] === 'eq') {
                    $out[count($out) - 1][1] .= $t;
                } else {
                    $out[] = ['eq', $t];
                }
            } elseif ($op === 'del') {
                $del .= $t;
            } else {
                $ins .= $t;
            }
        }
        $flush();

        return $out;
    }
}
