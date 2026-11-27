<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One thing an AI did or asked for (JYU-555 "AI 最近做了什麼"). */
class AiActivity extends Model
{
    protected $fillable = ['ai_connection_id', 'tool', 'summary', 'status', 'subject_type', 'subject_id', 'content_revision_id', 'ai_action_request_id'];

    public function aiConnection(): BelongsTo
    {
        return $this->belongsTo(AiConnection::class, 'ai_connection_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function actionRequest(): BelongsTo
    {
        return $this->belongsTo(AiActionRequest::class, 'ai_action_request_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'content_revision_id');
    }
}
