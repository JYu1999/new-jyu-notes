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

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);

        // Use the GFM components individually so we can skip DisallowedRawHtmlExtension,
        // which would otherwise escape iframe / title / etc. — needed for our YouTube embeds.
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new AutolinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }
}
