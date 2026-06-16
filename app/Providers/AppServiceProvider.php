<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Non-enforcing: maps Post/Tweet to short type strings for content_references
        // while leaving other polymorphic models (e.g. Sanctum's tokenable User) on
        // their default class-name morph. enforceMorphMap() would throw for User.
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'post' => \App\Models\Post::class,
            'tweet' => \App\Models\Tweet::class,
        ]);
    }
}
