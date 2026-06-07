# Tag Color Picker + Existing Tag Colors & vi/id Translations — Design

**Date:** 2026-06-07
**Status:** Approved

## Problem

1. Admin tag forms (create + edit) take tag color as a raw hex text input — nobody remembers hex codes.
2. All 11 existing tags (local DB and production jyu1999.com, same ids 1–11) have `color = null`.
3. Existing tags only have zh/en/ja translations; vi and id are missing.

## Scope

- Replace the hex text input with a color picker component in `resources/views/admin/tags/index.blade.php` (both the create form and each row's edit form).
- Set colors and add vi/id translations for the 11 existing tags, in both local DB and production (via API with a user-supplied token; token is never committed).
- After completion, create a changelog todo on production (`POST /api/todos`, English title/description, `status: done`, `show_in_changelog: true`).

Out of scope: public-facing pages (post-card, tweet-card) keep their uniform tag styling — colors render only as the dot in the admin tag list for now. No backend changes (validation `regex:/^#[0-9a-fA-F]{6}$/`, nullable, already fits).

## Design

### Color picker component

- New Blade partial `resources/views/admin/tags/_color-picker.blade.php`, included by both the create form and the per-row edit form. Takes the current value (e.g. `@include(..., ['value' => $tag->color])`).
- Alpine.js component holding `color` state:
  - **10 preset swatches** (buttons) — clicking one sets `color`; the selected swatch gets a ring highlight.
  - **Custom button** wrapping a native `<input type="color">` — syncs into `color` on input. Shows ring + the custom color when the current value isn't one of the presets.
  - **Clear (✕) button** — resets `color` to empty string (= no color).
- The submitted value lives in `<input type="hidden" name="color" :value="color">`. Empty string is converted to `null` by Laravel's `ConvertEmptyStringsToNull`, so existing validation and service code are untouched.

### Preset palette (muted, paper-theme-friendly; works in light and dark mode)

| Name | Hex | Name | Hex |
|---|---|---|---|
| 陶土紅 | `#b2543b` | 青瓷 | `#4a7a6f` |
| 緋紅 | `#a83a2e` | 靛藍 | `#3f5e7a` |
| 橙 | `#c4783a` | 紫藤 | `#7a5a8a` |
| 赭黃 | `#b08234` | 棕褐 | `#8a6f4a` |
| 草綠 | `#6f8a4a` | 墨綠 | `#4a6b3f` |

### Existing tag data update (local + production)

Colors:

| id | Tag | Color | id | Tag | Color |
|---|---|---|---|---|---|
| 1 | 技術 | `#3f5e7a` | 7 | 日本 | `#a83a2e` |
| 2 | 職涯 | `#b08234` | 8 | 資安 | `#5a6275` |
| 3 | 生活 | `#6f8a4a` | 9 | 生產力工具 | `#4a6b3f` |
| 4 | 閱讀 | `#8a6f4a` | 10 | 部落格 | `#b2543b` |
| 5 | 旅遊 | `#4a7a6f` | 11 | 人工智慧 | `#7a5a8a` |
| 6 | 好想工作室 | `#c4783a` | | | |

New translations (slug = existing en slug, shared across locales):

| id | vi | id-locale |
|---|---|---|
| 1 | Công nghệ | Teknologi |
| 2 | Sự nghiệp | Karier |
| 3 | Cuộc sống | Kehidupan |
| 4 | Đọc sách | Membaca |
| 5 | Du lịch | Perjalanan |
| 6 | Goodidea Studio | Goodidea Studio |
| 7 | Nhật Bản | Jepang |
| 8 | Bảo mật | Keamanan |
| 9 | Công cụ năng suất | Alat Produktivitas |
| 10 | Blog | Blog |
| 11 | AI | AI |

- **Local:** one-off update via tinker/script (no seeder — tags aren't seeded in this project).
- **Production:** `PATCH https://jyu1999.com/api/tags/{id}` per tag. **Gotcha:** when `translations` is present, `TagService::update()` deletes any locale not in the payload — every PATCH must include all five locales (zh/en/ja/vi/id) with their current names/slugs plus the new vi/id entries.

## Error handling

- Picker: invalid state impossible by construction (only preset hexes, native picker output `#rrggbb`, or empty). Server-side validation unchanged as backstop.
- Production API: verify each PATCH response echoes the expected color and 5 translations; report any failure instead of assuming success.

## Testing

- Existing tag admin/API tests must stay green (`./vendor/bin/sail artisan test`, pgsql test DB; ExampleTest failure is pre-existing).
- Manual check of create + edit forms in admin (swatch select, custom color, clear, submit).

## Addendum (same day): public colored chips + 語言 tag

The original "out of scope" call on public pages is reversed — tags should show their colors publicly.

### Public tag chips

- New `.tag-chip` utility in `resources/css/app.css`, driven by a per-tag `--tag-color` CSS variable:
  - background `color-mix(in srgb, var(--tag-color) 12%, transparent)`, hover 20%
  - border-color `color-mix(... 30%, transparent)` (for the bordered pill variants)
  - text: light mode `color-mix(var(--tag-color) 80%, var(--color-ink))` (slightly darkened); `[data-theme='dark']` switches the mix to 55% toward the cream ink (lightened pastel) — implemented via a `--tag-mix` variable override
- Applied in all 5 public render sites — home popular tags, posts index sidebar, posts show, `components/post-card`, `components/tweet-card`: when `$tag->color` is set, the chip gets `tag-chip` + `style="--tag-color: <hex>"` replacing the static bg/text/border-color classes; **uncolored tags keep the exact current styling**. Per-site sizing/radius/padding classes unchanged.
- View test: a public page rendering one colored and one uncolored tag asserts `--tag-color` + `tag-chip` for the former and unchanged classes for the latter.

### 「語言」tag (data, local + production)

- Translations: zh 語言 / en Language / ja 言語 / vi Ngôn ngữ / id Bahasa; slug `language` for all locales; color 梅紫 `#8a5a6b`.
- Production via `POST /api/tags` with the user-supplied token.

### Changelog

- Update the existing production changelog entry (todo id 7) to also mention public colored chips and the new tag, instead of creating a second same-day entry.
