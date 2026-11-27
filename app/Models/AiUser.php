<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * The site owner as seen by an AI connection (JYU-554).
 *
 * Same `users` row as App\Models\User, but with Passport's token methods so the
 * `api` guard (OAuth / MCP) can coexist with the Sanctum API tokens still on
 * User until JYU-557 removes them.
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
