<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\Tweet;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Relation::morphMap([
            'post' => Post::class,
            'tweet' => Tweet::class,
        ]);
    }
}
