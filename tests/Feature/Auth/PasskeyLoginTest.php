<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Support\FakeAuthenticator;
use Tests\Support\RegistersPasskeys;
use Tests\TestCase;

/**
 * JYU-566: 在登入頁按「用 Passkey 登入」，不用輸入 email 和密碼就登入；
 * 「記住我」和密碼登入一樣是 7 天；密碼登入繼續保留。
 */
class PasskeyLoginTest extends TestCase
{
    use RefreshDatabase;
    use RegistersPasskeys;

    private User $admin;

    private FakeAuthenticator $phone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('secret'), 'role' => User::ROLE_ADMIN]);
        $this->phone = new FakeAuthenticator;
        $this->registerPasskey($this->admin, $this->phone, 'iPhone');
    }

    private function signInWithPasskey(FakeAuthenticator $authenticator, array $extra = [])
    {
        $options = $this->getJson(route('auth.passkey.options'))->assertOk()->json('options');

        return $this->postJson(route('auth.passkey.login'), ['credential' => $authenticator->login($options)] + $extra);
    }

    public function test_login_page_offers_passkey_login_next_to_the_password_form(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('用 Passkey 登入')
            ->assertSee(route('auth.passkey.options'), false)
            ->assertSee('name="password"', false);
    }

    public function test_signs_in_with_a_registered_passkey_without_email_or_password(): void
    {
        $this->freezeSecond();

        $response = $this->signInWithPasskey($this->phone);

        $response->assertOk()->assertJson(['redirect' => route('admin.dashboard')]);
        $this->assertAuthenticatedAs($this->admin);
        $this->assertEquals(now(), $this->admin->passkeys()->first()->last_used_at);
    }

    public function test_remember_me_lasts_seven_days_like_password_login(): void
    {
        $this->freezeTime();

        $response = $this->signInWithPasskey($this->phone, ['remember' => true]);

        $cookie = $response->getCookie(Auth::guard('web')->getRecallerName(), false);
        $this->assertNotNull($cookie, 'remember cookie should be set');
        $this->assertSame(now()->addDays(7)->getTimestamp(), $cookie->getExpiresTime());
    }

    public function test_without_remember_me_no_remember_cookie_is_set(): void
    {
        $response = $this->signInWithPasskey($this->phone);

        $this->assertNull($response->getCookie(Auth::guard('web')->getRecallerName(), false));
    }

    public function test_returns_to_the_page_that_asked_for_login(): void
    {
        // e.g. the OAuth consent screen (JYU-554) or any admin page.
        $this->get(route('admin.agents.index'))->assertRedirect(route('auth.login.show'));

        $this->signInWithPasskey($this->phone)->assertJson(['redirect' => route('admin.agents.index')]);
    }

    public function test_unknown_passkey_is_rejected(): void
    {
        $this->signInWithPasskey(new FakeAuthenticator)->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_passkey_from_another_site_is_rejected(): void
    {
        $other = new FakeAuthenticator('https://evil.example');
        $options = $this->getJson(route('auth.passkey.options'))->json('options');
        $credential = $this->phone->login($options);
        $credential['response']['clientDataJSON'] = $other->login($options)['response']['clientDataJSON'];

        $this->postJson(route('auth.passkey.login'), ['credential' => $credential])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_password_login_still_works(): void
    {
        $this->post('/login', ['email' => 'a@b.c', 'password' => 'secret'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_logged_in_admin_cannot_request_login_options(): void
    {
        $this->actingAs($this->admin)->getJson(route('auth.passkey.options'))->assertRedirect();
    }
}
