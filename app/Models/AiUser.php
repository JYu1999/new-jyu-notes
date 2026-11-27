<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * The site owner as seen by an AI connection (JYU-554).
 *
 * Same `users` row as App\Models\User, with Passport's token methods for the
 * `api` guard (OAuth / MCP). Kept separate from User so the admin session user
 * carries no API-token behaviour at all.
 */
class AiUser extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];

    public function isAdmin(): bool
    {
        return $this->role === User::ROLE_ADMIN;
    }
}
