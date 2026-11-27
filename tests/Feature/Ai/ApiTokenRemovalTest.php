<?php

namespace Tests\Feature\Ai;

use App\Mcp\Servers\BlogServer;
use App\Mcp\Tools\ListMedia;
use App\Mcp\Tools\ListTaxonomy;
use App\Mcp\Tools\ManageTaxonomy;
use App\Mcp\Tools\SearchContent;
use App\Models\AiConnection;
use App\Models\AiUser;
use App\Models\Category;
use App\Models\Media;
use App\Models\Tag;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\PostService;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * JYU-557: the old API tokens are gone; AI connections cover what they did.
 */
class ApiTokenRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
    }

    private function ai()
    {
        $client = Client::query()->forceCreate(['name' => 'Claude', 'redirect_uris' => ['https://claude.ai/cb'], 'grant_types' => ['authorization_code'], 'revoked' => false]);
        app()->instance(AiConnection::class, AiConnection::create([
            'user_id' => $this->admin->id, 'oauth_client_id' => $client->id, 'name' => 'Claude',
            'permissions' => ['read', 'draft', 'organize', 'publish'], 'expires_at' => now()->addDays(30),
        ]));

        return BlogServer::actingAs(AiUser::find($this->admin->id), 'api');
    }

    public function test_the_old_api_and_token_pages_are_gone(): void
    {
        foreach (['/api/me', '/api/posts', '/api/tweets', '/api/tags', '/api/categories', '/api/media', '/api/todos'] as $url) {
            $this->getJson($url, ['Authorization' => 'Bearer 1|anything'])->assertNotFound();
        }
        $this->postJson('/api/posts', ['title' => 'x'])->assertNotFound();

        $this->actingAs($this->admin)->get('/admin/tokens')->assertNotFound();
        $this->assertFalse(app('router')->has('admin.tokens.index'));
        $this->assertStringNotContainsString('API Tokens', $this->get(route('admin.dashboard'))->getContent());
        $this->assertFalse(Schema::hasTable('personal_access_tokens'), 'every old token is invalid: the table is gone');
    }

    public function test_listing_content_without_a_keyword_like_the_old_index_endpoints(): void
    {
        $this->actingAs($this->admin);
        app(PostService::class)->create(['locale' => 'zh', 'title' => '草稿一篇', 'slug' => 'd', 'body' => 'x', 'status' => 'draft']);
        app(PostService::class)->create(['locale' => 'zh', 'title' => '已發布一篇', 'slug' => 'p', 'body' => 'x', 'status' => 'published']);

        $this->ai()->tool(SearchContent::class, ['type' => 'post', 'status' => 'draft'])
            ->assertOk()->assertSee('草稿一篇')->assertDontSee('已發布一篇');
    }

    public function test_listing_and_renaming_tags_and_categories(): void
    {
        $tag = app(TagService::class)->create(['translations' => [['locale' => 'zh', 'name' => '監控'], ['locale' => 'en', 'name' => 'Monitoring']]]);
        $category = app(CategoryService::class)->create(['translations' => [['locale' => 'zh', 'name' => '維運', 'description' => '舊說明']]]);
        $server = $this->ai();

        $server->tool(ListTaxonomy::class)->assertOk()->assertSee(['監控', 'Monitoring', '維運']);

        $server->tool(ManageTaxonomy::class, ['action' => 'update_tag', 'tag_id' => $tag->id, 'locale' => 'zh', 'name' => '可觀測性'])->assertOk();
        $this->assertSame(['en' => 'Monitoring', 'zh' => '可觀測性'], Tag::find($tag->id)->translations()->orderBy('locale')->pluck('name', 'locale')->all(), 'other languages are kept');

        $server->tool(ManageTaxonomy::class, ['action' => 'update_category', 'category_id' => $category->id, 'locale' => 'zh', 'description' => '新說明'])->assertOk();
        $translation = Category::find($category->id)->translations()->first();
        $this->assertSame(['維運', '新說明'], [$translation->name, $translation->description]);
    }

    public function test_listing_media(): void
    {
        Media::create(['path' => 'uploads/2026/11/a.png', 'mime_type' => 'image/png', 'size' => 10, 'original_filename' => 'a.png']);

        $this->ai()->tool(ListMedia::class)->assertOk()->assertSee(['a.png', 'image/png']);
    }

    public function test_the_ai_has_a_tool_for_every_old_api_operation_except_deleting(): void
    {
        $names = collect((new \ReflectionClass(BlogServer::class))->getDefaultProperties()['tools'])
            ->map(fn ($class) => app($class)->name())->sort()->values()->all();

        $this->assertSame([
            'create_draft', 'create_translation', 'get_content', 'list_media', 'list_taxonomy', 'list_translation_gaps',
            'manage_taxonomy', 'request_publish', 'search_content', 'update_content', 'upload_media',
        ], $names);
    }

    public function test_dropping_the_tokens_table_keeps_a_record_without_the_secrets(): void
    {
        Storage::fake('local');
        $migration = require database_path('migrations/2026_11_27_150000_drop_personal_access_tokens_table.php');
        $migration->down();
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => User::class, 'tokenable_id' => $this->admin->id, 'name' => 'old-script',
            'token' => hash('sha256', 'secret'), 'abilities' => '["posts:read"]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasTable('personal_access_tokens'));
        $files = Storage::disk('local')->files('exports');
        $this->assertCount(1, $files);
        $export = Storage::disk('local')->get($files[0]);
        $this->assertStringContainsString('old-script', $export);
        $this->assertStringNotContainsString(hash('sha256', 'secret'), $export);
    }
}
