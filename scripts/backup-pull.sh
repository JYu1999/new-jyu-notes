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

if ! listing=$(rclone lsf "b2:${B2_BUCKET}/db" --files-only); then
  echo "錯誤：無法列出 b2:${B2_BUCKET}/db（請檢查 rclone 的 b2 remote 設定與 B2_BUCKET）" >&2
  exit 1
fi
latest=$(sort <<<"$listing" | tail -n 1)
if [[ -z "$latest" ]]; then
  echo "錯誤：b2:${B2_BUCKET}/db 底下找不到任何 dump" >&2
  exit 1
fi

echo "拉回最新 DB dump：${latest}"
rclone copyto "b2:${B2_BUCKET}/db/${latest}" "$DEST/db/${latest}" --progress

echo "同步媒體鏡像到 $DEST/media"
rclone sync "b2:${B2_BUCKET}/media" "$DEST/media" --progress

echo "完成：DB → $DEST/db/${latest}；媒體 → $DEST/media"
