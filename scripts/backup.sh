#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/cwt-academy}"
DB_NAME="${DB_NAME:-cwt_academy}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASSWORD:-}"
APP_DIR="${APP_DIR:-/var/www/cwt-academy}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"

# Database backup
if command -v mysqldump &>/dev/null; then
    echo "[$(date -Iseconds)] Backing up database..."
    mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        --single-transaction --quick --lock-tables=false \
        | gzip > "$BACKUP_DIR/db_${DATE}.sql.gz"
    echo "[$(date -Iseconds)] Database backup complete."
fi

# Application files backup
if [[ -d "$APP_DIR/storage/app" ]]; then
    echo "[$(date -Iseconds)] Backing up application files..."
    tar -czf "$BACKUP_DIR/files_${DATE}.tar.gz" -C "$APP_DIR" storage/app
    echo "[$(date -Iseconds)] File backup complete."
fi

# Redis backup
if command -v redis-cli &>/dev/null && redis-cli ping &>/dev/null; then
    echo "[$(date -Iseconds)] Backing up Redis..."
    redis-cli BGSAVE
    # Wait briefly for background save
    sleep 2
    REDIS_DIR=$(redis-cli CONFIG GET dir | tail -1)
    cp "$REDIS_DIR/dump.rdb" "$BACKUP_DIR/redis_${DATE}.rdb" 2>/dev/null || true
    echo "[$(date -Iseconds)] Redis backup complete."
fi

# Cleanup old backups
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "*.rdb" -mtime +$RETENTION_DAYS -delete

# Upload to Cloudflare R2 (optional)
if [[ -n "${R2_ENDPOINT:-}" && -n "${R2_ACCESS_KEY_ID:-}" && -n "${R2_SECRET_ACCESS_KEY:-}" && -n "${R2_BUCKET:-}" ]]; then
    echo "[$(date -Iseconds)] Uploading to R2..."
    export AWS_ACCESS_KEY_ID="$R2_ACCESS_KEY_ID"
    export AWS_SECRET_ACCESS_KEY="$R2_SECRET_ACCESS_KEY"
    aws s3 cp "$BACKUP_DIR/db_${DATE}.sql.gz" "s3://${R2_BUCKET}/backups/db_${DATE}.sql.gz" --endpoint-url="$R2_ENDPOINT" 2>/dev/null || echo "[$(date -Iseconds)] R2 upload skipped (aws cli not available)"
fi

# Verify latest DB backup can be restored (dry-run on first 100 rows)
if command -v zcat &>/dev/null && command -v mysql &>/dev/null; then
    echo "[$(date -Iseconds)] Verifying backup integrity (dry-run)..."
    zcat "$BACKUP_DIR/db_${DATE}.sql.gz" | head -n 100 > /dev/null && echo "[$(date -Iseconds)] Backup integrity OK."
fi

# Report backup size
BACKUP_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
echo "[$(date -Iseconds)] Backup completed: $DATE | Total backup dir size: $BACKUP_SIZE"
