<?php

namespace App\Http\Requests\Api\Tweet;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:tweets:create
    }

    public function rules(): array
    {
        return [
            'tweet_group_id' => 'nullable|integer|exists:tweet_groups,id',
            'locale' => 'required|string|in:zh,en,ja,vi,id',
            'body' => 'required|string|max:2000',
            'media' => 'nullable|array|max:4',
            'media.*.path' => 'required_with:media|string|max:500',
            'media.*.type' => 'required_with:media|in:image,video',
            'media.*.alt' => 'nullable|string|max:200',
            'tag_ids' => 'array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];
    }
}
