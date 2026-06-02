<?php

namespace App\Http\Requests\Admin\ApiToken;

use App\Support\Abilities;
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
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(Abilities::all())],
            'expires_in' => ['required', Rule::in(['1h', '8h', '24h', '7d', 'custom'])],
            'expires_at' => ['required_if:expires_in,custom', 'nullable', 'date', 'after:now'],
        ];
    }
}
