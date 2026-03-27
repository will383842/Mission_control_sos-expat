#!/bin/bash
# =============================================================================
# DAILY BACKUP — PostgreSQL + Laravel storage
# Runs inside inf-app container via cron (scheduler)
# Keeps 30 days of backups locally + optional cloud sync
# =============================================================================

set -e

BACKUP_DIR="/var/www/html/storage/backups"
DATE=$(date +%Y-%m-%d_%H%M)
KEEP_DAYS=30

mkdir -p "$BACKUP_DIR"

echo "[BACKUP] Starting backup at $(date)"

# 1. PostgreSQL full dump
echo "[BACKUP] Dumping PostgreSQL..."
PGPASSWORD="${DB_PASSWORD}" pg_dump \
  -h "${DB_HOST:-inf-postgres}" \
  -U "${DB_USERNAME:-inf_user}" \
  -d "${DB_DATABASE:-mission_control}" \
  --format=custom \
  --compress=9 \
  -f "$BACKUP_DIR/db_${DATE}.dump"

DB_SIZE=$(du -sh "$BACKUP_DIR/db_${DATE}.dump" | cut -f1)
echo "[BACKUP] Database dump: $DB_SIZE"

# 2. Count records for verification
RECORD_COUNT=$(PGPASSWORD="${DB_PASSWORD}" psql \
  -h "${DB_HOST:-inf-postgres}" \
  -U "${DB_USERNAME:-inf_user}" \
  -d "${DB_DATABASE:-mission_control}" \
  -t -c "SELECT json_build_object(
    'influenceurs', (SELECT count(*) FROM influenceurs WHERE deleted_at IS NULL),
    'contacts', (SELECT count(*) FROM contacts WHERE deleted_at IS NULL),
    'ai_sessions', (SELECT count(*) FROM ai_research_sessions),
    'templates', (SELECT count(*) FROM email_templates),
    'contact_types', (SELECT count(*) FROM contact_types),
    'users', (SELECT count(*) FROM users WHERE deleted_at IS NULL)
  );" 2>/dev/null || echo '{}')

echo "[BACKUP] Records: $RECORD_COUNT"

# 3. Save backup manifest
echo "{\"date\":\"$DATE\",\"db_size\":\"$DB_SIZE\",\"records\":$RECORD_COUNT}" \
  > "$BACKUP_DIR/manifest_${DATE}.json"

# 4. Cleanup old backups (keep KEEP_DAYS days)
echo "[BACKUP] Cleaning up backups older than $KEEP_DAYS days..."
find "$BACKUP_DIR" -name "db_*.dump" -mtime +$KEEP_DAYS -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "manifest_*.json" -mtime +$KEEP_DAYS -delete 2>/dev/null || true

# 5. List current backups
BACKUP_COUNT=$(ls -1 "$BACKUP_DIR"/db_*.dump 2>/dev/null | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
echo "[BACKUP] Complete: $BACKUP_COUNT backups, total size: $TOTAL_SIZE"
echo "[BACKUP] Finished at $(date)"
