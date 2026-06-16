# Tweet `@`-Mention + Cross-Type References Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing post-only `@`-mention + backlink feature into a symmetric cross-type system so posts and tweets can each mention the other and themselves, with backlinks shown on both public pages; also ship a small mobile fix for wide images stretching article pages.

**Architecture:** Replace the post-only `post_references(source_post_id, target_post_id)` table with one polymorphic `content_references(source_*, target_*)` table backed by a `Reference` model, a shared `HasReferences` trait on Post & Tweet, a generalized `ReferenceExtractor`, and a `ReferenceSyncer` service called by both `PostService` and `TweetService`. A unified `/admin/mentions/search` endpoint feeds a shared Alpine `@`-mention behaviour in both editors; a shared Blade partial renders mixed-type backlinks.

**Tech Stack:** Laravel 11, PostgreSQL, Eloquent polymorphic relations + morph map, Alpine.js, Tailwind v4 (`@tailwindcss/vite`), Sail, PHPUnit. **Run all PHP/artisan/composer via `./vendor/bin/sail`.**

---

## File Structure

**Create:**
- `database/migrations/2026_06_16_000001_create_content_references_table.php` — new polymorphic table + migrate existing `post_references` rows + drop old table.
- `app/Models/Reference.php` — `source()`/`target()` morphTo, `$table = 'content_references'`.
- `app/Models/Concerns/HasReferences.php` — shared `outgoingReferences()`/`incomingReferences()`/`publishedBacklinks()`.
- `app/Support/ReferenceExtractor.php` — generalizes `PostReferenceExtractor` to posts + tweets.
- `app/Services/ReferenceSyncer.php` — extract → resolve → overwrite a source's references.
- `app/Http/Controllers/Admin/MentionController.php` — unified search endpoint.
- `app/Console/Commands/BackfillReferences.php` — `references:backfill` (replaces `posts:backfill-references`).
- `resources/views/public/partials/backlinks.blade.php` — mixed-type backlink list.
- `tests/Unit/ReferenceExtractorTest.php`, `tests/Feature/ReferenceSyncTest.php`, `tests/Feature/BackfillReferencesTest.php`, `tests/Feature/Public/TweetBacklinkDisplayTest.php`, `tests/Feature/Admin/MentionSearchTest.php`.

**Modify:**
- `app/Providers/AppServiceProvider.php` — register morph map.
- `app/Models/Post.php` — use `HasReferences`, remove old `outgoingReferences()`/`backlinks()`.
- `app/Models/Tweet.php` — use `HasReferences`, add `preview()`.
- `app/Services/PostService.php` — delegate to `ReferenceSyncer`.
- `app/Services/TweetService.php` — call `ReferenceSyncer` on create/update.
- `app/Repositories/TweetRepository.php` — add `searchForMention()`.
- `app/Http/Controllers/Admin/PostController.php` — remove `search()`.
- `routes/web.php` — replace `posts/search` route with `mentions/search`.
- `resources/js/app.js` — generalize `mentionBehavior`; add `tweetComposer()`.
- `resources/views/admin/posts/edit.blade.php` — pass `mentionExcludeType`/`Id`.
- `resources/views/admin/tweets/edit.blade.php` — wire `tweetComposer` + dropdown.
- `app/Http/Controllers/Public/PostController.php` — backlinks via `publishedBacklinks()`.
- `app/Http/Controllers/Public/TweetController.php` — pass `publishedBacklinks()`.
- `resources/views/public/posts/show.blade.php` — use backlinks partial.
- `resources/views/public/tweets/show.blade.php` — add backlinks section.
- Rename/rewrite `tests/Unit/PostReferenceExtractorTest.php`, `tests/Feature/PostReferenceSyncTest.php`, `tests/Feature/BackfillPostReferencesTest.php`, `tests/Feature/Public/PostBacklinkDisplayTest.php`.

---

## Task 1: Commit the mobile wide-image fix

Already applied in the working tree (verify, then commit). This is independent of the references work.

**Files:**
- Modify: `resources/views/public/posts/show.blade.php` (`<article class="max-w-3xl min-w-0">`)
- Modify: `resources/css/app.css` (`.prose-blog img { … max-width: 100%; height: auto; }`)

- [ ] **Step 1: Verify the two changes are present**

Run: `git diff --stat resources/views/public/posts/show.blade.php resources/css/app.css`
Expected: both files show as modified.

- [ ] **Step 2: Build assets to confirm CSS compiles**

Run: `npm run build`
Expected: Vite build completes without error.

- [ ] **Step 3: Commit**

```bash
git add resources/views/public/posts/show.blade.php resources/css/app.css
git commit -m "fix: prevent wide images stretching article page on mobile

Article is a CSS grid item; grid items default to min-width:auto, so a
wide image's intrinsic width forced the track past the viewport (text
shrank, page scrolled horizontally). Add min-w-0 to let the track shrink
and height:auto on prose images.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Polymorphic `content_references` migration

**Files:**
- Create: `database/migrations/2026_06_16_000001_create_content_references_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_references', function (Blueprint $table) {
            $table->id();
            $table->morphs('source'); // source_type, source_id
            $table->morphs('target'); // target_type, target_id
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'target_type', 'target_id'], 'content_references_unique');
            $table->index(['target_type', 'target_id'], 'content_references_target_idx');
        });

        // Migrate existing post→post references, if the old table exists.
        if (Schema::hasTable('post_references')) {
            DB::table('post_references')->orderBy('id')->each(function ($row) {
                DB::table('content_references')->insert([
                    'source_type' => 'post',
                    'source_id' => $row->source_post_id,
                    'target_type' => 'post',
                    'target_id' => $row->target_post_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            });

            Schema::dropIfExists('post_references');
        }
    }

    public function down(): void
    {
        Schema::create('post_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('target_post_id')->constrained('posts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['source_post_id', 'target_post_id']);
            $table->index('target_post_id');
        });

        if (Schema::hasTable('content_references')) {
            DB::table('content_references')
                ->where('source_type', 'post')
                ->where('target_type', 'post')
                ->orderBy('id')
                ->each(function ($row) {
                    DB::table('post_references')->insert([
                        'source_post_id' => $row->source_id,
                        'target_post_id' => $row->target_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                });
        }

        Schema::dropIfExists('content_references');
    }
};
```

- [ ] **Step 2: Run the migration against the test DB schema**

Run: `./vendor/bin/sail artisan migrate --force`
Expected: `content_references` created; `post_references` dropped. No errors.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_16_000001_create_content_references_table.php
git commit -m "feat: polymorphic content_references table (migrates post_references)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Register the morph map

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the morph map in `boot()`**

Replace the `boot()` body (`//`) with:

