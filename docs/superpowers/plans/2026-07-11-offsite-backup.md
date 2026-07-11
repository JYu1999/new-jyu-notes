# 異地備份（JYU-23）Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 每天用 GitHub Actions 把 Laravel Cloud Postgres 與 Cloudflare R2 媒體備份到 Backblaze B2，並提供本地拉回腳本與還原文件。

**Architecture:** 一個排程 workflow（pg_dump → 驗證 → 上傳 B2；rclone 增量同步 R2 → B2；30 天輪替），加上一支手動執行的本地拉回 shell script，以及一份含一次性設定與還原演練步驟的文件。詳見 spec：`docs/superpowers/specs/2026-07-11-offsite-backup-design.md`。

**Tech Stack:** GitHub Actions、pg_dump/pg_restore（PostgreSQL 18 client）、rclone（S3 API 讀 R2、B2 native API 寫 B2）、bash。

## Global Constraints

- Postgres client 版本必須是 18（與伺服器一致），從 PGDG apt repo 安裝。
- 排程 cron：`0 19 * * *`（UTC，即台北 03:00）。
- DB dump 保留 30 天；媒體鏡像靠 B2 lifecycle 版本保留防誤刪（bucket 設定，非程式碼）。
- 所有 credentials 一律來自 GitHub Secrets 或本地 rclone config / 環境變數，絕不寫進 git。
- Secrets 名稱（照 spec）：`BACKUP_DATABASE_URL`、`R2_ACCESS_KEY_ID`、`R2_SECRET_ACCESS_KEY`、`R2_ENDPOINT`、`R2_BUCKET`、`B2_KEY_ID`、`B2_APP_KEY`、`B2_BUCKET`。
- 這些是 infra 檔案，無法跑單元測試；每個 task 的「測試」= 語法/靜態驗證（actionlint、bash -n、shellcheck），最終驗收靠 merge 後手動 `workflow_dispatch`（Task 4）。

---

### Task 1: 備份 workflow（`.github/workflows/backup.yml`）

**Files:**
- Create: `.github/workflows/backup.yml`

**Interfaces:**
- Consumes: GitHub Secrets（見 Global Constraints 的 8 個名稱）
- Produces: B2 bucket 內的 `db/db-YYYY-MM-DD.dump` 與 `media/` 鏡像（Task 2 的 `backup-pull.sh` 與 Task 3 的還原文件依賴這個佈局）

- [ ] **Step 1: 建立 workflow 檔**

寫入 `.github/workflows/backup.yml`（完整內容）：

```yaml
name: Backup

on:
  schedule:
    - cron: '0 19 * * *' # 台北時間 03:00
  workflow_dispatch:

concurrency:
  group: backup
  cancel-in-progress: false

jobs:
  backup:
    runs-on: ubuntu-latest
    timeout-minutes: 60

    env:
      # rclone 用環境變數定義 remote，不落地 config 檔
      # remote "r2"：以 S3 API 唯讀存取 Cloudflare R2
      RCLONE_CONFIG_R2_TYPE: s3
      RCLONE_CONFIG_R2_PROVIDER: Cloudflare
      RCLONE_CONFIG_R2_ACCESS_KEY_ID: ${{ secrets.R2_ACCESS_KEY_ID }}
      RCLONE_CONFIG_R2_SECRET_ACCESS_KEY: ${{ secrets.R2_SECRET_ACCESS_KEY }}
      RCLONE_CONFIG_R2_ENDPOINT: ${{ secrets.R2_ENDPOINT }}
      # remote "b2"：以 B2 native API 寫入備份 bucket
      RCLONE_CONFIG_B2_TYPE: b2
      RCLONE_CONFIG_B2_ACCOUNT: ${{ secrets.B2_KEY_ID }}
      RCLONE_CONFIG_B2_KEY: ${{ secrets.B2_APP_KEY }}
      R2_BUCKET: ${{ secrets.R2_BUCKET }}
      B2_BUCKET: ${{ secrets.B2_BUCKET }}

    steps:
      - name: Install postgresql-client-18 (PGDG)
        run: |
          sudo apt-get update
          sudo apt-get install -y postgresql-common
          sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
          sudo apt-get install -y postgresql-client-18

      - name: Install rclone
        run: sudo apt-get install -y rclone

      - name: Dump database
        env:
          DATABASE_URL: ${{ secrets.BACKUP_DATABASE_URL }}
        run: |
          DUMP_FILE="db-$(date -u +%F).dump"
          echo "DUMP_FILE=${DUMP_FILE}" >> "$GITHUB_ENV"
          pg_dump "$DATABASE_URL" \
            --format=custom \
            --no-owner \
            --no-privileges \
            --file="$DUMP_FILE"

      - name: Verify dump integrity
        run: pg_restore --list "$DUMP_FILE" > /dev/null

      - name: Upload dump to B2
        run: rclone copyto "$DUMP_FILE" "b2:${B2_BUCKET}/db/${DUMP_FILE}" --stats-one-line

      - name: Sync media R2 -> B2
        run: |
          rclone sync "r2:${R2_BUCKET}" "b2:${B2_BUCKET}/media" \
            --fast-list \
            --transfers 16 \
            --stats-one-line

      - name: Prune dumps older than 30 days
        run: rclone delete "b2:${B2_BUCKET}/db" --min-age 30d --stats-one-line
```

