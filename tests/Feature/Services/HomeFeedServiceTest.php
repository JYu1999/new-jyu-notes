<?php

namespace Tests\Feature\Services;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Services\HomeFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomeFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $slug, string $at, string $locale = 'zh', string $status = Post::STATUS_PUBLISHED): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create([])->id, 'locale' => $locale, 'slug' => $slug,
            'title' => $slug, 'body' => 'b', 'status' => $status,
            'published_at' => Carbon::parse($at), 'last_modified_at' => Carbon::parse($at),
        ]);
    }

    private function makeTweet(string $body, string $at, string $locale = 'zh', string $status = Tweet::STATUS_PUBLISHED): Tweet
    {
        return Tweet::create([
            'tweet_group_id' => TweetGroup::create([])->id, 'locale' => $locale, 'body' => $body,
            'status' => $status, 'published_at' => Carbon::parse($at),
        ]);
    }

    private function keys(array $page): array
    {
        return $page['items']->map(fn ($i) => $i['type'].':'.($i['type'] === 'post' ? $i['model']->slug : $i['model']->body))->all();
    }

    public function test_mixes_posts_and_tweets_newest_first(): void
    {
        $this->makePost('p1', '2026-03-01 10:00');
        $this->makeTweet('t1', '2026-03-02 10:00');
        $this->makePost('p2', '2026-03-03 10:00');

        $page = app(HomeFeedService::class)->latest('zh', 'all', null, 10);

        $this->assertSame(['post:p2', 'tweet:t1', 'post:p1'], $this->keys($page));
        $this->assertNull($page['next']);
    }

    public function test_filters_by_type(): void
    {
        $this->makePost('p1', '2026-03-01');
        $this->makeTweet('t1', '2026-03-02');

        $this->assertSame(['post:p1'], $this->keys(app(HomeFeedService::class)->latest('zh', 'post', null, 10)));
        $this->assertSame(['tweet:t1'], $this->keys(app(HomeFeedService::class)->latest('zh', 'tweet', null, 10)));
    }

    public function test_excludes_unpublished_and_other_locales(): void
    {
        $this->makePost('draft', '2026-03-01', status: Post::STATUS_DRAFT);
        $this->makePost('english', '2026-03-01', locale: 'en');
        $this->makeTweet('hidden', '2026-03-01', status: Tweet::STATUS_HIDDEN);
        $this->makePost('ok', '2026-03-01');

        $this->assertSame(['post:ok'], $this->keys(app(HomeFeedService::class)->latest('zh', 'all', null, 10)));
    }

    public function test_paginates_with_cursor_without_gaps_or_duplicates(): void
    {
        // Same timestamps on purpose: the cursor must break ties deterministically.
        foreach (range(1, 4) as $i) {
            $this->makePost("p{$i}", '2026-03-01 12:00');
            $this->makeTweet("t{$i}", '2026-03-01 12:00');
        }
        $this->makePost('old', '2026-01-01');

        $service = app(HomeFeedService::class);
        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $page = $service->latest('zh', 'all', $cursor, 3);
            $seen = [...$seen, ...$this->keys($page)];
            $cursor = $page['next'];
            $pages++;
        } while ($cursor !== null && $pages < 10);

        $this->assertCount(9, $seen);
        $this->assertSame(array_unique($seen), $seen, 'An item appeared twice');
        $this->assertSame('post:old', end($seen));
        $this->assertSame(3, $pages);
    }

    public function test_next_cursor_is_null_on_last_page(): void
    {
        $this->makePost('p1', '2026-03-01');
        $this->makePost('p2', '2026-03-02');

        $page = app(HomeFeedService::class)->latest('zh', 'all', null, 2);

        $this->assertCount(2, $page['items']);
        $this->assertNull($page['next']);
    }

    public function test_invalid_cursor_starts_from_the_top(): void
    {
        $this->makePost('p1', '2026-03-01');

        $this->assertSame(['post:p1'], $this->keys(app(HomeFeedService::class)->latest('zh', 'all', 'garbage', 10)));
    }
}
