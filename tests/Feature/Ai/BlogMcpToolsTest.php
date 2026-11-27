<?php

namespace Tests\Feature\Ai;

use App\Mcp\Servers\BlogServer;
use App\Mcp\Tools\CreateDraft;
use App\Mcp\Tools\CreateTranslation;
use App\Mcp\Tools\GetContent;
use App\Mcp\Tools\ListTranslationGaps;
use App\Mcp\Tools\ManageTaxonomy;
use App\Mcp\Tools\RequestPublish;
use App\Mcp\Tools\SearchContent;
use App\Mcp\Tools\UpdateContent;
use App\Mcp\Tools\UploadMedia;
use App\Models\AiActionRequest;
use App\Models\AiConnection;
use App\Models\AiUser;
use App\Models\ContentRevision;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\PostService;
use App\Services\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * JYU-554: what a connected AI can and cannot do.
 */
class BlogMcpToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
    }

    /** Act as a connected AI with the given permissions (what EnsureAiConnection sets up per request). */
    private function as(array $permissions = ['read', 'draft', 'organize', 'publish'])
    {
        $client = Client::query()->forceCreate([
            'name' => 'Claude', 'redirect_uris' => ['https://claude.ai/cb'], 'grant_types' => ['authorization_code'], 'revoked' => false,
        ]);
        $connection = AiConnection::create([
            'user_id' => $this->admin->id, 'oauth_client_id' => $client->id, 'name' => 'Claude',
            'permissions' => $permissions, 'expires_at' => now()->addDays(30),
        ]);
        app()->instance(AiConnection::class, $connection);
        app(RevisionService::class)->actAsAi('Claude', $this->admin->id);

        return BlogServer::actingAs(AiUser::find($this->admin->id), 'api');
    }

    private function article(array $attrs = []): Post
    {
        $this->actingAs($this->admin);

        return app(PostService::class)->create($attrs + ['locale' => 'zh', 'title' => '監控導入', 'slug' => uniqid('p'), 'body' => '關於監控的內容', 'status' => 'published']);
    }

    public function test_search_and_read_include_drafts(): void
    {
        $draft = $this->article(['title' => '監控草稿', 'status' => 'draft']);
        $server = $this->as(['read']);

        $server->tool(SearchContent::class, ['query' => '監控'])
            ->assertOk()->assertSee(['監控草稿', '"status":"draft"']);
        $server->tool(GetContent::class, ['type' => 'post', 'id' => $draft->id])
            ->assertOk()->assertSee(['關於監控的內容', '"translations":[]']);
    }

    public function test_list_translation_gaps(): void
    {
        $this->article(['title' => '只有中文']);

        $this->as(['read'])->tool(ListTranslationGaps::class, ['locale' => 'en'])
            ->assertOk()->assertSee(['"missing"', '只有中文']);
    }

    public function test_create_draft_is_always_a_draft_and_recorded_as_the_ai(): void
    {
        $this->as(['read', 'draft'])->tool(CreateDraft::class, [
            'type' => 'post', 'locale' => 'zh', 'title' => 'AI 寫的', 'body' => '# 內容',
        ])->assertOk()->assertSee('"status":"draft"');

        $post = Post::firstWhere('title', 'AI 寫的');
        $this->assertSame('draft', $post->status);
        $revision = $post->revisions()->first();
        $this->assertSame([ContentRevision::ACTOR_AI, 'Claude'], [$revision->actor, $revision->actor_name]);
    }

    public function test_update_content_edits_text_but_never_status(): void
    {
        $post = $this->article(['status' => 'draft']);

        $this->as(['draft'])->tool(UpdateContent::class, ['type' => 'post', 'id' => $post->id, 'body' => '改好了', 'status' => 'published'])->assertOk();

        $post->refresh();
        $this->assertSame(['改好了', 'draft'], [$post->body, $post->status]);
    }

    public function test_create_translation_makes_a_draft_in_another_language(): void
    {
        $post = $this->article();

        $this->as(['draft'])->tool(CreateTranslation::class, [
            'type' => 'post', 'id' => $post->id, 'locale' => 'ja', 'title' => '監視の導入', 'body' => '監視について',
        ])->assertOk()->assertSee('"locale":"ja"');

        $ja = Post::where('post_group_id', $post->post_group_id)->where('locale', 'ja')->firstOrFail();
        $this->assertSame(['監視の導入', '監視について', 'draft'], [$ja->title, $ja->body, $ja->status]);
        $this->assertSame('關於監控的內容', $post->fresh()->body, 'the original is untouched');
    }

    public function test_manage_taxonomy_creates_tags_and_categories_and_sets_tags(): void
    {
        $post = $this->article();
        $server = $this->as(['organize']);

        $server->tool(ManageTaxonomy::class, ['action' => 'create_tag', 'name' => '可觀測性'])->assertOk();
        $server->tool(ManageTaxonomy::class, ['action' => 'create_category', 'name' => '維運筆記'])->assertOk();
        $server->tool(ManageTaxonomy::class, ['action' => 'set_tags', 'type' => 'post', 'id' => $post->id, 'tags' => ['可觀測性']])
            ->assertOk()->assertSee('可觀測性');

        $this->assertSame(1, Tag::count());
        $this->assertSame(1, $post->tags()->count());
        $this->assertDatabaseHas('category_translations', ['name' => '維運筆記']);
    }

    public function test_upload_media_stores_the_file_and_returns_markdown(): void
    {
        Storage::fake(config('media.disk'));
        $png = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        $this->as(['organize'])->tool(UploadMedia::class, ['filename' => 'dot.png', 'data' => $png, 'alt' => '點'])
            ->assertOk()->assertSee('![點](');

        $this->assertDatabaseHas('media', ['original_filename' => 'dot.png', 'uploaded_by' => $this->admin->id]);
        $this->as(['organize'])->tool(UploadMedia::class, ['filename' => 'x.exe', 'data' => base64_encode('MZ')])->assertHasErrors();
    }

    public function test_request_publish_only_queues_a_request(): void
    {
        $post = $this->article(['status' => 'draft']);

        $server = $this->as(['publish']);
        $server->tool(RequestPublish::class, ['type' => 'post', 'id' => $post->id, 'action' => 'publish', 'note' => '翻好了'])
            ->assertOk()->assertSee('作者在後台核准後才會執行');

        $this->assertSame('draft', $post->fresh()->status);
        $this->assertDatabaseHas('ai_action_requests', ['publishable_id' => $post->id, 'action' => 'publish', 'status' => 'pending', 'note' => '翻好了']);
        $server->tool(RequestPublish::class, ['type' => 'post', 'id' => $post->id, 'action' => 'publish', 'note' => '翻好了']);
        $this->assertSame(1, AiActionRequest::count(), 'asking twice keeps one pending request');
    }

    public function test_tools_outside_the_granted_permissions_are_refused(): void
    {
        $post = $this->article(['status' => 'draft']);
        $server = $this->as(['read']);

        // Tools outside the grant aren't registered for this connection, so calling one fails.
        $server->tool(UpdateContent::class, ['type' => 'post', 'id' => $post->id, 'body' => 'x'])->assertHasErrors();
        $server->tool(RequestPublish::class, ['type' => 'post', 'id' => $post->id, 'action' => 'publish'])->assertHasErrors();
        $this->assertSame('關於監控的內容', $post->fresh()->body);
    }

    public function test_there_is_no_way_to_delete_content(): void
    {
        $names = collect((new \ReflectionClass(BlogServer::class))->getDefaultProperties()['tools'])
            ->map(fn ($class) => app($class)->name());

        $this->assertCount(9, $names);
        $this->assertEmpty($names->filter(fn ($n) => str_contains($n, 'delete') || str_contains($n, 'trash')));
    }
}
