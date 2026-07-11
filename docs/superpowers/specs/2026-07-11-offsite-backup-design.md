# 異地備份方案設計（JYU-23）

日期：2026-07-11
狀態：已核准

## 背景與目標

部落格部署在 Laravel Cloud（Serverless Postgres 18），媒體檔存 Cloudflare R2（經
`s3` disk，由 media.jyu1999.com 提供）。目前沒有任何備份機制；若 Laravel Cloud
或 Cloudflare 出問題，資料會永久遺失。

目標：每天自動把 Postgres 資料與 R2 媒體備份到獨立於兩家服務商之外的
Backblaze B2，並提供隨時可手動執行的本地拉回腳本。可接受的最大資料遺失量
（RPO）為一天。

程式碼本身已在 GitHub，不在備份範圍內。

## 關鍵決策

### 決策一：備份目的地選 Backblaze B2

**考慮過的選項**：AWS S3、Google Cloud Storage、Backblaze B2、本地機器。

**選 B2 的理由**：

- **異地原則**：備份不能跟正本放同一家服務商。正本在 Laravel Cloud（底層
  AWS）與 Cloudflare，B2 與兩者皆無關，任一家全面故障都不影響備份。
- **成本**：$6/TB/月、前 10GB 免費、下載流量前 3 倍儲存量免費。部落格規模
  幾乎確定免費或每月不到 $1，明顯低於 S3 / GCS。
- **相容性**：提供 S3 相容 API，rclone、Laravel s3 driver 均可直接使用，
  不需要學新工具。
- **版本保留**：bucket 內建檔案版本功能，配 lifecycle 即可實現「誤刪可救回」，
  不需自己實作。

**風險與緩解**：

- Backblaze 是規模較小的公司，長期存續性不如 AWS/Google。緩解：備份資料
  同時有本地副本（`backup-pull.sh`）；且 B2 若出事，正本仍在，換一家目的地
  重跑備份即可，不會有資料遺失窗口以外的損失。
- B2 曾有區域性故障紀錄，可用性 SLA 不如 S3。緩解：備份對可用性要求低——
  某天備份失敗會收到通知，隔天補跑即可，RPO 仍在可接受範圍。

### 決策二：備份執行環境選 GitHub Actions

**考慮過的選項**：GitHub Actions 排程、spatie/laravel-backup 跑在 Laravel
Cloud scheduler、本地機器 cron。

**選 GitHub Actions 的理由**：

- **獨立性（最關鍵）**：備份系統跑在 Laravel Cloud 之外，Laravel Cloud 整個
  停擺時備份系統照常運作。spatie 方案跑在 Laravel Cloud 裡，平台掛掉備份
  也跟著停，違反異地備份精神。
- **可靠性**：相比本地機器 cron（依賴電腦開機、不睡眠，漏跑難察覺），
  GitHub Actions 排程穩定且失敗會寄 email。
- **環境自由**：runner 可自由安裝 `postgresql-client-18` 與 rclone；
  Laravel Cloud runtime 是否有 `pg_dump` binary 不可控。
- **增量媒體備份**：用 rclone 只傳有變動的檔案；spatie 每次全量打包媒體，
  媒體變多後又慢又貴。
- **零成本、零維運**：repo 已有 CI（JYU-78），免費額度綽綽有餘，credentials
  放 GitHub Secrets。

**風險與緩解**：

- 需開啟資料庫 public access，攻擊面變大。緩解：連線仍受強密碼保護；
  個人部落格的風險可接受。
- GitHub Secrets 集中存放多組 credentials，repo 權限外洩即全部外洩。緩解：
  R2 token 唯讀、B2 key 限定備份 bucket，即使外洩也動不了正本。
- GitHub 排程 cron 在尖峰時段可能延遲數十分鐘、且 repo 60 天無 commit 會
  自動停用排程。緩解：延遲對每日備份無影響；此 repo 持續開發中，停用風險低，
  且停用前 GitHub 會寄通知。
- GitHub 本身也是單點（同時是程式碼與備份系統的家）。緩解：GitHub 掛掉
  只影響「產生新備份」，既有備份都在 B2，資料面沒有共同單點。

## 整體架構

