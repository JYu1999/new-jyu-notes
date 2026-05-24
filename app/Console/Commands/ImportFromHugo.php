<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tag;
use App\Models\Tweet;
use App\Models\TweetGroup;
use App\Models\User;
use App\Services\MediaService;
use App\Support\ShortcodeConverter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

class ImportFromHugo extends Command
{
    protected $signature = 'blog:import-from-hugo
                            {path=hugo-blowfish-blog : Path to Hugo project (relative to app root, or absolute)}
                            {--fresh : Truncate posts/tweets/tags/categories/media before import}
                            {--dry-run : Print actions without writing}';

    protected $description = 'Import posts, tweets, tags, categories, and media from a legacy Hugo blog.';

    /** locale filename → DB locale */
    private const LOCALE_MAP = [
        'index.md' => 'zh',
        'index.en.md' => 'en',
        'index.ja.md' => 'ja',
    ];

    /**
     * Predefined Hugo tag mapping: zh/en/ja name → canonical English slug.
     * From legacy AGENTS.md (allowed-tags table).
     */
    private const TAG_DICTIONARY = [
        ['slug' => 'technical',      'zh' => '技術',           'en' => 'Technical',         'ja' => '技術'],
        ['slug' => 'career',         'zh' => '職涯',           'en' => 'Career',            'ja' => 'キャリア'],
        ['slug' => 'life',           'zh' => '生活',           'en' => 'Life',              'ja' => '生活'],
        ['slug' => 'reading',        'zh' => '閱讀',           'en' => 'Reading',           'ja' => '読書'],
        ['slug' => 'travel',         'zh' => '旅遊',           'en' => 'Travel',            'ja' => '旅行'],
        ['slug' => 'goodidea',       'zh' => '好想工作室',     'en' => 'Goodidea Studio',   'ja' => 'グッドアイデアスタジオ'],
        ['slug' => 'japan',          'zh' => '日本',           'en' => 'Japan',             'ja' => '日本'],
        ['slug' => 'security',       'zh' => '資安',           'en' => 'Security',          'ja' => 'セキュリティ'],
        ['slug' => 'tools',          'zh' => '生產力工具',     'en' => 'Tools',             'ja' => '生産性ツール'],
        ['slug' => 'blog',           'zh' => '部落格',         'en' => 'Blog',              'ja' => 'ブログ'],
        ['slug' => 'ai',             'zh' => '人工智慧',       'en' => 'AI',                'ja' => 'AI'],
    ];

    private ShortcodeConverter $shortcoder;
    private MediaService $media;
    private ?User $admin = null;
    private array $tagsBySlug = [];      // slug => Tag
    private array $catsBySlug = [];      // slug => Category
    private array $tweetIdByBundle = []; // [bundleSlug][locale] => tweet id (for internal link rewriting)

    public function __construct(ShortcodeConverter $shortcoder, MediaService $media)
    {
        parent::__construct();
        $this->shortcoder = $shortcoder;
        $this->media = $media;
    }

