<?php

namespace App\Services;

use App\Models\Tag;
use App\Support\SlugGenerator;
use Illuminate\Support\Facades\DB;

class TagService
{
    public function create(array $data): Tag
    {
        return DB::transaction(function () use ($data) {
            $tag = Tag::create([
                'color' => $data['color'] ?? null,
            ]);

            foreach ($data['translations'] ?? [] as $trans) {
                $slug = ($trans['slug'] ?? null) ?: SlugGenerator::forTag($trans['name'], $trans['locale']);
                $tag->translations()->create([
                    'locale' => $trans['locale'],
                    'name' => $trans['name'],
                    'slug' => $slug,
                ]);
            }

            return $tag->fresh('translations');
        });
    }

    public function update(Tag $tag, array $data): Tag
    {
        return DB::transaction(function () use ($tag, $data) {
            if (array_key_exists('color', $data)) {
                $tag->update(['color' => $data['color']]);
            }

            if (! empty($data['translations'])) {
                $byLocale = collect($tag->translations)->keyBy('locale');
                $incomingLocales = collect($data['translations'])->pluck('locale');

                foreach ($data['translations'] as $trans) {
                    $existing = $byLocale->get($trans['locale']);
                    $slug = ($trans['slug'] ?? null) ?: SlugGenerator::forTag($trans['name'], $trans['locale'], $tag->id);
                    if ($existing) {
                        $existing->update([
                            'name' => $trans['name'],
                            'slug' => $slug,
                        ]);
                    } else {
                        $tag->translations()->create([
                            'locale' => $trans['locale'],
                            'name' => $trans['name'],
                            'slug' => $slug,
                        ]);
                    }
                }

                // Remove translations no longer present
                $tag->translations()
                    ->whereNotIn('locale', $incomingLocales->all())
                    ->delete();
            }

            return $tag->fresh('translations');
        });
    }

    public function delete(Tag $tag): void
    {
        DB::transaction(function () use ($tag) {
            $tag->posts()->detach();
            $tag->tweets()->detach();
            $tag->translations()->delete();
            $tag->delete();
        });
    }
}
