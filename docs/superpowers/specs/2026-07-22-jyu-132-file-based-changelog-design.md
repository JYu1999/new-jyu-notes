# JYU-132: Changelog 應跟著每次 PR 一起，而不是透過 Todos 顯示 — 設計文件

日期：2026-07-22
分支：feature/JYU-132

## 背景

現行 changelog（見 [2026-06-02-changelog-todos-design.md](2026-06-02-changelog-todos-design.md)）是資料庫 `todos` 表 + API 驅動：要讓 AI 幫忙寫入一筆 changelog，得先建立一支 Sanctum API Token、告訴 AI 用 API 寫資料、用完再手動刪除 Token。這個流程本身沒有解決任何問題，只是為了「寫入」而繞了一大圈。

更根本的問題是：changelog 內容跟 PR 的 merge 是兩件分開的事——TODO 何時建立、何時標記完成，全靠事後想起來，而不是 PR 流程本身自動帶出來的副產品。

`todos` 後台（CRUD、優先級、狀態）本身除了餵給 changelog 之外沒有其他用途（已確認），可以整套一併簡化掉。

## 目標

- Changelog 條目改成**檔案**（一筆條目一個檔案），跟著程式碼改動進同一個 PR、同一次 code review。
- AI 在完成一次值得公開的改動後，自動讀取 diff / git log，生成英文說明，建立對應檔案——不需要 Token、不需要 API 呼叫。
- 公開 `/changelog` 頁面改成即時掃描檔案目錄渲染，視覺呈現與現行頁面一致。
- 移除現行 `todos` 資料表與圍繞它的後台 CRUD、API、ability 權限。
- 既有正式站上的 17 筆歷史 changelog 資料原樣遷移過來。

## 非目標（YAGNI）

- **規則式 CI 保險網**（PR 貼 changelog label 卻沒有對應檔案就擋 merge）：討論過，決定先不做。純靠 AI/開發者在 PR 中主動建立檔案。之後如果發現常漏做，可以再加。
- **GitHub Actions + LLM 自動生成**、**headless Claude Code + MCP**、**MCP 遠端連線**：都討論過，判斷對目前一人維護、PR 幾乎都經由 AI 對話產生的情境來說，多的基礎設施（CI 憑證、prompt injection 風險、持續費用）換不到對等的效益，故不採用。
- 同一天多筆條目的**顯示順序規則**（例如加數字前綴強制排序）：預設用檔名字母序即可，不額外加編號規則。量少、影響輕微，之後有需要再調整。
- 多語言 changelog、分頁、看板式專案管理功能：沿用前版設計文件的判斷，維持不做。

## 決策摘要（已與使用者確認）

1. **資料來源**：`changelog/` 目錄下的 Markdown 檔案，一筆條目一個檔案。
2. **檔名格式**：`YYYY-MM-DD-slug.md`（日期 = 改動完成當下的日期，slug 從英文說明轉換：小寫、空白轉 `-`）。
3. **內容格式**：純文字/單行英文說明，即 changelog 顯示的那一行，原樣寫入（含既有資料裡的 `(Author: ...)` 附註，比照歷史資料原樣保留）。
4. **產生時機**：AI 完成一次「使用者看得到差異」的改動後，主動讀取 diff/git log 生成內容並建立檔案，跟著其他改動一起進同一個 PR。純內部改動（重構、測試、CI 調整）不需要建立檔案。
5. **Review 方式**：這個 `.md` 檔案是 PR diff 的一部分，跟程式碼改動一起接受 code review；內容不滿意可直接修改或請 AI 依回饋調整，重新 commit。
6. **既有資料遷移**：不寫資料庫匯出腳本，改由 AI 直接讀取正式站公開的 `/changelog` 頁面內容，逐條轉成檔案，人工確認清單無誤後一次性 commit（詳見下方遷移清單）。
7. **`todos` 表**：不保留，一併移除相關程式碼；資料表用新增的 migration 下 `dropIfExists`，不刪除舊的 create migration 檔案（保留 migration 歷史完整性）。

## 元件

### 1. 公開 Changelog 頁面

- [ChangelogController.php](../../../app/Http/Controllers/Public/ChangelogController.php)：資料來源從 `TodoService::changelogGrouped()` 改成掃描 `changelog/*.md`，解析檔名日期、讀取內容，依日期分組（新到舊）。
- [changelog.blade.php](../../../resources/views/public/changelog.blade.php)：沿用現有樣板結構，視新資料形狀微調（若維持「日期 → 條目陣列」的形狀，改動應該很小）。
- 空狀態文案沿用「No entries yet.」。

### 2. AI 端建立檔案的流程

1. AI 完成一次值得公開的改動。
2. 讀取這次的 diff / 相關 commit，生成一行精簡英文說明。
3. 在 `changelog/` 目錄新增 `YYYY-MM-DD-slug.md`，內容為該說明。
4. 檔案跟其他改動的檔案一起加入同一個 PR。
5. 開發者於 code review 時一併檢視此檔案內容是否精確。

不涉及任何 Token、API、資料庫寫入。

### 3. 既有資料遷移

自正式站 `/changelog`（https://jyu1999.com/changelog）讀取的完整清單，共 17 筆：

