<?php

namespace App\Http\Requests\Admin\Tag;

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
            'color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'translations' => 'sometimes|array|min:1',
            'translations.*.locale' => 'required|in:zh,en,ja,vi,id|distinct',
            'translations.*.name' => 'required|string|max:100',
            'translations.*.slug' => ['nullable', 'string', 'max:120', 'not_regex:#[/\\\\?#&\s]#'],
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => '顏色格式錯誤（請使用 #RRGGBB 形式，例如 #b2543b）',
            'translations.*.slug.not_regex' => 'Slug 不可包含空白、斜線、? 、# 或 & 符號',
        ];
    }
}
