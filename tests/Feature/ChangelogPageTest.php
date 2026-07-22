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

    public function test_empty_state(): void
    {
        $this->get('/changelog')->assertOk()->assertSee('No entries yet.');
    }

    public function test_ignores_calendar_invalid_date_headings_and_does_not_crash(): void
    {
        $this->putContent(<<<'MD'
        ## 2026-13-01
        - This should not appear

        ## 2026-05-19
        - Valid Entry
        MD);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('Valid Entry')
            ->assertDontSee('This should not appear')
            ->assertDontSee('December 31, 2026'); // month 13 should not roll over
    }
}
