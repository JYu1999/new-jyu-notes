<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyConfirmationResponse;

/**
 * After re-confirming identity with a passkey (JYU-566): back to where the
 * confirmation was asked for, or the 「帳號與安全」page — not the site root.
 */
class PasskeyConfirmedResponse implements PasskeyConfirmationResponse
{
    public function toResponse($request)
    {
        $redirect = redirect()->intended(route('admin.account.show'));

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $redirect->getTargetUrl()])
            : $redirect;
    }
}