    public function handle(): int
    {
        $pathArg = $this->argument('path');
        $hugoRoot = str_starts_with($pathArg, '/')
            ? $pathArg
            : base_path(ltrim($pathArg, '/'));

        if (! is_dir($hugoRoot)) {
            $this->error("Hugo root not found: {$hugoRoot}");
            return self::FAILURE;
        }
        $contentDir = "{$hugoRoot}/content";
        if (! is_dir($contentDir)) {
            $this->error("Hugo content/ not found at: {$contentDir}");
            return self::FAILURE;
        }

        $this->admin = User::query()->first();
        if (! $this->admin) {
            $this->error('No admin user found. Run `php artisan db:seed --class=AdminUserSeeder` first.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('Truncating existing posts / tweets / tags / categories / media…');
            if (! $this->option('dry-run')) {
                DB::transaction(function () {
                    DB::table('post_tag')->truncate();
                    DB::table('tweet_tag')->truncate();
                    DB::table('category_post')->truncate();
                    DB::table('post_view_logs')->truncate();
                    DB::statement('TRUNCATE posts, tweets, pages RESTART IDENTITY CASCADE');
                    DB::statement('TRUNCATE post_groups, tweet_groups, page_groups RESTART IDENTITY CASCADE');
                    DB::statement('TRUNCATE tag_translations, tags RESTART IDENTITY CASCADE');
                    DB::statement('TRUNCATE category_translations, categories RESTART IDENTITY CASCADE');
                    DB::statement('TRUNCATE media RESTART IDENTITY CASCADE');
                });
            }
        }

        $this->seedTagDictionary();
        $this->importCategoryDefinitions("{$contentDir}/categories");
        $postCount = $this->importPosts("{$contentDir}/posts");
        $tweetCount = $this->importTweets("{$contentDir}/tweets");
        $pageCount = $this->importPages($contentDir);

        if (! $this->option('dry-run')) {
            $this->rewriteInternalLinks();
        }

        $this->info("Done. Imported {$postCount} post groups, {$tweetCount} tweet groups, {$pageCount} pages.");
        return self::SUCCESS;
    }

    /**
     * Walk Hugo top-level content directories (about, why-blog, etc.) and import
     * each as a static Page. Skips known special dirs (posts, tweets, categories, etc.).
     */
    private function importPages(string $contentDir): int
    {
        $reserved = ['posts', 'tweets', 'categories', 'resume', '_index.md', '_index.en.md', '_index.ja.md'];
        $count = 0;

        foreach (scandir($contentDir) as $entry) {
            if (in_array($entry, ['.', '..'], true)) continue;
            if (in_array($entry, $reserved, true)) continue;

            $full = "{$contentDir}/{$entry}";
            if (! is_dir($full)) continue;

            $slug = $entry;
            $locales = $this->findLocaleFiles($full);
            if (! $locales) continue;

            $group = $this->option('dry-run') ? new \App\Models\PageGroup() : \App\Models\PageGroup::create();

            foreach ($locales as $locale => $filePath) {
                $this->importPageFile($group, $locale, $slug, $filePath, $full);
            }

            $count++;
        }

        // Also handle truncate of pages if --fresh was specified earlier
        return $count;
    }

    private function importPageFile(\App\Models\PageGroup $group, string $locale, string $slug, string $filePath, string $bundleDir): ?\App\Models\Page
    {
        $raw = file_get_contents($filePath);
        [$fm, $body] = $this->splitFrontMatter($raw);

        $storageSubdir = "imports/pages/{$slug}";

        $converter = clone $this->shortcoder;
        $converter->assetRewrites = $this->buildAssetRewrites($bundleDir, $storageSubdir);
        $body = $converter->convert($body);

        $coverPath = $this->ingestCoverImage($bundleDir, $storageSubdir);

        $publishedAt = $this->parseDate($fm['date'] ?? null);
        $status = ($fm['draft'] ?? false) ? \App\Models\Page::STATUS_DRAFT : \App\Models\Page::STATUS_PUBLISHED;

        if ($this->option('dry-run')) {
            $this->line("Page[{$locale}]: {$slug} ({$status})");
            return null;
        }

        $page = \App\Models\Page::create([
            'page_group_id' => $group->id,
            'locale' => $locale,
            'slug' => $slug,
            'title' => $fm['title'] ?? $slug,
            'body' => trim($body),
            'cover_image_path' => $coverPath,
            'status' => $status,
            'published_at' => $publishedAt,
            'author_id' => $this->admin->id,
        ]);

        if ($publishedAt) {
            DB::table('pages')->where('id', $page->id)->update([
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ]);
            $page->refresh();
        }

        $this->line("Page[{$locale}]: {$slug}");
        return $page;
    }

    /**
     * Populate the predefined tag dictionary into DB (zh/en/ja translations).
     */
    private function seedTagDictionary(): void
    {
        foreach (self::TAG_DICTIONARY as $row) {
            if ($this->option('dry-run')) {
                $this->line("would create tag: {$row['slug']}");
                continue;
            }

            // Look up existing tag by its English slug translation; if none, create new.
            $existing = \App\Models\TagTranslation::query()
                ->where('locale', 'en')
                ->where('slug', $row['slug'])
                ->first();

            $tag = $existing ? $existing->tag : Tag::create([]);

            foreach (['zh', 'en', 'ja'] as $locale) {
                \App\Models\TagTranslation::updateOrCreate(
                    ['tag_id' => $tag->id, 'locale' => $locale],
                    ['name' => $row[$locale], 'slug' => $row['slug']],
                );
            }

            $this->tagsBySlug[$row['slug']] = $tag;
            foreach (['zh', 'en', 'ja'] as $locale) {
                $this->tagsBySlug[$locale . ':' . $row[$locale]] = $tag;
            }
        }
    }

    /**
     * Import categories from content/categories/<slug>/_index.md
     */
    private function importCategoryDefinitions(string $dir): void
    {
        if (! is_dir($dir)) return;

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $slug = $entry;
            $full = "{$dir}/{$entry}";
            if (! is_dir($full)) continue;

            $files = [
                'zh' => "{$full}/_index.md",
                'en' => "{$full}/_index.en.md",
                'ja' => "{$full}/_index.ja.md",
            ];

            $primary = is_file($files['zh']) ? $files['zh']
                : (is_file($files['en']) ? $files['en'] : (is_file($files['ja']) ? $files['ja'] : null));
            if (! $primary) continue;

            $category = $this->option('dry-run')
                ? new Category(['sort_method' => 'manual'])
                : Category::create(['sort_method' => 'manual']);

            foreach ($files as $locale => $path) {
                if (! is_file($path)) continue;
                [$fm, $body] = $this->splitFrontMatter(file_get_contents($path));
                $name = $fm['title'] ?? $slug;
                $description = $fm['description'] ?? trim($body) ?: null;

                if (! $this->option('dry-run')) {
                    \App\Models\CategoryTranslation::create([
                        'category_id' => $category->id,
                        'locale' => $locale,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                    ]);
                }
            }

            $this->catsBySlug[$slug] = $category;
            $this->line("Category: {$slug}");
        }
    }

