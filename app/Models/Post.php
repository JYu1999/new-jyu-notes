<?php

namespace App\Models;

use App\Models\Concerns\HasReferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, HasReferences, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN = 'hidden';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_HIDDEN,
    ];

    public const SUPPORTED_LOCALES = ['zh', 'en', 'ja', 'vi', 'id'];

    private const READING_SPEEDS = [
        'zh' => ['type' => 'character', 'speed' => 400],  // 400 字/分鐘 (Brysbaert 2019)
        'en' => ['type' => 'word',      'speed' => 230],  // 200-250 words/分鐘，取中間值 (Brysbaert 2019)
        'ja' => ['type' => 'character', 'speed' => 500],  // 400-600 文字/分鐘，取中間值
        'vi' => ['type' => 'word',      'speed' => 230],  // 200-250 words/分鐘，與英文類似
        'id' => ['type' => 'word',      'speed' => 230],  // 200-250 words/分鐘，與英文類似
    ];

    protected $fillable = [
        'post_group_id',
        'locale',
        'slug',
        'title',
        'excerpt',
        'body',
        'cover_image_path',
        'status',
        'is_featured',
        'views_count',
        'published_at',
        'last_modified_at',
        'author_id',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'views_count' => 'integer',
            'published_at' => 'datetime',
            'last_modified_at' => 'datetime',
        ];
    }

    // ===== Relationships =====

    public function group(): BelongsTo
    {
        return $this->belongsTo(PostGroup::class, 'post_group_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post')
            ->withPivot('order_in_category');
    }

    public function viewLogs(): HasMany
    {
        return $this->hasMany(PostViewLog::class);
    }

    // ===== Translation helpers =====

    public function siblings(): HasMany
    {
        return $this->hasMany(self::class, 'post_group_id', 'post_group_id')
            ->where('id', '!=', $this->id);
    }

    public function translation(string $locale): ?self
    {
        if ($locale === $this->locale) {
            return $this;
        }

        return self::query()
            ->where('post_group_id', $this->post_group_id)
            ->where('locale', $locale)
            ->first();
    }

    public function allTranslations(): Collection
    {
        return self::query()
            ->where('post_group_id', $this->post_group_id)
            ->get();
    }

    // ===== Accessors =====

    public function getReadingTimeAttribute(): int
    {
        $text = strip_tags($this->body ?? '');
        $config = self::READING_SPEEDS[$this->locale]
            ?? throw new \RuntimeException("Missing reading speed config for locale: {$this->locale}");

        if ($config['type'] === 'character') {
            $count = mb_strlen(preg_replace('/\s+/', '', $text));
        } else {
            $count = str_word_count($text);
        }

        return max(1, (int) ceil($count / $config['speed']));
    }

    // ===== Scopes =====

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale);
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured', true);
    }

    public function scopeSortBy(Builder $q, string $sort): Builder
    {
        return match ($sort) {
            'views' => $q->orderByDesc('views_count'),
            'updated' => $q->orderByDesc('last_modified_at'),
            default => $q->orderByDesc('published_at'),
        };
    }
}
