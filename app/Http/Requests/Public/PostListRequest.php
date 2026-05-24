<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class PostListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => 'nullable|in:published,updated,views',
            'tag' => 'nullable|integer|exists:tags,id',
            'category' => 'nullable|integer|exists:categories,id',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
