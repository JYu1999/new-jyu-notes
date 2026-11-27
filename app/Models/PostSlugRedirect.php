<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A previous slug of a published post, kept so old links keep working (JYU-550). */
class PostSlugRedirect extends Model
{
    protected $fillable = ['post_id', 'locale', 'old_slug'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
