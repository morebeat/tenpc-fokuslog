-- Update Script für FokusLog (V2)
-- Dieses Skript aktualisiert eine bestehende Datenbank auf den neuesten Stand
-- (Gamification, Tags, Benutzer-Erweiterungen, Audit-Log).
-- Es ist idempotent und kann sicher mehrfach ausgeführt werden.

-- --------------------------------------------------------
-- 1. Neue Tabellen anlegen (IF NOT EXISTS verhindert Fehler)
-- --------------------------------------------------------

-- Badges für Gamification
CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL,
    required_streak INT NOT NULL UNIQUE,
    icon_class VARCHAR(50) DEFAULT 'badge-default'
);

-- Verknüpfung User <-> Badges
CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, badge_id)
);

-- Tags (Kategorien)
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);

-- Verknüpfung Einträge <-> Tags
CREATE TABLE IF NOT EXISTS entry_tags (
    entry_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (entry_id, tag_id),
    FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Audit Log (falls noch nicht vorhanden)
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- 2. Standard-Daten einfügen
-- --------------------------------------------------------

INSERT IGNORE INTO badges (name, description, required_streak, icon_class) VALUES
('3-Tage-Serie', 'Drei Tage in Folge einen Eintrag gemacht!', 3, 'badge-bronze'),
('Wochen-Held', 'Sieben Tage in Folge durchgehalten!', 7, 'badge-silver'),
('Halbmond', 'Fünfzehn Tage am Stück dabei!', 15, 'badge-gold'),
('Monats-Meister', 'Einen ganzen Monat jeden Tag eingetragen!', 30, 'badge-platinum');

-- --------------------------------------------------------
-- 3. Tabellen erweitern (Spalten hinzufügen)
-- Wir nutzen Prepared Statements, um "IF NOT EXISTS" für Spalten zu simulieren
-- (Kompatibel mit MySQL und MariaDB)
-- --------------------------------------------------------

SET @dbname = DATABASE();

-- 3.1 Tabelle 'users' erweitern
SET @tablename = "users";

-- Spalte: gender
SET @columnname = "gender";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN gender ENUM('male', 'female', 'diverse') DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Spalte: initial_weight
SET @columnname = "initial_weight";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN initial_weight DECIMAL(5,2) DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Spalte: points
SET @columnname = "points";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN points INT DEFAULT 0;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Spalte: streak_current
SET @columnname = "streak_current";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN streak_current INT DEFAULT 0;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Spalte: last_entry_date
SET @columnname = "last_entry_date";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN last_entry_date DATE DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3.2 Tabelle 'entries' erweitern
SET @tablename = "entries";

-- Spalte: weight
SET @columnname = "weight";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE (table_name = @tablename) AND (table_schema = @dbname) AND (column_name = @columnname)) > 0,
  "SELECT 1",
  "ALTER TABLE entries ADD COLUMN weight DECIMAL(5,2) DEFAULT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;