<?php

namespace Tests;

use App\Support\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Saving a tweet queues link-preview fetches (JYU-540). Never touch the
        // network from tests: no DNS (every host "doesn't resolve") and no HTTP.
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return [];
            }
        });
        Http::preventStrayRequests();
    }
}