```php
\Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
    'post' => \App\Models\Post::class,
    'tweet' => \App\Models\Tweet::class,
]);
```

- [ ] **Step 2: Verify it loads**

Run: `./vendor/bin/sail artisan tinker --execute="echo (new App\Models\Post)->getMorphClass();"`
Expected: prints `post`.

- [ ] **Step 3: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "feat: morph map for post/tweet polymorphic references

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: `Reference` model

**Files:**
- Create: `app/Models/Reference.php`

- [ ] **Step 1: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reference extends Model
{
    protected $table = 'content_references';

    protected $fillable = [
        'source_type',
        'source_id',
        'target_type',
        'target_id',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/Reference.php
git commit -m "feat: Reference model over content_references

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: `ReferenceExtractor` (posts + tweets)

**Files:**
- Create: `app/Support/ReferenceExtractor.php`
- Create: `tests/Unit/ReferenceExtractorTest.php`
- Delete: `app/Support/PostReferenceExtractor.php`, `tests/Unit/PostReferenceExtractorTest.php` (in Step 6)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Support\ReferenceExtractor;
use PHPUnit\Framework\TestCase;

class ReferenceExtractorTest extends TestCase
{
    private function extract(string $body): array
    {
        return (new ReferenceExtractor())->extract($body);
    }

    public function test_matches_post_markdown_link(): void
    {
        $out = $this->extract('參考 [這篇](/zh/posts/unexpected-risks) 很讚');
        $this->assertSame([['type' => 'post', 'locale' => 'zh', 'slug' => 'unexpected-risks']], $out);
    }

    public function test_matches_post_html_href_and_trailing_slash(): void
    {
        $out = $this->extract('<a href="/en/posts/two-years-work-reflection/">x</a>');
        $this->assertSame([['type' => 'post', 'locale' => 'en', 'slug' => 'two-years-work-reflection']], $out);
    }

    public function test_matches_post_absolute_url_path_only(): void
    {
        $out = $this->extract('see https://jyu1999.com/ja/posts/mcp-first-experience here');
        $this->assertSame([['type' => 'post', 'locale' => 'ja', 'slug' => 'mcp-first-experience']], $out);
    }

    public function test_ignores_storage_image_paths_and_junk(): void
    {
        $body = '![x](/storage/imports/posts/foo/image.png) and /posts/{{ and /posts/bar/1.JPG';
        $this->assertSame([], $this->extract($body));
    }

    public function test_dedupes_repeated_post_links(): void
    {
        $body = '[a](/zh/posts/foo) [b](/zh/posts/foo)';
        $this->assertSame([['type' => 'post', 'locale' => 'zh', 'slug' => 'foo']], $this->extract($body));
    }

    public function test_matches_tweet_link_with_numeric_id(): void
    {
        $out = $this->extract('看這則 [推文](/zh/tweets/123)');
        $this->assertSame([['type' => 'tweet', 'id' => 123]], $out);
    }

    public function test_matches_tweet_trailing_slash_and_dedupes(): void
    {
        $out = $this->extract('[a](/en/tweets/45/) [b](/zh/tweets/45)');
        $this->assertSame([['type' => 'tweet', 'id' => 45]], $out);
    }

    public function test_mixed_post_and_tweet_links(): void
    {
        $out = $this->extract('[p](/zh/posts/foo) 與 [t](/zh/tweets/9)');
        $this->assertSame([
            ['type' => 'post', 'locale' => 'zh', 'slug' => 'foo'],
            ['type' => 'tweet', 'id' => 9],
        ], $out);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=ReferenceExtractorTest`
Expected: FAIL — class `App\Support\ReferenceExtractor` not found.

- [ ] **Step 3: Write the extractor**

```php
<?php

namespace App\Support;

use App\Models\Post;

class ReferenceExtractor
{
    /**
     * 從 body 抽取內部 post / tweet 連結,回傳去重後的條目。
     *
     * post:  /{locale}/posts/{slug}   → ['type' => 'post', 'locale', 'slug']
     * tweet: /{locale}/tweets/{id}    → ['type' => 'tweet', 'id' => int]
     *
     * @return array<int, array{type: string, locale?: string, slug?: string, id?: int}>
     */
    public function extract(string $body): array
    {
        $locales = implode('|', Post::SUPPORTED_LOCALES);

        $result = [];
        $seen = [];

        // Posts
        if (preg_match_all("#/({$locales})/posts/([A-Za-z0-9_-]+)/?#", $body, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $key = "post:{$row[1]}/{$row[2]}";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $result[] = ['type' => 'post', 'locale' => $row[1], 'slug' => $row[2]];
            }
        }

        // Tweets (numeric id; locale prefix required but resolution is by id)
        if (preg_match_all("#/({$locales})/tweets/(\d+)/?#", $body, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $id = (int) $row[2];
                $key = "tweet:{$id}";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $result[] = ['type' => 'tweet', 'id' => $id];
            }
        }

        return $result;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=ReferenceExtractorTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Delete the old extractor + its test**

Run: `git rm app/Support/PostReferenceExtractor.php tests/Unit/PostReferenceExtractorTest.php`
(`PostService` still imports it — that import is removed in Task 8. If you run the full suite now it will error; that's expected until Task 8.)

- [ ] **Step 6: Commit**

```bash
git add app/Support/ReferenceExtractor.php tests/Unit/ReferenceExtractorTest.php
git commit -m "feat: ReferenceExtractor parses post + tweet internal links

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: `HasReferences` trait + model wiring + Tweet `preview()`

**Files:**
- Create: `app/Models/Concerns/HasReferences.php`
- Modify: `app/Models/Post.php`
- Modify: `app/Models/Tweet.php`

- [ ] **Step 1: Write the trait**

```php
<?php

namespace App\Models\Concerns;

use App\Models\Reference;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasReferences
{
    /** References where this model is the source (content it mentions). */
    public function outgoingReferences(): MorphMany
    {
        return $this->morphMany(Reference::class, 'source');
    }

    /** References where this model is the target (content mentioning it). */
    public function incomingReferences(): MorphMany
    {
        return $this->morphMany(Reference::class, 'target');
    }

    /**
     * Mixed-type source models that publicly mention this model,
     * newest first. Both Post and Tweet expose status + published_at.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function publishedBacklinks(): Collection
    {
        return $this->incomingReferences()
            ->with('source')
            ->get()
            ->map(fn (Reference $r) => $r->source)
            ->filter()
            ->filter(fn ($m) => $m->status === 'published')
            ->sortByDesc('published_at')
            ->values();
    }
}
```

- [ ] **Step 2: Wire `Post` to the trait**

In `app/Models/Post.php`:
- Add `use App\Models\Concerns\HasReferences;` with the other imports.
- Add the trait to the class: change `use HasFactory, SoftDeletes;` → `use HasFactory, HasReferences, SoftDeletes;`.
- Delete the existing `outgoingReferences()` and `backlinks()` methods (lines 84–96).
- Remove the now-unused `use Illuminate\Database\Eloquent\Relations\BelongsToMany;` import **only if** no other relation uses it — `tags()` and `categories()` return `BelongsToMany`, so KEEP the import.

- [ ] **Step 3: Wire `Tweet` to the trait + add `preview()`**

In `app/Models/Tweet.php`:
- Add `use App\Models\Concerns\HasReferences;` import.
- Change `use HasFactory, SoftDeletes;` → `use HasFactory, HasReferences, SoftDeletes;`.
- Add this method (used by mention search + backlink rendering):

```php
/** Plain-text preview of the body (markdown/HTML stripped), for labels. */
public function preview(int $limit = 60): string
{
    $rendered = app(\App\Support\MarkdownRenderer::class)->render((string) $this->body);
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($rendered)));

    return \Illuminate\Support\Str::limit($plain, $limit);
}
```

- [ ] **Step 4: Sanity-check relations resolve**

Run: `./vendor/bin/sail artisan tinker --execute="echo get_class((new App\Models\Tweet)->outgoingReferences());"`
Expected: prints `Illuminate\Database\Eloquent\Relations\MorphMany`.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Concerns/HasReferences.php app/Models/Post.php app/Models/Tweet.php
git commit -m "feat: HasReferences trait on Post + Tweet; Tweet::preview()

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: `ReferenceSyncer` service

**Files:**
- Create: `app/Services/ReferenceSyncer.php`
- Create: `tests/Feature/ReferenceSyncTest.php`
- Delete: `tests/Feature/PostReferenceSyncTest.php` (Step 6)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tweet;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function posts(): PostService
    {
        return app(PostService::class);
    }

    private function tweets(): TweetService
    {
        return app(TweetService::class);
    }

    private function makePost(string $slug, string $body): Post
    {
        return $this->posts()->create([
            'locale' => 'zh', 'slug' => $slug, 'title' => $slug,
            'body' => $body, 'status' => Post::STATUS_PUBLISHED,
        ]);
    }

    private function makeTweet(string $body): Tweet
    {
        return $this->tweets()->create([
            'locale' => 'zh', 'body' => $body, 'status' => Tweet::STATUS_PUBLISHED,
        ]);
    }

    public function test_post_referencing_post_is_recorded(): void
    {
        $target = $this->makePost('target', 'body');
        $source = $this->makePost('source', "看 [連結](/zh/posts/{$target->slug})");

        $this->assertCount(1, $source->fresh()->outgoingReferences);
        $this->assertTrue(
            $target->fresh()->publishedBacklinks()->contains(fn ($m) => $m->is($source))
        );
    }

    public function test_post_referencing_tweet_is_recorded(): void
    {
        $tweet = $this->makeTweet('原推文');
        $post = $this->makePost('p', "引用推文 [t](/zh/tweets/{$tweet->id})");

        $backlinks = $tweet->fresh()->publishedBacklinks();
        $this->assertCount(1, $backlinks);
        $this->assertTrue($backlinks->first()->is($post));
    }

    public function test_tweet_referencing_post_is_recorded(): void
    {
        $post = $this->makePost('p2', 'body');
        $tweet = $this->makeTweet("看文章 [a](/zh/posts/{$post->slug})");

        $backlinks = $post->fresh()->publishedBacklinks();
        $this->assertCount(1, $backlinks);
        $this->assertTrue($backlinks->first()->is($tweet));
    }

    public function test_tweet_referencing_tweet_is_recorded(): void
    {
        $target = $this->makeTweet('被提及');
        $source = $this->makeTweet("看這則 [t](/zh/tweets/{$target->id})");

        $this->assertCount(1, $source->fresh()->outgoingReferences);
        $this->assertTrue($target->fresh()->publishedBacklinks()->first()->is($source));
    }

    public function test_removing_link_on_update_removes_reference(): void
    {
        $target = $this->makePost('t3', 'body');
        $source = $this->makePost('s3', "[x](/zh/posts/{$target->slug})");
        $this->assertCount(1, $source->fresh()->outgoingReferences);

        $this->posts()->update($source, ['body' => '已無連結']);

        $this->assertCount(0, $source->fresh()->outgoingReferences);
    }

    public function test_post_self_link_is_ignored(): void
    {
        $post = $this->makePost('me', '指向自己 [self](/zh/posts/me)');
        $this->assertCount(0, $post->fresh()->outgoingReferences);
    }

    public function test_tweet_self_link_is_ignored(): void
    {
        $tweet = $this->makeTweet('placeholder');
        $this->tweets()->update($tweet, ['body' => "self [x](/zh/tweets/{$tweet->id})"]);
        $this->assertCount(0, $tweet->fresh()->outgoingReferences);
    }

    public function test_unresolvable_link_is_ignored(): void
    {
        $source = $this->makePost('s4', '[ghost](/zh/posts/nope) [t](/zh/tweets/999999)');
        $this->assertCount(0, $source->fresh()->outgoingReferences);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=ReferenceSyncTest`
Expected: FAIL — `App\Services\ReferenceSyncer` not found (and `TweetService` does not yet sync). Task 8 wires the services.

- [ ] **Step 3: Write the syncer**

```php
<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tweet;
use App\Support\ReferenceExtractor;
use Illuminate\Database\Eloquent\Model;

class ReferenceSyncer
{
    /**
     * 解析 $source->body 內部連結,覆寫此 source 的 outgoing references。
     */
    public function sync(Model $source): void
    {
        $entries = (new ReferenceExtractor())->extract((string) ($source->body ?? ''));

        $targets = []; // dedupe key "type:id" => ['type' => , 'id' => ]
        foreach ($entries as $entry) {
            $target = $this->resolve($entry);
            if (! $target) {
                continue;
            }
            // skip self-reference (same type + same id)
            if ($target->getMorphClass() === $source->getMorphClass() && $target->getKey() === $source->getKey()) {
                continue;
            }
            $targets[$target->getMorphClass().':'.$target->getKey()] = [
                'type' => $target->getMorphClass(),
                'id' => $target->getKey(),
            ];
        }

        // Overwrite: drop all existing outgoing, re-insert the resolved set.
        $source->outgoingReferences()->delete();
        foreach ($targets as $t) {
            $source->outgoingReferences()->create([
                'target_type' => $t['type'],
                'target_id' => $t['id'],
            ]);
        }
    }

    private function resolve(array $entry): ?Model
    {
        if ($entry['type'] === 'post') {
            return Post::query()
                ->where('locale', $entry['locale'])
                ->where('slug', $entry['slug'])
                ->first();
        }

        if ($entry['type'] === 'tweet') {
            return Tweet::query()->find($entry['id']);
        }

        return null;
    }
}
```

- [ ] **Step 4: Commit the syncer (test still red until Task 8)**

```bash
git add app/Services/ReferenceSyncer.php tests/Feature/ReferenceSyncTest.php
git rm tests/Feature/PostReferenceSyncTest.php
git commit -m "feat: ReferenceSyncer resolves + overwrites cross-type references

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Wire `ReferenceSyncer` into PostService & TweetService

**Files:**
- Modify: `app/Services/PostService.php`
- Modify: `app/Services/TweetService.php`

- [ ] **Step 1: Update `PostService`**

- Replace import `use App\Support\PostReferenceExtractor;` with `use App\Services\ReferenceSyncer;`.
- Replace the body of `syncReferences()` (lines ~219–236) with a delegation:

```php
public function syncReferences(Post $post): void
{
    (new ReferenceSyncer())->sync($post);
}
```

(Keep the method name; `BackfillReferences` and create/update already call `$this->syncReferences($post)`.)

- [ ] **Step 2: Update `TweetService`**

- Add import `use App\Services\ReferenceSyncer;`.
- In `create()`, after `syncTagsAcrossGroup` and before `return $tweet->fresh(['tags']);`, add:

```php
(new ReferenceSyncer())->sync($tweet);
```

- In `update()`, after the tag sync and before `return $tweet->fresh(['tags']);`, add:

```php
(new ReferenceSyncer())->sync($tweet);
```

- [ ] **Step 3: Run the sync test — now green**

Run: `./vendor/bin/sail artisan test --filter=ReferenceSyncTest`
Expected: PASS (9 tests).

- [ ] **Step 4: Commit**

```bash
git add app/Services/PostService.php app/Services/TweetService.php
git commit -m "feat: sync references on post + tweet save via ReferenceSyncer

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: `TweetRepository::searchForMention`

**Files:**
- Modify: `app/Repositories/TweetRepository.php`

- [ ] **Step 1: Add the method (mirror PostRepository, search body)**

Add to `TweetRepository` (after `findPublished`), and add `use Illuminate\Database\Eloquent\Collection as EloquentCollection;` at the top:

```php
/**
 * 搜尋已發佈推文供 @ 提及自動完成。以 body 多關鍵字 ILIKE 命中;空查詢回最近發佈。
 *
 * @return EloquentCollection<int, Tweet>
 */
public function searchForMention(string $q, string $locale, ?int $exclude = null, int $limit = 6): EloquentCollection
{
    $base = Tweet::query()
        ->where('status', Tweet::STATUS_PUBLISHED)
        ->where('locale', $locale)
        ->when($exclude, fn ($qb) => $qb->where('id', '!=', $exclude));

    $tokens = preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    if (empty($tokens)) {
        return $base->orderByDesc('published_at')->limit($limit)->get(['id', 'body', 'locale']);
    }

    foreach ($tokens as $token) {
        $base->where('body', 'ILIKE', '%'.$token.'%');
    }

    return $base->orderByDesc('published_at')->limit($limit)->get(['id', 'body', 'locale']);
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Repositories/TweetRepository.php
git commit -m "feat: TweetRepository::searchForMention for @ autocomplete

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Unified `/admin/mentions/search` endpoint

**Files:**
- Create: `app/Http/Controllers/Admin/MentionController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/PostController.php`
- Create: `tests/Feature/Admin/MentionSearchTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionSearchTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): self
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_returns_both_posts_and_tweets_with_type(): void
    {
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'monitoring', 'title' => '導入監控',
            'body' => 'b', 'status' => Post::STATUS_PUBLISHED,
        ]);
        app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '今天聊聊監控這件事', 'status' => 'published',
        ]);

        $res = $this->actingAdmin()->getJson('/admin/mentions/search?q=監控&locale=zh');

        $res->assertOk();
        $types = collect($res->json())->pluck('type')->unique()->values()->all();
        sort($types);
        $this->assertSame(['post', 'tweet'], $types);
    }

    public function test_tweet_result_label_is_body_snippet_and_url_is_tweet_path(): void
    {
        $tweet = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '只有推文內容沒有標題', 'status' => 'published',
        ]);

        $res = $this->actingAdmin()->getJson('/admin/mentions/search?q=推文&locale=zh');

        $item = collect($res->json())->firstWhere('type', 'tweet');
        $this->assertNotNull($item);
        $this->assertStringContainsString('推文', $item['label']);
        $this->assertSame("/zh/tweets/{$tweet->id}", $item['url']);
    }

    public function test_excludes_self_by_type_and_id(): void
    {
        $tweet = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '自我參照測試內容', 'status' => 'published',
        ]);

        $res = $this->actingAdmin()->getJson(
            "/admin/mentions/search?q=自我&locale=zh&exclude_type=tweet&exclude_id={$tweet->id}"
        );

        $this->assertEmpty(collect($res->json())->where('type', 'tweet')->all());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=MentionSearchTest`
Expected: FAIL — route `/admin/mentions/search` not found (404).

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tweet;
use App\Repositories\PostRepository;
use App\Repositories\TweetRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentionController extends Controller
{
    public function search(Request $request, PostRepository $posts, TweetRepository $tweets): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $locale = (string) $request->query('locale', app()->getLocale());
        $excludeType = (string) $request->query('exclude_type', '');
        $excludeId = $request->integer('exclude_id');

        $excludePost = $excludeType === 'post' && $excludeId > 0 ? $excludeId : null;
        $excludeTweet = $excludeType === 'tweet' && $excludeId > 0 ? $excludeId : null;

        $postResults = $posts->searchForMention($q, $locale, $excludePost, 6)
            ->map(fn (Post $p) => [
                'type' => 'post',
                'id' => $p->id,
                'label' => $p->title,
                'url' => "/{$p->locale}/posts/{$p->slug}",
            ]);

        $tweetResults = $tweets->searchForMention($q, $locale, $excludeTweet, 6)
            ->map(fn (Tweet $t) => [
                'type' => 'tweet',
                'id' => $t->id,
                'label' => $t->preview(60),
                'url' => "/{$t->locale}/tweets/{$t->id}",
            ]);

        return response()->json($postResults->concat($tweetResults)->values()->all());
    }
}
```

