<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Re-confirm identity before adding or deleting a passkey (JYU-566), so a
 * session someone forgot to log out of can't quietly add a passkey of its own.
 * Confirming with an existing passkey goes through laravel/passkeys'
 * PasskeyConfirmationController instead; both set auth.password_confirmed_at.
 */
class ConfirmIdentityController extends Controller
{
    public function show(Request $request): View
    {
        return view('admin.account.confirm', [
            'hasPasskeys' => $request->user()->hasPasskeysEnabled(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(
            ['password' => ['required', 'string', 'current_password:web']],
            ['password.current_password' => '密碼不對'],
            ['password' => '密碼'],
        );

        $request->session()->passwordConfirmed();

        return $request->wantsJson()
            ? response()->json(['confirmed' => true])
            : redirect()->intended(route('admin.account.show'));
    }
}
