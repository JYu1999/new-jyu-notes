<?php

namespace App\Http\Middleware;

use App\Services\AiConnectionService;
use App\Support\AiPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps Passport's "approve" endpoint (JYU-554): only the site owner may connect an AI,
 * and the permissions / duration ticked on the consent screen are stored once Passport
 * has issued the authorization code.
 */
class RecordAiConsent
{
    public function __construct(private AiConnectionService $connections) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(AiPermissions::keys())],
            'days' => ['required', 'integer', Rule::in(AiPermissions::DURATIONS)],
        ]);

        // Read the pending request before Passport consumes it from the session.
        $serialized = $request->session()->get('authRequest');
        $authRequest = is_string($serialized) ? unserialize($serialized, ['allowed_classes' => [
            AuthorizationRequest::class, Client::class, Scope::class, User::class,
        ]]) : null;

        $response = $next($request);

        $location = (string) $response->headers->get('Location');
        if ($authRequest instanceof AuthorizationRequest && $response->isRedirection() && str_contains($location, 'code=')) {
            $this->connections->grant(
                $request->user(),
                $authRequest->getClient()->getIdentifier(),
                array_values(array_unique($data['permissions'] ?? [])),
                (int) $data['days'],
            );
        }

        return $response;
    }
}