    /**
     * Walk content/posts/<slug>/ and import each locale variant as a Post.
     */
    private function importPosts(string $dir): int
    {
        if (! is_dir($dir)) return 0;

        $count = 0;
        foreach (scandir($dir) as $entry) {
            if (in_array($entry, ['.', '..'], true)) continue;
            $full = "{$dir}/{$entry}";
            if (! is_dir($full)) continue;

            $slug = $entry;
            $locales = $this->findLocaleFiles($full);
            if (! $locales) continue;

            $postGroup = $this->option('dry-run')
                ? new PostGroup()
                : PostGroup::create();

            $primaryPost = null;
            foreach ($locales as $locale => $filePath) {
                $post = $this->importPostFile($postGroup, $locale, $slug, $filePath, $full);
                if ($post && ! $primaryPost) $primaryPost = $post;
            }

            // Sync tags + categories from front matter (primary locale)
            if ($primaryPost) {
                $this->syncPostFrontMatterAssociations($primaryPost, $locales);
            }

            $count++;
        }
        return $count;
    }

    private function findLocaleFiles(string $dir): array
    {
        $out = [];
        foreach (self::LOCALE_MAP as $filename => $locale) {
            $p = "{$dir}/{$filename}";
            if (is_file($p)) $out[$locale] = $p;
        }
        return $out;
    }

    /**
     * Import one Post (one locale).
     */
    private function importPostFile(PostGroup $group, string $locale, string $slug, string $filePath, string $bundleDir): ?Post
    {
        $raw = file_get_contents($filePath);
        [$fm, $body] = $this->splitFrontMatter($raw);

        $storageSubdir = "imports/posts/{$slug}";

        // Convert shortcodes (with per-bundle asset path mapping)
        $converter = clone $this->shortcoder;
        $converter->assetRewrites = $this->buildAssetRewrites($bundleDir, $storageSubdir);
        $body = $converter->convert($body);

        $coverPath = $this->ingestCoverImage($bundleDir, $storageSubdir);

        $publishedAt = $this->parseDate($fm['date'] ?? null);
        $lastModifiedAt = $this->parseDate($fm['lastmod'] ?? null) ?? $publishedAt ?? now();

        $status = ($fm['draft'] ?? false) ? Post::STATUS_DRAFT : Post::STATUS_PUBLISHED;

        if ($this->option('dry-run')) {
            $this->line("Post[{$locale}]: {$slug} ({$status})");
            return null;
        }

        $post = Post::create([
            'post_group_id' => $group->id,
            'locale' => $locale,
            'slug' => $slug,
            'title' => $fm['title'] ?? $slug,
            'excerpt' => $fm['description'] ?? null,
            'body' => trim($body),
            'cover_image_path' => $coverPath,
            'status' => $status,
            'is_featured' => false,
            'published_at' => $publishedAt,
            'last_modified_at' => $lastModifiedAt,
            'author_id' => $this->admin->id,
        ]);

        // Preserve Hugo's date/lastmod as Eloquent created_at/updated_at
        if ($publishedAt) {
            DB::table('posts')->where('id', $post->id)->update([
                'created_at' => $publishedAt,
                'updated_at' => $lastModifiedAt,
            ]);
            $post->refresh();
        }

        $this->line("Post[{$locale}]: {$slug}");
        return $post;
    }

