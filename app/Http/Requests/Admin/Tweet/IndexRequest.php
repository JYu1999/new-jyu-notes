<?php

namespace App\Http\Requests\Admin\Tweet;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:all,published,draft,hidden,trashed',
            'locale' => 'nullable|in:zh,en,ja,vi,id',
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'partial' => 'nullable|boolean',
        ];
    }
}
