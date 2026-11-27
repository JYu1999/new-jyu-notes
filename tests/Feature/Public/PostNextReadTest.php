<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JYU-536: after finishing a post, readers find what to read next.
 */
class PostNextReadTest extends TestCase
{
    use RefreshDatabase;

    private function publish(string $slug, string $title, string $at, array $extra = []): Post
    {
        $post = app(PostService::class)->create([
            'locale' => 'zh', 'slug' => $slug, 'title' => $title, 'body' => $extra['body'] ?? '內文',
            'status' => $extra['status'] ?? Post::STATUS_PUBLISHED, 'tag_ids' => $extra['tag_ids'] ?? [],
        ]);
        $post->forceFill(['published_at' => Carbon::parse($at)])->save();

        return $post;
    }

    private function tag(string $name, string $slug): Tag
    {
        $tag = Tag::create([]);
        $tag->translations()->create(['locale' => 'zh', 'name' => $name, 'slug' => $slug]);

        return $tag;
    }

    private function section(string $html, string $labelledBy): string
    {
        $this->assertMatchesRegularExpression('/<section[^>]*aria-labelledby="'.$labelledBy.'"/', $html, "Section {$labelledBy} missing");
        preg_match('/<section[^>]*aria-labelledby="'.$labelledBy.'"[^>]*>(.*?)<\/section>/s', $html, $m);

        return $m[1];
    }

    public function test_backlinks_show_type_date_and_title_or_excerpt_as_cards(): void
    {
        $target = $this->publish('target', '被引用的文章', '2025-12-20');
        $this->publish('citing', 'MCP 二次烙賽體驗', '2026-01-02', ['body' => '見 [這篇](/zh/posts/target)']);
        $tweet = app(TweetService::class)->create(['locale' => 'zh', 'body' => '留下麵包屑：讓 AI 和未來的自己都找得到線索 [文](/zh/posts/target)', 'status' => 'published']);
        $tweet->forceFill(['published_at' => Carbon::parse('2026-01-03')])->save();

        $section = $this->section($this->get('/zh/posts/target')->getContent(), 'backlinks-h');

        $this->assertStringContainsString('被提及於', $this->get('/zh/posts/target')->getContent());
        $this->assertStringContainsString('文章 · 2026/01/02', $section);
        $this->assertStringContainsString('MCP 二次烙賽體驗', $section);
        $this->assertStringContainsString('碎念 · 2026/01/03', $section);
        $this->assertStringContainsString('留下麵包屑', $section);
        $this->assertSame(2, preg_match_all('/<a [^>]*class="ui-card /', $section));
    }