- [ ] **Step 4: Replace the route**

In `routes/web.php`, change line 58 from:

```php
Route::get('posts/search', [Admin\PostController::class, 'search'])->name('posts.search');
```

to:

```php
Route::get('mentions/search', [Admin\MentionController::class, 'search'])->name('mentions.search');
```

- [ ] **Step 5: Remove the old `PostController::search`**

In `app/Http/Controllers/Admin/PostController.php`, delete the `search()` method (lines ~114–131) and any now-unused imports (`PostRepository`, `JsonResponse`) **only if** no other method uses them — check first; `index()` may use `PostRepository`. Keep imports still in use.

- [ ] **Step 6: Run to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=MentionSearchTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/MentionController.php routes/web.php app/Http/Controllers/Admin/PostController.php tests/Feature/Admin/MentionSearchTest.php
git commit -m "feat: unified /admin/mentions/search returning posts + tweets

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 11: Frontend — generalize `mentionBehavior` + tweet composer

**Files:**
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/posts/edit.blade.php`
- Modify: `resources/views/admin/tweets/edit.blade.php`

- [ ] **Step 1: Generalize `mentionBehavior` in `resources/js/app.js`**

Replace the `searchMentions(q)` URL/params block so it hits the unified endpoint with type-aware exclusion. Change the `params` and fetch URL inside `searchMentions` (lines ~272–281) to:

```js
const params = new URLSearchParams({
    q,
    locale: this.mentionLocale(),
    exclude_type: this.mentionExcludeType ?? '',
    exclude_id: this.mentionExcludeId ?? '',
});
try {
    const res = await fetch(`/admin/mentions/search?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        signal: this._mentionController.signal,
    });
```

- [ ] **Step 2: Add defaults to the `mentionBehavior` object**

At the top of the `mentionBehavior` object literal (after `mentionActive: false,`), add config fields with defaults so consumers can override via the component factory:

```js
mentionExcludeType: null,
mentionExcludeId: null,
```

- [ ] **Step 3: Update `markdownMediaInsert` factory (posts) to pass exclusion**

In `window.markdownMediaInsert = function ({ locale = 'zh', postId = null } = {})`, change the returned object so it sets the new fields. Replace the `locale, postId,` lines in the returned object with:

```js
locale,
postId,
mentionExcludeType: 'post',
mentionExcludeId: postId,
```

(`pickMention` already inserts `[label](url)`; it reads `item.title` today — change that line to `item.label`. In `pickMention`, replace `const safeTitle = item.title.replace(...)` with `const safeTitle = item.label.replace(/[\[\]]/g, '\\$&');`.)

- [ ] **Step 4: Add a `tweetComposer` factory**

After `window.markdownMediaInsert = ...`, add:

```js
/**
 * Tweet composer: YouTube paste prompt + @ mention autocomplete.
 * Expects x-ref="body" (textarea) in scope.
 */
window.tweetComposer = function ({ locale = 'zh', tweetId = null } = {}) {
    return {
        ...youtubePasteBehavior,
        ...mentionBehavior,
        locale,
        mentionExcludeType: 'tweet',
        mentionExcludeId: tweetId,
        handlePaste(event) {
            this.detectYoutubePaste(event);
        },
    };
};
```

- [ ] **Step 5: Update the posts editor dropdown to show `label` + type badge**

In `resources/views/admin/posts/edit.blade.php` (mention dropdown, lines ~81–89), change the result row to use `item.label` and add a small type tag:

```html
<template x-for="(item, i) in mentionResults" :key="item.type + ':' + item.id">
    <button type="button"
        @click="pickMention(item)"
        @mouseenter="mentionIndex = i"
        :class="i === mentionIndex ? 'bg-paper-2' : ''"
        class="w-full text-left px-3 py-2 border-b border-line last:border-0 hover:bg-paper-2 flex items-start gap-2">
        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-paper-2 text-ink-3 shrink-0"
            x-text="item.type === 'tweet' ? '推' : '文'"></span>
        <span class="min-w-0">
            <span class="block text-sm truncate" x-text="item.label"></span>
            <span class="block text-xs text-ink-3 font-mono truncate" x-text="item.url"></span>
        </span>
    </button>
</template>
```

- [ ] **Step 6: Wire the tweet editor**

In `resources/views/admin/tweets/edit.blade.php`:
- Change the composer wrapper (line ~45) from `<div class="relative" x-data="youtubePastePrompt()">` to:

```html
<div class="relative" x-data="tweetComposer({ locale: @js($tweet->locale ?: app()->getLocale()), tweetId: @js($tweet->id) })">
```

- Change the textarea `@input` (line ~48) from `@input="dismissYtPrompt()"` to `@input="dismissYtPrompt(); detectMention()"` and add `@keydown="onMentionKeydown($event)"`.
- After `@include('admin.partials.youtube-embed-prompt')` (line ~50), add the same dropdown markup as in Step 5:

```html
{{-- @ 提及搜尋下拉（文章 + 推文） --}}
<div x-show="mentionActive && mentionResults.length" x-cloak
    class="mt-1 bg-card border border-line rounded-md shadow-lg overflow-hidden max-h-64 overflow-y-auto">
    <template x-for="(item, i) in mentionResults" :key="item.type + ':' + item.id">
        <button type="button"
            @click="pickMention(item)"
            @mouseenter="mentionIndex = i"
            :class="i === mentionIndex ? 'bg-paper-2' : ''"
            class="w-full text-left px-3 py-2 border-b border-line last:border-0 hover:bg-paper-2 flex items-start gap-2">
            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-paper-2 text-ink-3 shrink-0"
                x-text="item.type === 'tweet' ? '推' : '文'"></span>
            <span class="min-w-0">
                <span class="block text-sm truncate" x-text="item.label"></span>
                <span class="block text-xs text-ink-3 font-mono truncate" x-text="item.url"></span>
            </span>
        </button>
    </template>
</div>
```

- [ ] **Step 7: Build assets**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 8: Manual smoke check (optional but recommended)**

Run: `./vendor/bin/sail up -d` then in the admin tweet editor type `@` — a dropdown of recent posts + tweets should appear; selecting inserts `[label](url)`. Repeat in the post editor; tweets should now also appear.

- [ ] **Step 9: Commit**

```bash
git add resources/js/app.js resources/views/admin/posts/edit.blade.php resources/views/admin/tweets/edit.blade.php public/build
git commit -m "feat: @ mention autocomplete in tweet editor; cross-type results

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 12: Public backlinks — shared partial + post & tweet pages

**Files:**
- Create: `resources/views/public/partials/backlinks.blade.php`
- Modify: `app/Http/Controllers/Public/PostController.php`
- Modify: `app/Http/Controllers/Public/TweetController.php`
- Modify: `resources/views/public/posts/show.blade.php`
- Modify: `resources/views/public/tweets/show.blade.php`
- Modify: `tests/Feature/Public/PostBacklinkDisplayTest.php`
- Create: `tests/Feature/Public/TweetBacklinkDisplayTest.php`

- [ ] **Step 1: Write the shared partial**

`resources/views/public/partials/backlinks.blade.php` — expects `$backlinks` (mixed Post/Tweet collection):

```blade
@if($backlinks->isNotEmpty())
    @php $loc = app()->getLocale(); @endphp
    <section class="mt-12 pt-6 border-t border-line">
        <div class="text-[10px] uppercase tracking-widest text-ink-3 font-mono mb-4">🔗 {{ __('public.mentioned_in') }}</div>
        <div class="grid gap-3">
            @foreach($backlinks as $bl)
                @if($bl instanceof \App\Models\Tweet)
                    <a href="{{ route('public.tweets.show', [$bl->locale, $bl->id]) }}"
                        class="block p-4 border border-line rounded-lg hover:border-accent transition-colors">
                        <div class="text-[10px] uppercase tracking-widest text-ink-3 font-mono mb-1">Tweet · {{ $bl->published_at?->format('Y/m/d') }}</div>
                        <div class="text-sm text-ink-2 line-clamp-2">{{ $bl->preview(120) }}</div>
                    </a>
                @else
                    <a href="{{ route('public.posts.show', [$bl->locale, $bl->slug]) }}"
                        class="block p-4 border border-line rounded-lg hover:border-accent transition-colors">
                        <div class="font-serif text-base font-semibold mb-1">{{ $bl->title }}</div>
                        @if($bl->excerpt)
                            <div class="text-xs text-ink-3 line-clamp-2">{{ $bl->excerpt }}</div>
                        @endif
                    </a>
                @endif
            @endforeach
        </div>
    </section>
@endif
```

- [ ] **Step 2: Update `Public\PostController@show` to use polymorphic backlinks**

Replace the `$backlinks = $post->backlinks()...->get(...)` block (lines ~62–65) with:

```php
$backlinks = $post->publishedBacklinks();
```

(The `view(...)` call already passes `'backlinks' => $backlinks`.)

- [ ] **Step 3: Use the partial in the post show view**

In `resources/views/public/posts/show.blade.php`, replace the entire `{{-- Backlinks：被以下文章提及 --}}` block (lines ~60–76) with:

```blade
{{-- Backlinks：被以下文章 / 推文提及 --}}
@include('public.partials.backlinks', ['backlinks' => $backlinks])
```

- [ ] **Step 4: Update `Public\TweetController@show` to pass backlinks**

In `app/Http/Controllers/Public/TweetController.php`, add `'backlinks' => $tweet->publishedBacklinks(),` to the `view('public.tweets.show', [...])` array.

- [ ] **Step 5: Add the section to the tweet show view**

In `resources/views/public/tweets/show.blade.php`, after `<x-tweet-card :tweet="$tweet" />`, add:

```blade
@include('public.partials.backlinks', ['backlinks' => $backlinks])
```

- [ ] **Step 6: Rewrite the post backlink test (polymorphic + cross-type)**

Replace `tests/Feature/Public/PostBacklinkDisplayTest.php` with:

```php
<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostBacklinkDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_post_source_appears_in_backlinks(): void
    {
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'target', 'title' => '目標文章',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'source', 'title' => '來源文章',
            'body' => '看 [這篇](/zh/posts/target)', 'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->get('/zh/posts/target')
            ->assertOk()
            ->assertSee('被以下文章')
            ->assertSee('來源文章');
    }

    public function test_tweet_source_appears_in_post_backlinks(): void
    {
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'target-x', 'title' => '被推文提及的文章',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '推薦這篇 [文章](/zh/posts/target-x)', 'status' => 'published',
        ]);

        $this->get('/zh/posts/target-x')
            ->assertOk()
            ->assertSee('推薦這篇');
    }

    public function test_draft_source_is_hidden(): void
    {
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'target2', 'title' => '目標2',
            'body' => 't', 'status' => Post::STATUS_PUBLISHED,
        ]);
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'draft-source', 'title' => '草稿來源',
            'body' => '[x](/zh/posts/target2)', 'status' => Post::STATUS_DRAFT,
        ]);

        $this->get('/zh/posts/target2')
            ->assertOk()
            ->assertDontSee('草稿來源');
    }
}
```

> `assertSee('被以下文章')` matches the existing `mentioned_in` zh string (`被以下文章提及`). If that string changed, align the assertion to `__('public.mentioned_in')`.

- [ ] **Step 7: Write the tweet backlink display test**

`tests/Feature/Public/TweetBacklinkDisplayTest.php`:

```php
<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TweetBacklinkDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_mentioning_tweet_appears_in_tweet_backlinks(): void
    {
        $tweet = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '原始推文', 'status' => 'published',
        ]);
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'cites-tweet', 'title' => '引用推文的文章',
            'body' => "見 [推文](/zh/tweets/{$tweet->id})", 'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->get("/zh/tweets/{$tweet->id}")
            ->assertOk()
            ->assertSee('引用推文的文章');
    }

    public function test_tweet_mentioning_tweet_appears_in_backlinks(): void
    {
        $target = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '被提及的推文', 'status' => 'published',
        ]);
        app(TweetService::class)->create([
            'locale' => 'zh', 'body' => "回應 [這則](/zh/tweets/{$target->id})", 'status' => 'published',
        ]);

        $this->get("/zh/tweets/{$target->id}")
            ->assertOk()
            ->assertSee('回應');
    }

    public function test_draft_source_hidden_from_tweet_backlinks(): void
    {
        $tweet = app(TweetService::class)->create([
            'locale' => 'zh', 'body' => '目標推文', 'status' => 'published',
        ]);
        app(PostService::class)->create([
            'locale' => 'zh', 'slug' => 'draft-cite', 'title' => '草稿引用',
            'body' => "[x](/zh/tweets/{$tweet->id})", 'status' => Post::STATUS_DRAFT,
        ]);

        $this->get("/zh/tweets/{$tweet->id}")
            ->assertOk()
            ->assertDontSee('草稿引用');
    }
}
```

- [ ] **Step 8: Run both display tests**

Run: `./vendor/bin/sail artisan test --filter=BacklinkDisplay`
Expected: PASS (PostBacklinkDisplayTest 3 + TweetBacklinkDisplayTest 3).

- [ ] **Step 9: Commit**

```bash
git add resources/views/public/partials/backlinks.blade.php app/Http/Controllers/Public/PostController.php app/Http/Controllers/Public/TweetController.php resources/views/public/posts/show.blade.php resources/views/public/tweets/show.blade.php tests/Feature/Public/PostBacklinkDisplayTest.php tests/Feature/Public/TweetBacklinkDisplayTest.php
git commit -m "feat: mixed-type backlinks on post + tweet public pages

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 13: `references:backfill` command (replaces `posts:backfill-references`)

