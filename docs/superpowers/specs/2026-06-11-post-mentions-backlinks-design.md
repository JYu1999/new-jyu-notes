# 文章互連:`@` 提及 + Backlink

日期:2026-06-11

## 目標

兩個互補的功能,解決「在後台寫文章時想引用其他文章很麻煩」的痛點:

1. **`@` 提及(編輯端)**:在內文 Markdown textarea 輸入 `@關鍵字` 即時搜尋自己的文章,選取後自動插入標準 Markdown 連結 —— 不必記語法、不必另外去找 URL。
2. **Backlink(前台)**:被提及的文章,在公開頁面底部顯示「被以下文章提及」,反向連回引用它的文章。

範圍僅限 **Post 對 Post** 的互連,不含 Tweet、Page、Category。

## 核心模型

每篇文章以 `post_group_id` + `locale` 區分,每個語言是獨立一筆 `posts`。Backlink 採 **精準對應**:reference 指向由 `locale` + `slug` 解析出的「確切那一篇」,不跨翻譯共享。實務上既有資料已是同語言互連(zh 連 zh、en 連 en),不會產生混語言清單。

### 新增資料表 `post_references`

| 欄位 | 說明 |
| --- | --- |
| `id` | PK |
| `source_post_id` | 含連結的那篇(FK `posts.id`,`onDelete cascade`) |
| `target_post_id` | 被連到的那篇(FK `posts.id`,`onDelete cascade`) |
| `created_at` / `updated_at` | timestamps |

- `unique(source_post_id, target_post_id)`。
- 兩個欄位皆加索引(尤其 `target_post_id`,前台 backlink 查詢用)。
- 不分來源狀態都記錄;公開顯示時才過濾「來源已發佈」。所以草稿先連好、之後一發佈,backlink 自動出現。

### Post 模型關聯(`app/Models/Post.php`)

- `outgoingReferences(): BelongsToMany` —— 本篇連出去的文章(`source` → `target`)。
- `backlinks(): BelongsToMany` —— 引用本篇的文章(`target` → `source`)。

## 連結抽取(後端,存檔時)

新增 `App\Support\PostReferenceExtractor`(純函式,易測)與在 `PostService` 注入的同步流程:

- **抽取**:掃 `body`,比對內部文章連結 path,涵蓋 Markdown `[..](…)` 與 HTML `href="…"`,以及帶 host 的絕對網址(只取 path)。
- **比對 pattern**:`#/(zh|en|ja|vi|id)/posts/([^/\s")]+)/?#` —— 要求 **locale 前綴 + 單段 slug + 可選結尾斜線**。
  - 此規則沿用 `PostService::equivalentUrlInLocale()` 既有慣例。
  - 自動排除 `/storage/imports/posts/.../image.png` 這類圖片路徑,以及 `/posts/{{` 這類匯入殘留 junk(皆無 locale 前綴或為多段路徑)。
  - 結尾斜線需在解析前去除(DB 的 slug 無斜線)。
- **解析**:每個 `(locale, slug)` 解析為未刪除的 `Post`;排除自己、去重,得到 target post id 集合。
- **同步**:`$post->outgoingReferences()->sync($targetIds)`,在 `PostService::create()` / `update()` 的交易內、body 寫入後執行。

## 後端搜尋端點(`@` 用)

- 路由:`GET /admin/posts/search`(admin web 中介層,session 驗證,沿用既有 admin 群組)。
- Query:`q`(關鍵字)、`locale`(預設當前文章 locale)、`exclude`(選填,排除正在編輯的 post id)。
- 條件:`status = published`、指定 `locale`、`title` / `slug` / `excerpt` 任一 `LIKE %q%`、limit 8,標題命中優先。
- 回傳 JSON:`[{ id, title, slug, locale, url }]`(`url` = `/{locale}/posts/{slug}`)。

只回已發佈 → 不會插入到會 404 的死連結,也不需要草稿標記。

## 編輯器 `@` 提及(前端 Alpine,`resources/js/app.js`)

