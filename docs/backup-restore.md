# 備份與還原 Runbook（JYU-23）

架構與決策見 `docs/superpowers/specs/2026-07-11-offsite-backup-design.md`。

## 一次性設定（啟用備份前必做）

1. **Laravel Cloud**：Database → 開啟 public access，複製連線字串
   （`postgres://user:pass@host:5432/main`）。
2. **Backblaze B2**：
   - 建立 private bucket（例：`jyu1999-backup`）。
   - Lifecycle settings：`Keep prior versions for 30 days`（媒體誤刪保護）。
   - 注意：workflow 的 30 天輪替刪除 dump 後，B2 會再保留舊版本 30 天，
     所以 dump 實際佔用儲存約 60 天，屬預期行為。
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

> 注意：用 bucket-scoped token 寫入 R2 時，rclone 可能因嘗試檢查/建立 bucket 而
> 收到 AccessDenied，此時在指令加上 `--s3-no-check-bucket` 即可。

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