**Files:**
- Create: `app/Console/Commands/BackfillReferences.php`
- Delete: `app/Console/Commands/BackfillPostReferences.php`
- Create: `tests/Feature/BackfillReferencesTest.php`
- Delete: `tests/Feature/BackfillPostReferencesTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostGroup;
use App\Models\Tweet;
use App\Models\TweetGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillReferencesTest extends TestCase
{
    use RefreshDatabase;

    /** Raw create bypasses the service sync, simulating legacy rows without references. */
    private function rawPost(string $slug, string $body, string $status = 'published'): Post
    {
        return Post::create([
            'post_group_id' => PostGroup::create()->id,
            'locale' => 'zh', 'slug' => $slug, 'title' => $slug,
            'body' => $body, 'status' => $status, 'last_modified_at' => now(),
        ]);
    }

    private function rawTweet(string $body, string $status = 'published'): Tweet
    {
        return Tweet::create([
            'tweet_group_id' => TweetGroup::create()->id,
            'locale' => 'zh', 'body' => $body, 'status' => $status,
            'published_at' => now(),
        ]);
    }

    public function test_backfill_populates_post_and_tweet_references(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '看 [這篇](/zh/posts/target)');
        $tweet = $this->rawTweet('被引用');
        $this->rawPost('cites-tweet', "見 [推文](/zh/tweets/{$tweet->id})");

        $this->assertSame(0, DB::table('content_references')->count());

        $this->artisan('references:backfill')->assertExitCode(0);

        $this->assertSame(2, DB::table('content_references')->count());
    }

    public function test_backfill_is_idempotent(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '[x](/zh/posts/target)');

        $this->artisan('references:backfill')->assertExitCode(0);
        $this->artisan('references:backfill')->assertExitCode(0);

        $this->assertSame(1, DB::table('content_references')->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->rawPost('target', 'target body');
        $this->rawPost('source', '[x](/zh/posts/target)');

        $this->artisan('references:backfill --dry-run')->assertExitCode(0);

        $this->assertSame(0, DB::table('content_references')->count());
    }

    public function test_unresolvable_link_is_reported_as_anomaly(): void
    {
        $this->rawPost('source', '[ghost](/zh/posts/does-not-exist)');

        $this->artisan('references:backfill --dry-run')
            ->assertExitCode(0)
            ->expectsOutputToContain('找不到對應');

        $this->artisan('references:backfill --dry-run')
            ->assertExitCode(0)
            ->expectsOutputToContain('posts/does-not-exist');
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/sail artisan test --filter=BackfillReferencesTest`
Expected: FAIL — command `references:backfill` not defined.

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Tweet;
use App\Services\ReferenceSyncer;
use App\Support\ReferenceExtractor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BackfillReferences extends Command
{
    protected $signature = 'references:backfill {--dry-run : 只印出將會發生的變更,不寫入資料庫}';

    protected $description = 'Scan all posts and tweets and (re)populate content_references from internal links.';

    public function handle(ReferenceSyncer $syncer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $extractor = new ReferenceExtractor();

        $this->info($dryRun ? '== DRY RUN（不會寫入資料庫）==' : '== Backfill content_references ==');

        $scanned = 0;
        $withLinks = 0;
        $totalRefs = 0;
        $unresolvedTotal = 0;

        foreach (Post::all()->concat(Tweet::all()) as $source) {
            $scanned++;
            $entries = $extractor->extract((string) $source->body);
            if (empty($entries)) {
                continue;
            }

            $resolved = [];
            $unresolved = [];
            foreach ($entries as $entry) {
                $target = $this->resolve($entry);
                if ($target && ! ($target->getMorphClass() === $source->getMorphClass() && $target->getKey() === $source->getKey())) {
                    $resolved[] = $this->label($entry);
                } elseif (! $target) {
                    $unresolved[] = $this->label($entry);
                }
            }

            $withLinks++;
            $totalRefs += count($resolved);
            $unresolvedTotal += count($unresolved);

            $line = sprintf(
                '[%s #%s] → %d 筆: %s',
                $source->getMorphClass(),
                $source->getKey(),
                count($resolved),
                $resolved ? implode(', ', $resolved) : '(無可解析的目標)'
            );
            if ($unresolved) {
                $line .= '  ⚠ 找不到對應: '.implode(', ', $unresolved);
            }
            $this->line($line);

            if (! $dryRun) {
                $syncer->sync($source);
            }
        }

        $this->newLine();
        $this->table(
            ['掃描', '含內部連結', '建立 reference', '無法解析(異常)'],
            [[$scanned, $withLinks, $totalRefs, $unresolvedTotal]]
        );

        if ($dryRun) {
            $this->warn('這是 dry-run,未寫入任何資料。確認無誤後拿掉 --dry-run 再跑。');
        } else {
            $this->info('完成。content_references 目前共 '.DB::table('content_references')->count().' 筆。');
        }

        return self::SUCCESS;
    }

    private function resolve(array $entry): ?Model
    {
        if ($entry['type'] === 'post') {
            return Post::query()->where('locale', $entry['locale'])->where('slug', $entry['slug'])->first();
        }
        if ($entry['type'] === 'tweet') {
            return Tweet::query()->find($entry['id']);
        }

        return null;
    }

    private function label(array $entry): string
    {
        return $entry['type'] === 'post'
            ? "posts/{$entry['slug']}"
            : "tweets/{$entry['id']}";
    }
}
```

- [ ] **Step 4: Remove the old command + its test**

Run: `git rm app/Console/Commands/BackfillPostReferences.php tests/Feature/BackfillPostReferencesTest.php`

- [ ] **Step 5: Run to verify it passes**

Run: `./vendor/bin/sail artisan test --filter=BackfillReferencesTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/BackfillReferences.php tests/Feature/BackfillReferencesTest.php
git commit -m "feat: references:backfill scans posts + tweets (replaces posts:backfill-references)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 14: Full verification + backfill run

