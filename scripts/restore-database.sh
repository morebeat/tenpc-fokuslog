#!/bin/bash
# scripts/restore-database.sh
#
# Stellt die FokusLog-Datenbank aus einem .sql.gz-Backup wieder her.
#
# Verwendung:
#   bash scripts/restore-database.sh backups/backup_fokuslog_20260210_120000.sql.gz
#
# Achtung: Überschreibt die bestehende Datenbank vollständig!
#

set -e

YELLOW='\033[1;33m'
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}=== FokusLog Database Restore ===${NC}"
echo ""

# Backup-Datei prüfen
DUMP_FILE="${1:-}"
if [ -z "$DUMP_FILE" ]; then
    echo -e "${RED}✗ Fehler: Keine Backup-Datei angegeben${NC}"
    echo "  Verwendung: bash scripts/restore-database.sh <backup.sql.gz>"
    exit 1
fi

if [ ! -f "$DUMP_FILE" ]; then
    echo -e "${RED}✗ Fehler: Datei nicht gefunden: $DUMP_FILE${NC}"
    exit 1
fi

# Lade Umgebungsvariablen aus .env
if [ -f ".env" ]; then
    export $(cat .env | grep -v '#' | xargs)
    echo -e "${GREEN}✓ .env geladen${NC}"
else
    echo -e "${RED}✗ Fehler: .env nicht gefunden${NC}"
    exit 1
fi

DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-fokuslog}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

echo ""
echo -e "${YELLOW}Ziel-Datenbank:  $DB_NAME @ $DB_HOST${NC}"
echo -e "${YELLOW}Backup-Datei:    $DUMP_FILE${NC}"
echo ""
echo -e "${RED}⚠️  ACHTUNG: Diese Aktion überschreibt alle Daten in '$DB_NAME'!${NC}"
read -r -p "Fortfahren? (y/N): " CONFIRM

if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
    echo "Abgebrochen."
    exit 0
fi

echo ""
echo "Stelle Datenbank wieder her..."

if [ -z "$DB_PASS" ]; then
    zcat "$DUMP_FILE" | mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME"
else
    zcat "$DUMP_FILE" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
fi

echo ""
echo -e "${GREEN}✓ Datenbank erfolgreich wiederhergestellt${NC}"
echo -e "${GREEN}✓ Quelle: $DUMP_FILE${NC}"
