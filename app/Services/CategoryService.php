<?php

namespace App\Services;

use App\Models\Category;
use App\Support\SlugGenerator;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $category = Category::create([
                'cover_image_path' => $data['cover_image_path'] ?? null,
                'sort_method' => $data['sort_method'] ?? Category::SORT_DATE_DESC,
            ]);

            foreach ($data['translations'] ?? [] as $trans) {
                $slug = ($trans['slug'] ?? null) ?: SlugGenerator::forCategory($trans['name'], $trans['locale']);
                $category->translations()->create([
                    'locale' => $trans['locale'],
                    'name' => $trans['name'],
                    'slug' => $slug,
                    'description' => $trans['description'] ?? null,
                ]);
            }

            return $category->fresh('translations');
        });
    }

    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $update = [];
            if (array_key_exists('cover_image_path', $data)) {
                $update['cover_image_path'] = $data['cover_image_path'];
            }
            if (isset($data['sort_method'])) {
                $update['sort_method'] = $data['sort_method'];
            }
            if ($update) {
                $category->update($update);
            }

            if (! empty($data['translations'])) {
                $byLocale = collect($category->translations)->keyBy('locale');
                $incomingLocales = collect($data['translations'])->pluck('locale');

                foreach ($data['translations'] as $trans) {
                    $existing = $byLocale->get($trans['locale']);
                    $slug = ($trans['slug'] ?? null) ?: SlugGenerator::forCategory($trans['name'], $trans['locale'], $category->id);
                    $payload = [
                        'name' => $trans['name'],
                        'slug' => $slug,
                        'description' => $trans['description'] ?? null,
                    ];
                    if ($existing) {
                        $existing->update($payload);
                    } else {
                        $category->translations()->create(array_merge(
                            ['locale' => $trans['locale']],
                            $payload
                        ));
                    }
                }

                $category->translations()
                    ->whereNotIn('locale', $incomingLocales->all())
                    ->delete();
            }

            return $category->fresh('translations');
        });
    }

    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category) {
            $category->posts()->detach();
            $category->translations()->delete();
            $category->delete();
        });
    }
}
