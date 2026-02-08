-- Migration: Glossary-Tabelle erweitern für bessere Wiederverwendbarkeit
-- Diese Migration fügt zusätzliche Felder hinzu, um Hilfe-Inhalte
-- in verschiedenen Layouts und externen Anwendungen nutzen zu können.
--
-- Hinweis: Führe dieses Skript einmalig aus. Falls Spalten bereits existieren,
-- werden die entsprechenden ALTER-Statements übersprungen (via Stored Procedure).

DELIMITER //

-- Prozedur zum sicheren Hinzufügen von Spalten
DROP PROCEDURE IF EXISTS add_column_if_not_exists//
CREATE PROCEDURE add_column_if_not_exists(
    IN tbl VARCHAR(64),
    IN col VARCHAR(64),
    IN col_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = tbl 
        AND COLUMN_NAME = col
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', col, ' ', col_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- Neue Felder hinzufügen
CALL add_column_if_not_exists('glossary', 'content_plain', 
    "TEXT COLLATE utf8mb4_unicode_ci COMMENT 'Reiner Text ohne HTML-Tags für Previews' AFTER `content`");

CALL add_column_if_not_exists('glossary', 'content_sections', 
    "JSON DEFAULT NULL COMMENT 'Strukturierte Abschnitte als JSON' AFTER `content_plain`");

CALL add_column_if_not_exists('glossary', 'keywords', 
    "VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Komma-getrennte Stichwörter für Suche' AFTER `full_content`");

CALL add_column_if_not_exists('glossary', 'target_audience', 
    "SET('eltern','kinder','erwachsene','lehrer','aerzte','alle') DEFAULT 'alle' COMMENT 'Zielgruppen für die Seite' AFTER `keywords`");

CALL add_column_if_not_exists('glossary', 'reading_time_min', 
    "TINYINT UNSIGNED DEFAULT NULL COMMENT 'Geschätzte Lesezeit in Minuten' AFTER `target_audience`");

CALL add_column_if_not_exists('glossary', 'source_file', 
    "VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ursprüngliche Datei (relativ zu app/help/)' AFTER `reading_time_min`");

CALL add_column_if_not_exists('glossary', 'last_imported_at', 
    "TIMESTAMP NULL DEFAULT NULL COMMENT 'Zeitpunkt des letzten Imports' AFTER `source_file`");

CALL add_column_if_not_exists('glossary', 'file_hash', 
    "CHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MD5-Hash der Quelldatei für Change-Detection' AFTER `last_imported_at`");

CALL add_column_if_not_exists('glossary', 'updated_at', 
    "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");

-- Prozedur aufräumen
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

-- Fulltext-Index für Suche (nur wenn nicht vorhanden)
-- MySQL 5.7+/8.0 unterstützt IF NOT EXISTS für Indizes nicht, daher mit Fehlertoleranz
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'glossary' AND index_name = 'idx_glossary_search');

SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE glossary ADD FULLTEXT INDEX idx_glossary_search (title, content, keywords)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
