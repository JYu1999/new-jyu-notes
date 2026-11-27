<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Passport\Client;

/** An AI connected over OAuth / MCP and what it may do (JYU-554). */
class AiConnection extends Model
{
    protected $fillable = ['user_id', 'oauth_client_id', 'name', 'permissions', 'expires_at', 'last_used_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'oauth_client_id');
    }

    public function publishRequests(): HasMany
    {
        return $this->hasMany(AiPublishRequest::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function allows(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}
