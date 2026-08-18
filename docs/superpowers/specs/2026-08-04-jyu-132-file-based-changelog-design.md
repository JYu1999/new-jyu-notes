# JYU-132: Changelog 應跟著每次 PR 一起，而不是透過 Todos 顯示 — 設計文件

日期：2026-08-04
分支：feature/JYU-132

## 背景

現行 changelog（見 [2026-06-02-changelog-todos-design.md](2026-06-02-changelog-todos-design.md)）是資料庫 `todos` 表 + API 驅動：要讓 AI 幫忙寫入一筆 changelog，得先建立一支 Sanctum API Token、告訴 AI 用 API 寫資料、用完再手動刪除 Token。這個流程本身沒有解決任何問題，只是為了「寫入」而繞了一大圈。

更根本的問題是：changelog 內容跟 PR 的 merge 是兩件分開的事——TODO 何時建立、何時標記完成，全靠事後想起來，而不是 PR 流程本身自動帶出來的副產品。

（本文件是同一個 JYU-132 需求的重寫版本。先前的分支曾經實作過一次，並在後續討論中決定把 Todo 系統的處理方式從「整套刪除」改為「標記 deprecated」，因此重新開一個乾淨的分支、重寫本文件反映最終決定。）

## 目標

- Changelog 條目改成**單一 Markdown 檔案**（`CHANGELOG.md`），跟著程式碼改動進同一個 PR、同一次 code review。
- AI 在完成一次值得公開的改動後，自動讀取 diff / git log，生成英文說明，插入該檔案正確的日期區塊——不需要 Token、不需要 API 呼叫。
- 公開 `/changelog` 頁面改成即時解析該檔案渲染，視覺呈現與現行頁面一致。
- 既有正式站上的 17 筆歷史 changelog 資料原樣遷移過來。
- `todos` 系統（model、service、admin/API controller、資料庫表格、路由、ability）**標記為 deprecated，不刪除、功能不變**——changelog 不再讀寫它，但它作為一個獨立系統繼續正常運作。

## 非目標（YAGNI）

- **規則式 CI 保險網**（PR 貼 changelog label 卻沒有對應檔案就擋 merge）：討論過，決定先不做。純靠 AI/開發者在 PR 中主動建立檔案。之後如果發現常漏做，可以再加。
- **GitHub Actions + LLM 自動生成**、**headless Claude Code + MCP**、**MCP 遠端連線**：都討論過，判斷對目前一人維護、PR 幾乎都經由 AI 對話產生的情境來說，多的基礎設施（CI 憑證、prompt injection 風險、持續費用）換不到對等的效益，故不採用。
- 同一天多筆條目的**顯示順序規則**：不額外加編號機制，順序就是該日期區塊底下 bullet 的撰寫順序（由 controller 端解析時依檔案內順序保留，見下方「決策摘要」第 8 點）。
- 多語言 changelog、分頁、看板式專案管理功能：沿用前版設計文件的判斷，維持不做。
- **一筆一檔（fragment 式）方案**：討論過，一開始考慮是為了規避多 PR 並行時的 git 衝突風險、且讓 AI 的寫入動作永遠是「新增」而非「編輯既有內容」。重新評估後認為：(1) 這個部落格幾乎都是單人、依序處理 PR，並行衝突的實際機率低；(2) 兩種方案的 PR diff 可讀性沒有差異；(3) 單一檔案對人類更好瀏覽、controller 端不用管理一整個目錄。因此改採單一檔案。仍然承認的殘餘風險：「插入既有檔案」比「新增獨立檔案」多一種操作類別上的出錯可能，已於「風險與緩解」章節記錄並接受。
- **整套刪除 `todos` 系統**：這是最初的規劃方向，重新評估後改為**標記 deprecated**。原因：(1) `todos` 表可能存在未公開、未完整盤點過的資料，`DROP TABLE` 是不可逆操作，直接刪除有資料遺失風險；(2) 保留整套系統（後台 CRUD、API、ability）不動，換來的是可逆、零風險，唯一的代價只是它不再被 changelog 使用；(3) 用 `@deprecated` 標記傳達「刻意保留、非遺漏清理」的意圖，成本極低。
- **後台 UI 層級的 deprecated 提示**（頁面內橫幅、側邊欄標籤改名等）：討論後決定不做。目前後台只有維護者本人在用，`@deprecated` 的目標讀者是「未來看程式碼的人」，寫在程式碼註解裡已經足夠達到溝通目的，不需要額外的 UI 改動。

## 決策摘要（已與使用者確認）

