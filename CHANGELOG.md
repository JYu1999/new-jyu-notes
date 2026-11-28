## 2026-11-28
- Sign in to the admin with a passkey (Touch ID, Face ID or your phone): press "用 Passkey 登入" on the login page, no email or password needed; "Remember me" works the same 7 days, and password login stays as the fallback. Add, rename and delete passkeys on the new "帳號與安全" page, opened from your name at the bottom of the sidebar; adding or deleting one first asks for your password (or an existing passkey) again. After a password login on a device without a passkey, the admin offers to add one in a single tap, and "不用了" stops it asking on that device
- "Remember me" on the admin login now lasts 7 days instead of about a year, and remembered logins from before this change have to sign in again; if you get logged out while writing a post or tweet, the editor says so with a link to sign in again and keeps your text, then carries on autosaving when you come back
- Security update: upgraded Laravel, Symfony, Guzzle, CommonMark, Flysystem and front-end build tools (Vite, PostCSS, concurrently) to patched versions, closing every advisory reported by `composer audit` and `npm audit`
- "+ 新增 Tweet" on the admin tweet list now opens the same full-screen composer as the phone shortcut (centred on desktop), and cancelling or publishing returns to the page you came from; each photo has its own alt text and sensitive flag, replacing the tweet-wide sensitive switch

## 2026-11-27
- Removed the retired Todo feature from the admin; the Changelog page reads only CHANGELOG.md as before
- Old API tokens and the token-based /api endpoints are removed: AI assistants now connect through "AI 連線", which can also list content, tags, categories and media and rename tags and categories; existing tokens no longer work
- AI requests that need your confirmation (publishing or hiding by default) wait in "等你確認" on the AI page and in "Needs attention" on the admin home; open one to see the content and the proposed change, then approve to apply it or reject to leave everything as it was
- Manage connected AIs on the "AI 連線" page: see each connection's dates, last use and whether it's active, disconnect it instantly, choose allow / needs confirmation / deny for each kind of action (a single connection can only be stricter), review what AIs recently did with links to the version diff, and see the list of tools they can use
- Connect Claude (or another MCP client) to the blog from the new admin "AI 連線" page: add the connection URL as a connector, sign in, tick what it may do (read, write drafts and translations, organise tags / categories / media, ask to publish) and for how long (90 / 30 / 7 days); it can then search and read content, list missing translations, write drafts and translations, organise and upload media, and request publishing — but never delete anything
- Version history for posts, tweets and pages: see every saved version by day (who, when, characters added/removed), filter to AI or status changes, view only the changed paragraphs with additions and deletions marked plus title/slug/tag/category/status changes, compare with any older version, and restore the content from before a version (also works from the trash)
- Type "/" on an empty line in the post editor to insert a heading (H2/H3), quote, code block, divider, image upload, YouTube embed or @ mention; filter by typing, choose with ↑↓, Enter to insert, Esc to close
- Full-screen "new tweet" composer for phones (＋ 碎念 button on the admin home and tweet list): language and tag chips, up to 4 photos with upload progress and per-photo removal, a sensitive-content switch, @ mentions and draft autosave
- Changing the URL (slug) of a published post keeps the old link working: it permanently redirects to the new address
- Schedule a post to publish at a chosen date and time from the editor's publish options; scheduled posts are labelled with their time in the admin list, publish themselves when the time comes, and the schedule can be cancelled to keep the post as a draft
- New focus-mode post editor: switch or add language versions from the header, autosave for drafts, Write / Split / Preview modes (preview matches the public post page), a formatting toolbar, word count with reading time, ⌘S to save and ⌘⇧P to preview, plus a side panel with status, featured, slug, excerpt length, categories, tags, references in both directions and every translation's status; pasting a YouTube link can embed it from a chosen start time
- Choose the order of featured posts by dragging them in the admin "Featured" tab; newly featured posts go last, and the home page follows this order
- Select several posts in the admin list to publish, hide, tag, start English translations or move them to the trash at once
- Admin post list shows one row per article with the status of every language (published, draft, outdated, not yet translated — click to start a translation), plus search across title, slug and body, filters by category, tag and translation status, and sorting by modified, published or views
- Admin home "Needs attention" lists posts missing a translation, translations older than their original, and unused media, each linking to a filtered list; outdated translations are labelled in the post list and editor
- Admin home shows unique post views for the last 30 days (with change vs. the previous 30 days), a daily bar chart, and this week's four most-read posts
- Tag counts on the home page and Topics page now include tweets as well as posts
- Admin home: greeting, a quick tweet box (Markdown, @ mentions, paste images, tags, sensitive flag, language, ⌘↵ to publish) and "continue writing" with recent drafts plus an all-drafts page
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
