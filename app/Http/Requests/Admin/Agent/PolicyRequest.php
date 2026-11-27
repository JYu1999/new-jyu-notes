<?php

namespace App\Http\Requests\Admin\Agent;

use App\Support\AiPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Global allow / ask / deny per AI action (JYU-555). */
class PolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $rules = ['policy' => 'required|array'];
        foreach (AiPolicy::ACTIONS as $action => $def) {
            $rules["policy.$action"] = ['sometimes', Rule::in($def['modes'])];
        }

        return $rules;
    }
}
