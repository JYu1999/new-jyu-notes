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

class Tweet extends Model
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

    public const SUPPORTED_LOCALES = Post::SUPPORTED_LOCALES;

    protected $fillable = [
        'tweet_group_id',
        'locale',
        'body',
        'media',
        'status',
        'published_at',
        'author_id',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TweetGroup::class, 'tweet_group_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tweet_tag');
    }

    public function siblings(): HasMany
    {
        return $this->hasMany(self::class, 'tweet_group_id', 'tweet_group_id')
            ->where('id', '!=', $this->id);
    }

    public function translation(string $locale): ?self
    {
        if ($locale === $this->locale) {
            return $this;
        }

        return self::query()
            ->where('tweet_group_id', $this->tweet_group_id)
            ->where('locale', $locale)
            ->first();
    }

    public function allTranslations(): Collection
    {
        return self::query()
            ->where('tweet_group_id', $this->tweet_group_id)
            ->get();
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale);
    }

    /** Plain-text preview of the body (markdown/HTML stripped), for labels. */
    public function preview(int $limit = 60): string
    {
        $rendered = app(\App\Support\MarkdownRenderer::class)->render((string) $this->body);
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($rendered)));

        return \Illuminate\Support\Str::limit($plain, $limit);
    }
}
