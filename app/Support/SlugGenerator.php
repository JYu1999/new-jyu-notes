<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Str;

class SlugGenerator
{
    /**
     * Generate a unique slug for a Post within its locale.
     */
    public static function forPost(string $title, string $locale, ?int $ignorePostId = null): string
    {
        $base = self::slugify($title);
        $slug = $base;
        $suffix = 2;

        while (self::postSlugExists($locale, $slug, $ignorePostId)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /**
     * Generate a unique slug for a Tag within its locale.
     */
    public static function forTag(string $name, string $locale, ?int $ignoreTagId = null): string
    {
        $base = self::slugify($name);
        $slug = $base;
        $suffix = 2;

        while (self::tagSlugExists($locale, $slug, $ignoreTagId)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /**
     * Generate a unique slug for a Category within its locale.
     */
    public static function forCategory(string $name, string $locale, ?int $ignoreCategoryId = null): string
    {
        $base = self::slugify($name);
        $slug = $base;
        $suffix = 2;

        while (self::categorySlugExists($locale, $slug, $ignoreCategoryId)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    private static function slugify(string $text): string
    {
        $slug = Str::slug($text, '-', null, [
            '@' => 'at',
            '&' => 'and',
        ]);

        if ($slug === '') {
            $slug = 'untitled-' . substr(md5($text . microtime()), 0, 8);
        }

        return Str::limit($slug, 100, '');
    }

    private static function postSlugExists(string $locale, string $slug, ?int $ignoreId): bool
    {
        $q = Post::query()->where('locale', $locale)->where('slug', $slug);
        if ($ignoreId !== null) {
            $q->where('id', '!=', $ignoreId);
        }
        return $q->exists();
    }

    private static function tagSlugExists(string $locale, string $slug, ?int $ignoreId): bool
    {
        $q = \App\Models\TagTranslation::query()->where('locale', $locale)->where('slug', $slug);
        if ($ignoreId !== null) {
            $q->where('tag_id', '!=', $ignoreId);
        }
        return $q->exists();
    }

    private static function categorySlugExists(string $locale, string $slug, ?int $ignoreId): bool
    {
        $q = \App\Models\CategoryTranslation::query()->where('locale', $locale)->where('slug', $slug);
        if ($ignoreId !== null) {
            $q->where('category_id', '!=', $ignoreId);
        }
        return $q->exists();
    }
}