內文 textarea 目前以 `x-data="markdownMediaInsert()"` 綁定。新增一個 `mentionBehavior` 物件,比照 `youtubePasteBehavior` 的 spread 寫法併入 `markdownMediaInsert`,共用同一個 `x-ref="body"`。

- **觸發**:`@` 位於**行首或空白後**才啟動(避免 email 如 `jyu@furuke.com` 誤觸)。
- **查詢**:`@` 之後到游標、且不含空白/換行的字串為 query;debounce(~250ms)打 `/admin/posts/search`(帶當前 locale 與 `exclude=當前 post id`)。
  - locale:編輯模式用該文章固定 locale;新增模式即時讀 `select[name=locale]` 的值。
- **結果面板**:在既有 `.relative` 容器內浮出(v1 錨在 textarea 左下角,穩定可用),每列顯示標題 + slug。
  - 鍵盤:↑↓ 移動選取、Enter / 點擊選取、Esc 關閉;textarea `@input` 既有的 `dismissYtPrompt()` 旁加上 mention 的關閉判斷。
- **插入**:把游標處的 `@查詢` 取代為 `[標題](/{locale}/posts/{slug})`,游標移到連結之後。存進 DB 的是普通 Markdown 連結,渲染與相容性不變。

*(備註:游標精準定位需 mirror-div 技巧,列為日後 polish;v1 面板錨在 textarea 左下角。)*

## 前台顯示(卡片清單)

`resources/views/public/posts/show.blade.php`,內文之後、標籤/系列導覽附近,新增「🔗 被以下文章提及」區塊:

- 查詢:`target = 本篇` 且來源 `published` 且未刪除,依來源 `published_at` 倒序。
- 版型(選定**卡片清單**):每篇一張卡片,標題(serif)+ 摘要(灰字),連到 `/{loc}/posts/{slug}`。
- 無 backlink 時整個區塊不顯示。

控制器(`Public\PostController@show`)預先載入 backlink 集合傳入 view,避免在 Blade 內查詢。

### i18n

`lang/{zh,en,ja,vi,id}/public.php` 新增 `mentioned_in` 字串(比照近期敏感媒體 overlay 的多語做法)。Admin 端 `@` 面板文字維持硬編 zh,與既有 admin UI 一致。

## 既有文章 backfill

新增 artisan 指令 `posts:backfill-references`:

- 對全部現有文章跑一次跟存檔時**同一套**抽取/同步邏輯(共用 `PostReferenceExtractor` + sync)。
- 用 `sync()` 覆寫,**可重複執行**,不會產生重複。
- 跑完後,現有約 93 筆內部連結立即成為已追蹤 reference,前台 backlink 馬上出現,不必逐篇重存。
- 之後新增/編輯文章存檔時自動維護,不需再跑指令。

## 測試

- **抽取器**(`PostReferenceExtractor` 單元測試):locale 前綴單段 slug 命中;Markdown 與 HTML 兩種寫法;帶 host 絕對網址;結尾斜線;排除 `/storage/imports/...` 圖片與 `/posts/{{` junk;排除自連。
- **存檔同步**:建立/更新含內部連結 → 寫入 `post_references`;移除連結後再存 → 對應 reference 消失;無法解析的連結忽略。
- **前台 backlink**:已發佈來源顯示;草稿來源隱藏;來源由草稿改發佈後出現;無 backlink 時區塊不顯示。
- **搜尋端點**:依 title/slug/excerpt 命中;限定 locale;只回已發佈;`exclude` 排除指定 id;limit 生效。
- **backfill 指令**:對既有資料跑完後 reference 數正確;重複執行不重複。

## 已知限制(v1 接受)

- **目標文章改 slug**:別篇 body 內的舊連結會 404,且該來源下次存檔重新抽取前,backlink 會消失(reference 靠 slug 重新解析)。日後可加 slug 轉址/歷史表解決,先不做。
- **跨語言連結**:若有 en 文章連到 `/zh/posts/x`,zh 頁會出現一張 en 標題卡片 —— 這是「精準對應」的正常結果,既有資料中極少見。
