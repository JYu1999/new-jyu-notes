<?php

namespace App\Http\Requests\Api\Todo;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated by auth:sanctum + ability middleware
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'required', Rule::in([Todo::PRIORITY_LOW, Todo::PRIORITY_MEDIUM, Todo::PRIORITY_HIGH])],
            'status' => ['sometimes', 'required', Rule::in([Todo::STATUS_OPEN, Todo::STATUS_DONE])],
            'show_in_changelog' => ['sometimes', 'boolean'],
        ];
    }
}
