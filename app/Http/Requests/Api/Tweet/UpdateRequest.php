<?php

namespace App\Http\Requests\Api\Tweet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:tweets:update
    }

    public function rules(): array
    {
        return [
            'body' => 'sometimes|required|string|max:2000',
            'media' => 'sometimes|nullable|array|max:4',
            'media.*.path' => 'required_with:media|string|max:500',
            'media.*.type' => 'required_with:media|in:image,video',
            'media.*.alt' => 'nullable|string|max:200',
            'media.*.sensitive' => 'nullable|boolean',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];
    }
}
