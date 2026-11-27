<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class TweetListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tag' => ['nullable', 'string', 'max:120'],
            'month' => ['nullable', 'date_format:Y-m'],
            'media' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'partial' => ['nullable', 'boolean'],
        ];
    }

    /** @return array{tag: ?string, month: ?string, media: bool} */
    public function filters(): array
    {
        return [
            'tag' => $this->validated('tag') ?: null,
            'month' => $this->validated('month') ?: null,
            'media' => $this->boolean('media'),
        ];
    }
}
