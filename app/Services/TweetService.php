<?php

namespace App\Services;

use App\Models\Tweet;
use App\Models\TweetGroup;
use Illuminate\Support\Facades\DB;

class TweetService
{
    public function create(array $data): Tweet
    {
        return DB::transaction(function () use ($data) {
            $groupId = $data['tweet_group_id']
                ?? TweetGroup::create()->id;

            $tweet = Tweet::create([
                'tweet_group_id' => $groupId,
                'locale' => $data['locale'],
                'body' => $data['body'],
                'media' => $data['media'] ?? null,
                'status' => $data['status'] ?? Tweet::STATUS_DRAFT,
                'published_at' => $this->resolvePublishedAt($data),
                'author_id' => $data['author_id'] ?? auth()->id(),
            ]);

            if (isset($data['tag_ids'])) {
                $this->syncTagsAcrossGroup($tweet, $data['tag_ids']);
            }

            return $tweet->fresh(['tags']);
        });
    }

    public function update(Tweet $tweet, array $data): Tweet
    {
        return DB::transaction(function () use ($tweet, $data) {
            $updateData = array_filter([
                'body' => $data['body'] ?? null,
                'status' => $data['status'] ?? null,
                'published_at' => $this->resolvePublishedAtForUpdate($tweet, $data),
            ], fn ($v) => $v !== null);

            if (array_key_exists('media', $data)) {
                $updateData['media'] = $data['media'];
            }

            $tweet->update($updateData);

            if (isset($data['tag_ids'])) {
                $this->syncTagsAcrossGroup($tweet, $data['tag_ids']);
            }

            return $tweet->fresh(['tags']);
        });
    }

    public function softDelete(Tweet $tweet): void
    {
        $tweet->delete();
    }

    public function restore(Tweet $tweet): void
    {
        $tweet->restore();
    }

    public function updateStatus(Tweet $tweet, string $status): void
    {
        $tweet->update([
            'status' => $status,
            'published_at' => $status === Tweet::STATUS_PUBLISHED && ! $tweet->published_at
                ? now()
                : $tweet->published_at,
        ]);
    }

    public function createTranslation(Tweet $existingTweet, string $newLocale): Tweet
    {
        return DB::transaction(function () use ($existingTweet, $newLocale) {
            $existing = Tweet::query()
                ->where('tweet_group_id', $existingTweet->tweet_group_id)
                ->where('locale', $newLocale)
                ->withTrashed()
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                return $existing;
            }

            $newTweet = Tweet::create([
                'tweet_group_id' => $existingTweet->tweet_group_id,
                'locale' => $newLocale,
                'body' => $existingTweet->body,
                'media' => $existingTweet->media,
                'status' => Tweet::STATUS_DRAFT,
                'author_id' => auth()->id(),
            ]);

            $siblingTagIds = $existingTweet->tags()->pluck('tags.id')->all();
            if ($siblingTagIds) {
                $newTweet->tags()->sync($siblingTagIds);
            }

            return $newTweet;
        });
    }

    public function syncTagsAcrossGroup(Tweet $tweet, array $tagIds): void
    {
        DB::transaction(function () use ($tweet, $tagIds) {
            $siblings = Tweet::where('tweet_group_id', $tweet->tweet_group_id)->get();
            foreach ($siblings as $t) {
                $t->tags()->sync($tagIds);
            }
        });
    }

    private function resolvePublishedAt(array $data)
    {
        if (! empty($data['published_at'])) {
            return $data['published_at'];
        }
        if (($data['status'] ?? null) === Tweet::STATUS_PUBLISHED) {
            return now();
        }
        return null;
    }

    private function resolvePublishedAtForUpdate(Tweet $tweet, array $data)
    {
        if (array_key_exists('published_at', $data) && $data['published_at']) {
            return $data['published_at'];
        }
        $newStatus = $data['status'] ?? null;
        if ($newStatus === Tweet::STATUS_PUBLISHED && ! $tweet->published_at) {
            return now();
        }
        return null;
    }
}
