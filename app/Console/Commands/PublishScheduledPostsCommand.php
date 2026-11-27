<?php

namespace App\Console\Commands;

use App\Services\PostService;
use Illuminate\Console\Command;

/** Publishes scheduled drafts whose time has come (JYU-549). Runs every minute. */
class PublishScheduledPostsCommand extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish scheduled posts whose scheduled time has passed';

    public function handle(PostService $posts): int
    {
        $count = $posts->publishDue();
        if ($count > 0) {
            $this->info("Published {$count} scheduled post(s).");
        }

        return self::SUCCESS;
    }
}
