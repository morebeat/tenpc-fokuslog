#!/bin/bash
# scripts/backup-database.sh
# 
# Erstellt ein SQL-Backup der Produktions-Datenbank
# und speichert es mit Zeitstempel ab
#
# Verwendung:
#   bash scripts/backup-database.sh
#

set -e

# Farben für Output
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== FokusLog Database Backup ===${NC}"
echo ""

# Lade Umgebungsvariablen aus .env
if [ -f ".env" ]; then
    export $(cat .env | grep -v '#' | xargs)
    echo -e "${GREEN}✓ .env geladen${NC}"
else
    echo -e "${RED}✗ Fehler: .env nicht gefunden${NC}"
    exit 1
fi

# Setze Standardwerte wenn nicht vorhanden
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-fokuslog}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
BACKUP_DIR="${BACKUP_DIR:-./backups}"

# Erstelle Backup-Verzeichnis
mkdir -p "$BACKUP_DIR"

# Erzeuge Zeitstempel
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/backup_${DB_NAME}_${TIMESTAMP}.sql"

echo -e "${YELLOW}Sicherung der Datenbank: $DB_NAME @ $DB_HOST${NC}"
echo "Speicherort: $BACKUP_FILE"
echo ""

# Erstelle Backup
if [ -z "$DB_PASS" ]; then
    # Kein Passwort
    mysqldump -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$BACKUP_FILE"
else
    # Mit Passwort
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE"
fi

if [ $? -eq 0 ]; then
    # Komprimiere Backup
    gzip "$BACKUP_FILE"
    BACKUP_FILE="${BACKUP_FILE}.gz"
    
    FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo ""
    echo -e "${GREEN}✓ Backup erfolgreich erstellt${NC}"
    echo -e "${GREEN}✓ Datei: $BACKUP_FILE (${FILE_SIZE})${NC}"
    
    # Entferne alte Backups (älter als 30 Tage)
    echo ""
    echo -e "${YELLOW}Räume alte Backups auf (älter als 30 Tage)...${NC}"
    find "$BACKUP_DIR" -name "backup_${DB_NAME}_*.sql.gz" -mtime +30 -delete
    echo -e "${GREEN}✓ Aufräumen abgeschlossen${NC}"
else
    echo ""
    echo -e "${RED}✗ Fehler bei der Sicherung${NC}"
    exit 1
fi