    /**
     * Build asset URL rewrite map for shortcode converter and inline-image references.
     *
     * @param  string  $bundleDir       absolute path of the Hugo bundle directory
     * @param  string  $storageSubdir   target subdirectory under storage/public (e.g. "imports/posts/<slug>")
     * @return array<string, string>    map of bare filename → web URL
     */
    private function buildAssetRewrites(string $bundleDir, string $storageSubdir): array
    {
        $rewrites = [];
        foreach (glob("{$bundleDir}/*.{png,jpg,jpeg,webp,gif,mp4,webm}", GLOB_BRACE) ?: [] as $file) {
            $filename = basename($file);
            if ($this->option('dry-run')) {
                $rewrites[$filename] = "/storage/{$storageSubdir}/{$filename}";
            } else {
                $media = $this->media->registerLocalFile($file, $storageSubdir, $this->admin);
                if ($media) {
                    $rewrites[$filename] = '/storage/' . $media->path;
                }
            }
        }
        return $rewrites;
    }

    private function ingestCoverImage(string $bundleDir, string $storageSubdir): ?string
    {
        // 1) Explicit Hugo conventions
        $explicit = ['featured.png', 'featured.jpg', 'featured.jpeg', 'featured.webp',
                     'cover.png', 'cover.jpg', 'cover.jpeg', 'cover.webp',
                     'book-cover.png', 'book-cover.jpg', 'book-cover.jpeg', 'book-cover.webp'];
        foreach ($explicit as $name) {
            $path = "{$bundleDir}/{$name}";
            if (is_file($path)) {
                if ($this->option('dry-run')) return "{$storageSubdir}/{$name}";
                $media = $this->media->registerLocalFile($path, $storageSubdir, $this->admin);
                return $media?->path;
            }
        }

        // 2) Fallback: first image in the bundle (alphabetical, excluding video)
        $images = glob("{$bundleDir}/*.{png,jpg,jpeg,webp,gif}", GLOB_BRACE) ?: [];
        sort($images);
        if (! empty($images)) {
            $first = $images[0];
            if ($this->option('dry-run')) return "{$storageSubdir}/" . basename($first);
            $media = $this->media->registerLocalFile($first, $storageSubdir, $this->admin);
            return $media?->path;
        }

        return null;
    }

    /**
     * Apply front-matter tags + categories to the post group.
     */
    private function syncPostFrontMatterAssociations(Post $primaryPost, array $locales): void
    {
        // Re-read the primary locale's front matter
        $primaryFile = $locales['zh'] ?? $locales['en'] ?? $locales['ja'] ?? null;
        if (! $primaryFile) return;
        [$fm, ] = $this->splitFrontMatter(file_get_contents($primaryFile));

        // Tags
        $tagIds = [];
        foreach ((array) ($fm['tags'] ?? []) as $tagName) {
            $tag = $this->resolveTagByName($primaryPost->locale, $tagName);
            if ($tag) $tagIds[] = $tag->id;
        }

        // Categories
        $categoryIdsWithOrder = [];
        foreach ((array) ($fm['categories'] ?? []) as $catSlug) {
            $cat = $this->catsBySlug[$catSlug] ?? null;
            if ($cat) {
                $order = isset($fm['series_order']) ? (int) $fm['series_order'] : null;
                $categoryIdsWithOrder[$cat->id] = ['order_in_category' => $order];
            }
        }

        // Hugo `series` (string or array) → use first as category slug if exists
        if (! empty($fm['series'])) {
            $seriesName = is_array($fm['series']) ? ($fm['series'][0] ?? null) : $fm['series'];
            if ($seriesName) {
                $seriesCat = $this->findOrCreateCategoryByName($seriesName, $primaryPost->locale);
                if ($seriesCat) {
                    $order = isset($fm['series_order']) ? (int) $fm['series_order'] : null;
                    $categoryIdsWithOrder[$seriesCat->id] = ['order_in_category' => $order];
                }
            }
        }

        if ($this->option('dry-run')) return;

        // Apply to all locales in group
        $allPosts = Post::query()
            ->where('post_group_id', $primaryPost->post_group_id)
            ->get();
        foreach ($allPosts as $p) {
            $p->tags()->sync($tagIds);
            $p->categories()->sync($categoryIdsWithOrder);
        }
    }

