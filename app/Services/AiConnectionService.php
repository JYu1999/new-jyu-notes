<?php

namespace App\Services;

use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;

/** Connecting and disconnecting AIs over OAuth / MCP (JYU-554). */
class AiConnectionService
{
    /** Store what the owner allowed on the consent screen; reconnecting replaces the previous grant. */
    public function grant(User $user, string $clientId, array $permissions, int $days): AiConnection
    {
        $client = Client::query()->findOrFail($clientId);

        return AiConnection::query()->updateOrCreate(
            ['user_id' => $user->id, 'oauth_client_id' => $client->getKey()],
            [
                'name' => $client->name,
                'permissions' => $permissions,
                'expires_at' => now()->addDays($days),
                'revoked_at' => null,
            ],
        );
    }

    /**
     * Cut the connection off now: the connection is marked revoked and every
     * access / refresh token of that client for this user stops working.
     */
    public function revoke(AiConnection $connection): void
    {
        DB::transaction(function () use ($connection) {
            $connection->forceFill(['revoked_at' => $connection->revoked_at ?? now()])->save();
            $this->revokeTokens($connection);
        });
    }

    /** Revoke the OAuth tokens behind a connection (also used when it has expired). */
    public function revokeTokens(AiConnection $connection): void
    {
        $tokenIds = DB::table('oauth_access_tokens')
            ->where('user_id', $connection->user_id)
            ->where('client_id', $connection->oauth_client_id)
            ->pluck('id');

        DB::table('oauth_access_tokens')->whereIn('id', $tokenIds)->update(['revoked' => true]);
        DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
    }
}
