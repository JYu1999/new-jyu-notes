<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\AdminNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Passkey;
use Tests\Support\FakeAuthenticator;
use Tests\Support\RegistersPasskeys;
use Tests\TestCase;

/**
 * JYU-566: 「帳號與安全」頁（/admin/account）管理 passkey：清單、新增、改名、刪除；
 * 新增或刪除前要先再驗證一次身分（密碼，或已註冊的 passkey）。
 */
class AccountPasskeysTest extends TestCase
{
    use RefreshDatabase;
    use RegistersPasskeys;

    private const IPHONE_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('secret'), 'role' => User::ROLE_ADMIN]);
    }

    private function passkey(string $name = 'MacBook · Safari'): Passkey
    {
        $this->registerPasskey($this->admin, new FakeAuthenticator, $name, logout: false);
        session()->forget('auth.password_confirmed_at');

        return $this->admin->passkeys()->where('name', $name)->firstOrFail();
    }

    // ── Page and entry point ────────────────────────────────────────────

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.account.show'))->assertRedirect(route('auth.login.show'));
    }

    public function test_page_shows_account_and_passkey_list(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 28)->setTime(5, 0));
        $this->passkey('MacBook · Safari');
        $this->passkey('iPhone');
        $this->admin->passkeys()->where('name', 'MacBook · Safari')->update(['last_used_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.account.show'))
            ->assertOk()
            ->assertSee('帳號與安全')
            ->assertSee('JYu')
            ->assertSee('a@b.c')
            ->assertSee('新增 passkey')
            ->assertSeeInOrder(['iPhone', '新增 11/28', '尚未使用'])
            ->assertSeeInOrder(['MacBook · Safari', '新增 11/28', '最後使用 今天']);
    }

    public function test_page_without_passkeys_explains_what_they_are(): void
    {
        $this->actingAs($this->admin)->get(route('admin.account.show'))
            ->assertOk()
            ->assertSee('用 Touch ID、Face ID 或手機登入，不用打密碼。')
            ->assertSee('還沒有 passkey');
    }

    public function test_new_passkey_name_defaults_to_this_device(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.account.show'), ['User-Agent' => self::IPHONE_SAFARI])
            ->assertSee('iPhone · Safari');
    }

    public function test_sidebar_account_block_links_to_the_page_but_main_nav_does_not(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->getContent();

        preg_match('/<aside[^>]*data-admin-sidebar[^>]*>(.*?)<\/aside>/s', $html, $m);
        $this->assertStringContainsString('href="'.route('admin.account.show').'"', $m[1]);

        $urls = collect(AdminNav::groups(request()))->flatMap(fn ($g) => array_column($g['items'], 'url'));
        $this->assertNotContains(route('admin.account.show'), $urls);
    }

    // ── Re-authentication before adding ─────────────────────────────────

    public function test_adding_needs_identity_confirmed_first(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.account.passkeys.options'))
            ->assertStatus(423);
    }

    public function test_wrong_password_does_not_confirm(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.account.confirm.password'), ['password' => 'nope'])
            ->assertUnprocessable();

        $this->getJson(route('admin.account.passkeys.options'))->assertStatus(423);
    }

    public function test_password_confirms_then_passkey_can_be_added(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.account.confirm.password'), ['password' => 'secret'])
            ->assertOk();

        $options = $this->getJson(route('admin.account.passkeys.options'))->assertOk()->json('options');
        $this->assertSame('jyu-notes.test', $options['rp']['id']);

        $this->postJson(route('admin.account.passkeys.store'), [
            'name' => 'MacBook · Safari',
            'credential' => (new FakeAuthenticator)->register($options),
        ])->assertOk()->assertJson(['name' => 'MacBook · Safari']);

        $this->assertSame(['MacBook · Safari'], $this->admin->passkeys()->pluck('name')->all());
    }

    public function test_an_existing_passkey_can_confirm_identity(): void
    {
        $mac = new FakeAuthenticator;
        $this->registerPasskey($this->admin, $mac, logout: false);
        session()->forget('auth.password_confirmed_at');

        $options = $this->getJson(route('admin.account.confirm.passkey-options'))->assertOk()->json('options');
        $this->assertSame([$mac->id()], array_column($options['allowCredentials'], 'id'));

        $this->postJson(route('admin.account.confirm.passkey'), ['credential' => $mac->login($options)])
            ->assertOk();

        $this->getJson(route('admin.account.passkeys.options'))->assertOk();
    }

    public function test_confirmation_expires_after_fifteen_minutes(): void
    {
        $this->actingAs($this->admin)->confirmIdentity();
        $this->getJson(route('admin.account.passkeys.options'))->assertOk();

        $this->travel(16)->minutes();

        $this->getJson(route('admin.account.passkeys.options'))->assertStatus(423);
    }

    public function test_password_login_counts_as_fresh_confirmation_so_the_prompt_is_one_tap(): void
    {
        $this->post('/login', ['email' => 'a@b.c', 'password' => 'secret']);

        $this->getJson(route('admin.account.passkeys.options'))->assertOk();
    }

    public function test_registration_from_another_origin_is_rejected(): void
    {
        $this->actingAs($this->admin)->confirmIdentity();
        $options = $this->getJson(route('admin.account.passkeys.options'))->json('options');

        $this->postJson(route('admin.account.passkeys.store'), [
            'name' => 'x',
            'credential' => (new FakeAuthenticator('https://evil.example'))->register($options),
        ])->assertUnprocessable();

        $this->assertSame(0, $this->admin->passkeys()->count());
    }

    public function test_confirm_page_offers_password_and_passkey(): void
    {
        $this->passkey();

        $this->get(route('admin.account.confirm'))
            ->assertOk()
            ->assertSee('先確認是你')
            ->assertSee('name="password"', false)
            ->assertSee('用 passkey 確認');
    }

    public function test_confirm_page_form_returns_to_the_account_page(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.account.confirm.password'), ['password' => 'secret'])
            ->assertRedirect(route('admin.account.show'));
    }

    // ── Rename and delete ───────────────────────────────────────────────

    public function test_rename(): void
    {
        $passkey = $this->passkey();

        $this->patch(route('admin.account.passkeys.update', $passkey), ['name' => '公司電腦'])
            ->assertRedirect(route('admin.account.show'));

        $this->assertSame('公司電腦', $passkey->fresh()->name);
    }

    public function test_rename_needs_a_name(): void
    {
        $passkey = $this->passkey();

        $this->patch(route('admin.account.passkeys.update', $passkey), ['name' => ' '])->assertSessionHasErrors('name');

        $this->assertSame('MacBook · Safari', $passkey->fresh()->name);
    }

    public function test_delete_without_confirmation_goes_to_confirm_page_first(): void
    {
        $passkey = $this->passkey();

        $this->from(route('admin.account.show'))
            ->delete(route('admin.account.passkeys.destroy', $passkey))
            ->assertRedirect(route('admin.account.confirm'));

        $this->assertNotNull($passkey->fresh());

        // After confirming it returns to the account page, not to the DELETE url.
        $this->post(route('admin.account.confirm.password'), ['password' => 'secret'])
            ->assertRedirect(route('admin.account.show'));
    }

    public function test_delete_after_confirmation(): void
    {
        $passkey = $this->passkey();
        $this->confirmIdentity();

        $this->from(route('admin.account.show'))
            ->delete(route('admin.account.passkeys.destroy', $passkey))
            ->assertRedirect(route('admin.account.show'))
            ->assertSessionHas('success');

        $this->assertNull($passkey->fresh());
    }

    public function test_cannot_touch_someone_elses_passkey(): void
    {
        $other = User::create(['name' => 'B', 'email' => 'b@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
        $this->registerPasskey($other, new FakeAuthenticator);
        $theirs = $other->passkeys()->first();

        $this->actingAs($this->admin)->confirmIdentity();
        $this->patch(route('admin.account.passkeys.update', $theirs), ['name' => 'x'])->assertNotFound();
        $this->delete(route('admin.account.passkeys.destroy', $theirs))->assertNotFound();

        $this->assertNotNull($theirs->fresh());
    }

    // ── 「下次用 passkey 登入這台裝置？」 ───────────────────────────────────

    public function test_password_login_offers_a_passkey_once(): void
    {
        $this->post('/login', ['email' => 'a@b.c', 'password' => 'secret']);

        $this->get(route('admin.dashboard'))->assertSee('下次用 passkey 登入這台裝置？')->assertSee('不用了');
        $this->get(route('admin.dashboard'))->assertDontSee('下次用 passkey 登入這台裝置？');
    }

    public function test_passkey_login_does_not_offer_another_passkey(): void
    {
        $phone = new FakeAuthenticator;
        $this->registerPasskey($this->admin, $phone);

        $options = $this->getJson(route('auth.passkey.options'))->json('options');
        $this->postJson(route('auth.passkey.login'), ['credential' => $phone->login($options)])->assertOk();

        $this->get(route('admin.dashboard'))->assertDontSee('下次用 passkey 登入這台裝置？');
        // A passkey login is not a password re-entry.
        $this->getJson(route('admin.account.passkeys.options'))->assertStatus(423);
    }
}
