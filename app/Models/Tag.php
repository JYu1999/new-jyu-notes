<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['color'];

    public function translations(): HasMany
    {
        return $this->hasMany(TagTranslation::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag');
    }

    public function tweets(): BelongsToMany
    {
        return $this->belongsToMany(Tweet::class, 'tweet_tag');
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
}
