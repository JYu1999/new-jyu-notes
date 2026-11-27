<?php

namespace App\Http\Requests\Admin\Agent;

use App\Support\AiPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Per-connection policy (JYU-555): only the global mode or something stricter. */
class ConnectionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $global = AiPolicy::global();
        $rules = ['policy' => 'required|array'];
        foreach (array_keys(AiPolicy::ACTIONS) as $action) {
            $rules["policy.$action"] = ['sometimes', Rule::in(AiPolicy::connectionModes($action, $global))];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['policy.*.in' => '單一連線只能比整體設定更嚴格，不能放寬。'];
    }
}
