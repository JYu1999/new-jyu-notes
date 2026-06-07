# Tag Color Picker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hex text input in admin tag forms with a swatch-based color picker, then set colors + add vi/id translations for the 11 existing tags (local DB + production API), and log a changelog todo on production.

**Architecture:** A shared Blade partial (`admin/tags/_color-picker.blade.php`) driven by Alpine.js holds the picker state and writes the chosen hex into a hidden `<input name="color">`; both the create form and the per-row edit form in `admin/tags/index.blade.php` include it. Backend (validation, TagService) is untouched — empty string becomes `null` via `ConvertEmptyStringsToNull`. Data updates are one-off operations: tinker script locally, `PATCH /api/tags/{id}` on production.

**Tech Stack:** Laravel 12 (Sail), Blade, Alpine.js 3, Tailwind CSS 4, PHPUnit (pgsql test DB), curl + jq for production API.

**Spec:** `docs/superpowers/specs/2026-06-07-tag-color-picker-design.md`

**Important conventions:**
- All composer/artisan/php commands run via `./vendor/bin/sail` (never on host).
- `tests/Feature/ExampleTest.php` failure is pre-existing — ignore it.
- The production API token is supplied by the user in conversation. NEVER write it into any committed file.

---

### Task 1: Color picker partial (TDD)

