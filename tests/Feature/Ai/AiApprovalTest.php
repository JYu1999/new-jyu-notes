<?php

namespace Tests\Feature\Ai;

use App\Mcp\Servers\BlogServer;
use App\Mcp\Tools\RequestPublish;
use App\Mcp\Tools\UpdateContent;
use App\Models\AiActionRequest;
use App\Models\AiConnection;
use App\Models\AiUser;
use App\Models\ContentRevision;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use App\Services\RevisionService;
use App\Support\AiPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * JYU-556: AI publish (and other "需確認") requests only take effect after approval.
 */
class AiApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AiConnection $connection;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
        $client = Client::query()->forceCreate(['name' => 'Claude', 'redirect_uris' => ['https://claude.ai/cb'], 'grant_types' => ['authorization_code'], 'revoked' => false]);
        $this->connection = AiConnection::create([
            'user_id' => $this->admin->id, 'oauth_client_id' => $client->id, 'name' => 'Claude',
            'permissions' => ['read', 'draft', 'organize', 'publish'], 'expires_at' => now()->addDays(90),
        ]);
        $this->actingAs($this->admin);
        $this->post = app(PostService::class)->create(['locale' => 'en', 'title' => 'How to Report Progress?', 'slug' => 'report', 'body' => "First\n\nSecond", 'status' => 'draft']);
    }

    private function ai()
    {
        app()->instance(AiConnection::class, $this->connection);
        app(RevisionService::class)->actAsAi('Claude', $this->admin->id);

        return BlogServer::actingAs(AiUser::find($this->admin->id), 'api');
    }

    private function askToPublish(string $action = 'publish'): AiActionRequest
    {
        $this->ai()->tool(RequestPublish::class, ['type' => 'post', 'id' => $this->post->id, 'action' => $action, 'note' => '翻好了']);
        app()->forgetInstance(AiConnection::class);
        $this->actingAs($this->admin);

        return AiActionRequest::latest('id')->firstOrFail();
    }

    public function test_pending_requests_show_on_the_ai_page_and_the_admin_home(): void
    {
        $r = $this->askToPublish();
        $this->travel(3)->minutes();

        $agents = $this->get(route('admin.agents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('等你確認 · 1', $agents);
        $this->assertStringContainsString('Claude 想發布文章〈How to Report Progress?〉 EN 版', $agents);
        $this->assertStringContainsString('3分鐘前 · 「翻好了」', $agents);
        $this->assertStringContainsString('href="'.route('admin.agents.requests.show', $r).'"', $agents);
        $this->assertMatchesRegularExpression('/>核准並發布<\/button>/', $agents);
        $this->assertMatchesRegularExpression('/>拒絕<\/button>/', $agents);

        $home = $this->get(route('admin.dashboard'))->getContent();
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.agents.requests.show', $r), '/').'" data-attention.*?Claude 想發布文章〈How to Report Progress\?〉 EN 版.*?3分鐘前 · 點進去檢查後核准或拒絕/s', $home);
    }

    public function test_request_page_shows_the_content_and_the_proposed_change(): void
    {
        AiPolicy::saveGlobal(['draft' => 'ask']);
        $this->ai()->tool(UpdateContent::class, ['type' => 'post', 'id' => $this->post->id, 'title' => 'Reporting Progress', 'body' => "First\n\nSecond, improved"]);
        $this->actingAs($this->admin);
        $r = AiActionRequest::firstOrFail();

        $html = $this->get(route('admin.agents.requests.show', $r))->assertOk()->getContent();

        $this->assertStringContainsString('Claude 想更新文章〈How to Report Progress?〉 EN 版', $html);
        $this->assertStringContainsString('<del class="text-danger">How to Report Progress?</del> → <ins class="no-underline bg-good/15">Reporting Progress</ins>', $html);
        $this->assertStringContainsString('Second<ins>, improved</ins>', $html);
        $this->assertSame(1, substr_count($html, 'data-proposed-paragraph'));
        $this->assertStringContainsString('href="'.route('admin.revisions.index', ['type' => 'posts', 'id' => $this->post->id]).'"', $html);
    }

    public function test_approving_publishes_as_the_ai(): void
    {
        $r = $this->askToPublish();
        $this->assertSame('draft', $this->post->fresh()->status);

        $this->post(route('admin.agents.requests.approve', $r))->assertRedirect(route('admin.agents.index'))
            ->assertSessionHas('success', '已核准並執行：發布文章〈How to Report Progress?〉 EN 版');

        $this->assertSame('published', $this->post->fresh()->status);
        $this->assertSame(AiActionRequest::STATUS_APPROVED, $r->fresh()->status);
        $rev = $this->post->revisions()->latest('id')->first();
        $this->assertSame([ContentRevision::ACTOR_AI, 'Claude', 'status'], [$rev->actor, $rev->actor_name, $rev->kind]);
        $this->assertStringNotContainsString('等你確認', $this->get(route('admin.agents.index'))->getContent());
    }

    public function test_approving_a_queued_edit_applies_it(): void
    {
        AiPolicy::saveGlobal(['draft' => 'ask']);
        $this->ai()->tool(UpdateContent::class, ['type' => 'post', 'id' => $this->post->id, 'body' => '改好了']);
        $this->actingAs($this->admin);

        $this->post(route('admin.agents.requests.approve', AiActionRequest::firstOrFail()));

        $this->assertSame('改好了', $this->post->fresh()->body);
    }

    public function test_rejecting_leaves_the_content_as_it_was(): void
    {
        $r = $this->askToPublish();

        $this->post(route('admin.agents.requests.reject', $r))->assertRedirect()->assertSessionHas('success', '已拒絕，內容維持原狀');

        $this->assertSame('draft', $this->post->fresh()->status);
        $this->assertSame(AiActionRequest::STATUS_REJECTED, $r->fresh()->status);
        $this->assertStringContainsString(' · 已拒絕', $this->get(route('admin.agents.index'))->getContent());
    }

    public function test_hide_requests_say_so_on_the_button(): void
    {
        app(PostService::class)->updateStatus($this->post, 'published');
        $r = $this->askToPublish('hide');

        $this->assertMatchesRegularExpression('/>核准並隱藏<\/button>/', $this->get(route('admin.agents.requests.show', $r))->getContent());
        $this->post(route('admin.agents.requests.approve', $r));
        $this->assertSame('hidden', $this->post->fresh()->status);
    }

    public function test_a_request_can_only_be_decided_once(): void
    {
        $r = $this->askToPublish();
        $this->post(route('admin.agents.requests.reject', $r));

        $this->post(route('admin.agents.requests.approve', $r))->assertStatus(409);
        $this->assertSame('draft', $this->post->fresh()->status);
    }

    public function test_a_failed_run_is_reported_and_nothing_changes(): void
    {
        $r = $this->askToPublish();
        $this->post->forceDelete();

        $this->post(route('admin.agents.requests.approve', $r))->assertSessionHas('error');
        $this->assertSame(AiActionRequest::STATUS_FAILED, $r->fresh()->status);
    }

    public function test_only_the_owner_can_approve(): void
    {
        $r = $this->askToPublish();
        auth()->shouldUse('web');
        auth('web')->logout();

        $this->post(route('admin.agents.requests.approve', $r))->assertRedirect(route('auth.login.show'));
        $this->assertSame('draft', $this->post->fresh()->status);
    }
}
