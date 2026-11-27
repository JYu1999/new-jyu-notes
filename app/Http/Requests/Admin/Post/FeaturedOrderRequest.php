<?php

namespace App\Http\Requests\Admin\Post;

use Illuminate\Foundation\Http\FormRequest;

class FeaturedOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'group_ids' => 'required|array|min:1|max:500',
            'group_ids.*' => 'integer|distinct|exists:post_groups,id',
        ];
    }
}
