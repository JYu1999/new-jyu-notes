<?php

namespace App\Console\Commands;

use App\Models\Tweet;
use App\Services\LinkPreviewService;
use Illuminate\Console\Command;

class FetchLinkPreviewsCommand extends Command
{
    protected $signature = 'link-previews:fetch {--refresh : Re-fetch links that already have a preview}';

    protected $description = 'Fetch link preview titles for external links in published tweets';

    public function handle(LinkPreviewService $previews): int
    {
        $count = 0;
        Tweet::query()->published()->orderBy('id')->each(function (Tweet $tweet) use ($previews, &$count) {
            $previews->fetchForTweet($tweet, (bool) $this->option('refresh'));
            $count++;
        });

        $this->info("Checked {$count} tweets.");

        return self::SUCCESS;
    }
}
