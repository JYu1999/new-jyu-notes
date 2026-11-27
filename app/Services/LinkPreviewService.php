<?php

namespace App\Services;

use App\Models\LinkPreview;
use App\Models\Tweet;
use App\Support\HostResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Link preview cards for external URLs in tweets (JYU-540).
 *
 * Titles are fetched server-side once and cached in `link_previews`.
 * Because the server requests a URL written in a tweet, every fetch is
 * guarded against SSRF: http(s) only, and the host (and every redirect
 * target) must resolve to public IP addresses.
 */
class LinkPreviewService
{
    public const MAX_BYTES = 512 * 1024;

    public const TIMEOUT_SECONDS = 5;

    /** Hosts we never preview: our own site and things already embedded. */
    private const SKIP_HOSTS = ['youtube.com', 'www.youtube.com', 'youtu.be', 'm.youtube.com'];

    public function __construct(private HostResolver $resolver) {}

    /**
     * External http(s) URLs in a Markdown body, in order, without duplicates.
     *
     * @return list<string>
     */
    public function externalUrls(string $markdown): array
    {
        // Shortcodes ({{< youtube … >}}) are embeds, not links.
        $markdown = preg_replace('/\{\{<.*?>\}\}/s', ' ', $markdown);
        preg_match_all('~https?://[^\s<>"\'()\[\]]+~i', $markdown, $m);

        $own = parse_url((string) config('app.url'), PHP_URL_HOST);

        return collect($m[0])
            ->map(fn ($url) => rtrim($url, '.,;:!?'))
            ->filter(function ($url) use ($own) {
                $host = strtolower((string) parse_url($url, PHP_URL_HOST));

                return $host !== '' && $host !== $own && ! in_array($host, self::SKIP_HOSTS, true);
            })
            ->unique()
            ->values()
            ->all();
    }

    /** The preview card for a tweet: its first external link that has a usable preview. */
    public function forTweet(Tweet $tweet): ?LinkPreview
    {
        $url = $this->externalUrls((string) $tweet->body)[0] ?? null;
        if ($url === null) {
            return null;
        }

        $preview = LinkPreview::where('url_hash', LinkPreview::hashFor($url))->first();

        return $preview?->isUsable() ? $preview : null;
    }

    /** Fetch (or re-fetch) every external link in a tweet. */
    public function fetchForTweet(Tweet $tweet, bool $refresh = false): void
    {
        foreach ($this->externalUrls((string) $tweet->body) as $url) {
            $existing = LinkPreview::where('url_hash', LinkPreview::hashFor($url))->first();
            if ($existing && ! $refresh) {
                continue;
            }
            $this->fetch($url);
        }
    }

    public function fetch(string $url): LinkPreview
    {
        $title = null;
        try {
            if ($this->isAllowed($url)) {
                $title = $this->fetchTitle($url);
            }
        } catch (Throwable) {
            $title = null;
        }

        return LinkPreview::updateOrCreate(
            ['url_hash' => LinkPreview::hashFor($url)],
            [
                'url' => $url,
                'domain' => preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST))),
                'title' => $title,
                'status' => $title ? LinkPreview::STATUS_OK : LinkPreview::STATUS_FAILED,
                'fetched_at' => now(),
            ],
        );
    }

    /** http(s), no credentials, and the host resolves only to public addresses. */
    public function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) {
            return false;
        }

        $ips = $this->resolver->resolve($host);
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    private function fetchTitle(string $url): ?string
    {
        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; BlogLinkPreview/1.0)', 'Accept' => 'text/html'])
            ->withOptions([
                'stream' => true,
                'allow_redirects' => [
                    'max' => 3,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri) {
                        if (! $this->isAllowed((string) $uri)) {
                            throw new \RuntimeException('Redirect to a non-public address blocked');
                        }
                    },
                ],
            ])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }
        $type = strtolower($response->header('Content-Type'));
        if ($type !== '' && ! str_contains($type, 'html')) {
            return null;
        }

        $html = $response->toPsrResponse()->getBody()->read(self::MAX_BYTES);

        if (preg_match('/charset=([\w-]+)/i', $type, $cm) && strtolower($cm[1]) !== 'utf-8') {
            $html = @mb_convert_encoding($html, 'UTF-8', $cm[1]) ?: $html;
        }

        return self::parseTitle($html);
    }

    /** og:title → twitter:title → <title>; entities decoded, whitespace collapsed. */
    public static function parseTitle(string $html): ?string
    {
        $candidates = [];
        foreach (['og:title', 'twitter:title'] as $prop) {
            $p = preg_quote($prop, '/');
            if (preg_match('/<meta[^>]+(?:property|name)=["\']'.$p.'["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)
                || preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']'.$p.'["\']/i', $html, $m)) {
                $candidates[] = $m[1];
            }
        }
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $candidates[] = $m[1];
        }

        foreach ($candidates as $raw) {
            $title = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($title !== '') {
                return Str::limit($title, 200);
            }
        }

        return null;
    }
}