1. **資料來源**：專案根目錄的單一檔案 `CHANGELOG.md`。
2. **內部格式**：以 `## YYYY-MM-DD` 作為日期區塊標題（ISO 格式，避免解析歧義），底下每筆條目一行 `- 英文說明`，區塊之間空一行。日期新到舊排列。
3. **內容格式**：每個 bullet 是純文字英文說明，即 changelog 顯示的那一行，原樣寫入（含既有資料裡的 `(Author: ...)` 附註，比照歷史資料原樣保留）。
4. **產生時機**：AI 完成一次「使用者看得到差異」的改動後，主動讀取 diff/git log 生成內容，插入 `CHANGELOG.md` 正確的日期區塊（當天已有區塊就加一行 bullet；沒有就在檔案最上方新增一個區塊），跟著其他改動一起進同一個 PR。純內部改動（重構、測試、CI 調整）不需要更新這個檔案。
5. **Review 方式**：`CHANGELOG.md` 的異動是 PR diff 的一部分，跟程式碼改動一起接受 code review；內容不滿意可直接修改或請 AI 依回饋調整，重新 commit。
6. **既有資料遷移**：不寫資料庫匯出腳本，改由 AI 直接讀取正式站公開的 `/changelog` 頁面內容，轉成單一檔案的完整內容，人工確認無誤後一次性 commit。
7. **`todos` 系統**：**保留，功能完全不變**（資料表、後台 CRUD、API、ability、路由、admin 側邊欄都不動）。唯一的改動是在 `Todo` model、`TodoService`、`Admin\TodoController`、`Api\TodoController` 這 4 個類別加上 `@deprecated` PHPDoc 註解，說明「自 JYU-132 起不再用於 changelog，僅保留供歷史資料查閱」。
8. **解析時的排序保證**：controller 端解析 `CHANGELOG.md` 後，日期區塊一律依日期**重新排序**（新到舊），不信任檔案裡區塊本身的物理順序；但同一區塊底下的 bullet 順序，保留檔案裡由上到下的原始順序。這讓「AI 不小心把新區塊插到錯的位置」不會影響最終顯示結果，只有「同一天內 bullet 的先後」需要 AI/開發者自己插對地方。

## 元件

### 1. 公開 Changelog 頁面

- [ChangelogController.php](../../../app/Http/Controllers/Public/ChangelogController.php)：資料來源從 `TodoService::changelogGrouped()` 改成讀取 `CHANGELOG.md`，用正則解析 `## YYYY-MM-DD` 區塊與其下的 `- ` bullet，依日期分組並重新排序（新到舊）。
- [changelog.blade.php](../../../resources/views/public/changelog.blade.php)：沿用現有樣板結構，不需要修改（`(object) ['title' => ...]` 的形狀跟現有的 `$item->title` 用法相容）。
- 空狀態文案沿用「No entries yet.」（檔案不存在或內容為空時）。

### 2. AI 端更新檔案的流程

1. AI 完成一次值得公開的改動。
2. 讀取這次的 diff / 相關 commit，生成一行精簡英文說明。
3. 開啟 `CHANGELOG.md`：若檔案最上方已經是今天日期的區塊，直接在該區塊底下加一行 `- 說明`；否則在檔案最上方新增一個 `## 今天日期` 區塊。
4. 檔案跟其他改動的檔案一起加入同一個 PR。
5. 開發者於 code review 時一併檢視這次插入的內容是否精確、位置是否正確。

不涉及任何 Token、API、資料庫寫入。

### 3. 既有資料遷移

自正式站 `/changelog`（https://jyu1999.com/changelog）讀取，轉成 `CHANGELOG.md` 的完整內容，共 17 筆、8 個日期區塊：

```markdown
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
```

最後一個區塊（2026-06-02）裡有 3 筆是舊系統本身（API Token、Todo 後台、changelog 頁面）上線的歷史紀錄，即使該系統即將被取代，仍是真實發生過的功能歷史，原樣保留。

### 4. Todo 系統的 deprecated 標記

在以下 4 個檔案的 class 上方加上 PHPDoc 註解，內容統一為：

```php
/**
 * @deprecated 自 JYU-132 起，changelog 改由 CHANGELOG.md 產生，
 * 此系統不再被 changelog 使用，僅保留供歷史資料查閱。
 */
```

- `app/Models/Todo.php`
- `app/Services/TodoService.php`
- `app/Http/Controllers/Admin/TodoController.php`
- `app/Http/Controllers/Api/TodoController.php`

