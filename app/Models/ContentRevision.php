<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One saved version of a post, tweet or page (JYU-553). */
class ContentRevision extends Model
{
    public const ACTOR_USER = 'user';

    public const ACTOR_AI = 'ai';

    public const ACTOR_SYSTEM = 'system';

    protected $fillable = [
        'user_id', 'actor', 'actor_name', 'kind', 'summary', 'snapshot', 'chars_added', 'chars_removed',
    ];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function revisionable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAi(): bool
    {
        return $this->actor === self::ACTOR_AI;
    }
}
