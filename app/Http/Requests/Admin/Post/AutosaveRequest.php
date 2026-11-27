<?php

namespace App\Http\Requests\Admin\Post;

use Illuminate\Foundation\Http\FormRequest;

/** Editor autosave (JYU-548): the same fields as the form, all optional, no status change. */
class AutosaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'post_id' => 'nullable|integer|exists:posts,id',
            'locale' => 'required_without:post_id|nullable|string|in:zh,en,ja,vi,id',
            'title' => 'nullable|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', 'not_regex:~[/\\\\?#&\s]~'],
            'excerpt' => 'nullable|string|max:1000',
            'body' => 'nullable|string',
            'cover_image_path' => 'nullable|string|max:500',
            'is_featured' => 'sometimes|boolean',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'category_ids' => 'sometimes|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'categories_order' => 'sometimes|array',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_featured' => $this->boolean('is_featured')]);
    }
}
