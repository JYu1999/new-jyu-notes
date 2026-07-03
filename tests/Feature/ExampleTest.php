<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL redirects to the locale-prefixed home page.
     */
    public function test_the_application_redirects_to_the_localized_home(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/'.app()->getLocale());
    }
}
