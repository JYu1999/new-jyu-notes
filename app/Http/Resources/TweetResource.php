<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TweetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tweet_group_id' => $this->tweet_group_id,
            'locale' => $this->locale,
            'body' => $this->body,
            'media' => $this->media ?? [],
            'status' => $this->status,
            'published_at' => $this->published_at,
            'tag_ids' => $this->tags->pluck('id')->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
