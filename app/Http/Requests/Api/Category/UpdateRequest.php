<?php

namespace App\Http\Requests\Api\Category;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:categories:update
    }

    public function rules(): array
    {
        return [
            'cover_image_path' => 'sometimes|nullable|string|max:500',
            'sort_method' => 'sometimes|required|in:manual,date_desc,date_asc',
            'translations' => 'sometimes|array|min:1',
            'translations.*.locale' => 'required|in:zh,en,ja,vi,id|distinct',
            'translations.*.name' => 'required|string|max:100',
            'translations.*.slug' => ['nullable', 'string', 'max:120', 'not_regex:~[/\\\\?#&\s]~'],
            'translations.*.description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Ensure each translation always carries slug/description keys so the
     * service can read them directly even when the client omits them.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        if (isset($validated['translations'])) {
            $validated['translations'] = array_map(static fn (array $t) => $t + [
                'slug' => null,
                'description' => null,
            ], $validated['translations']);
        }

        return data_get($validated, $key, $default);
    }
}
