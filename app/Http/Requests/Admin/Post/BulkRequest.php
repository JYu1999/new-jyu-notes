<?php

namespace App\Http\Requests\Admin\Post;

use Illuminate\Foundation\Http\FormRequest;

class BulkRequest extends FormRequest
{
    public const ACTIONS = ['publish', 'hide', 'tag', 'translate_en', 'trash'];

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:'.implode(',', self::ACTIONS),
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer|exists:posts,id',
            'tag_id' => 'required_if:action,tag|nullable|integer|exists:tags,id',
        ];
    }
}