**Files:** none (verification only)

- [ ] **Step 1: Run the whole test suite**

Run: `./vendor/bin/sail artisan test`
Expected: All green except the pre-existing `Tests\Unit\ExampleTest` failure (known, see project memory). No new failures. If any reference/mention/backlink test fails, fix before continuing.

- [ ] **Step 2: Build assets**

Run: `npm run build`
Expected: success.

- [ ] **Step 3: Dry-run the backfill against real data, then run it**

Run: `./vendor/bin/sail artisan references:backfill --dry-run`
Expected: report lists resolved/unresolved per post & tweet; review unresolved anomalies.

Run: `./vendor/bin/sail artisan references:backfill`
Expected: writes `content_references`; prints final count. (Existing post→post rows were already migrated in Task 2; this re-syncs everything and picks up any tweet links.)

- [ ] **Step 4: Final commit if anything pending (assets/build)**

```bash
git add -A
git commit -m "chore: rebuild assets for tweet mention feature" || echo "nothing to commit"
```

---

## Self-Review Notes (for the implementer)

- **Morph map matters everywhere:** `getMorphClass()` returns `post`/`tweet` only because of Task 3. If a `*_type` column shows a full class name, the morph map didn't load.
- **`status === 'published'`** is intentionally a string (not `Post::STATUS_PUBLISHED`) in the trait so it works uniformly for both models.
- **Test DB is pgsql** (per project memory); `ILIKE` and the partial indexes assume Postgres.
- **`mentioned_in` i18n key already exists** — do not re-add it.
- **Known pre-existing failure:** `Tests\Unit\ExampleTest` fails independent of this work; don't chase it.
