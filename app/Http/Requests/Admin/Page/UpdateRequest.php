<?php

namespace App\Http\Requests\Admin\Page;

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
            'title' => 'sometimes|required|string|max:255',
            'slug' => ['nullable', 'string', 'max:120', 'not_regex:~[/\\\\?#&\s]~'],
            'body' => 'sometimes|required|string',
            'cover_image_path' => 'nullable|string|max:500',
            'status' => 'sometimes|required|in:draft,published,hidden',
            'published_at' => 'nullable|date',
        ];
    }
}
