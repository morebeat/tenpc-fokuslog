#!/bin/bash
# scripts/rebuild-database.sh
#
# WARNUNG: Dieses Skript löscht die bestehende Datenbank und erstellt sie neu.
# Es sollte NUR in einer Entwicklungsumgebung verwendet werden.
#
# Verwendung:
#   bash scripts/rebuild-database.sh
#

set -e

# Farben für Output
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== FokusLog Database Rebuild ===${NC}"
echo -e "${RED}WARNUNG: Alle Daten in der Datenbank werden gelöscht!${NC}"
read -p "Sind Sie sicher, dass Sie fortfahren möchten? (j/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Jj]$ ]]
then
    echo "Abbruch."
    exit 1
fi

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

echo -e "${YELLOW}Datenbank wird zurückgesetzt: $DB_NAME @ $DB_HOST${NC}"

# MySQL-Befehl zusammenstellen
MYSQL_CMD="mysql -h ${DB_HOST} -u ${DB_USER}"
if [ ! -z "$DB_PASS" ]; then
    MYSQL_CMD="${MYSQL_CMD} -p'${DB_PASS}'"
fi

# 1. Datenbank löschen und neu erstellen
eval $MYSQL_CMD -e "\"DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
echo -e "${GREEN}✓ Datenbank '$DB_NAME' neu erstellt.${NC}"

# 2. Schema aus db/schema.sql importieren
eval $MYSQL_CMD "$DB_NAME" < "db/schema.sql"
echo -e "${GREEN}✓ Schema aus 'db/schema.sql' importiert.${NC}"

# 3. Testdaten aus db/seed.sql importieren
eval $MYSQL_CMD "$DB_NAME" < "db/seed.sql"
echo -e "${GREEN}✓ Testdaten aus 'db/seed.sql' importiert.${NC}"

echo -e "\n${GREEN}✓✓✓ Datenbank-Reset erfolgreich abgeschlossen!${NC}"