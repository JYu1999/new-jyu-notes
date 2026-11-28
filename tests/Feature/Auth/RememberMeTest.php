<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * JYU-565: 「記住我」7 天後失效，忘記登出也不會一直登入著。
 */
class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('secret'), 'role' => User::ROLE_ADMIN]);
    }

    private function recallerName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    public function test_remember_me_cookie_lasts_seven_days(): void
    {
        $this->freezeTime();

        $response = $this->post('/login', ['email' => 'a@b.c', 'password' => 'secret', 'remember' => '1']);

        $response->assertRedirect(route('admin.dashboard'));
        $cookie = $response->getCookie($this->recallerName(), false);
        $this->assertNotNull($cookie, 'remember cookie should be set');
        $this->assertSame(now()->addDays(7)->getTimestamp(), $cookie->getExpiresTime());
    }

    public function test_login_without_remember_me_sets_no_remember_cookie(): void
    {
        $response = $this->post('/login', ['email' => 'a@b.c', 'password' => 'secret']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertNull($response->getCookie($this->recallerName(), false));
    }

    public function test_migration_invalidates_remember_cookies_issued_before_the_change(): void
    {
        $this->admin->forceFill(['remember_token' => 'old-one-year-token'])->save();

        $migration = require database_path('migrations/2026_11_28_000000_invalidate_old_remember_tokens.php');
        $migration->up();

        $this->assertNotSame('old-one-year-token', $this->admin->fresh()->remember_token);
    }
}
