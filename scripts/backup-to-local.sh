#!/bin/bash
# =============================================================================
# BACKUP LOCAL — Télécharge le backup PostgreSQL du VPS vers ton PC
# =============================================================================
#
# USAGE:
#   ./scripts/backup-to-local.sh
#
# Ce script :
#   1. Lance un backup frais sur le VPS
#   2. Télécharge le dump + manifest vers ton PC
#   3. Garde 30 jours de backups locaux
#
# AUTOMATISER (Windows Task Scheduler) :
#   - Créer une tâche planifiée qui exécute :
#     bash "C:\Users\willi\Documents\Projets\VS_CODE\Outils_communication\Influenceurs_tracker_sos_expat\scripts\backup-to-local.sh"
#   - Fréquence : quotidienne, par exemple à 08:00
#
# =============================================================================

set -e

VPS_HOST="root@95.216.179.163"
CONTAINER_BACKUP_DIR="/var/www/html/storage/backups"
VPS_TMP_DIR="/tmp/inf-backups"
LOCAL_BACKUP_DIR="C:/Users/willi/Documents/Backups/influenceurs-tracker"
KEEP_DAYS=30

# Couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}[BACKUP LOCAL]${NC} Démarrage $(date)"

# 1. Créer le dossier local
mkdir -p "$LOCAL_BACKUP_DIR"

# 2. Lancer un backup frais sur le VPS
echo -e "${YELLOW}[BACKUP LOCAL]${NC} Lancement du backup sur le VPS..."
ssh "$VPS_HOST" "docker exec inf-app php artisan backup:database" 2>&1

# 3. Copier les backups du container Docker vers /tmp du VPS
echo -e "${YELLOW}[BACKUP LOCAL]${NC} Extraction depuis Docker..."
ssh "$VPS_HOST" "mkdir -p $VPS_TMP_DIR && docker cp inf-app:$CONTAINER_BACKUP_DIR/. $VPS_TMP_DIR/"

# 4. Trouver le dernier backup
LATEST_DUMP=$(ssh "$VPS_HOST" "ls -t ${VPS_TMP_DIR}/db_*.sql.gz 2>/dev/null | head -1")
LATEST_MANIFEST=$(ssh "$VPS_HOST" "ls -t ${VPS_TMP_DIR}/manifest_*.json 2>/dev/null | head -1")

if [ -z "$LATEST_DUMP" ]; then
    echo -e "${RED}[BACKUP LOCAL] ERREUR: Aucun backup trouvé sur le VPS${NC}"
    exit 1
fi

DUMP_NAME=$(basename "$LATEST_DUMP")
MANIFEST_NAME=$(basename "$LATEST_MANIFEST")

# 5. Télécharger
echo -e "${YELLOW}[BACKUP LOCAL]${NC} Téléchargement de $DUMP_NAME..."
scp "$VPS_HOST:$LATEST_DUMP" "$LOCAL_BACKUP_DIR/$DUMP_NAME"

if [ -n "$LATEST_MANIFEST" ]; then
    scp "$VPS_HOST:$LATEST_MANIFEST" "$LOCAL_BACKUP_DIR/$MANIFEST_NAME"
fi

# 5. Vérifier
LOCAL_SIZE=$(du -sh "$LOCAL_BACKUP_DIR/$DUMP_NAME" | cut -f1)
echo -e "${GREEN}[BACKUP LOCAL]${NC} Sauvé : $LOCAL_BACKUP_DIR/$DUMP_NAME ($LOCAL_SIZE)"

# 6. Afficher le manifest
if [ -f "$LOCAL_BACKUP_DIR/$MANIFEST_NAME" ]; then
    echo -e "${GREEN}[BACKUP LOCAL]${NC} Contenu :"
    cat "$LOCAL_BACKUP_DIR/$MANIFEST_NAME"
    echo ""
fi

# 7. Nettoyage vieux backups locaux
echo -e "${YELLOW}[BACKUP LOCAL]${NC} Nettoyage backups > $KEEP_DAYS jours..."
find "$LOCAL_BACKUP_DIR" -name "db_*.sql.gz" -mtime +$KEEP_DAYS -delete 2>/dev/null || true
find "$LOCAL_BACKUP_DIR" -name "manifest_*.json" -mtime +$KEEP_DAYS -delete 2>/dev/null || true

# 8. Résumé
BACKUP_COUNT=$(ls -1 "$LOCAL_BACKUP_DIR"/db_*.sql.gz 2>/dev/null | wc -l)
TOTAL_SIZE=$(du -sh "$LOCAL_BACKUP_DIR" 2>/dev/null | cut -f1)
echo -e "${GREEN}[BACKUP LOCAL]${NC} Terminé : $BACKUP_COUNT backups locaux, taille totale : $TOTAL_SIZE"
echo -e "${GREEN}[BACKUP LOCAL]${NC} Dossier : $LOCAL_BACKUP_DIR"
echo -e "${GREEN}[BACKUP LOCAL]${NC} Fin $(date)"
