<?php

namespace App\Actions\Passkeys;

use Laravel\Passkeys\Actions\StorePasskey as BaseStorePasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\WebauthnException;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * JYU-566: laravel/passkeys (v0.3) lets a failed registration check — wrong
 * origin, stale challenge, user verification missing — escape as a 500. Answer
 * it as a validation error instead, the way its VerifyPasskey does for logins.
 */
class StorePasskey extends BaseStorePasskey
{
    protected function validate(
        AuthenticatorAttestationResponse $response,
        PublicKeyCredentialCreationOptions $options
    ): CredentialRecord {
        try {
            return parent::validate($response, $options);
        } catch (WebauthnException) {
            throw InvalidPasskeyException::make('Unable to register passkey. Please try again.');
        }
    }
}
