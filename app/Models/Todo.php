<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Since JYU-132, changelog is generated from CHANGELOG.md instead
 * of this system. Kept functional for historical data only — not deleted
 * because the table was never fully audited for content beyond what the
 * public changelog displayed. See docs/superpowers/specs/2026-08-04-jyu-132-file-based-changelog-design.md.
 */
class Todo extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'show_in_changelog',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'show_in_changelog' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
