<?php

namespace App\Http\Requests\Api\Post;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:posts:create
    }

    public function rules(): array
    {
        return [
            'post_group_id' => 'nullable|integer|exists:post_groups,id',
            'locale' => 'required|string|in:zh,en,ja,vi,id',
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', 'not_regex:~[/\\\\?#&\s]~'],
            'excerpt' => 'nullable|string|max:1000',
            'body' => 'required|string',
            'cover_image_path' => 'nullable|string|max:500',
            'is_featured' => 'sometimes|boolean',
            'tag_ids' => 'array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'category_ids' => 'array',
            'category_ids.*' => 'integer|exists:categories,id',
            'categories_order' => 'array',
        ];
    }
}
