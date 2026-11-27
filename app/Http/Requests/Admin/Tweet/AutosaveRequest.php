<?php

namespace App\Http\Requests\Admin\Tweet;

use Illuminate\Foundation\Http\FormRequest;

/** Draft autosave from the mobile composer (JYU-551): the form's fields, all optional. */
class AutosaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'tweet_id' => 'nullable|integer|exists:tweets,id',
            'locale' => 'required_without:tweet_id|nullable|in:zh,en,ja,vi,id',
            'body' => 'nullable|string|max:2000',
            'media' => 'nullable|array|max:4',
            'media.*.path' => 'required_with:media|string|max:500',
            'media.*.type' => 'required_with:media|in:image,video',
            'media.*.alt' => 'nullable|string|max:200',
            'media.*.sensitive' => 'nullable|boolean',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];
    }
}
