<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Registers a FakeAuthenticator for a user through the real 「帳號與安全」endpoints (JYU-566).
 */
trait RegistersPasskeys
{
    /** Marks the current session as having just re-entered the password. */
    protected function confirmIdentity(): static
    {
        session()->passwordConfirmed();

        return $this;
    }

    protected function registerPasskey(User $user, FakeAuthenticator $authenticator, string $name = 'MacBook · Safari', bool $logout = true): void
    {
        $this->actingAs($user)->confirmIdentity();

        $options = $this->getJson(route('admin.account.passkeys.options'))->assertOk()->json('options');
        $this->postJson(route('admin.account.passkeys.store'), [
            'name' => $name,
            'credential' => $authenticator->register($options),
        ])->assertOk();

        if ($logout) {
            Auth::guard('web')->logout();
            session()->flush();
        }
    }
}
