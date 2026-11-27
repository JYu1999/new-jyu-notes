<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Version history filters (JYU-553). */
class RevisionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'filter' => 'nullable|in:all,ai,status',
            'rev' => 'nullable|integer',
            'compare' => 'nullable|integer',
        ];
    }
}