    public function test_previous_and_next_follow_publish_date_when_not_in_a_series(): void
    {
        $this->publish('older', '比較舊', '2025-01-01');
        $this->publish('middle', '中間', '2025-06-01');
        $this->publish('newer', '比較新', '2025-12-01');

        $html = $this->get('/zh/posts/middle')->getContent();
        preg_match('/<nav[^>]*aria-label="上一篇 \/ 下一篇"[^>]*>(.*?)<\/nav>/su', $html, $m);

        $this->assertNotEmpty($m);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(url('/zh/posts/older'), '/').'"[\s\S]*上一篇[\s\S]*比較舊/u', $m[1]);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(url('/zh/posts/newer'), '/').'"[\s\S]*下一篇[\s\S]*比較新/u', $m[1]);
    }

    public function test_series_order_wins_over_publish_date(): void
    {
        $a = $this->publish('a', '系列一', '2025-03-01');
        $b = $this->publish('b', '系列二', '2025-01-01');
        $this->publish('outside', '系列外但時間相近', '2025-02-01');
        $cat = Category::create(['sort_method' => Category::SORT_MANUAL]);
        $cat->translations()->create(['locale' => 'zh', 'name' => '系列', 'slug' => 's']);
        $a->categories()->attach($cat, ['order_in_category' => 1]);
        $b->categories()->attach($cat, ['order_in_category' => 2]);

        $html = $this->get('/zh/posts/a')->getContent();
        preg_match('/<nav[^>]*aria-label="上一篇 \/ 下一篇"[^>]*>(.*?)<\/nav>/su', $html, $m);

        $this->assertStringContainsString(url('/zh/posts/b'), $m[1]);
        $this->assertStringNotContainsString(url('/zh/posts/outside'), $m[1]);
    }

    public function test_related_reading_lists_up_to_three_published_posts_sharing_tag_or_category(): void
    {
        $ai = $this->tag('人工智慧', 'ai');
        $life = $this->tag('生活', 'life');
        $this->publish('me', '本文', '2026-01-10', ['tag_ids' => [$ai->id]]);
        $this->publish('r1', '相關一', '2026-01-09', ['tag_ids' => [$ai->id]]);
        $this->publish('r2', '相關二', '2026-01-08', ['tag_ids' => [$ai->id]]);
        $this->publish('r3', '相關三', '2026-01-07', ['tag_ids' => [$ai->id]]);
        $this->publish('r4', '相關四', '2026-01-06', ['tag_ids' => [$ai->id]]);
        $this->publish('draft', '相關草稿', '2026-01-09', ['tag_ids' => [$ai->id], 'status' => Post::STATUS_DRAFT]);
        $this->publish('other', '無關', '2026-01-09', ['tag_ids' => [$life->id]]);

        $section = $this->section($this->get('/zh/posts/me')->getContent(), 'related-h');

        $this->assertSame(3, substr_count($section, '<a '));
        foreach (['相關一', '相關二', '相關三'] as $t) {
            $this->assertStringContainsString($t, $section);
        }
        foreach (['本文', '相關四', '相關草稿', '無關'] as $t) {
            $this->assertStringNotContainsString('>'.$t.'<', $section);
        }
    }

    public function test_related_prefers_posts_sharing_more_topics(): void
    {
        $ai = $this->tag('人工智慧', 'ai');
        $tech = $this->tag('技術', 'tech');
        $this->publish('me', '本文', '2026-01-10', ['tag_ids' => [$ai->id, $tech->id]]);
        $this->publish('one', '只有一個相同', '2026-01-09', ['tag_ids' => [$ai->id]]);
        $this->publish('two', '兩個都相同', '2025-01-01', ['tag_ids' => [$ai->id, $tech->id]]);

        $section = $this->section($this->get('/zh/posts/me')->getContent(), 'related-h');

        $this->assertLessThan(strpos($section, '只有一個相同'), strpos($section, '兩個都相同'));
    }

    public function test_no_related_section_without_shared_topics(): void
    {
        $this->publish('lonely', '孤單的文章', '2026-01-10');

        $this->assertStringNotContainsString('related-h', $this->get('/zh/posts/lonely')->getContent());
    }

    public function test_sidebar_lists_cited_content_and_backlink_count_linking_to_backlinks(): void
    {
        $this->publish('why', '為什麼有這個部落格', '2025-01-01');
        $this->publish('draft-target', '草稿目標', '2025-01-01', ['status' => Post::STATUS_DRAFT]);
        $this->publish('me', '本文', '2025-06-01', ['body' => '[a](/zh/posts/why) [b](/zh/posts/draft-target)']);
        $this->publish('fan1', '引用一', '2025-07-01', ['body' => '[x](/zh/posts/me)']);
        $this->publish('fan2', '引用二', '2025-08-01', ['body' => '[x](/zh/posts/me)']);

        $html = $this->get('/zh/posts/me')->getContent();
        preg_match('/<section[^>]*data-references[^>]*>(.*?)<\/section>/s', $html, $box);

        $this->assertNotEmpty($box, 'References box missing');
        $this->assertStringContainsString('本文引用', $box[1]);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(url('/zh/posts/why'), '/').'"[^>]*>→ 為什麼有這個部落格/u', $box[1]);
        $this->assertStringNotContainsString('草稿目標', $box[1]);
        $this->assertStringContainsString('被 2 篇提及', $box[1]);
        $this->assertStringContainsString('href="#backlinks"', $box[1]);
        $this->assertMatchesRegularExpression('/<section[^>]*id="backlinks"/', $html);
    }

    public function test_no_references_box_for_unconnected_post(): void
    {
        $this->publish('solo', '獨立文章', '2025-06-01');

        $this->assertStringNotContainsString('data-references', $this->get('/zh/posts/solo')->getContent());
    }
}
