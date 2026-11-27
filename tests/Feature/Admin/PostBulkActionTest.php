<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JYU-546: bulk actions on the admin post list.
 */
class PostBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'JYu', 'email' => 'a@b.c', 'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN]);
    }

    private function makePost(string $title, array $attrs = [], ?PostGroup $group = null): Post
    {
        return Post::create([
            'post_group_id' => ($group ?? PostGroup::create([]))->id, 'locale' => 'zh', 'slug' => uniqid('p'),
            'title' => $title, 'body' => 'x', 'status' => 'draft', 'last_modified_at' => now(), ...$attrs,
        ]);
    }

    private function bulk(array $data)
    {
        return $this->actingAs($this->admin)->from(route('admin.posts.index'))->post(route('admin.posts.bulk'), $data);
    }

    public function test_list_has_checkboxes_selected_count_toolbar_and_cancel(): void
    {
        $p = $this->makePost('A');

        $html = $this->actingAs($this->admin)->get(route('admin.posts.index'))->getContent();

        $this->assertMatchesRegularExpression('/<input type="checkbox" value="'.$p->id.'" x-model="selected"/', $html);
        $this->assertStringContainsString('aria-label="全選這一頁"', $html);
        $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('admin.posts.bulk'), '/').'" x-show="selected.length"[^>]*role="toolbar"/', $html);
        $this->assertStringContainsString("'已選 ' + selected.length + ' 篇'", $html);
        foreach (['publish' => '發布', 'hide' => '隱藏', 'tag' => '加上', 'translate_en' => '建立英文翻譯', 'trash' => '移到垃圾桶'] as $action => $label) {
            $this->assertMatchesRegularExpression('/name="action" value="'.$action.'"[^>]*>'.$label.'</u', $html, $action);
        }
        $this->assertMatchesRegularExpression('/<button type="button" @click="selected = \[\]"[^>]*>取消</u', $html);
    }

    public function test_trash_asks_for_confirmation(): void
    {
        $this->makePost('A');

        $html = $this->actingAs($this->admin)->get(route('admin.posts.index'))->getContent();

        $this->assertStringContainsString("\$event.submitter?.value === 'trash' && !confirm(", $html);
    }

    public function test_bulk_publish_and_hide(): void
    {
        $a = $this->makePost('A');
        $b = $this->makePost('B');
        $c = $this->makePost('C');

        $this->bulk(['action' => 'publish', 'ids' => [$a->id, $b->id]])->assertRedirect(route('admin.posts.index'))->assertSessionHas('success', '已發布 2 篇');
        $this->assertSame(['published', 'published', 'draft'], [$a->fresh()->status, $b->fresh()->status, $c->fresh()->status]);
        $this->assertNotNull($a->fresh()->published_at);

        $this->bulk(['action' => 'hide', 'ids' => [$a->id]])->assertSessionHas('success', '已隱藏 1 篇');
        $this->assertSame('hidden', $a->fresh()->status);
    }

    public function test_bulk_add_tag_keeps_existing_tags_and_applies_to_every_language(): void
    {
        $old = Tag::create([]);
        $new = Tag::create([]);
        $g = PostGroup::create([]);
        $zh = $this->makePost('A', [], $g);
        $en = $this->makePost('A-en', ['locale' => 'en'], $g);
        $zh->tags()->attach($old);
        $en->tags()->attach($old);

        $this->bulk(['action' => 'tag', 'ids' => [$zh->id], 'tag_id' => $new->id])->assertSessionHas('success');

        foreach ([$zh, $en] as $p) {
            $this->assertEqualsCanonicalizing([$old->id, $new->id], $p->fresh()->tags->pluck('id')->all());
        }
    }

    public function test_tag_action_needs_a_tag(): void
    {
        $this->bulk(['action' => 'tag', 'ids' => [$this->makePost('A')->id]])->assertSessionHasErrors('tag_id');
    }

    public function test_bulk_create_english_translation_skips_articles_that_have_one(): void
    {
        $a = $this->makePost('沒有英文');
        $g = PostGroup::create([]);
        $b = $this->makePost('已有英文', [], $g);
        $this->makePost('Has English', ['locale' => 'en'], $g);

        $this->bulk(['action' => 'translate_en', 'ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', '已建立 1 篇英文翻譯草稿（1 篇已有英文版，略過）');

        $this->assertSame('draft', Post::where('post_group_id', $a->post_group_id)->where('locale', 'en')->value('status'));
        $this->assertSame(1, Post::where('post_group_id', $g->id)->where('locale', 'en')->count());
    }

    public function test_bulk_trash_moves_every_language_version_and_can_be_restored(): void
    {
        $g = PostGroup::create([]);
        $zh = $this->makePost('A', [], $g);
        $en = $this->makePost('A-en', ['locale' => 'en'], $g);
        $keep = $this->makePost('B');

        $this->bulk(['action' => 'trash', 'ids' => [$zh->id]])->assertSessionHas('success', '已將 1 篇文章（共 2 個語言版本）移到垃圾桶');

        $this->assertSoftDeleted($zh);
        $this->assertSoftDeleted($en);
        $this->assertNull($keep->fresh()->deleted_at);

        $trash = $this->actingAs($this->admin)->get(route('admin.posts.index', ['status' => 'trashed']))->getContent();
        $this->assertStringContainsString(route('admin.posts.restore', $zh->id), $trash);
    }

    public function test_row_menu_trash_also_moves_the_whole_article(): void
    {
        $p = $this->makePost('A');

        $html = $this->actingAs($this->admin)->get(route('admin.posts.index'))->getContent();

        $this->assertMatchesRegularExpression('/<form method="POST" action="'.preg_quote(route('admin.posts.bulk'), '/').'" onsubmit="return confirm\([\s\S]*?name="action" value="trash"[\s\S]*?name="ids\[\]" value="'.$p->id.'"/u', $html);
    }

    public function test_invalid_requests_are_rejected(): void
    {
        $this->bulk(['action' => 'explode', 'ids' => [$this->makePost('A')->id]])->assertSessionHasErrors('action');
        $this->bulk(['action' => 'publish', 'ids' => []])->assertSessionHasErrors('ids');
        $this->bulk(['action' => 'publish', 'ids' => [999999]])->assertSessionHasErrors('ids.0');
    }

    public function test_guests_cannot_bulk(): void
    {
        $this->post(route('admin.posts.bulk'), ['action' => 'publish', 'ids' => [$this->makePost('A')->id]])->assertRedirect(route('auth.login.show'));
    }
}
