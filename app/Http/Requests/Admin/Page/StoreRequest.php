<?php

namespace App\Http\Requests\Admin\Page;

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
            'page_group_id' => 'nullable|integer|exists:page_groups,id',
            'locale' => 'required|in:zh,en,ja,vi,id',
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:120', 'not_regex:~[/\\\\?#&\s]~'],
            'body' => 'required|string',
            'cover_image_path' => 'nullable|string|max:500',
            'status' => 'required|in:draft,published,hidden',
            'published_at' => 'nullable|date',
        ];
    }
}
