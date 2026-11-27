<?php

namespace App\Http\Requests\Admin\Post;

use App\Http\Requests\Admin\Post\Concerns\SchedulesPublishing;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRequest extends FormRequest
{
    use SchedulesPublishing;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', 'not_regex:~[/\\\\?#&\s]~'],
            'excerpt' => 'nullable|string|max:1000',
            'body' => 'sometimes|required|string',
            'cover_image_path' => 'nullable|string|max:500',
            'status' => 'sometimes|required|in:draft,published,hidden',
            'is_featured' => 'sometimes|boolean',
            'published_at' => 'nullable|date',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'category_ids' => 'sometimes|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'categories_order' => 'sometimes|array',
            ...$this->scheduleRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeScheduledAt();
        $this->merge(['is_featured' => $this->boolean('is_featured')]);
    }

    /** A post that is already live can't be scheduled (JYU-549). */
    public function after(): array
    {
        return [function (Validator $validator) {
            $post = $this->route('post');
            if ($this->input('publish_when') === 'schedule' && $post?->status === Post::STATUS_PUBLISHED) {
                $validator->errors()->add('scheduled_at', '已發布的文章不能排程');
            }
        }];
    }

    public function messages(): array
    {
        return $this->scheduleMessages();
    }
}