```
每天 19:00 UTC（台北 03:00）
GitHub Actions ──┬── pg_dump ──→ B2 bucket /db/db-YYYY-MM-DD.dump（保留 30 天）
                 └── rclone sync ──→ B2 bucket /media/（R2 的增量鏡像）

需要時手動
scripts/backup-pull.sh ──→ 從 B2 拉回 ~/Backups/jyu1999/
```

## 元件

### 1. GitHub Actions workflow（`.github/workflows/backup.yml`）

- 排程：cron `0 19 * * *`（台北 03:00），另提供 `workflow_dispatch` 手動觸發。
- DB 備份：
  - 從 PGDG apt repo 安裝 `postgresql-client-18`（需與伺服器 Postgres 18 版本相符）。
  - `pg_dump --format=custom`（自帶壓縮、支援 `pg_restore` 選擇性還原）。
  - 上傳前以 `pg_restore --list` 驗證 dump 檔完整性，驗證失敗即讓 workflow 失敗。
  - 以 rclone 上傳到 B2 `/db/db-YYYY-MM-DD.dump`。
- 媒體備份：`rclone sync` R2 bucket → B2 `/media/`，只傳有變動的檔案。
- 輪替：`rclone delete --min-age 30d` 清掉 `/db/` 下超過 30 天的 dump。
- 失敗通知：沿用 GitHub 內建的 workflow 失敗 email 通知。

### 2. 本地拉回腳本（`scripts/backup-pull.sh`）

- 用 rclone 從 B2 拉最新的 db dump 與媒體鏡像到 `~/Backups/jyu1999/`。
- 手動執行即可，不做排程。
- credentials 從本地 rclone config 或環境變數讀取，不寫死在腳本中。

### 3. 還原文件（`docs/backup-restore.md`）

- DB 還原：`pg_restore` 指令與注意事項。
- 媒體還原：rclone 從 B2 倒回 R2（或新的物件儲存）的指令。
- 沒驗證過還原流程的備份不算備份；文件中的指令需實際演練過。

## 防呆設計

- **媒體防誤刪**：`rclone sync` 是鏡像，R2 誤刪後下次備份 B2 會跟著刪。B2
  bucket 開啟版本保留（lifecycle：舊版本保留 30 天），誤刪後 30 天內可救回。
- **權限最小化**：
  - R2 使用唯讀 API token——備份系統被入侵也動不了正本。
  - B2 application key 只授權備份 bucket。
  - 所有 credentials 放 GitHub repo Secrets，不進 git。

## GitHub Secrets 清單

| Secret | 內容 |
|---|---|
| `BACKUP_DATABASE_URL` | Laravel Cloud Postgres 的 public 連線字串 |
| `R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY` | R2 唯讀 token |
| `R2_ENDPOINT` | R2 的 S3 endpoint |
| `R2_BUCKET` | R2 media bucket 名稱 |
| `B2_KEY_ID` / `B2_APP_KEY` | B2 application key（限定備份 bucket） |
| `B2_BUCKET` | B2 備份 bucket 名稱 |

## 一次性手動設定（使用者操作）

1. Laravel Cloud 後台開啟 database public access，取得連線字串。
2. 註冊 Backblaze B2、建 bucket、設定 lifecycle（舊版本保留 30 天）、建
   application key。
3. Cloudflare 建 R2 唯讀 API token。
4. 將上述 credentials 加入 GitHub repo Secrets。

## 不做的事（YAGNI）

- 不加密 dump：內容本來就是公開部落格，B2 bucket 為 private；加密會多一組
  金鑰管理負擔。dump 內含 users 資料表（密碼 hash）與 API tokens，已知悉並
  接受此取捨。
- 不做每小時備份、不做多地域複本。
- 不用 spatie/laravel-backup：理由見「決策二」。

## 驗收標準

1. 手動觸發 workflow 一次成功：B2 出現當日 dump 與媒體鏡像。
2. `pg_restore --list` 驗證步驟在 dump 損壞時會讓 workflow 失敗。
3. `scripts/backup-pull.sh` 能把 dump 與媒體拉回本地。
4. 依 `docs/backup-restore.md` 實際還原一次到本地 DB 成功。
