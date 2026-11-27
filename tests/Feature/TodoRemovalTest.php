<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * JYU-559: the Todo feature is gone; the changelog only comes from CHANGELOG.md.
 */
class TodoRemovalTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_27_150100_drop_todos_table.php');
    }

    public function test_changelog_page_reads_only_changelog_md(): void
    {
        $path = storage_path('framework/testing/CHANGELOG.md');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "## 2026-11-20\n- Added a thing\n\n## 2026-11-27\n- Newest change\n");
        config(['changelog.path' => $path]);

        $html = $this->get('/changelog')->assertOk()->getContent();

        $this->assertStringContainsString('Newest change', $html);
        $this->assertStringContainsString('Added a thing', $html);
        $this->assertLessThan(strpos($html, 'Added a thing'), strpos($html, 'Newest change'), 'newest first');
        $this->assertFalse(Schema::hasTable('todos'));
    }

    public function test_admin_has_no_todo_page_or_sidebar_entry(): void
    {
        $admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/todos')->assertNotFound();
        $this->assertFalse(app('router')->has('admin.todos.index'));
        $this->assertStringNotContainsString('Todos', $this->get(route('admin.dashboard'))->getContent());
    }

    public function test_todo_api_endpoints_are_gone(): void
    {
        $this->getJson('/api/todos')->assertNotFound();
        $this->postJson('/api/todos', ['title' => 'x'])->assertNotFound();
    }

    public function test_existing_todos_are_exported_before_the_table_is_dropped(): void
    {
        Storage::fake('local');
        $migration = $this->migration();
        $migration->down();
        DB::table('todos')->insert([
            ['title' => '寫 changelog', 'description' => '舊資料', 'priority' => 'high', 'status' => 'done', 'show_in_changelog' => true, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['title' => '第二筆', 'description' => null, 'priority' => 'low', 'status' => 'open', 'show_in_changelog' => false, 'completed_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasTable('todos'));
        $files = Storage::disk('local')->files('exports');
        $this->assertCount(1, $files);
        $rows = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertSame(['寫 changelog', '第二筆'], array_column($rows, 'title'));
        $this->assertSame('舊資料', $rows[0]['description']);
    }

    public function test_an_empty_todos_table_is_dropped_without_an_export(): void
    {
        Storage::fake('local');
        $migration = $this->migration();
        $migration->down();

        $migration->up();

        $this->assertFalse(Schema::hasTable('todos'));
        $this->assertSame([], Storage::disk('local')->files('exports'));
    }
}
