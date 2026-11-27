<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JYU-552: type "/" in the editor to insert blocks without leaving the keyboard.
 *
 * The menu runs in the browser; these tests pin the editor wiring and the command
 * list (resources/js/slash-commands.js). Behaviour was checked in the browser.
 */
class SlashCommandTest extends TestCase
{
    use RefreshDatabase;

    private function editorHtml(): string
    {
        $admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);

        return $this->actingAs($admin)->get(route('admin.posts.create'))->assertOk()->getContent();
    }

    public function test_editor_hints_at_the_slash_menu(): void
    {
        $this->assertStringContainsString('輸入 / 叫出所有指令', $this->editorHtml());
    }

    public function test_typing_slash_opens_a_keyboard_driven_menu(): void
    {
        $html = $this->editorHtml();

        $this->assertStringContainsString('@input="dismissYtPrompt(); detectMention(); detectSlash()"', $html);
        $this->assertStringContainsString('@keydown="onSlashKeydown($event) || onMentionKeydown($event)"', $html);
        $this->assertMatchesRegularExpression('/x-show="slashActive"[^>]*data-slash-menu role="listbox" aria-label="指令"/', $html);
        $this->assertStringContainsString('↑↓ 選擇　Enter 插入　Esc 關閉', $html);
        $this->assertStringContainsString('x-for="(cmd, i) in slashResults"', $html);
        $this->assertStringContainsString(':aria-selected="i === slashIndex ? \'true\' : \'false\'"', $html);
        $this->assertStringContainsString('沒有符合的指令', $html);
    }

    public function test_every_required_command_is_available(): void
    {
        $js = file_get_contents(resource_path('js/slash-commands.js'));

        foreach (['h2' => '標題 H2', 'h3' => '標題 H3', 'quote' => '引用區塊', 'code' => '程式碼區塊', 'hr' => '分隔線',
            'image' => '圖片或影片', 'youtube' => 'YouTube 嵌入', 'mention' => '引用文章或碎念'] as $id => $label) {
            $this->assertMatchesRegularExpression("/id: '{$id}', label: '{$label}'/", $js);
        }
    }

    public function test_filtering_by_cheng_shi_leaves_only_the_code_block(): void
    {
        $js = file_get_contents(resource_path('js/slash-commands.js'));
        preg_match_all("/\{ id: '(\w+)'.*\}/", $js, $rows, PREG_SET_ORDER);

        $matches = array_values(array_filter($rows, fn ($r) => str_contains($r[0], '程式')));

        $this->assertCount(8, $rows);
        $this->assertSame(['code'], array_column($matches, 1));
    }

    public function test_the_menu_wiring_is_in_the_bundle(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('...slashCommandBehavior,', $js);
        $this->assertStringContainsString("import { applyCommand, filterCommands, slashQueryAt } from './slash-commands';", $js);
    }
}
