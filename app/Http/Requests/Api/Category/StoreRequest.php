<?php

namespace App\Http\Requests\Api\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum + ability:categories:create
    }

    public function rules(): array
    {
        return [
            'cover_image_path' => 'nullable|string|max:500',
            'sort_method' => 'sometimes|in:manual,date_desc,date_asc',
            'translations' => 'required|array|min:1',
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
