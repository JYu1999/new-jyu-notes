<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    private string $changelogFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->changelogFile = storage_path('framework/testing/changelog-'.uniqid().'.md');
        config(['changelog.path' => $this->changelogFile]);
    }

    protected function tearDown(): void
    {
        File::delete($this->changelogFile);

        parent::tearDown();
    }

    private function putContent(string $content): void
    {
        File::put($this->changelogFile, $content);
    }

    // 真實存在日期與內容的格式都正確
    public function test_changelog_shows_grouped_entries(): void
    {
        $this->putContent(<<<'MD'
        ## 2026-05-19
        - A Feature

        ## 2026-05-18
        - C Feature
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('May 19, 2026')
            ->assertSee('A Feature')
            ->assertSee('May 18, 2026')
            ->assertSee('C Feature');
    }

    // 日期順序寫反了，會強制新到舊排列
    public function test_orders_newest_date_first_even_if_file_lists_them_out_of_order(): void
    {
        $this->putContent(<<<'MD'
        ## 2026-05-18
        - Old Entry

        ## 2026-05-19
        - New Entry
        MD);

        $content = $this->get('/changelog')->getContent();

        $this->assertTrue(strpos($content, 'New Entry') < strpos($content, 'Old Entry'));
    }

    // 一天多筆內容，會按照寫的順序由上到下排列
    public function test_same_day_entries_preserve_file_order(): void
    {
        $this->putContent(<<<'MD'
        ## 2026-05-19
        - First Entry
        - Second Entry
        MD);

        $content = $this->get('/changelog')->getContent();

        $this->assertTrue(strpos($content, 'First Entry') < strpos($content, 'Second Entry'));
    }

    // 沒有標題包住的文字、格式錯誤的標題(## not-a-date），都會被忽略
    public function test_ignores_content_outside_a_date_heading_and_malformed_headers(): void
    {
        $this->putContent(<<<'MD'
        Random preamble text that is not a bullet.

        ## not-a-date
        - Should not appear

        ## 2026-05-19
        - Valid Entry
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('Valid Entry')
            ->assertDontSee('Should not appear')
            ->assertDontSee('Random preamble');
    }

    // 格式對但日期不存在，會被忽略
    public function test_ignores_calendar_invalid_date_headings_and_does_not_crash(): void
    {
        $this->putContent(<<<'MD'
        ## 2026-13-01
        - Should not appear

        ## 2026-05-19
        - Valid Entry
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('Valid Entry')
            ->assertDontSee('Should not appear');
    }

    // 檔案不存在，顯示「No entries yet.」
    public function test_empty_state(): void
    {
        $this->get('/changelog')->assertOk()->assertSee('No entries yet.');
    }

    // 格式對但日期不存在，內容不會被誤判到前一個區塊
    public function test_bullets_after_an_invalid_heading_are_not_attributed_to_the_prior_section(): void
    {
        $this->putContent(<<<'MD'
        ## 2026-05-19
        - Valid Entry

        ## 2026-13-01
        - Should not appear
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('Valid Entry')
            ->assertDontSee('Should not appear');
    }

    // 只有標題沒內容的日期，會被忽略
    public function test_a_date_heading_with_no_bullets_does_not_render_as_an_empty_section(): void
    {
        $this->putContent(<<<'MD'
        ## 2026-08-05

        ## 2026-05-19
        - Real entry
        MD);

        $content = $this->get('/changelog')->getContent();

        $this->assertStringNotContainsString('August 5, 2026', $content);
        $this->assertStringContainsString('May 19, 2026', $content);
        $this->assertStringContainsString('Real entry', $content);
    }

    // 檔案為空標題，顯示「No entries yet.」
    public function test_file_with_only_headings_and_no_bullets_shows_empty_state(): void
    {
        $this->putContent(<<<'MD'
        ## 2026-08-05

        ## 2026-05-19
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('No entries yet.');
    }

    // 不是日期格式的標題(## Not a Date），內容不會被誤判到前一個區塊
    public function test_bullets_after_a_non_date_heading_are_not_attributed_to_the_prior_section(): void
    {
        $this->putContent(<<<'MD'
        ## 2026-05-19
        - Valid Entry

        ## Not a Date
        - Should not appear
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('Valid Entry')
            ->assertDontSee('Should not appear');
    }
}
