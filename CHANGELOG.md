## 2026-11-27
- Tag counts on the home page and Topics page now include tweets as well as posts
- Admin sidebar grouped into Writing / Organize / Site, with post, tweet and page counts
- External links in tweets show a preview card with the site and page title
- Topics page shows each category's description
- Press ⌘K / Ctrl+K (or tap search) on any page to search posts and tweets as you type, with highlighted matches, type tabs, keyboard navigation, recent searches and an "all languages" option
- Fix home "Latest" tabs showing two tabs as selected after switching
- Home timeline renders tweets with full formatting (Markdown, YouTube embeds, blurred-image warning with tap to reveal), shows post cover images, expands long tweets, and loads more automatically as you scroll
- Simpler home intro
- Changelog entries support inline Markdown (code, bold, links), and home sidebar tags show how many posts each has
- Tweets page can be filtered by tag, by month (with counts per month) and to only tweets with photos or videos; tweets are grouped by month with a "load older" button
- End of each post now shows who mentions it, previous / next posts, and up to three related posts on the same topic; the sidebar lists what the post links to
- Post pages show breadcrumbs, publish and update dates, other-language versions, a reading progress bar, a highlighted table of contents (a bottom sheet on phones), copy buttons on code blocks, adjustable text size, back to top and copy link
- Redesigned home page: intro, main topics, featured posts that adapt to how many are featured, a mixed timeline of posts and tweets with filters and "load more", plus series, tags and latest site update
- Posts list can be filtered to featured posts
- New site menu (Posts, Tweets, Topics, About) on every page, with a scrollable menu on phones, a header search box and ⌘K / Ctrl+K shortcut
- New Topics page listing every category, series and tag
- Dark mode no longer flashes a light page on load, and the site follows your system theme until you pick one
- Higher-contrast colors for dates and secondary text in light and dark mode
- Tags, section headings, buttons and cards now share one consistent style across public pages

## 2026-08-04
- Changelog now lives in CHANGELOG.md, updated as part of each PR instead of a todo list(Author: yi-rong)

## 2026-07-12
- Fix homepage layout collapsing when a tweet contains a very long URL

## 2026-07-11
- Add offsite backup system (Postgres + R2 to B2)
- Add Google Analytics (GA4) to public pages
- Fix bullet and numbered list rendering(Author: yi-rong)

## 2026-07-04
- Add estimated reading time to articles(Author: yi-rong)

## 2026-06-16
- Wide images no longer break article layout on mobile
- Posts and tweets can now @-mention each other, with backlinks on both

## 2026-06-12
- Post @-mentions and backlinks

## 2026-06-08
- Fullscreen image viewer
- Sensitive image blur on tweets

## 2026-06-07
- Tag colors: admin picker + public tinted chips
- YouTube paste-to-embed in admin editor
- Admin media upload UX improvements

## 2026-06-02
- Faster images — media now served via Cloudflare R2
- New: API access with scoped, expiring tokens (for automation)
- New: lightweight todo / roadmap manager in the admin panel
- New: public changelog page
