<?php

namespace Tests\Feature\Ai;

use App\Mcp\Servers\BlogServer;
use App\Mcp\Tools\CreateDraft;
use App\Mcp\Tools\RequestPublish;
use App\Mcp\Tools\SearchContent;
use App\Mcp\Tools\UpdateContent;
use App\Models\AiActionRequest;
use App\Models\AiActivity;
use App\Models\AiConnection;
use App\Models\AiUser;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use App\Services\RevisionService;
use App\Support\AiPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * JYU-555: manage connected AIs and what they may do.
 */
class AiManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.display_timezone' => 'Asia/Taipei']);
        $this->travelTo(Carbon::parse('2026-11-27 06:00:00', 'UTC'));
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
    }

    private function connection(array $attrs = []): AiConnection
    {
        $client = Client::query()->forceCreate([
            'name' => $attrs['name'] ?? 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
            'grant_types' => ['authorization_code'], 'revoked' => false,
        ]);

        return AiConnection::create($attrs + [
            'user_id' => $this->admin->id, 'oauth_client_id' => $client->id, 'name' => 'Claude',
            'permissions' => ['read', 'draft', 'organize', 'publish'], 'expires_at' => now()->addDays(90),
        ]);
    }

    private function server(AiConnection $c)
    {
        app()->instance(AiConnection::class, $c);
        app(RevisionService::class)->actAsAi($c->name, $c->user_id);

        return BlogServer::actingAs(AiUser::find($this->admin->id), 'api');
    }

    private function page(): string
    {
        return $this->actingAs($this->admin)->get(route('admin.agents.index'))->assertOk()->getContent();
    }

    public function test_lists_connected_ais_with_dates_and_activity_state(): void
    {
        $this->connection(['last_used_at' => now()->subMinutes(3)]);
        $this->connection(['name' => 'Claude Code', 'last_used_at' => now()->subDays(2), 'expires_at' => now()->addDays(7)]);

        $html = $this->page();

        $this->assertStringContainsString('Claude<span class="font-normal text-ink-3"> · claude.ai</span>', $html);
        $this->assertStringContainsString('11/27 連線', $html);
        $this->assertStringContainsString('最後使用 3分鐘前', $html);
        $this->assertStringContainsString('授權到 2027/02/25', $html);
        $this->assertMatchesRegularExpression('/data-connection-state[^>]*>\s*<span[^>]*><\/span>使用中/', $html);
        $this->assertMatchesRegularExpression('/data-connection-state[^>]*>\s*<span[^>]*><\/span>閒置/', $html);
    }

    public function test_revoking_cuts_the_ai_off_immediately(): void
    {
        $c = $this->connection();
        DB::table('oauth_access_tokens')->insert(['id' => 'tok1', 'user_id' => $this->admin->id, 'client_id' => $c->oauth_client_id, 'scopes' => '[]', 'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour()]);

        $this->actingAs($this->admin)->delete(route('admin.agents.connections.revoke', $c))->assertRedirect()->assertSessionHas('success');

        $this->assertNotNull($c->fresh()->revoked_at);
        $this->assertTrue((bool) DB::table('oauth_access_tokens')->where('id', 'tok1')->value('revoked'));
        $this->assertStringNotContainsString('data-connection="'.$c->id.'"', $this->page());
    }

    public function test_global_policy_allow_ask_deny(): void
    {
        $html = $this->page();
        foreach (AiPolicy::ACTIONS as $def) {
            $this->assertStringContainsString('aria-label="'.$def['name'].'"', $html);
        }
        $this->assertMatchesRegularExpression('/name="policy\[publish\]" value="ask" role="radio" aria-checked="true"/', $html);
        $this->assertMatchesRegularExpression('/name="policy\[trash\]" value="allow" role="radio" aria-checked="false"\s+disabled/', $html, 'deleting can never be allowed');

        $this->put(route('admin.agents.policy'), ['policy' => ['draft' => 'deny']])->assertRedirect();
        $this->assertSame('deny', AiPolicy::global()['draft']);
        $this->put(route('admin.agents.policy'), ['policy' => ['trash' => 'allow']])->assertSessionHasErrors('policy.trash');
    }

    public function test_global_policy_controls_what_the_ai_can_do(): void
    {
        $c = $this->connection();
        $this->actingAs($this->admin);
        $post = app(PostService::class)->create(['locale' => 'zh', 'title' => 'A', 'slug' => 'a', 'body' => '原文', 'status' => 'draft']);

        AiPolicy::saveGlobal(['draft' => 'deny']);
        $this->server($c)->tool(UpdateContent::class, ['type' => 'post', 'id' => $post->id, 'body' => '改'])->assertHasErrors();

        AiPolicy::saveGlobal(['draft' => 'ask']);
        $this->server($c)->tool(UpdateContent::class, ['type' => 'post', 'id' => $post->id, 'body' => '改'])->assertOk()->assertSee('需要作者確認');
        $this->assertSame('原文', $post->fresh()->body, '需確認 queues the change instead of running it');
        $this->assertDatabaseHas('ai_action_requests', ['tool' => 'update_content', 'status' => 'pending', 'publishable_id' => $post->id]);

        AiPolicy::saveGlobal(['publish' => 'allow']);
        $this->server($c)->tool(RequestPublish::class, ['type' => 'post', 'id' => $post->id, 'action' => 'publish'])->assertOk();
        $this->assertSame('published', $post->fresh()->status, 'allowed publishing happens right away');
    }

    public function test_a_connection_can_only_be_stricter_than_global(): void
    {
        $c = $this->connection();
        AiPolicy::saveGlobal(['draft' => 'ask']);

        $this->actingAs($this->admin)->put(route('admin.agents.connections.policy', $c), ['policy' => ['draft' => 'allow']])
            ->assertSessionHasErrors(['policy.draft' => '單一連線只能比整體設定更嚴格，不能放寬。']);
        $this->put(route('admin.agents.connections.policy', $c), ['policy' => ['draft' => 'deny']])->assertSessionHasNoErrors();

        $this->assertSame('deny', AiPolicy::effective($c->fresh(), 'draft'));
        $this->assertSame('allow', AiPolicy::effective($c->fresh(), 'read'));

        // If the global setting is loosened later, the connection stays as strict as it chose.
        AiPolicy::saveGlobal(['draft' => 'allow']);
        $this->assertSame('deny', AiPolicy::effective($c->fresh(), 'draft'));
    }

    public function test_actions_not_granted_on_the_consent_screen_stay_denied(): void
    {
        $c = $this->connection(['permissions' => ['read']]);
        AiPolicy::saveGlobal(['publish' => 'allow']);

        $this->assertSame('deny', AiPolicy::effective($c, 'publish'));
        $this->assertSame('deny', AiPolicy::effective($c, 'trash'));
        $this->assertMatchesRegularExpression('/發布、隱藏<span class="text-xs text-ink-3"> · 未授權<\/span>/', $this->page());
    }

    public function test_recent_activity_links_writes_to_their_version_diff(): void
    {
        $c = $this->connection();
        $this->server($c)->tool(SearchContent::class, ['query' => '監控'])->assertOk();
        $this->server($c)->tool(CreateDraft::class, ['type' => 'post', 'locale' => 'zh', 'title' => 'AI 草稿', 'body' => '內容'])->assertOk();

        $post = Post::firstWhere('title', 'AI 草稿');
        $activity = AiActivity::latest('id')->first();
        $this->assertSame($post->revisions()->first()->id, $activity->content_revision_id);

        $html = $this->page();
        $this->assertStringContainsString('搜尋「監控」相關內容（唯讀）', $html);
        $this->assertStringContainsString('href="'.route('admin.revisions.index', ['type' => 'posts', 'id' => $post->id, 'rev' => $activity->content_revision_id]).'"', $html);
        $this->assertStringContainsString('建立文章草稿〈AI 草稿〉', $html);
        $this->assertStringContainsString('Claude · 14:00', $html);

        $this->get(route('admin.agents.activity'))->assertOk()->assertSee('搜尋「監控」相關內容（唯讀）');
    }

    public function test_queued_actions_show_as_waiting_in_activity(): void
    {
        $c = $this->connection();
        $this->actingAs($this->admin);
        $post = app(PostService::class)->create(['locale' => 'zh', 'title' => '待發布', 'slug' => 'p', 'body' => 'x', 'status' => 'draft']);
        $this->server($c)->tool(RequestPublish::class, ['type' => 'post', 'id' => $post->id, 'action' => 'publish']);

        $html = $this->page();
        $this->assertStringContainsString('要求發布文章〈待發布〉', $html);
        $this->assertStringContainsString(' · 等待確認', $html);
        $this->assertSame(1, AiActionRequest::count());
    }

    public function test_developer_tool_list_marks_read_write_and_confirm(): void
    {
        $html = $this->page();
        preg_match('/<details data-tools.*?<\/details>/s', $html, $m);

        foreach (['search_content' => '讀', 'get_content' => '讀', 'create_draft' => '寫', 'upload_media' => '寫', 'request_publish' => '需確認'] as $tool => $kind) {
            $this->assertMatchesRegularExpression('/'.$tool.'\s*<span[^>]*>'.$kind.'<\/span>/', $m[0]);
        }
    }
}