    private function resolveTagByName(string $locale, string $tagName): ?Tag
    {
        $key = $locale . ':' . $tagName;
        if (isset($this->tagsBySlug[$key])) {
            return $this->tagsBySlug[$key];
        }

        // Search across all locales
        $translation = \App\Models\TagTranslation::query()
            ->where('name', $tagName)
            ->first();
        if ($translation) {
            $tag = $translation->tag;
            $this->tagsBySlug[$key] = $tag;
            return $tag;
        }

        // Auto-create a tag with this name for the given locale
        if ($this->option('dry-run')) return null;

        $tag = Tag::create([]);
        $slug = \Illuminate\Support\Str::slug($tagName, '-');
        if ($slug === '') {
            $slug = 'tag-' . $tag->id;
        }
        \App\Models\TagTranslation::create([
            'tag_id' => $tag->id,
            'locale' => $locale,
            'name' => $tagName,
            'slug' => $slug,
        ]);
        $this->tagsBySlug[$key] = $tag;
        return $tag;
    }

    private function findOrCreateCategoryByName(string $name, string $locale): ?Category
    {
        $translation = \App\Models\CategoryTranslation::query()
            ->where('name', $name)
            ->first();
        if ($translation) {
            return $translation->category;
        }

        if ($this->option('dry-run')) return null;

        $cat = Category::create(['sort_method' => 'manual']);
        $slug = \Illuminate\Support\Str::slug($name, '-');
        if ($slug === '') {
            $slug = 'series-' . $cat->id;
        }
        \App\Models\CategoryTranslation::create([
            'category_id' => $cat->id,
            'locale' => $locale,
            'name' => $name,
            'slug' => $slug,
        ]);
        return $cat;
    }

    /**
     * Import tweets from content/tweets/<slug>/index*.md
     */
    private function importTweets(string $dir): int
    {
        if (! is_dir($dir)) return 0;

        $count = 0;
        foreach (scandir($dir) as $entry) {
            if (in_array($entry, ['.', '..'], true)) continue;
            $full = "{$dir}/{$entry}";
            if (! is_dir($full)) continue;
            // Skip non-tweet top-level files
            if (! is_file("{$full}/index.md") && ! is_file("{$full}/index.en.md") && ! is_file("{$full}/index.ja.md")) {
                continue;
            }

            $locales = $this->findLocaleFiles($full);
            if (! $locales) continue;

            $group = $this->option('dry-run') ? new TweetGroup() : TweetGroup::create();
            $primary = null;

            foreach ($locales as $locale => $filePath) {
                $tweet = $this->importTweetFile($group, $locale, $filePath, $full);
                if ($tweet && ! $primary) $primary = $tweet;
            }

            if ($primary) {
                $this->syncTweetFrontMatterAssociations($primary, $locales);
            }

            $count++;
        }
        return $count;
    }

    private function importTweetFile(TweetGroup $group, string $locale, string $filePath, string $bundleDir): ?Tweet
    {
        $raw = file_get_contents($filePath);
        [$fm, $body] = $this->splitFrontMatter($raw);

        $slug = basename($bundleDir);
        $storageSubdir = "imports/tweets/{$slug}";

        $converter = clone $this->shortcoder;
        $converter->assetRewrites = $this->buildAssetRewrites($bundleDir, $storageSubdir);
        $body = $converter->convert($body);

        // Collect inline media (only files NOT already referenced in body to avoid duplicate rendering)
        $media = [];
        foreach (glob("{$bundleDir}/*.{png,jpg,jpeg,webp,gif,mp4,webm}", GLOB_BRACE) ?: [] as $file) {
            $filename = basename($file);
            if (str_starts_with($filename, 'featured.')) continue;
            // Skip if body already embeds this file (avoid Bug 4: duplicate render)
            if (stripos($body, $filename) !== false) continue;

            $mime = mime_content_type($file) ?: '';
            $type = str_starts_with($mime, 'video/') ? 'video' : 'image';

            if (! $this->option('dry-run')) {
                $mediaRecord = $this->media->registerLocalFile($file, $storageSubdir, $this->admin);
                if ($mediaRecord) {
                    $media[] = [
                        'path' => $mediaRecord->path,
                        'type' => $type,
                        'alt' => null,
                    ];
                }
            }
        }

        $publishedAt = $this->parseDate($fm['date'] ?? null);
        $status = ($fm['draft'] ?? false) ? Tweet::STATUS_DRAFT : Tweet::STATUS_PUBLISHED;

        if ($this->option('dry-run')) {
            $this->line("Tweet[{$locale}]: {$slug} ({$status})");
            return null;
        }

        $tweet = Tweet::create([
            'tweet_group_id' => $group->id,
            'locale' => $locale,
            'body' => trim($body),
            'media' => $media ?: null,
            'status' => $status,
            'published_at' => $publishedAt,
            'author_id' => $this->admin->id,
        ]);

        // Preserve Hugo's date as Eloquent created_at/updated_at
        if ($publishedAt) {
            DB::table('tweets')->where('id', $tweet->id)->update([
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ]);
            $tweet->refresh();
        }

        // Track bundle slug → tweet id so internal `/tweets/<slug>/` links can be rewritten.
        $this->tweetIdByBundle[$slug][$locale] = $tweet->id;

        $this->line("Tweet[{$locale}]: {$slug}");
        return $tweet;
    }

