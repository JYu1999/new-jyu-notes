<?php

namespace App\Http\Requests\Admin\Post;

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
            'post_group_id' => 'nullable|integer|exists:post_groups,id',
            'locale' => 'required|string|in:zh,en,ja,vi,id',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|regex:/^[a-z0-9\-]+$/',
            'excerpt' => 'nullable|string|max:1000',
            'body' => 'required|string',
            'cover_image_path' => 'nullable|string|max:500',
            'status' => 'required|in:draft,published,hidden',
            'is_featured' => 'sometimes|boolean',
            'published_at' => 'nullable|date',
            'tag_ids' => 'array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'category_ids' => 'array',
            'category_ids.*' => 'integer|exists:categories,id',
            'categories_order' => 'array',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_featured' => $this->boolean('is_featured'),
        ]);
    }
}
