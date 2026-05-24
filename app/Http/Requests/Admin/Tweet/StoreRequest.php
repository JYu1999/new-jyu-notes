<?php

namespace App\Http\Requests\Admin\Tweet;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'tweet_group_id' => 'nullable|integer|exists:tweet_groups,id',
            'locale' => 'required|in:zh,en,ja,vi,id',
            'body' => 'required|string|max:2000',
            'media' => 'nullable|array|max:4',
            'media.*.path' => 'required_with:media|string|max:500',
            'media.*.type' => 'required_with:media|in:image,video',
            'media.*.alt' => 'nullable|string|max:200',
            'status' => 'required|in:draft,published,hidden',
            'published_at' => 'nullable|date',
            'tag_ids' => 'array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];
    }
}