除了這 4 段註解，**不做任何其他改動**：`database/migrations/..._create_todos_table.php`、`routes/web.php`、`routes/api.php`、`config/abilities.php`、`resources/views/layouts/admin.blade.php`（側邊欄）、`resources/views/admin/todos/index.blade.php`、所有既有的 Todo 相關測試（`TodoServiceTest`、`TodoModelTest`、`Admin/TodoViewTest`、`Admin/TodoAdminTest`、`Api/TodoApiTest`、`AbilitiesTest`）全部維持原樣、繼續通過。

### 5. 檔案異動範圍

**新增**
- `CHANGELOG.md`（單一檔案，內容為上方遷移內容）
- `config/changelog.php`（存放 `CHANGELOG.md` 的路徑，方便測試時指向暫存檔案）

**修改**
- [ChangelogController.php](../../../app/Http/Controllers/Public/ChangelogController.php)
- [tests/Feature/ChangelogPageTest.php](../../../tests/Feature/ChangelogPageTest.php)（改測檔案式行為）
- `app/Models/Todo.php`（只加 `@deprecated` 註解）
- `app/Services/TodoService.php`（只加 `@deprecated` 註解）
- `app/Http/Controllers/Admin/TodoController.php`（只加 `@deprecated` 註解）
- `app/Http/Controllers/Api/TodoController.php`（只加 `@deprecated` 註解）

**不動**
- `changelog.blade.php`、`database/migrations/..._create_todos_table.php`、`routes/web.php`、`routes/api.php`、`config/abilities.php`、`resources/views/layouts/admin.blade.php`、`resources/views/admin/todos/index.blade.php`、所有既有 Todo 測試檔案。

## 資料流

1. AI 完成一次值得公開的改動 → 讀 diff/git log → 插入 `CHANGELOG.md` 正確的日期區塊 → 跟著程式碼進同一個 PR。
2. Code review 時一併檢視這次插入的內容跟位置。
3. PR merge → 部署新版程式碼。
4. `/changelog` 頁面下次請求時即時解析檔案，顯示新條目，無需額外動作。
5. Todo 系統維持獨立運作，不受 changelog 機制變動影響。

## 錯誤處理

- `CHANGELOG.md` 不存在或內容為空 → 頁面顯示「No entries yet.」。
- 不屬於任何 `## YYYY-MM-DD` 區塊的內容（例如檔案開頭的說明文字、格式錯誤的日期標題）→ 略過不顯示，不會讓整頁渲染失敗。

## 測試策略

- `ChangelogController` / `/changelog` 頁面：用暫存的 `CHANGELOG.md` fixture 內容，驗證依日期分組、依日期重新排序（即使檔案裡區塊順序寫反也要能排對）、同一區塊內 bullet 保留原始順序、內容正確顯示；檔案不存在或為空時顯示空狀態文案；忽略無法辨識的內容（沒有日期區塊包住的文字、格式錯誤的標題）；日期格式對但不是真實存在的日期（例如 `2026-13-01`）要被當成格式錯誤忽略，不能讓頁面出錯。
- 既有的 Todo 相關測試（`TodoServiceTest`、`TodoModelTest`、`Admin/TodoViewTest`、`Admin/TodoAdminTest`、`Api/TodoApiTest`、`AbilitiesTest`）維持原樣，不修改、不刪除，全部要繼續通過。

## 安全

- `todos` 系統（API、Token ability）維持原樣繼續存在，攻擊面不變——這次改動不影響它的安全性。
- changelog 內容跟程式碼一樣經過 PR review，不存在未經審核就對外公開的路徑。

## 風險與緩解

- **既有連結**：`/changelog` URL 本身不變，不受影響。
- **歷史資料遺漏**：遷移內容經過使用者逐條核對確認（對照正式站 `/changelog` 頁面），非自動化腳本轉換，降低轉譯錯誤風險。
- **AI 插入既有檔案時寫錯位置/格式**：相較一筆一檔（純新增，不可能動到既有內容），插入共用檔案多了一種操作類別上的出錯可能。緩解方式有兩層：(1) controller 端解析時對日期區塊**強制重新排序**（見決策摘要第 8 點），就算插入的物理位置不對，顯示結果仍然正確；(2) 這個異動本來就是 PR diff 的一部分，會經過 code review，插入內容或格式有誤會在 review 階段被發現。
- **未來若忘記更新 `CHANGELOG.md`**：目前沒有強制機制（已列入非目標）。若之後發現經常遺漏，可以再加規則式 CI 檢查作為保險網。
- **`todos` 資料保留但無人維護**：因為決定不刪除，`todos` 表跟其資料會繼續存在但沒有新資料流入（changelog 不再寫入）。這是刻意的取捨——保留資料完整性優先於系統精簡。
