<?php

namespace App\Jobs;

use App\Models\Tweet;
use App\Services\LinkPreviewService;
use Illuminate\Foundation\Bus\Dispatchable;

/** Runs after the response is sent when a tweet is saved, so saving stays fast. */
class FetchLinkPreviews
{
    use Dispatchable;

    public function __construct(public int $tweetId) {}

    public function handle(LinkPreviewService $previews): void
    {
        $tweet = Tweet::find($this->tweetId);
        if ($tweet) {
            $previews->fetchForTweet($tweet);
        }
    }
}
