<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * JYU-565: when the session expires while an editor is open, the page fetches a
 * fresh CSRF token instead of failing silently (a valid remember-me cookie logs
 * the admin straight back in; without one the endpoint answers 401).
 */
class CsrfTokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('secret'), 'role' => User::ROLE_ADMIN]);
    }

    /** The 「記住我」cookie value SessionGuard would issue for the admin with this token. */
    private function recaller(string $token): string
    {
        $passwordMac = Auth::guard('web')->hashPasswordForCookie($this->admin->getAuthPassword());

        return $this->admin->id.'|'.$token.'|'.$passwordMac;
    }

    public function test_logged_in_admin_gets_the_current_session_token(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.session.csrf'));

        $response->assertOk()->assertJson(['token' => session()->token()]);
        $response->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson(route('admin.session.csrf'))->assertUnauthorized();
    }

    public function test_valid_remember_cookie_logs_back_in_after_the_session_expired(): void
    {
        $this->admin->forceFill(['remember_token' => 'still-valid'])->save();
        $recaller = $this->recaller('still-valid');

        $response = $this->withCredentials()->withCookie(Auth::guard('web')->getRecallerName(), $recaller)
            ->getJson(route('admin.session.csrf'));

        $response->assertOk()->assertJsonStructure(['token']);
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_stale_remember_cookie_gets_401(): void
    {
        $this->admin->forceFill(['remember_token' => 'rotated'])->save();
        $recaller = $this->recaller('old-token');

        $this->withCredentials()->withCookie(Auth::guard('web')->getRecallerName(), $recaller)
            ->getJson(route('admin.session.csrf'))
            ->assertUnauthorized();
    }
}