| 檔名 | 內容 |
|---|---|
| `2026-07-12-fix-homepage-layout-collapsing-when-a-tweet-contains-a-very-long-url.md` | Fix homepage layout collapsing when a tweet contains a very long URL |
| `2026-07-11-add-offsite-backup-system-postgres-r2-to-b2.md` | Add offsite backup system (Postgres + R2 to B2) |
| `2026-07-11-add-google-analytics-ga4-to-public-pages.md` | Add Google Analytics (GA4) to public pages |
| `2026-07-11-fix-bullet-and-numbered-list-rendering.md` | Fix bullet and numbered list rendering(Author: yi-rong) |
| `2026-07-04-add-estimated-reading-time-to-articles.md` | Add estimated reading time to articles(Author: yi-rong) |
| `2026-06-16-wide-images-no-longer-break-article-layout-on-mobile.md` | Wide images no longer break article layout on mobile |
| `2026-06-16-posts-and-tweets-can-now-mention-each-other-with-backlinks-on-both.md` | Posts and tweets can now @-mention each other, with backlinks on both |
| `2026-06-12-post-mentions-and-backlinks.md` | Post @-mentions and backlinks |
| `2026-06-08-fullscreen-image-viewer.md` | Fullscreen image viewer |
| `2026-06-08-sensitive-image-blur-on-tweets.md` | Sensitive image blur on tweets |
| `2026-06-07-tag-colors-admin-picker-public-tinted-chips.md` | Tag colors: admin picker + public tinted chips |
| `2026-06-07-youtube-paste-to-embed-in-admin-editor.md` | YouTube paste-to-embed in admin editor |
| `2026-06-07-admin-media-upload-ux-improvements.md` | Admin media upload UX improvements |
| `2026-06-02-faster-images-media-now-served-via-cloudflare-r2.md` | Faster images — media now served via Cloudflare R2 |
| `2026-06-02-new-api-access-with-scoped-expiring-tokens-for-automation.md` | New: API access with scoped, expiring tokens (for automation) |
| `2026-06-02-new-lightweight-todo-roadmap-manager-in-the-admin-panel.md` | New: lightweight todo / roadmap manager in the admin panel |
| `2026-06-02-new-public-changelog-page.md` | New: public changelog page |

後三筆是舊系統本身（API Token、Todo 後台、changelog 頁面）上線的歷史紀錄，即使該系統即將被取代，仍是真實發生過的功能歷史，原樣保留。

### 4. 移除範圍

**刪除**
- `app/Models/Todo.php`
- `app/Services/TodoService.php`
- `app/Http/Controllers/Admin/TodoController.php`
- `app/Http/Controllers/Api/TodoController.php`
- `app/Http/Requests/Admin/Todo/StoreRequest.php`、`UpdateRequest.php`
- `app/Http/Requests/Api/Todo/StoreRequest.php`、`UpdateRequest.php`
- `resources/views/admin/todos/index.blade.php`
- `tests/Feature/TodoServiceTest.php`
- `tests/Feature/TodoModelTest.php`
- `tests/Feature/Admin/TodoViewTest.php`
- `tests/Feature/Admin/TodoAdminTest.php`
- `tests/Feature/Api/TodoApiTest.php`

**修改**
- [ChangelogController.php](../../../app/Http/Controllers/Public/ChangelogController.php)
- [changelog.blade.php](../../../resources/views/public/changelog.blade.php)
- [layouts/admin.blade.php](../../../resources/views/layouts/admin.blade.php)（移除側邊欄 Todos 項目）
- [routes/web.php](../../../routes/web.php)（移除 admin todos 路由 4 條）
- [routes/api.php](../../../routes/api.php)（移除 API todos 路由 5 條）
- [config/abilities.php](../../../config/abilities.php)（移除 `todos` 項目）
- [tests/Feature/AbilitiesTest.php](../../../tests/Feature/AbilitiesTest.php)（更新 abilities 總數期望值）
- [tests/Feature/ChangelogPageTest.php](../../../tests/Feature/ChangelogPageTest.php)（改測檔案式行為）

**新增**
- `changelog/` 目錄下 17 個歷史遷移檔案
- 一支新的 migration：`dropIfExists('todos')`（不刪除舊的 create migration 檔）

## 資料流

1. AI 完成一次值得公開的改動 → 讀 diff/git log → 在 `changelog/` 建立檔案 → 跟著程式碼進同一個 PR。
2. Code review 時一併檢視 changelog 檔案內容。
3. PR merge → 部署新版程式碼。
4. `/changelog` 頁面下次請求時即時掃描目錄，顯示新條目，無需額外動作。

## 錯誤處理

- `changelog/` 目錄不存在或無檔案 → 頁面顯示「No entries yet.」。
- 檔名不符合 `YYYY-MM-DD-*.md` 格式的檔案 → 略過不顯示（避免非預期檔案汙染頁面）。

## 測試策略

- `ChangelogController` / `/changelog` 頁面：讀取 `changelog/` 目錄下的 fixture 檔案，驗證依日期分組、新到舊排序、內容正確顯示；目錄為空時顯示空狀態文案；忽略不符命名規則的檔案。
- 移除所有 Todo 相關測試（`TodoServiceTest`、`TodoModelTest`、`Admin/TodoViewTest`、`Admin/TodoAdminTest`、`Api/TodoApiTest`）。
- `AbilitiesTest`：更新 abilities 總數期望值（移除 `todos` 的 4 項）。

## 安全

- 移除了寫入 changelog 的 API 與 Token 權限面，攻擊面同步縮小。
- changelog 內容跟程式碼一樣經過 PR review，不存在未經審核就對外公開的路徑。

## 風險與緩解

- **既有連結**：若外部有人加了 `/changelog` 的書籤或分享連結，URL 本身不變，不受影響。
- **歷史資料遺漏**：遷移清單已經過使用者逐條核對確認（見上表），非自動化腳本轉換，降低轉譯錯誤風險。
- **未來若忘記建立 changelog 檔案**：目前沒有強制機制（已列入非目標）。若之後發現經常遺漏，可以再加規則式 CI 檢查作為保險網，屬於獨立、可隨時疊加的後續強化，不影響本次設計。
