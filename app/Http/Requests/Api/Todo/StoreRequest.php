<?php

namespace App\Http\Requests\Api\Todo;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated by auth:sanctum + ability middleware
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in([Todo::PRIORITY_LOW, Todo::PRIORITY_MEDIUM, Todo::PRIORITY_HIGH])],
            'status' => ['required', Rule::in([Todo::STATUS_OPEN, Todo::STATUS_DONE])],
            'show_in_changelog' => ['boolean'],
        ];
    }
}
