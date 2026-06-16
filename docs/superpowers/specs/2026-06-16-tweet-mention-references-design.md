# 跨型別互連:Tweet 也納入 `@` 提及 + Backlink

日期:2026-06-16

## 目標

既有的 `@` 提及 + Backlink（見 `2026-06-11-post-mentions-backlinks-design.md`）只支援 **Post → Post**。本次把 **Tweet** 也納入同一套互連系統,達成對稱的四向互連:

| 來源 ↓ \ 目標 → | Post | Tweet |
| --- | --- | --- |
| **Post** | ✅(既有) | 🆕 |
| **Tweet** | 🆕 | 🆕 |

具體交付(對應使用者選定範圍):

1. **文章可 `@` 提及推文** —— 文章編輯器的 `@` 自動完成可搜尋並插入推文連結;推文被文章提及時,推文頁顯示反向連結。
2. **推文編輯器加入 `@` 提及** —— 推文 composer 也獲得 `@` 自動完成,可提及文章與其他推文(目前推文完全沒有 `@` 功能)。
3. **推文可被互相提及** —— tweet → tweet 互連並顯示反向連結。
4. **推文頁面顯示反向連結** —— 推文公開頁顯示「被以下文章/推文提及」(來源不限型別)。

不含 Page、Category。

## 同時修正:行動裝置寬圖撐版（已於本次一併處理）

文章頁 `<article>` 是 CSS grid item(`grid lg:grid-cols-[1fr_220px]`),grid item 預設 `min-width: auto`,寬圖的內在寬度會把欄軌撐到超過視窗,造成文字縮小、頁面水平捲動、header 圖示錯位。

- `resources/views/public/posts/show.blade.php`:`<article class="max-w-3xl">` → 加 `min-w-0`,讓欄軌可縮到比內容窄。
- `resources/css/app.css`:`.prose-blog img` 補 `height: auto`(保險,維持比例)。

此為獨立小修,與下方互連重構無耦合,先行落地。

## 核心模型決策

### 從 post-only 改為多型別(polymorphic)單表

現有 `post_references(source_post_id, target_post_id)` 寫死兩端都是 post,無法表達跨型別。改為單一多型別資料表,以一致的方式承載四種組合,並對未來內容型別保持開放。

### 新增資料表 `content_references`

> 命名:**不用** `references` —— 它是 PostgreSQL 保留字(本專案測試/正式皆用 pgsql),會逼得到處要引號。用 `content_references`。

| 欄位 | 說明 |
| --- | --- |
| `id` | PK |
| `source_type` / `source_id` | `morphs('source')`,含連結的那筆(post 或 tweet) |
| `target_type` / `target_id` | `morphs('target')`,被連到的那筆 |
| `created_at` / `updated_at` | timestamps |

- `unique(source_type, source_id, target_type, target_id)`:去重。
- `index(target_type, target_id)`:前台 backlink 查詢用。
- 不分來源狀態都記錄;公開顯示時才過濾「來源已發佈」(沿用既有語意:草稿先連、發佈後 backlink 自動出現)。

### Morph map

在 `AppServiceProvider::boot()` 註冊 morph map,讓 `*_type` 存短字串而非完整 class name:

```php
Relation::enforceMorphMap([
    'post'  => \App\Models\Post::class,
    'tweet' => \App\Models\Tweet::class,
]);
```

好處:DB 值穩定(class 改名不影響資料)、與 extractor 回傳的 `type`(`'post'`/`'tweet'`)直接對齊。

### 資料遷移

新 migration:

1. 建立 `content_references`。
2. 把既有 `post_references` 每筆搬進來:`source_type='post'`、`target_type='post'`、id 照搬。
3. `drop` `post_references`。

無資料遺失;rollback 時反向重建 `post_references` 並搬回(best-effort,只還原 post→post 列)。

### `Reference` 模型 + `HasReferences` trait

- `App\Models\Reference`:`source(): MorphTo`、`target(): MorphTo`,`$table = 'content_references'`。
- `App\Models\Concerns\HasReferences`(Post、Tweet 共用):
  - `outgoingReferences(): MorphMany` —— `morphMany(Reference::class, 'source')`。
  - `incomingReferences(): MorphMany` —— `morphMany(Reference::class, 'target')`。
  - `publishedBacklinks(): Collection` —— 取 `incomingReferences` 並 eager-load `source`,map 成來源模型,過濾 `status = published`,依 `published_at` 倒序。Post 與 Tweet 皆有 `status`/`published_at` 欄位,邏輯一致。

