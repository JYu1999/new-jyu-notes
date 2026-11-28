<?php

namespace App\Http\Requests\Admin\Tweet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => 'sometimes|required|string|max:2000',
            'media' => 'nullable|array|max:4',
            'media.*.path' => 'required_with:media|string|max:500',
            'media.*.type' => 'required_with:media|in:image,video',
            'media.*.alt' => 'nullable|string|max:200',
            'media.*.sensitive' => 'nullable|boolean',
            'status' => 'sometimes|required|in:draft,published,hidden',
            'published_at' => 'nullable|date',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            // The composer goes back where it was opened from after publishing (JYU-551, JYU-563).
            'redirect_to' => 'nullable|in:dashboard,tweets',
            'tags_sent' => 'sometimes|boolean',
        ];
    }

    /** Forms that send `tags_sent` mean "no tags" when no tag is ticked (JYU-551). */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('tags_sent') && ! $this->has('tag_ids')) {
            $this->merge(['tag_ids' => []]);
        }
    }
}
