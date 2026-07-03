<?php

namespace App\Services;

use App\Models\User;
use App\Support\Abilities;
use Carbon\CarbonInterface;
use Laravel\Sanctum\NewAccessToken;

class ApiTokenService
{
    /**
     * Mint a personal access token after validating every ability.
     *
     * @param  string[]  $abilities
     *
     * @throws \InvalidArgumentException when an ability is not in the matrix
     */
    public function create(User $user, string $name, array $abilities, ?CarbonInterface $expiresAt = null): NewAccessToken
    {
        foreach ($abilities as $ability) {
            if (! Abilities::isValid($ability)) {
                throw new \InvalidArgumentException("Invalid ability: {$ability}");
            }
        }

        return $user->createToken($name, $abilities, $expiresAt);
    }

    public function revoke(User $user, int $tokenId): void
    {
        $user->tokens()->whereKey($tokenId)->delete();
    }
}
