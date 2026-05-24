<?php

namespace App\Http\Requests\Admin\Category;

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
            'cover_image_path' => 'nullable|string|max:500',
            'sort_method' => 'required|in:manual,date_desc,date_asc',
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|in:zh,en,ja,vi,id|distinct',
            'translations.*.name' => 'required|string|max:100',
            'translations.*.slug' => ['nullable', 'string', 'max:120', 'not_regex:~[/\\\\?#&\s]~'],
            'translations.*.description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'translations.*.slug.not_regex' => 'Slug 不可包含空白、斜線、? 、# 或 & 符號',
        ];
    }
}