- [ ] **Step 2: 用 actionlint 驗證**

Run: `docker run --rm -v /Users/zhanjieyu/Code/new-jyu-notes:/repo -w /repo rhysd/actionlint:latest -color`
Expected: 無輸出、exit 0（若 docker 沒開，退而求其次：`ruby -ryaml -e 'YAML.load_file(".github/workflows/backup.yml"); puts "yaml ok"'` 應輸出 `yaml ok`）

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/backup.yml
git commit -m "JYU-23: 每日備份 workflow（Postgres + R2 → B2）"
```

---

### Task 2: 本地拉回腳本（`scripts/backup-pull.sh`）

**Files:**
- Create: `scripts/backup-pull.sh`（chmod +x）

**Interfaces:**
- Consumes: Task 1 產出的 B2 佈局 `db/db-YYYY-MM-DD.dump` 與 `media/`；本地 rclone config 中名為 `b2` 的 remote；環境變數 `B2_BUCKET`
- Produces: `~/Backups/jyu1999/db/<最新 dump>` 與 `~/Backups/jyu1999/media/`（Task 3 還原文件引用此路徑與用法）

- [ ] **Step 1: 建立腳本**

寫入 `scripts/backup-pull.sh`（完整內容）：

```bash
#!/usr/bin/env bash
#
# 從 Backblaze B2 把最新的 DB dump 與媒體鏡像拉回本地。
#
# 前置需求：
#   1. brew install rclone
#   2. rclone config 建立名為 "b2" 的 remote（type: b2）
#
# 用法：
#   B2_BUCKET=<bucket 名稱> ./scripts/backup-pull.sh [目的地目錄]
#   目的地預設 ~/Backups/jyu1999
set -euo pipefail

B2_BUCKET="${B2_BUCKET:?請設定 B2_BUCKET 環境變數（B2 備份 bucket 名稱）}"
DEST="${1:-$HOME/Backups/jyu1999}"

mkdir -p "$DEST/db" "$DEST/media"

latest=$(rclone lsf "b2:${B2_BUCKET}/db" --files-only | sort | tail -n 1)
if [[ -z "$latest" ]]; then
  echo "錯誤：b2:${B2_BUCKET}/db 底下找不到任何 dump" >&2
  exit 1
fi

echo "拉回最新 DB dump：${latest}"
rclone copyto "b2:${B2_BUCKET}/db/${latest}" "$DEST/db/${latest}" --progress

echo "同步媒體鏡像到 $DEST/media"
rclone sync "b2:${B2_BUCKET}/media" "$DEST/media" --progress

echo "完成：DB → $DEST/db/${latest}；媒體 → $DEST/media"
```

- [ ] **Step 2: 語法與靜態檢查**

Run: `bash -n scripts/backup-pull.sh && docker run --rm -v /Users/zhanjieyu/Code/new-jyu-notes:/mnt koalaman/shellcheck:stable /mnt/scripts/backup-pull.sh`
Expected: 無輸出、exit 0（docker 沒開則至少跑 `bash -n`，應無輸出）

- [ ] **Step 3: 設定執行權限並 commit**

```bash
chmod +x scripts/backup-pull.sh
git add scripts/backup-pull.sh
git commit -m "JYU-23: 本地備份拉回腳本"
```

---

### Task 3: 設定與還原文件（`docs/backup-restore.md`）

**Files:**
- Create: `docs/backup-restore.md`

**Interfaces:**
- Consumes: Task 1 的 workflow 與 secrets 名稱、Task 2 的腳本用法
- Produces: 一次性設定步驟＋還原 runbook（驗收標準 4 的演練依據）

- [ ] **Step 1: 撰寫文件**

寫入 `docs/backup-restore.md`（完整內容）：

````markdown
# 備份與還原 Runbook（JYU-23）

架構與決策見 `docs/superpowers/specs/2026-07-11-offsite-backup-design.md`。

## 一次性設定（啟用備份前必做）

1. **Laravel Cloud**：Database → 開啟 public access，複製連線字串
   （`postgres://user:pass@host:5432/main`）。