**Files:**
- Test: `tests/Feature/Admin/TagViewTest.php` (create)
- Create: `resources/views/admin/tags/_color-picker.blade.php`
- Modify: `resources/views/admin/tags/index.blade.php` (create form lines 20–24, edit form lines 89–90)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagViewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@b.c',
            'password' => bcrypt('x'), 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_index_renders_color_picker_instead_of_text_input(): void
    {
        $tag = Tag::create(['color' => '#3f5e7a']);
        $tag->translations()->create(['locale' => 'zh', 'name' => '技術', 'slug' => 'tech']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.tags.index'))
            ->assertOk()
            // hidden input carries the value (create form + edit form)
            ->assertSee('type="hidden" name="color"', false)
            // preset swatch buttons exist (accent terracotta is in the palette)
            ->assertSee("color = '#b2543b'", false)
            // native custom picker present
            ->assertSee('type="color"', false);

        // the old free-text hex input is gone
        $this->assertStringNotContainsString('type="text" name="color"', $response->getContent());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --filter TagViewTest`
Expected: FAIL (old markup has `type="text" name="color"` and no hidden color input).

- [ ] **Step 3: Create the partial**

`resources/views/admin/tags/_color-picker.blade.php` — included with `['value' => <current hex or null>]`:

```blade
{{-- Color picker: preset swatches + native custom picker + clear. Submits via hidden input name="color". --}}
@php
    $presets = [
        '#b2543b' => '陶土紅',
        '#a83a2e' => '緋紅',
        '#c4783a' => '橙',
        '#b08234' => '赭黃',
        '#6f8a4a' => '草綠',
        '#4a6b3f' => '墨綠',
        '#4a7a6f' => '青瓷',
        '#3f5e7a' => '靛藍',
        '#7a5a8a' => '紫藤',
        '#8a6f4a' => '棕褐',
    ];
@endphp
<div x-data="{
        color: @js((string) ($value ?? '')),
        presets: @js(array_keys($presets)),
        get isCustom() { return this.color !== '' && !this.presets.includes(this.color) },
    }" class="flex items-center gap-1.5 flex-wrap">
    <input type="hidden" name="color" :value="color">
    @foreach($presets as $hex => $label)
        <button type="button" @click="color = '{{ $hex }}'" title="{{ $label }} {{ $hex }}"
            class="w-6 h-6 rounded-full border border-line transition-transform hover:scale-110"
            :class="color === '{{ $hex }}' && 'ring-2 ring-accent ring-offset-2 ring-offset-card scale-110'"
            style="background: {{ $hex }}"></button>
    @endforeach
    {{-- custom color via native picker --}}
    <label class="relative w-6 h-6 rounded-full border border-dashed border-line-2 cursor-pointer inline-flex items-center justify-center text-[11px] leading-none"
        :class="isCustom && 'ring-2 ring-accent ring-offset-2 ring-offset-card'"
        :style="isCustom ? `background:${color}` : ''" title="自訂顏色">
        <span x-show="!isCustom">🎨</span>
        <input type="color" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
            :value="color || '#b2543b'" @input="color = $event.target.value">
    </label>
    <span x-show="color" x-text="color" x-cloak class="text-xs font-mono text-ink-3 ml-1"></span>
    <button type="button" @click="color = ''" x-show="color" x-cloak
        class="text-xs text-ink-3 hover:text-danger" title="清除顏色">✕ 清除</button>
</div>
```

- [ ] **Step 4: Wire the partial into both forms in `index.blade.php`**

Replace the create-form color block (lines 20–24):

```blade
    <div>
        <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">顏色（選填）</label>
        @include('admin.tags._color-picker', ['value' => old('color')])
    </div>
```

Replace the edit-form color input (lines 89–90):

```blade
                            <div>
                                <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">顏色（選填）</label>
                                @include('admin.tags._color-picker', ['value' => $tag->color])
                            </div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --filter TagViewTest`
Expected: PASS

- [ ] **Step 6: Run the full suite**

Run: `./vendor/bin/sail artisan test`
Expected: green except the pre-existing `ExampleTest` failure.

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/tags/_color-picker.blade.php resources/views/admin/tags/index.blade.php tests/Feature/Admin/TagViewTest.php
git commit -m "feat: tag color picker with preset palette in admin tag forms"
```

---

### Task 2: Set colors + vi/id translations on local DB

One-off data operation — no code committed, no TDD.

- [ ] **Step 1: Write the tinker script to `storage/app/tag-update.php`**

```php
<?php

use App\Models\Tag;

$data = [
    1 => ['color' => '#3f5e7a', 'vi' => 'Công nghệ', 'id' => 'Teknologi'],
    2 => ['color' => '#b08234', 'vi' => 'Sự nghiệp', 'id' => 'Karier'],
    3 => ['color' => '#6f8a4a', 'vi' => 'Cuộc sống', 'id' => 'Kehidupan'],
    4 => ['color' => '#8a6f4a', 'vi' => 'Đọc sách', 'id' => 'Membaca'],
    5 => ['color' => '#4a7a6f', 'vi' => 'Du lịch', 'id' => 'Perjalanan'],
    6 => ['color' => '#c4783a', 'vi' => 'Goodidea Studio', 'id' => 'Goodidea Studio'],
    7 => ['color' => '#a83a2e', 'vi' => 'Nhật Bản', 'id' => 'Jepang'],
    8 => ['color' => '#5a6275', 'vi' => 'Bảo mật', 'id' => 'Keamanan'],
    9 => ['color' => '#4a6b3f', 'vi' => 'Công cụ năng suất', 'id' => 'Alat Produktivitas'],
    10 => ['color' => '#b2543b', 'vi' => 'Blog', 'id' => 'Blog'],
    11 => ['color' => '#7a5a8a', 'vi' => 'AI', 'id' => 'AI'],
];

foreach ($data as $tagId => $row) {
    $tag = Tag::with('translations')->find($tagId);
    if (! $tag) { echo "MISSING tag {$tagId}\n"; continue; }
    $tag->update(['color' => $row['color']]);
    $slug = $tag->translations->firstWhere('locale', 'en')?->slug;
    foreach (['vi', 'id'] as $loc) {
        $tag->translations()->updateOrCreate(['locale' => $loc], ['name' => $row[$loc], 'slug' => $slug]);
    }
    echo "OK {$tagId} {$row['color']} vi={$row['vi']} id={$row['id']}\n";
}
```

- [ ] **Step 2: Run it**

Run: `./vendor/bin/sail artisan tinker storage/app/tag-update.php`
Expected: `OK 1 … OK 11`, no `MISSING`.

- [ ] **Step 3: Verify**

Run a tinker query listing each tag's color + translation count; expect every tag to have a color and 5 translations (zh/en/ja/vi/id).

- [ ] **Step 4: Clean up**

Delete `storage/app/tag-update.php`.

---

### Task 3: Set colors + vi/id translations on production (jyu1999.com)

Uses the user-supplied API token (`Authorization: Bearer <token>`). NEVER commit it.

- [ ] **Step 1: Fetch current production tags**

`GET https://jyu1999.com/api/tags` — capture each tag's existing translations (locale/name/slug). Production ids 1–11 match local; verify names match before patching.

- [ ] **Step 2: PATCH each tag**

For each tag id, send `PATCH https://jyu1999.com/api/tags/{id}` with JSON body containing:
- `color`: from the table in Task 2
- `translations`: **all five locales** — the existing zh/en/ja entries exactly as fetched in Step 1 (name + slug), plus new vi/id entries (name from Task 2 table, slug = existing en slug)

> ⚠️ `TagService::update()` deletes any locale missing from the payload — omitting zh/en/ja would destroy them.

- [ ] **Step 3: Verify each response**

Each PATCH response must echo the expected `color` and 5 translations. Re-`GET /api/tags` at the end and confirm all 11 tags have colors and vi/id names. Report any mismatch.

---

### Task 4: Changelog todo on production

- [ ] **Step 1: POST the changelog entry**

`POST https://jyu1999.com/api/todos` with the same Bearer token:

```json
{
  "title": "Tag color picker in admin",
  "description": "Replaced the raw hex input in admin tag forms with a color picker (10 theme-matched preset swatches, native custom picker, clear button). Assigned colors to all existing tags and added Vietnamese and Indonesian translations.",
  "status": "done",
  "priority": "medium",
  "show_in_changelog": true
}
```

- [ ] **Step 2: Verify**

Response 201 with the created todo; confirm `show_in_changelog: true` and `status: done`. Remind the user: changelog entry is live, but the picker itself isn't deployed until master is pushed/deployed.

---

## Addendum Tasks (public colored chips + 語言 tag)

### Task 5: Public tag chips (TDD)

**Files:**
- Test (create): `tests/Feature/Public/TagColorDisplayTest.php`
- Modify: `resources/css/app.css` (add `.tag-chip` utility)
- Modify: `resources/views/public/home.blade.php:57`, `resources/views/public/posts/index.blade.php:69`, `resources/views/public/posts/show.blade.php:50`, `resources/views/components/post-card.blade.php:43`, `resources/views/components/tweet-card.blade.php:100`

- [ ] **Step 1: Write failing view test** — render a public page containing one colored tag (`#8a5a6b`) and one uncolored tag; assert colored chip outputs `tag-chip` + `style="--tag-color: #8a5a6b"`, uncolored keeps current static classes.
- [ ] **Step 2: Run test, verify FAIL** (`./vendor/bin/sail artisan test --filter TagColorDisplayTest`)
- [ ] **Step 3: Add CSS**

```css
/* Colored tag chips (public). Per-tag color via --tag-color. */
.tag-chip {
    --tag-mix: 80%;
    background: color-mix(in srgb, var(--tag-color) 12%, transparent);
    border-color: color-mix(in srgb, var(--tag-color) 30%, transparent);
    color: color-mix(in srgb, var(--tag-color) var(--tag-mix), var(--color-ink));
}
.tag-chip:hover { background: color-mix(in srgb, var(--tag-color) 20%, transparent); }
[data-theme='dark'] .tag-chip { --tag-mix: 55%; }
```

- [ ] **Step 4: Update the 5 render sites** — conditional classes, e.g. bordered pills (home / posts index / posts show):

```blade
class="font-mono text-xs px-3 py-1.5 border rounded-full {{ $tag->color ? 'tag-chip' : 'bg-card border-line text-ink-2 hover:text-accent hover:border-accent' }}"
@if($tag->color) style="--tag-color: {{ $tag->color }}" @endif
```

and borderless (post-card / tweet-card):

```blade
class="text-[10px] font-mono px-2 py-0.5 rounded {{ $tag->color ? 'tag-chip' : 'bg-paper-2 text-ink-3 hover:text-accent' }}"
@if($tag->color) style="--tag-color: {{ $tag->color }}" @endif
```

- [ ] **Step 5: Test passes; full suite green (ExampleTest pre-existing failure excepted)**
- [ ] **Step 6: Commit** `feat: show tag colors as tinted chips on public pages`

### Task 6: Create 語言 tag (local + production)

- zh 語言 / en Language / ja 言語 / vi Ngôn ngữ / id Bahasa, slug `language` all locales, color `#8a5a6b`
- Local: tinker (TagService::create or models directly); Production: `POST /api/tags` (token from user, never committed). Verify both.

### Task 7: Update changelog entry

- `PATCH /api/todos/7` on production: extend description to mention public tinted chips + new 語言 tag.
