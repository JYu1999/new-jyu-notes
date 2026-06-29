<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    public const SORT_MANUAL = 'manual';

    public const SORT_DATE_DESC = 'date_desc';

    public const SORT_DATE_ASC = 'date_asc';

    public const SORT_METHODS = [
        self::SORT_MANUAL,
        self::SORT_DATE_DESC,
        self::SORT_DATE_ASC,
    ];

    protected $fillable = ['cover_image_path', 'sort_method'];

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'category_post')
            ->withPivot('order_in_category');
    }

    public function name(string $locale): ?string
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)?->name
            ?? $translations->firstWhere('locale', 'zh')?->name
            ?? $translations->first()?->name;
    }

    public function slug(string $locale): ?string
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)?->slug
            ?? $translations->firstWhere('locale', 'zh')?->slug
            ?? $translations->first()?->slug;
    }

    public function description(string $locale): ?string
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)?->description
            ?? $translations->firstWhere('locale', 'zh')?->description;
    }
}