2. **Backblaze B2**：
   - 建立 private bucket（例：`jyu1999-backup`）。
   - Lifecycle settings：`Keep prior versions for 30 days`（媒體誤刪保護）。
   - App Keys → 建立 application key，**限定只能存取該 bucket**。
3. **Cloudflare R2**：R2 → Manage API Tokens → 建立 **Object Read only**
   token，記下 Access Key ID / Secret Access Key 與帳號的 S3 endpoint
   （`https://<account_id>.r2.cloudflarestorage.com`）。
4. **GitHub repo Secrets**（Settings → Secrets and variables → Actions）：

   | Secret | 值 |
   |---|---|
   | `BACKUP_DATABASE_URL` | Laravel Cloud public 連線字串 |
   | `R2_ACCESS_KEY_ID` | R2 唯讀 token 的 Access Key ID |
   | `R2_SECRET_ACCESS_KEY` | R2 唯讀 token 的 Secret |
   | `R2_ENDPOINT` | R2 S3 endpoint URL |
   | `R2_BUCKET` | R2 media bucket 名稱 |
   | `B2_KEY_ID` | B2 application key 的 keyID |
   | `B2_APP_KEY` | B2 application key 本體 |
   | `B2_BUCKET` | B2 備份 bucket 名稱 |

5. Actions → Backup → Run workflow 手動觸發一次，確認成功且 B2 出現
   `db/db-<今天>.dump` 與 `media/`。

## 拉回本地

```bash
brew install rclone
rclone config   # 建立名為 b2 的 remote（type: b2，填 keyID / appKey）
B2_BUCKET=jyu1999-backup ./scripts/backup-pull.sh
# 結果在 ~/Backups/jyu1999/{db,media}
```

## 還原：資料庫

以本地 sail Postgres 為例（正式站還原則把連線參數換成新 DB 的）：

```bash
# 1. 建一個空 DB（本地演練用）
./vendor/bin/sail exec pgsql createdb -U sail restore_drill

# 2. 把 dump 複製進容器並還原
docker compose cp ~/Backups/jyu1999/db/db-<日期>.dump pgsql:/tmp/restore.dump
./vendor/bin/sail exec pgsql pg_restore -U sail --dbname=restore_drill \
  --no-owner --no-privileges /tmp/restore.dump

# 3. 抽查資料
./vendor/bin/sail exec pgsql psql -U sail -d restore_drill \
  -c "select count(*) from posts;"
```

dump 是 `--format=custom`，可用 `pg_restore --list` 檢視內容、
`--table=<name>` 只還原單一資料表。

## 還原：媒體

還原回 R2（或任何 S3 相容儲存）：

```bash
# rclone config 先建好目標 remote（此例叫 r2，需「可寫」的 R2 token）
rclone sync "b2:jyu1999-backup/media" "r2:<media-bucket>" --progress
# 或從本地副本推回：
rclone sync ~/Backups/jyu1999/media "r2:<media-bucket>" --progress
```

媒體 URL 由 `media.jyu1999.com` 服務，bucket 換位置時記得更新
`AWS_*` 環境變數與該網域的來源設定。

## 誤刪救援（B2 版本保留）

R2 誤刪 → 下次 sync B2 也會刪，但 B2 保留舊版本 30 天：

```bash
rclone lsf "b2:jyu1999-backup/media" --b2-versions | grep <檔名>
rclone copyto "b2:jyu1999-backup/media/<檔名-vXXX>" ./recovered-file --b2-versions
```

## 演練紀錄

沒驗證過還原流程的備份不算備份。每次演練後在此追加一行：

| 日期 | 演練內容 | 結果 |
|---|---|---|
````

- [ ] **Step 2: Commit**

```bash
git add docs/backup-restore.md
git commit -m "JYU-23: 備份設定與還原 runbook"
```

---

### Task 4: 端到端驗收（merge 後、需使用者完成一次性設定）

**Files:** 無（操作性驗收）

**Interfaces:**
- Consumes: Task 1–3 全部產出＋使用者完成 `docs/backup-restore.md` 的一次性設定

- [ ] **Step 1: 手動觸發 workflow** — GitHub Actions → Backup → Run workflow，Expected: 全綠，B2 出現 `db/db-<今天>.dump` 與 `media/` 內容
- [ ] **Step 2: 跑 `B2_BUCKET=<bucket> ./scripts/backup-pull.sh`** — Expected: `~/Backups/jyu1999/db/` 有當日 dump、`media/` 有檔案
- [ ] **Step 3: 依 runbook 在本地 sail Postgres 演練還原** — Expected: `select count(*) from posts;` 回傳合理筆數，並在演練紀錄表補一行
