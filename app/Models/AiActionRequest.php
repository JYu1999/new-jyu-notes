<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An AI action waiting for the author (policy "需確認", JYU-555 / JYU-556):
 * the tool and its arguments are run only once the author approves.
 */
class AiActionRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'ai_connection_id', 'tool', 'arguments', 'summary', 'publishable_type', 'publishable_id',
        'action', 'note', 'status', 'error', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['arguments' => 'array', 'decided_at' => 'datetime'];
    }

    public function aiConnection(): BelongsTo
    {
        return $this->belongsTo(AiConnection::class, 'ai_connection_id');
    }

    /** The content the action is about, when there is one. */
    public function publishable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
