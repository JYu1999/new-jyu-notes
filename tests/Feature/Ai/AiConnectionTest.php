<?php

namespace Tests\Feature\Ai;

use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * JYU-554: connect Claude to the blog through an OAuth consent screen.
 */
class AiConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT = 'https://claude.ai/api/mcp/auth_callback';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (! file_exists(storage_path('oauth-private.key'))) {
            Artisan::call('passport:keys');
        }
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
    }

    private function registerClient(string $redirect = self::REDIRECT): string
    {
        return $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirect]])
            ->assertCreated()->json('client_id');
    }

    /** @return array{0: string, 1: string} [authorize query string, code verifier] */
    private function authorizeQuery(string $clientId): array
    {
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]), $verifier];
    }

    /** Show the consent screen and return the hidden auth_token. */
    private function consent(string $clientId): array
    {
        [$query, $verifier] = $this->authorizeQuery($clientId);
        $html = $this->actingAs($this->admin)->get('/oauth/authorize?'.$query)->assertOk()->getContent();
        preg_match('/name="auth_token" value="([^"]+)"/', $html, $m);

        return [$m[1], $verifier, $html];
    }

    private function approve(string $clientId, array $permissions = ['read', 'draft'], int $days = 30): array
    {
        [$authToken, $verifier] = $this->consent($clientId);
        $response = $this->post('/oauth/authorize', [
            'state' => 'xyz', 'client_id' => $clientId, 'auth_token' => $authToken,
            'permissions' => $permissions, 'days' => $days,
        ]);
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $params);

        return [$response, $params, $verifier];
    }

    public function test_admin_page_shows_the_connection_url_to_copy(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.agents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<span data-mcp-url class="flex-1 min-w-0 truncate font-mono text-sm">'.url('/mcp').'</span>', $html);
        $this->assertStringContainsString('複製網址', $html);
        $this->assertStringContainsString('在 Claude 的「設定 › 連接器」新增自訂連接器', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.agents.index'), '/').'"[^>]*>\s*<span[^>]*>AI 連線<\/span>/', $html);
    }

    public function test_oauth_discovery_and_client_registration(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->assertJson([
            'authorization_endpoint' => url('/oauth/authorize'),
            'token_endpoint' => url('/oauth/token'),
            'registration_endpoint' => url('/oauth/register'),
            'code_challenge_methods_supported' => ['S256'],
        ]);
        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->assertJsonPath('resource', url('/mcp'));

        $this->registerClient();
        $this->postJson('/oauth/register', ['client_name' => 'Evil', 'redirect_uris' => ['https://evil.example/cb']])
            ->assertStatus(400)->assertJson(['error' => 'invalid_redirect_uri']);
    }

    public function test_consent_screen_shows_the_ai_the_account_permissions_and_durations(): void
    {
        [, , $html] = $this->consent($this->registerClient());

        $this->assertStringContainsString('Claude 想連接你的部落格', $html);
        $this->assertStringContainsString('你目前以 <b>JYu</b> 登入', $html);
        foreach (['讀取所有內容', '建立與編輯草稿、翻譯', '整理分類、標籤與媒體', '要求發布或隱藏'] as $label) {
            $this->assertStringContainsString('<b>'.$label.'</b>', $html);
        }
        $this->assertMatchesRegularExpression('/value="publish"\s+class/', $html, 'publishing is not ticked by default');
        foreach ([90, 30, 7] as $days) {
            $this->assertStringContainsString('<option value="'.$days.'">'.$days.' 天</option>', $html);
        }
        $this->assertStringContainsString('允許連線', $html);
        $this->assertStringContainsString('拒絕', $html);
        $this->assertStringContainsString('完成後會回到 <span class="font-mono text-ink-2">claude.ai</span>', $html);
    }

    public function test_guests_are_sent_to_log_in_first(): void
    {
        [$query] = $this->authorizeQuery($this->registerClient());

        $this->get('/oauth/authorize?'.$query)->assertRedirect(route('auth.login.show'));
    }

    public function test_allowing_returns_to_claude_and_records_the_chosen_permissions_and_expiry(): void
    {
        $clientId = $this->registerClient();
        [$response, $params] = $this->approve($clientId, ['read', 'draft'], 30);

        $response->assertRedirect();
        $this->assertStringStartsWith(self::REDIRECT, $response->headers->get('Location'));
        $this->assertSame('xyz', $params['state']);
        $this->assertNotEmpty($params['code']);

        $connection = AiConnection::firstOrFail();
        $this->assertSame(['Claude', ['read', 'draft']], [$connection->name, $connection->permissions]);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $connection->expires_at->timestamp, 5);
    }

    public function test_denying_returns_an_error_to_claude_and_connects_nothing(): void
    {
        $clientId = $this->registerClient();
        [$authToken] = $this->consent($clientId);

        $response = $this->delete('/oauth/authorize', ['state' => 'xyz', 'client_id' => $clientId, 'auth_token' => $authToken]);

        $this->assertStringContainsString('error=access_denied', $response->headers->get('Location'));
        $this->assertSame(0, AiConnection::count());
    }

    public function test_only_the_site_owner_can_connect_an_ai(): void
    {
        $guest = User::create(['name' => 'Other', 'email' => 'o@b.c', 'password' => bcrypt('x'), 'role' => 'user']);
        [$query] = $this->authorizeQuery($this->registerClient());

        $this->actingAs($guest)->get('/oauth/authorize?'.$query)->assertForbidden();
    }

    public function test_claude_gets_a_token_and_sees_only_the_tools_it_was_allowed(): void
    {
        $clientId = $this->registerClient();
        [, $params, $verifier] = $this->approve($clientId, ['read', 'draft']);

        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => $verifier,
            'code' => $params['code'],
        ])->assertOk()->json('access_token');

        $tools = $this->mcp($token, 'tools/list')->assertOk()->json('result.tools.*.name');

        sort($tools);
        $this->assertSame(['create_draft', 'create_translation', 'get_content', 'list_translation_gaps', 'search_content', 'update_content'], $tools);
        $this->assertEmpty(array_filter($tools, fn ($t) => str_contains($t, 'delete')), 'no tool can delete content');
    }

    public function test_mcp_rejects_missing_revoked_or_expired_connections(): void
    {
        $this->mcp(null, 'tools/list')->assertUnauthorized();

        $clientId = $this->registerClient();
        [, $params, $verifier] = $this->approve($clientId);
        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT,
            'code_verifier' => $verifier, 'code' => $params['code'],
        ])->json('access_token');

        AiConnection::query()->update(['expires_at' => now()->subMinute()]);
        $this->mcp($token, 'tools/list')->assertUnauthorized();
    }

    private function mcp(?string $token, string $method, array $params = [])
    {
        $this->app['auth']->forgetGuards();
        $headers = ['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'];
        if ($token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $this->withHeaders($headers)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ?: new \stdClass]);
    }
}
