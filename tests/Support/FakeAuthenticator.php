<?php

namespace Tests\Support;

use OpenSSLAsymmetricKey;

/**
 * A software passkey (JYU-566): answers the WebAuthn options our server sends the
 * browser with the JSON a real authenticator would return through
 * navigator.credentials, signed with its own P-256 key. Lets feature tests run
 * the full registration and login ceremonies instead of mocking the verifier.
 */
class FakeAuthenticator
{
    private OpenSSLAsymmetricKey $key;

    public readonly string $credentialId;

    /** user.id from the registration options, returned as userHandle on login. */
    private string $userHandle = '';

    private int $counter = 0;

    public function __construct(private readonly string $origin = '')
    {
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->credentialId = random_bytes(16);
    }

    public function id(): string
    {
        return self::b64url($this->credentialId);
    }

    /**
     * navigator.credentials.create() for the given PublicKeyCredentialCreationOptionsJSON.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function register(array $options): array
    {
        $this->userHandle = self::unb64url($options['user']['id']);
        $clientData = $this->clientData('webauthn.create', $options['challenge']);

        $cose = self::cbor([1 => 2, 3 => -7, -1 => 1, -2 => self::bytes($this->coord('x')), -3 => self::bytes($this->coord('y'))]);
        $authData = hash('sha256', $options['rp']['id'], true)
            ."\x45" // UP | UV | AT
            .pack('N', $this->counter)
            .str_repeat("\0", 16) // AAGUID (unknown authenticator)
            .pack('n', strlen($this->credentialId)).$this->credentialId
            .$cose;
        $attestation = self::cbor(['fmt' => 'none', 'attStmt' => [], 'authData' => self::bytes($authData)]);

        return [
            'id' => $this->id(),
            'rawId' => $this->id(),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::b64url($clientData),
                'attestationObject' => self::b64url($attestation),
                'transports' => ['internal'],
            ],
            'clientExtensionResults' => new \stdClass,
        ];
    }

    /**
     * navigator.credentials.get() for the given PublicKeyCredentialRequestOptionsJSON.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function login(array $options): array
    {
        $clientData = $this->clientData('webauthn.get', $options['challenge']);
        $authData = hash('sha256', $options['rpId'] ?? config('passkeys.relying_party_id'), true)."\x05".pack('N', ++$this->counter);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return [
            'id' => $this->id(),
            'rawId' => $this->id(),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::b64url($clientData),
                'authenticatorData' => self::b64url($authData),
                'signature' => self::b64url($signature),
                'userHandle' => self::b64url($this->userHandle),
            ],
            'clientExtensionResults' => new \stdClass,
        ];
    }

    private function clientData(string $type, string $challenge): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $this->origin ?: rtrim(config('app.url'), '/'),
            'crossOrigin' => false,
        ], JSON_UNESCAPED_SLASHES);
    }

    private function coord(string $axis): string
    {
        return str_pad(openssl_pkey_get_details($this->key)['ec'][$axis], 32, "\0", STR_PAD_LEFT);
    }

    public static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64url(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }

    /** Marks a string as a CBOR byte string (plain PHP strings encode as text). */
    private static function bytes(string $raw): object
    {
        return (object) ['bytes' => $raw];
    }

    /** Minimal CBOR encoder: ints, text, byte strings and maps — all a passkey needs. */
    private static function cbor(mixed $value): string
    {
        return match (true) {
            is_int($value) && $value >= 0 => self::head(0, $value),
            is_int($value) => self::head(1, -1 - $value),
            is_object($value) => self::head(2, strlen($value->bytes)).$value->bytes,
            is_string($value) => self::head(3, strlen($value)).$value,
            is_array($value) => self::head(5, count($value)).implode('', array_map(
                fn ($k, $v) => self::cbor($k).self::cbor($v), array_keys($value), $value,
            )),
        };
    }

    private static function head(int $major, int $length): string
    {
        $type = $major << 5;

        return match (true) {
            $length < 24 => chr($type | $length),
            $length < 256 => chr($type | 24).chr($length),
            default => chr($type | 25).pack('n', $length),
        };
    }
}