> Laravel 沒有「兩端都 polymorphic 的 many-to-many」原生關聯,故以 `MorphMany` 到 `Reference` + eager-load 兩端,而非 `belongsToMany`。既有 `Post::outgoingReferences()`/`backlinks()` 的 `belongsToMany` 版本移除。

## 連結抽取:`ReferenceExtractor`

`App\Support\PostReferenceExtractor` 概化為 `App\Support\ReferenceExtractor`(保留純函式、易測):

`extract(string $body): array` 回傳去重後的條目,每筆帶 `type`:

- Post:`#/({$locales})/posts/([A-Za-z0-9_-]+)/?#` → `['type' => 'post', 'locale' => ..., 'slug' => ...]`
- Tweet:`#/({$locales})/tweets/(\d+)/?#` → `['type' => 'tweet', 'id' => (int) ...]`
  - tweet pattern 只吃數字,不會與 slug 互撞;tweet 由全域唯一 id 解析,URL 內 locale 僅作前綴比對、解析以 id 為準。

沿用既有排除規則(`/storage/...` 圖片、`/posts/{{` junk、結尾斜線)。

## 同步:`ReferenceSyncer`

新 service `App\Services\ReferenceSyncer`,取代 `PostService::syncReferences()` 內聯邏輯:

`sync(Model $source): void`:

1. 對 `$source->body` 跑 `ReferenceExtractor`。
2. 逐條解析:post → `Post::where(locale, slug)`;tweet → `Tweet::find(id)`。
3. 排除自連(同 type 同 id)。
4. 以解析出的 `(target_type, target_id)` 集合覆寫此 source 的 `content_references`(刪除已移除、插入新增、保留既有),於交易內、body 寫入後執行。

呼叫點:

- `PostService::create()` / `update()`:`$this->referenceSyncer->sync($post)`(取代既有 `syncReferences`)。
- `TweetService::create()` / `update()`:新增 `$this->referenceSyncer->sync($tweet)`。

## 後端搜尋端點(`@` 用,統一)

統一端點取代 `/admin/posts/search`:

- 路由:`GET /admin/mentions/search`(沿用 admin web 群組 + session 驗證)。
- Query:`q`、`locale`(預設當前 locale)、`exclude_type`、`exclude_id`(排除正在編輯的那筆)。
- 行為:同時查 Post 與 Tweet(各自 published + 指定 locale),`exclude_*` 命中時排除該筆。
  - Post:沿用 `PostRepository::searchForMention`(title/slug/excerpt 多關鍵字命中,標題優先)。
  - Tweet:新增 `TweetRepository::searchForMention`(`body` 多關鍵字 ILIKE;空查詢回最近發佈)。
- 回傳 JSON 陣列,每筆 `{ type, id, label, url }`:
  - Post:`label = title`,`url = /{locale}/posts/{slug}`。
  - Tweet:`label = ` 取 `body` 去 markdown/HTML 後前 ~60 字元的摘要片段,`url = /{locale}/tweets/{id}`。
- 各型別各取上限(如 6 筆),Post 在前、Tweet 在後;每筆附 `type` 供前端顯示小徽章。

## 編輯器 `@` 提及(前端 Alpine,`resources/js/app.js`)

`mentionBehavior` 概化:

- 改打 `/admin/mentions/search`,帶 `exclude_type` + `exclude_id`(取代單一 `exclude`)。
- 設定來源以 config 傳入:`mentionExcludeType`(`'post'`/`'tweet'`)、`mentionExcludeId`。
- 結果面板每列顯示 `label` + `url`,並依 `item.type` 顯示小徽章(文/推)。
- `pickMention` 插入 `[label](url)`(維持既有跳脫 `[ ]` 邏輯)。

接線:

