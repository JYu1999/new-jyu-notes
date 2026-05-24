<?php

namespace App\Http\Requests\Admin\Tweet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['status' => 'required|in:draft,published,hidden'];
    }
}
