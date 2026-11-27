<?php

namespace App\Http\Requests\Admin\Post;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:all,published,draft,hidden,featured,trashed',
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|integer|exists:categories,id',
            'tag' => 'nullable|integer|exists:tags,id',
            // "missing:en" / "stale:ja" (JYU-545); missing / stale kept for the dashboard links (JYU-544).
            'translation' => ['nullable', 'regex:/^(missing|stale):(zh|en|ja|vi|id)$/'],
            'missing' => 'nullable|in:zh,en,ja,vi,id',
            'stale' => 'nullable|in:zh,en,ja,vi,id',
            'sort' => 'nullable|in:updated,published,views',
            'page' => 'nullable|integer|min:1',
            'partial' => 'nullable|boolean',
        ];
    }

    /** @return array{status: string, q: ?string, category: ?int, tag: ?int, translation: ?string, sort: string} */
    public function filters(): array
    {
        $v = $this->validated();
        $translation = $v['translation'] ?? null;
        if (! $translation && isset($v['missing'])) {
            $translation = 'missing:'.$v['missing'];
        } elseif (! $translation && isset($v['stale'])) {
            $translation = 'stale:'.$v['stale'];
        }

        return [
            'status' => $v['status'] ?? 'all',
            'q' => $v['q'] ?? null,
            'category' => isset($v['category']) ? (int) $v['category'] : null,
            'tag' => isset($v['tag']) ? (int) $v['tag'] : null,
            'translation' => $translation,
            'sort' => $v['sort'] ?? 'updated',
        ];
    }
}
