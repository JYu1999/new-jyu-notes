<?php

namespace App\Http\Requests\Admin\Todo;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
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

    public function prepareForValidation(): void
    {
        $this->merge(['show_in_changelog' => $this->boolean('show_in_changelog')]);
    }
}
