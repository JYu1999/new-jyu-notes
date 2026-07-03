<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
