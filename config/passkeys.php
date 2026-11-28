<?php

/*
|--------------------------------------------------------------------------
| Passkeys (JYU-566)
|--------------------------------------------------------------------------
|
| Passkey login for the admin, via laravel/passkeys. The package's own routes
| are switched off (see AppServiceProvider); ours live in routes/web.php so
| they sit behind the same guest / auth + role:admin groups as the rest.
|
*/

return [

    // Passkeys are bound to this domain: it must be the production host (from
    // APP_URL), and local testing needs HTTPS (ryoluo/sail-ssl).
    'relying_party_id' => env('PASSKEYS_RP_ID', parse_url(config('app.url'), PHP_URL_HOST)),

    'allowed_origins' => [
        rtrim(config('app.url'), '/'),
    ],

    // Derives the opaque user handle stored on each authenticator.
    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),

    // How long the browser waits for Touch ID / Face ID (milliseconds).
    'timeout' => 60000,

    'guard' => 'web',

    // Applied to the passkey login, confirm and registration endpoints.
    'throttle' => 'throttle:10,1',

    // Where a passkey login lands when no page asked for it (same as password login).
    'redirect' => '/admin',

    // Adding or deleting a passkey needs the password (or a passkey) re-entered
    // within this many seconds — shorter than auth.password_timeout, because a
    // new passkey is a long-lived way in.
    'confirm_timeout' => 900,

];
