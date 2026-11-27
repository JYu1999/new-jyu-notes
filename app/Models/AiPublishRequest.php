<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** An AI asking to publish or hide content; nothing changes until the author approves (JYU-554 / JYU-556). */
class AiPublishRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    protected $fillable = ['ai_connection_id', 'publishable_type', 'publishable_id', 'action', 'note', 'status', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function aiConnection(): BelongsTo
    {
        return $this->belongsTo(AiConnection::class, 'ai_connection_id');
    }

    public function publishable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
