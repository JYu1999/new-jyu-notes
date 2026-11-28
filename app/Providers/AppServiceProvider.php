<?php

namespace App\Providers;

use App\Actions\Passkeys\StorePasskey;
use App\Http\Responses\PasskeyConfirmedResponse;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tweet;
use App\Services\RevisionService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Actions\StorePasskey as BaseStorePasskey;
use Laravel\Passkeys\Contracts\PasskeyConfirmationResponse;
use Laravel\Passkeys\Passkeys;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RevisionService::class);

        // OAuth routes are declared in routes/ai.php (JYU-554).
        Passport::ignoreRoutes();

        // Passkey routes are declared in routes/web.php (JYU-566).
        Passkeys::ignoreRoutes();
        $this->app->singleton(PasskeyConfirmationResponse::class, PasskeyConfirmedResponse::class);
        $this->app->bind(BaseStorePasskey::class, StorePasskey::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // AI connections (JYU-554): our consent screen; short-lived access tokens
        // (refreshed by the client), with the connection's own expiry on top.
        Passport::authorizationView(function (array $parameters) {
            abort_unless($parameters['user']?->isAdmin(), 403);

            return view('oauth.authorize', $parameters);
        });
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(90));

        // Non-enforcing: maps Post/Tweet to short type strings for content_references
        // while leaving other polymorphic models (e.g. Passport's token owners) on
        // their default class-name morph. enforceMorphMap() would throw for User.
        Relation::morphMap([
            'post' => Post::class,
            'tweet' => Tweet::class,
            'page' => Page::class,
        ]);
    }
}
