<?php

namespace Tests\Unit;

use App\Support\TextDiff;
use PHPUnit\Framework\TestCase;

class TextDiffTest extends TestCase
{
    public function test_inline_marks_a_single_character_change(): void
    {
        $this->assertSame(
            '我進來這<del>件</del><ins>間</ins>公司',
            TextDiff::inline('我進來這件公司', '我進來這間公司'),
        );
    }

    public function test_inline_diffs_latin_text_by_word(): void
    {
        $this->assertSame(
            'with <del>Antigraviry</del><ins>Antigravity</ins> setup',
            TextDiff::inline('with Antigraviry setup', 'with Antigravity setup'),
        );
    }

    public function test_inline_escapes_html(): void
    {
        $this->assertSame('&lt;<del>b</del><ins>i</ins>&gt;', TextDiff::inline('<b>', '<i>'));
    }

    public function test_paragraphs_report_only_what_changed_with_heading_context(): void
    {
        $old = "開頭\n\n## 工作\n\n第一段舊\n\n### 細節\n\n不變\n\n要刪掉的段落";
        $new = "開頭\n\n## 工作\n\n第一段新\n\n### 細節\n\n不變\n\n新增的段落";

        $rows = TextDiff::paragraphs($old, $new);
        $changed = array_values(array_filter($rows, fn ($r) => $r['type'] !== 'same'));

        $this->assertCount(2, $changed);
        $this->assertSame('changed', $changed[0]['type']);
        $this->assertSame('工作', $changed[0]['context']);
        $this->assertSame('第一段<del>舊</del><ins>新</ins>', $changed[0]['html']);
        $this->assertSame('工作 › 細節', $changed[1]['context']);
        $this->assertSame('<del>要刪掉</del><ins>新增</ins>的段落', $changed[1]['html']);
    }

    public function test_paragraphs_without_headings_are_numbered(): void
    {
        $rows = TextDiff::paragraphs("一\n\n二\n\n三", "一\n\n二\n\n三改");

        $this->assertSame('第 3 段', $rows[2]['context']);
        $this->assertSame('changed', $rows[2]['type']);
    }

    public function test_added_and_removed_paragraphs(): void
    {
        $rows = TextDiff::paragraphs("一\n\n二", "一\n\n二\n\n三");
        $this->assertSame(['same', 'same', 'added'], array_column($rows, 'type'));
        $this->assertSame('<ins>三</ins>', $rows[2]['html']);

        $rows = TextDiff::paragraphs("一\n\n二\n\n三", "一\n\n三");
        $this->assertSame(['same', 'removed', 'same'], array_column($rows, 'type'));
    }

    public function test_counts_characters_added_and_removed(): void
    {
        $this->assertSame(['added' => 1, 'removed' => 1], TextDiff::counts('我進來這件公司', '我進來這間公司'));
        $this->assertSame(['added' => 3, 'removed' => 0], TextDiff::counts('一', "一\n\n新段落"));
        $this->assertSame(['added' => 0, 'removed' => 2], TextDiff::counts("留下\n\n刪除", '留下'));
        $this->assertSame(['added' => 5, 'removed' => 0], TextDiff::counts('', 'Hello'));
    }
}
