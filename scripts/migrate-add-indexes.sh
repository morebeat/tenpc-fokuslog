#!/bin/bash
# scripts/migrate-add-indexes.sh
# 
# Migriert bestehende Datenbanken durch Hinzufügen von Performance-Indexes.
# Diese Indexes verbessern die Query-Performance bei größeren Datenmengen.
#
# Verwendung:
#   bash scripts/migrate-add-indexes.sh
#

set -e

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== FokusLog Database Migration: Add Performance Indexes ===${NC}"
echo ""

# Lade Umgebungsvariablen aus .env
if [ -f "api/.env" ]; then
    export $(cat api/.env | grep -v '#' | xargs)
    echo -e "${GREEN}✓ .env geladen${NC}"
else
    echo -e "${RED}✗ Fehler: api/.env nicht gefunden${NC}"
    exit 1
fi

# Setze Standardwerte wenn nicht vorhanden
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-fokuslog}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

echo -e "${YELLOW}Verbinde zu Datenbank: $DB_NAME @ $DB_HOST${NC}"
echo ""

# Erstelle SQL-Script
MIGRATION_SQL=$(cat <<'EOF'
-- ============================================================================
-- P0 Refactoring: Performance Indexes Migration
-- ============================================================================

-- Häufig abgefragte Spalten indizieren für schnellere Queries
CREATE INDEX IF NOT EXISTS idx_users_family_id ON users(family_id);
CREATE INDEX IF NOT EXISTS idx_medications_family_id ON medications(family_id);
CREATE INDEX IF NOT EXISTS idx_entries_user_id ON entries(user_id);
CREATE INDEX IF NOT EXISTS idx_entries_user_date ON entries(user_id, date);
CREATE INDEX IF NOT EXISTS idx_entries_medication_id ON entries(medication_id);
CREATE INDEX IF NOT EXISTS idx_user_badges_user_id ON user_badges(user_id);
CREATE INDEX IF NOT EXISTS idx_user_badges_badge_id ON user_badges(badge_id);
CREATE INDEX IF NOT EXISTS idx_entry_tags_entry_id ON entry_tags(entry_id);
CREATE INDEX IF NOT EXISTS idx_entry_tags_tag_id ON entry_tags(tag_id);
CREATE INDEX IF NOT EXISTS idx_tags_family_id ON tags(family_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_consents_user_id ON consents(user_id);

-- Bestätige erfolgreiche Migration
SELECT 'Migration erfolgreich abgeschlossen!' AS status;
EOF
)

# Führe Migration aus
if [ -z "$DB_PASS" ]; then
    # Kein Passwort
    mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" <<< "$MIGRATION_SQL"
else
    # Mit Passwort
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<< "$MIGRATION_SQL"
fi

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✓ Migration erfolgreich abgeschlossen${NC}"
    echo -e "${GREEN}✓ Alle Performance-Indexes wurden hinzugefügt${NC}"
    echo ""
    echo -e "${YELLOW}Nächste Schritte:${NC}"
    echo "1. Verifizie Indexes: SELECT * FROM information_schema.STATISTICS WHERE TABLE_NAME='entries';"
    echo "2. Teste Queries auf Performance"
    echo "3. Überwache Datenbank-Performance (z.B. slow query log)"
else
    echo ""
    echo -e "${RED}✗ Fehler bei der Migration${NC}"
    exit 1
fi
