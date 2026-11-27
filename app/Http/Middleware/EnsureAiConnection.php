<?php

namespace App\Http\Middleware;

use App\Models\AiConnection;
use App\Services\AiConnectionService;
use App\Services\RevisionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP requests (JYU-554): the OAuth token must belong to a connection the owner
 * approved that is not revoked or expired. The connection is made available to the
 * tools, and every content change in the request is attributed to it.
 */
class EnsureAiConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');
        $clientId = $user?->token()?->oauth_client_id;

        $connection = $clientId ? AiConnection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('oauth_client_id', $clientId)
            ->first() : null;

        if (! $connection || ! $connection->isActive() || ! $user->isAdmin()) {
            // Expired / revoked: also drop the tokens so reconnecting shows the consent screen again.
            if ($connection) {
                app(AiConnectionService::class)->revokeTokens($connection);
            }

            return response()->json(['error' => 'invalid_token', 'error_description' => '這個 AI 連線已中斷或過期，請重新連線。'], 401);
        }

        if (! $connection->last_used_at || $connection->last_used_at->lt(now()->subMinute())) {
            $connection->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        app()->instance(AiConnection::class, $connection);
        app(RevisionService::class)->actAsAi($connection->name, $connection->user_id);

        return $next($request);
    }
}
