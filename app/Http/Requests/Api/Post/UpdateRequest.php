<?php

namespace App\Http\Requests\Api\Post;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:posts:update
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'not_regex:~[/\\\\?#&\s]~'],
            'excerpt' => 'sometimes|nullable|string|max:1000',
            'body' => 'sometimes|required|string',
            'cover_image_path' => 'sometimes|nullable|string|max:500',
            'is_featured' => 'sometimes|boolean',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'category_ids' => 'sometimes|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'categories_order' => 'sometimes|array',
        ];
    }
}
