<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that colored tags render as tinted chips (tag-chip class + --tag-color CSS var)
 * and uncolored tags keep their original static classes without tag-chip.
 *
 * Page under test: POST SHOW — /zh/posts/{slug}
 * Chosen because:
 *   - Tags are attached directly to the post model (easy to seed).
 *   - The page is accessible without pagination, categories, or sidebars.
 *   - show.blade.php iterates $post->tags and uses the $tag variable in scope.
 */
class TagColorDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function makePublishedPost(): Post
    {
        $group = PostGroup::create([]);

        return Post::create([
            'post_group_id'  => $group->id,
            'locale'         => 'zh',
            'slug'           => 'test-tag-color-post',
            'title'          => 'Tag Color Test Post',
            'body'           => 'Hello world.',
            'status'         => Post::STATUS_PUBLISHED,
            'published_at'   => now(),
            'last_modified_at' => now(),
        ]);
    }

    private function makeTag(string $name, string $slug, ?string $color = null): Tag
    {
        $tag = Tag::create(['color' => $color]);
        $tag->translations()->create(['locale' => 'zh', 'name' => $name, 'slug' => $slug]);

        return $tag;
    }

    public function test_colored_tag_renders_tag_chip_class_and_css_var(): void
    {
        $post = $this->makePublishedPost();
        $coloredTag = $this->makeTag('彩色標籤', 'colored-tag', '#8a5a6b');
        $post->tags()->attach($coloredTag->id);

        $this->get('/zh/posts/test-tag-color-post')
            ->assertOk()
            ->assertSee('tag-chip', false)
            ->assertSee('style="--tag-color: #8a5a6b"', false);
    }

    public function test_uncolored_tag_keeps_static_classes_without_tag_chip(): void
    {
        $post = $this->makePublishedPost();
        $uncoloredTag = $this->makeTag('無色標籤', 'uncolored-tag', null);
        $post->tags()->attach($uncoloredTag->id);

        $html = $this->get('/zh/posts/test-tag-color-post')
            ->assertOk()
            ->getContent();

        // Uncolored tag must have the original static background class
        $this->assertStringContainsString('bg-card', $html);
        // Uncolored tag must NOT receive the tag-chip class
        $this->assertStringNotContainsString('tag-chip', $html);
    }

    public function test_page_with_both_colored_and_uncolored_tags_renders_correctly(): void
    {
        $post = $this->makePublishedPost();
        $coloredTag   = $this->makeTag('彩色', 'colored-2', '#8a5a6b');
        $uncoloredTag = $this->makeTag('無色', 'uncolored-2', null);
        $post->tags()->attach([$coloredTag->id, $uncoloredTag->id]);

        $response = $this->get('/zh/posts/test-tag-color-post')->assertOk();
        $html = $response->getContent();

        // Colored tag: tinted chip
        $this->assertStringContainsString('tag-chip', $html);
        $this->assertStringContainsString('--tag-color: #8a5a6b', $html);

        // Uncolored tag: static bg-card present, tag-chip NOT the only class
        // We can verify bg-card appears (from uncolored branch)
        $this->assertStringContainsString('bg-card', $html);
    }
}
