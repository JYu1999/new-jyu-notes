<?php

namespace Tests\Feature\Public;

use App\Jobs\FetchLinkPreviews;
use App\Models\LinkPreview;
use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Services\LinkPreviewService;
use App\Services\TweetService;
use App\Support\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * JYU-540: external links in tweets render as preview cards.
 */
class LinkPreviewTest extends TestCase
{
    use RefreshDatabase;

    /** Pretend DNS: these hosts are public, anything else private. */
    private function resolveAs(array $map): void
    {
        $this->app->instance(HostResolver::class, new class($map) extends HostResolver
        {
            public function __construct(private array $map) {}

            public function resolve(string $host): array
            {
                return $this->map[$host] ?? [];
            }
        });
    }

    private function service(): LinkPreviewService
    {
        return app(LinkPreviewService::class);
    }

    private function makeTweet(string $body): Tweet
    {
        return Tweet::create([
            'tweet_group_id' => TweetGroup::create([])->id, 'locale' => 'zh', 'body' => $body,
            'status' => Tweet::STATUS_PUBLISHED, 'published_at' => now(),
        ]);
    }

    // ── extracting links ───────────────────────────────────────────────

    public function test_finds_external_links_but_not_own_site_or_embeds(): void
    {
        config(['app.url' => 'https://jyu1999.com']);

        $urls = $this->service()->externalUrls(
            "看 [新聞](https://blocktempo.com/news/1)。\n也見 https://jyu1999.com/zh/posts/a 與 https://example.org/x.\n{{< youtube id=\"abc\" >}} https://www.youtube.com/watch?v=abc https://blocktempo.com/news/1"
        );

        $this->assertSame(['https://blocktempo.com/news/1', 'https://example.org/x'], $urls);
    }

    // ── fetching ───────────────────────────────────────────────────────

    public function test_fetch_prefers_og_title_and_decodes_entities(): void
    {
        $this->resolveAs(['blocktempo.com' => ['104.21.1.1']]);
        Http::fake(['blocktempo.com/*' => Http::response(
            '<html><head><title>Fallback</title><meta property="og:title" content="OnlyFans 老闆 &amp; 創辦人"></head></html>',
            200, ['Content-Type' => 'text/html; charset=utf-8'],
        )]);

        $preview = $this->service()->fetch('https://blocktempo.com/news/1');

        $this->assertSame('ok', $preview->status);
        $this->assertSame('OnlyFans 老闆 & 創辦人', $preview->title);
        $this->assertSame('blocktempo.com', $preview->domain);
    }

    public function test_fetch_falls_back_to_title_tag(): void
    {
        $this->resolveAs(['www.example.org' => ['93.184.216.34']]);
        Http::fake(['www.example.org/*' => Http::response("<title>\n  Example  Domain\n</title>", 200, ['Content-Type' => 'text/html'])]);

        $preview = $this->service()->fetch('https://www.example.org/page');

        $this->assertSame('Example Domain', $preview->title);
        $this->assertSame('example.org', $preview->domain);
    }

    public function test_failed_fetch_is_recorded_as_failed(): void
    {
        $this->resolveAs(['down.example' => ['93.184.216.34']]);
        Http::fake(['down.example/*' => Http::response('', 500)]);

        $this->assertSame('failed', $this->service()->fetch('https://down.example/x')->status);
    }

    public function test_non_html_responses_are_not_previewed(): void
    {
        $this->resolveAs(['files.example' => ['93.184.216.34']]);
        Http::fake(['files.example/*' => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf'])]);

        $this->assertSame('failed', $this->service()->fetch('https://files.example/a.pdf')->status);
    }

    public function test_private_and_local_addresses_are_never_requested(): void
    {
        $this->resolveAs([
            'internal.example' => ['10.0.0.5'],
            'mixed.example' => ['93.184.216.34', '127.0.0.1'],
        ]);
        Http::fake();

        foreach ([
            'http://localhost/admin',
            'http://127.0.0.1:8080/',
            'http://169.254.169.254/latest/meta-data/',
            'https://internal.example/',
            'https://mixed.example/',
            'https://unknown.example/',
            'ftp://files.example/x',
            'https://user:pass@public.example/',
        ] as $url) {
            $this->assertFalse($this->service()->isAllowed($url), $url);
            $this->assertSame('failed', $this->service()->fetch($url)->status, $url);
        }

        Http::assertNothingSent();
    }

    // ── when it runs ───────────────────────────────────────────────────

    public function test_saving_a_tweet_fetches_previews_after_the_response(): void
    {
        Bus::fake();

        $tweet = app(TweetService::class)->create(['locale' => 'zh', 'body' => 'https://example.org', 'status' => 'published']);

        Bus::assertDispatchedAfterResponse(FetchLinkPreviews::class, fn ($job) => $job->tweetId === $tweet->id);
    }

    public function test_backfill_command_fetches_links_in_published_tweets(): void
    {
        $this->resolveAs(['example.org' => ['93.184.216.34']]);
        Http::fake(['example.org/*' => Http::response('<title>Backfilled</title>', 200, ['Content-Type' => 'text/html'])]);
        $this->makeTweet('舊碎念 https://example.org/old');

        $this->artisan('link-previews:fetch')->assertSuccessful();

        $this->assertSame('Backfilled', LinkPreview::where('url', 'https://example.org/old')->value('title'));
    }

    // ── rendering ──────────────────────────────────────────────────────

    public function test_tweet_with_preview_shows_card_with_domain_and_title_opening_original_page(): void
    {
        $tweet = $this->makeTweet('剛好看到這個新聞 https://blocktempo.com/news/1');
        LinkPreview::create([
            'url_hash' => LinkPreview::hashFor('https://blocktempo.com/news/1'), 'url' => 'https://blocktempo.com/news/1',
            'domain' => 'blocktempo.com', 'title' => 'OnlyFans 老闆癌症病逝', 'status' => 'ok', 'fetched_at' => now(),
        ]);

        foreach (['/zh/tweets', "/zh/tweets/{$tweet->id}", '/zh'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match('/<a [^>]*data-link-preview[^>]*>.*?<\/a>/s', $html, $card);

            $this->assertNotEmpty($card, "{$url}: card missing");
            $this->assertStringContainsString('href="https://blocktempo.com/news/1"', $card[0]);
            $this->assertStringContainsString('target="_blank"', $card[0]);
            $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $card[0]);
            $this->assertStringContainsString('blocktempo.com', $card[0]);
            $this->assertStringContainsString('OnlyFans 老闆癌症病逝', $card[0]);
        }
    }

    public function test_without_preview_the_plain_link_still_shows(): void
    {
        $this->makeTweet('看這個 [新聞連結](https://blocktempo.com/news/2)');
        LinkPreview::create([
            'url_hash' => LinkPreview::hashFor('https://blocktempo.com/news/2'), 'url' => 'https://blocktempo.com/news/2',
            'domain' => 'blocktempo.com', 'title' => null, 'status' => 'failed', 'fetched_at' => now(),
        ]);

        $html = $this->get('/zh/tweets')->getContent();

        $this->assertStringNotContainsString('data-link-preview', $html);
        $this->assertMatchesRegularExpression('/<a [^>]*href="https:\/\/blocktempo.com\/news\/2"[^>]*>新聞連結<\/a>/u', $html);
    }
}
