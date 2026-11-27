<?php

namespace App\Http\Requests\Public;

use App\Services\HomeFeedService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(HomeFeedService::TYPES)],
            'before' => ['nullable', 'string', 'max:40'],
            'partial' => ['nullable', 'boolean'],
        ];
    }
}
