<?php

namespace Tests\Feature\Components;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared public UI components (resources/views/components/ui/*).
 * Each component renders the design-token classes from resources/css/app.css
 * (.ui-chip / .ui-btn / .ui-card / .ui-heading) instead of hard-coded colors.
 */
class UiComponentsTest extends TestCase
{
    use RefreshDatabase;

    // ── chip ────────────────────────────────────────────────────────────

    public function test_chip_renders_link_with_neutral_style_by_default(): void
    {
        $html = (string) $this->blade('<x-ui.chip href="/zh/tags/x">#技術</x-ui.chip>');

        $this->assertMatchesRegularExpression('/<a [^>]*href="\/zh\/tags\/x"/', $html);
        $this->assertStringContainsString('ui-chip', $html);
        $this->assertStringContainsString('ui-chip-neutral', $html);
        $this->assertStringContainsString('#技術', $html);
        $this->assertStringNotContainsString('tag-chip', $html);
    }

    public function test_chip_with_color_uses_tag_tint_variable(): void
    {
        $html = (string) $this->blade('<x-ui.chip color="#2f5f86">#技術</x-ui.chip>');

        $this->assertStringContainsString('tag-chip', $html);
        $this->assertStringContainsString('--tag-color: #2f5f86', $html);
        $this->assertStringNotContainsString('ui-chip-neutral', $html);
        $this->assertStringStartsWith('<span', trim($html));
    }

    public function test_chip_rejects_non_hex_color_to_avoid_style_injection(): void
    {
        $html = (string) $this->blade('<x-ui.chip color="red;background:url(x)">#x</x-ui.chip>');

        $this->assertStringNotContainsString('url(x)', $html);
        $this->assertStringContainsString('ui-chip-neutral', $html);
    }

    public function test_chip_sizes(): void
    {
        $this->assertStringContainsString('ui-chip-sm', (string) $this->blade('<x-ui.chip size="sm">a</x-ui.chip>'));
        $this->assertStringContainsString('ui-chip-md', (string) $this->blade('<x-ui.chip>a</x-ui.chip>'));
    }

    public function test_filter_chip_renders_button_with_aria_pressed(): void
    {
        $on = (string) $this->blade('<x-ui.chip as="button" :pressed="true">全部</x-ui.chip>');
        $off = (string) $this->blade('<x-ui.chip as="button" :pressed="false">文章</x-ui.chip>');

        $this->assertMatchesRegularExpression('/<button [^>]*type="button"/', $on);
        $this->assertStringContainsString('aria-pressed="true"', $on);
        $this->assertStringContainsString('ui-chip-pressed', $on);
        $this->assertStringContainsString('aria-pressed="false"', $off);
        $this->assertStringNotContainsString('ui-chip-pressed', $off);
    }

    public function test_tag_component_resolves_locale_name_link_and_color(): void
    {
        $tag = Tag::create(['color' => '#3f6236']);
        $tag->translations()->createMany([
            ['locale' => 'zh', 'name' => '職涯', 'slug' => 'career-zh'],
            ['locale' => 'en', 'name' => 'Career', 'slug' => 'career'],
        ]);

        app()->setLocale('en');
        $html = (string) $this->blade('<x-ui.tag :tag="$tag" :count="3" />', ['tag' => $tag->fresh('translations')]);

        $this->assertStringContainsString('href="'.route('public.tags.show', ['en', 'career']).'"', $html);
        $this->assertStringContainsString('#Career', $html);
        $this->assertStringContainsString('--tag-color: #3f6236', $html);
        $this->assertStringContainsString('>3<', $html);
    }

    public function test_tag_component_renders_nothing_without_translation(): void
    {
        $tag = Tag::create(['color' => null]);

        $this->assertSame('', trim((string) $this->blade('<x-ui.tag :tag="$tag" />', ['tag' => $tag])));
    }

    // ── section heading ────────────────────────────────────────────────

    public function test_section_heading_defaults_to_h2_large(): void
    {
        $html = (string) $this->blade('<x-ui.section-heading id="latest-h">最新</x-ui.section-heading>');

        $this->assertMatchesRegularExpression('/<h2[^>]*id="latest-h"/', $html);
        $this->assertStringContainsString('ui-heading-lg', $html);
        $this->assertStringContainsString('最新', $html);
    }

    public function test_section_heading_supports_level_size_and_action_link(): void
    {
        $html = (string) $this->blade(
            '<x-ui.section-heading as="h3" size="label" action-href="/zh/posts" action-label="還有 2 篇 →">標籤</x-ui.section-heading>'
        );

        $this->assertMatchesRegularExpression('/<h3[^>]*ui-heading-label/', $html);
        $this->assertStringContainsString('href="/zh/posts"', $html);
        $this->assertStringContainsString('還有 2 篇 →', $html);
    }

    // ── button ─────────────────────────────────────────────────────────

    public function test_button_renders_button_element_with_variant(): void
    {
        $html = (string) $this->blade('<x-ui.button variant="primary">送出</x-ui.button>');

        $this->assertMatchesRegularExpression('/<button [^>]*type="button"/', $html);
        $this->assertStringContainsString('ui-btn', $html);
        $this->assertStringContainsString('ui-btn-primary', $html);
    }

    public function test_button_with_href_renders_link(): void
    {
        $html = (string) $this->blade('<x-ui.button href="/zh/work">作品與經歷</x-ui.button>');

        $this->assertMatchesRegularExpression('/<a [^>]*href="\/zh\/work"/', $html);
        $this->assertStringContainsString('ui-btn-secondary', $html);
    }

    public function test_icon_button_requires_accessible_label_and_passes_attributes(): void
    {
        $html = (string) $this->blade('<x-ui.button icon aria-label="切換深色模式" x-data="{}" @click="go()">☾</x-ui.button>');

        $this->assertStringContainsString('ui-btn-icon', $html);
        $this->assertStringContainsString('aria-label="切換深色模式"', $html);
        $this->assertStringContainsString('x-data="{}"', $html);
        $this->assertStringContainsString('@click="go()"', $html);
    }

    public function test_submit_button_keeps_type(): void
    {
        $this->assertStringContainsString('type="submit"', (string) $this->blade('<x-ui.button type="submit">Go</x-ui.button>'));
    }

    // ── card ───────────────────────────────────────────────────────────

    public function test_card_renders_div_by_default(): void
    {
        $html = (string) $this->blade('<x-ui.card class="extra">內容</x-ui.card>');

        $this->assertStringStartsWith('<div', trim($html));
        $this->assertStringContainsString('ui-card', $html);
        $this->assertStringContainsString('extra', $html);
    }

    public function test_card_with_href_is_interactive_link(): void
    {
        $html = (string) $this->blade('<x-ui.card href="/zh/posts/a" padding="lg">內容</x-ui.card>');

        $this->assertMatchesRegularExpression('/<a [^>]*href="\/zh\/posts\/a"/', $html);
        $this->assertStringContainsString('ui-card-interactive', $html);
        $this->assertStringContainsString('ui-card-pad-lg', $html);
    }

    public function test_card_can_render_as_article(): void
    {
        $this->assertStringStartsWith('<article', trim((string) $this->blade('<x-ui.card as="article">x</x-ui.card>')));
    }
}