- 文章編輯器:`markdownMediaInsert({ locale, postId })` 既有併入 `mentionBehavior`;改傳 `mentionExcludeType:'post'`、`mentionExcludeId:postId`。
- 推文編輯器:新增 `tweetComposer({ locale, tweetId })` = `youtubePasteBehavior` + `mentionBehavior`(比照 `markdownMediaInsert` 的 spread 寫法),傳 `mentionExcludeType:'tweet'`、`mentionExcludeId:tweetId`。`resources/views/admin/tweets/edit.blade.php` 的 composer `<div x-data="youtubePastePrompt()">` 換成 `tweetComposer(...)`,textarea 加 `@input` 的 `detectMention()`、`@keydown="onMentionKeydown($event)"`,並加上與文章編輯器相同的 `@` 下拉面板 markup。
  - 推文 composer 內無「插入媒體到 textarea」流程(媒體是另一塊 upload grid),故只併 youtube + mention,不含 media-insert。

## 前台顯示(共用 backlinks partial)

新增共用 partial `resources/views/public/partials/backlinks.blade.php`,吃一個混型別來源集合,逐筆依型別渲染:

- Post 來源:標題(serif)+ 摘要卡片,連到 `/{loc}/posts/{slug}`。
- Tweet 來源:`body` 摘要片段卡片,連到 `/{loc}/tweets/{id}`。
- 集合為空則整個區塊不顯示。

套用處:

- `resources/views/public/posts/show.blade.php`:既有 backlink 區塊改用此 partial(資料來源改為混型別)。
- `resources/views/public/tweets/show.blade.php`:在 `<x-tweet-card>` 之後新增 backlink 區塊(套同一 partial)。

控制器預載 backlink(避免 Blade 內查詢):

- `Public\PostController@show`:`$post->publishedBacklinks()`。
- `Public\TweetController@show`:`$tweet->publishedBacklinks()`,傳入 view。

### i18n

`mentioned_in` 字串已存在(`lang/{zh,en,ja,vi,id}/public.php`),partial 沿用即可。Admin `@` 面板文字維持硬編 zh,與既有 admin UI 一致。

## 既有資料 backfill

`posts:backfill-references` 概化為 **`references:backfill`**:

- 掃全部 Post 與 Tweet,跑與存檔時同一套 `ReferenceSyncer`。
- 保留 `--dry-run`、逐筆 resolved/unresolved 報表、「找不到對應」異常提示。
- 報表區分 post/tweet 來源與目標。
- 用覆寫式 sync,可重複執行不重複。

（命名變更:`posts:backfill-references` → `references:backfill`,對應測試一併更名。）

## 測試(TDD)

**抽取器 `ReferenceExtractor`(單元):**
- 既有 post 案例全數沿用(markdown / html / 絕對網址 / 結尾斜線 / 排除圖片與 junk / 去重 / 多 locale)。
- 新增:`/zh/tweets/123` → `type=tweet, id=123`;tweet 結尾斜線;post 與 tweet 混在同一 body;tweet pattern 不吃非數字。

**同步 `ReferenceSyncer`(feature):**
- post → tweet:文章 body 含 `/zh/tweets/{id}` → `content_references` 寫入(source=post,target=tweet)。
- tweet → post、tweet → tweet:同理。
- 移除連結後再存 → 對應 reference 消失。
- 自連忽略(post 自連、tweet 自連)。
- 無法解析的連結忽略。
- 既有 post → post 行為不退化。

**前台 backlink:**
- 推文頁顯示「被文章提及」(已發佈來源);草稿來源隱藏。
- 文章頁顯示「被推文提及」。
- tweet → tweet backlink 顯示。
- 無 backlink 時區塊不顯示。

**統一搜尋端點 `/admin/mentions/search`:**
- 回傳同時含 post 與 tweet,各帶 `type`/`label`/`url`。
- tweet 以 body 命中、label 為摘要片段。
- `exclude_type`+`exclude_id` 排除自己。
- 只回已發佈、限定 locale。

**`references:backfill` 指令:**
- 對既有 post→post + 新的 tweet 連結跑完後計數正確。
- 重複執行不重複;`--dry-run` 不寫入;unresolvable 報為異常。

**資料遷移:**
- 既有 `post_references` 列遷移後,於 `content_references` 以 `post`/`post` 型別存在且 backlink 仍正常。

## 已知限制(沿用 v1 接受)

- 目標 **改 slug**:舊連結會 404,且來源下次存檔重抽前 backlink 會消失(post 靠 slug 解析)。tweet 以數字 id 解析,不受此影響。
- 跨語言連結:沿用「精準對應」語意,既有資料極少見。
- `@` 面板游標精準定位仍為日後 polish;面板錨在 textarea 左下角(沿用 v1)。
