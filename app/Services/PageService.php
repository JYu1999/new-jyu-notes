<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageGroup;
use App\Support\SlugGenerator;
use Illuminate\Support\Facades\DB;

class PageService
{
    public function create(array $data): Page
    {
        return DB::transaction(function () use ($data) {
            $groupId = $data['page_group_id']
                ?? PageGroup::create()->id;

            $locale = $data['locale'];
            $slug = $data['slug'] ?: $this->forSlug($data['title'], $locale);

            $page = Page::create([
                'page_group_id' => $groupId,
                'locale' => $locale,
                'slug' => $slug,
                'title' => $data['title'],
                'body' => $data['body'],
                'cover_image_path' => $data['cover_image_path'] ?? null,
                'status' => $data['status'] ?? Page::STATUS_PUBLISHED,
                'published_at' => $data['published_at'] ?? now(),
                'author_id' => $data['author_id'] ?? auth()->id(),
            ]);

            return $page;
        });
    }

    public function update(Page $page, array $data): Page
    {
        return DB::transaction(function () use ($page, $data) {
            if (isset($data['title']) && $data['title'] !== $page->title && empty($data['slug'])) {
                $data['slug'] = $this->forSlug($data['title'], $page->locale, $page->id);
            }

            $updateData = array_filter([
                'title' => $data['title'] ?? null,
                'slug' => $data['slug'] ?? null,
                'body' => $data['body'] ?? null,
                'status' => $data['status'] ?? null,
                'published_at' => $data['published_at'] ?? null,
            ], fn ($v) => $v !== null);

            if (array_key_exists('cover_image_path', $data)) {
                $updateData['cover_image_path'] = $data['cover_image_path'];
            }

            $page->update($updateData);
            return $page->fresh();
        });
    }

    public function softDelete(Page $page): void
    {
        $page->delete();
    }

    public function restore(Page $page): void
    {
        $page->restore();
    }

    public function createTranslation(Page $existing, string $newLocale): Page
    {
        return DB::transaction(function () use ($existing, $newLocale) {
            $existingTrans = Page::query()
                ->where('page_group_id', $existing->page_group_id)
                ->where('locale', $newLocale)
                ->withTrashed()
                ->first();

            if ($existingTrans) {
                if ($existingTrans->trashed()) $existingTrans->restore();
                return $existingTrans;
            }

            return Page::create([
                'page_group_id' => $existing->page_group_id,
                'locale' => $newLocale,
                'slug' => $this->forSlug($existing->title, $newLocale),
                'title' => $existing->title . ' (' . strtoupper($newLocale) . ')',
                'body' => $existing->body,
                'cover_image_path' => $existing->cover_image_path,
                'status' => Page::STATUS_DRAFT,
                'author_id' => auth()->id(),
            ]);
        });
    }

    private function forSlug(string $title, string $locale, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($title) ?: 'page-' . substr(md5($title), 0, 8);
        $slug = $base;
        $suffix = 2;
        while (Page::query()
            ->where('locale', $locale)->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }
}
