<?php

namespace App\Http\Requests\Admin\Todo;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @deprecated Since JYU-132, changelog is generated from CHANGELOG.md instead
 * of this system. Kept functional for historical data only — not deleted
 * because the table was never fully audited for content beyond what the
 * public changelog displayed. See docs/superpowers/specs/2026-08-04-jyu-132-file-based-changelog-design.md.
 */
class UpdateRequest extends FormRequest
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
