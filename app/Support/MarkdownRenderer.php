<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private MarkdownConverter $converter;

    private ShortcodeConverter $shortcodes;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);

        // Use the GFM components individually so we can skip DisallowedRawHtmlExtension,
        // which would otherwise escape iframe / title / etc. — needed for our YouTube embeds.
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new TaskListExtension);
        $environment->addExtension(new AutolinkExtension);

        $this->converter = new MarkdownConverter($environment);
        $this->shortcodes = new ShortcodeConverter;
    }

    public function render(string $markdown): string
    {
        // 編輯器插入的 {{< youtube ... >}} 在渲染時轉成 iframe。
        // 完整的 ShortcodeConverter 只在 Hugo 匯入時跑，這裡僅 pre-pass youtube。
        $markdown = $this->shortcodes->convertYoutube($markdown);

        return (string) $this->converter->convert($markdown);
    }
}