    private function syncTweetFrontMatterAssociations(Tweet $primary, array $locales): void
    {
        $primaryFile = $locales['zh'] ?? $locales['en'] ?? $locales['ja'] ?? null;
        if (! $primaryFile) return;
        [$fm, ] = $this->splitFrontMatter(file_get_contents($primaryFile));

        $tagIds = [];
        foreach ((array) ($fm['tags'] ?? []) as $tagName) {
            $tag = $this->resolveTagByName($primary->locale, $tagName);
            if ($tag) $tagIds[] = $tag->id;
        }

        if ($this->option('dry-run')) return;

        $allTweets = Tweet::query()
            ->where('tweet_group_id', $primary->tweet_group_id)
            ->get();
        foreach ($allTweets as $t) {
            $t->tags()->sync($tagIds);
        }
    }

    /**
     * Second pass: rewrite all internal Hugo paths in post/tweet bodies to
     * include the locale prefix and (for tweets) the correct DB id.
     *
     * - `/posts/<slug>/` → `/<locale>/posts/<slug>`
     * - `/tweets/<bundle-slug>/` → `/<locale>/tweets/<id>`
     *
     * Also replaces link text that exactly equals the URL with the target's title/body excerpt
     * (so `[/posts/foo](/posts/foo)` becomes `[Real Title](/zh/posts/foo)`).
     *
     * Storage paths like `/storage/imports/posts/...` are not touched — the regex
     * only matches inside `]( … )` markdown links and `href="..."` HTML attributes.
     */
    private function rewriteInternalLinks(): void
    {
        $this->info('Rewriting internal links…');

        // Build post slug → title lookup (per locale)
        $postTitleByLocaleSlug = [];
        foreach (Post::query()->select('locale', 'slug', 'title')->get() as $p) {
            $postTitleByLocaleSlug[$p->locale][$p->slug] = $p->title;
        }

        // Build tweet id → body excerpt lookup (for replacing bare-path link text)
        $tweetExcerptById = [];
        foreach (Tweet::query()->select('id', 'body')->get() as $t) {
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags($t->body)));
            $tweetExcerptById[$t->id] = \Illuminate\Support\Str::limit($plain, 60);
        }

        foreach (Post::query()->get() as $post) {
            $newBody = $this->rewriteBodyLinks($post->body, $post->locale, $postTitleByLocaleSlug, $tweetExcerptById);
            if ($newBody !== $post->body) {
                DB::table('posts')->where('id', $post->id)->update(['body' => $newBody]);
            }
        }

        foreach (Tweet::query()->get() as $tweet) {
            $newBody = $this->rewriteBodyLinks($tweet->body, $tweet->locale, $postTitleByLocaleSlug, $tweetExcerptById);
            if ($newBody !== $tweet->body) {
                DB::table('tweets')->where('id', $tweet->id)->update(['body' => $newBody]);
            }
        }

        foreach (\App\Models\Page::query()->get() as $page) {
            $newBody = $this->rewriteBodyLinks($page->body, $page->locale, $postTitleByLocaleSlug, $tweetExcerptById);
            if ($newBody !== $page->body) {
                DB::table('pages')->where('id', $page->id)->update(['body' => $newBody]);
            }
        }
    }

    private function rewriteBodyLinks(string $body, string $locale, array $postTitles, array $tweetExcerpts): string
    {
        // Markdown link: [text](/posts/slug/) or [text](/tweets/slug/)
        $body = preg_replace_callback(
            '#\[([^\]]+)\]\(/(posts|tweets)/([A-Za-z0-9_\-]+)/?\)#',
            function ($m) use ($locale, $postTitles, $tweetExcerpts) {
                [$text, $kind, $slug] = [$m[1], $m[2], $m[3]];
                if ($kind === 'posts') {
                    $href = "/{$locale}/posts/{$slug}";
                    $title = $postTitles[$locale][$slug] ?? null;
                    if ($title && (trim($text) === "/posts/{$slug}/" || trim($text) === "/posts/{$slug}")) {
                        $text = $title;
                    }
                    return "[{$text}]({$href})";
                }
                // tweets
                $id = $this->resolveTweetId($slug, $locale);
                if (! $id) return $m[0];
                $excerpt = $tweetExcerpts[$id] ?? null;
                if ($excerpt && (trim($text) === "/tweets/{$slug}/" || trim($text) === "/tweets/{$slug}")) {
                    $text = $excerpt;
                }
                return "[{$text}](/{$locale}/tweets/{$id})";
            },
            $body
        );

        // HTML <a href="/posts/slug/"> or <a href="/tweets/slug/">
        $body = preg_replace_callback(
            '#href="/(posts|tweets)/([A-Za-z0-9_\-]+)/?"#',
            function ($m) use ($locale) {
                [$kind, $slug] = [$m[1], $m[2]];
                if ($kind === 'posts') return 'href="/' . $locale . '/posts/' . $slug . '"';
                $id = $this->resolveTweetId($slug, $locale);
                return $id ? 'href="/' . $locale . '/tweets/' . $id . '"' : $m[0];
            },
            $body
        );

        // Clean up link TEXT inside <a>...</a> when it equals the path
        // (the article shortcode produces `<a href="...">/path</a>`).
        $body = preg_replace_callback(
            '#<a href="(/[a-z]{2,5}/(posts|tweets)/([A-Za-z0-9_\-]+))">(/(?:posts|tweets)/[A-Za-z0-9_\-]+/?)</a>#',
            function ($m) use ($postTitles, $tweetExcerpts) {
                [$href, $kind, $slug] = [$m[1], $m[2], $m[3]];
                if ($kind === 'posts') {
                    if (preg_match('#^/([a-z]{2,5})/posts/#', $href, $hm)) {
                        $loc = $hm[1];
                        $title = $postTitles[$loc][$slug] ?? null;
                        if ($title) return '<a href="' . $href . '">' . htmlspecialchars($title) . '</a>';
                    }
                }
                if ($kind === 'tweets') {
                    $excerpt = $tweetExcerpts[(int) $slug] ?? null;
                    if ($excerpt) return '<a href="' . $href . '">' . htmlspecialchars($excerpt) . '</a>';
                }
                return $m[0];
            },
            $body
        );

        return $body;
    }

    private function resolveTweetId(string $bundleSlug, string $locale): ?int
    {
        // Prefer same locale
        if (isset($this->tweetIdByBundle[$bundleSlug][$locale])) {
            return $this->tweetIdByBundle[$bundleSlug][$locale];
        }
        // Fallback: any locale
        foreach (['zh', 'en', 'ja', 'vi', 'id'] as $loc) {
            if (isset($this->tweetIdByBundle[$bundleSlug][$loc])) {
                return $this->tweetIdByBundle[$bundleSlug][$loc];
            }
        }
        return null;
    }

    /**
     * Split YAML front matter and body content.
     *
     * @return array{0: array, 1: string}
     */
    private function splitFrontMatter(string $raw): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $raw, $m)) {
            return [[], $raw];
        }
        try {
            // PARSE_DATETIME → returns \DateTime objects for ISO-8601 timestamps
            // instead of Unix integers (which Carbon::parse can't handle as strings).
            $fm = Yaml::parse($m[1], Yaml::PARSE_DATETIME) ?? [];
        } catch (\Throwable $e) {
            $fm = [];
        }
        return [is_array($fm) ? $fm : [], $m[2] ?? ''];
    }

    private function parseDate($val): ?Carbon
    {
        if ($val === null || $val === '') return null;
        try {
            if ($val instanceof \DateTimeInterface) {
                return Carbon::instance($val);
            }
            if (is_int($val)) {
                return Carbon::createFromTimestamp($val);
            }
            return Carbon::parse((string) $val);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
