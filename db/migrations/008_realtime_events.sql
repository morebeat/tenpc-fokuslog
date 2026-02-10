-- Migration: Realtime Events & Secure Sessions
-- Datum: 2026-02-10
-- Beschreibung: Tabellen für SSE Event-Queue und Database-Sessions

-- ============================================================================
-- Event-Queue für Server-Sent Events (SSE)
-- ============================================================================
-- Speichert Events, die an verbundene Clients gestreamt werden.
-- Events werden automatisch nach 1 Stunde gelöscht.

CREATE TABLE IF NOT EXISTS events_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    family_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'Event-Typ (z.B. entry.created, entry.updated)',
    data JSON NOT NULL COMMENT 'Event-Payload als JSON',
    exclude_user_id INT UNSIGNED NULL COMMENT 'User-ID, die das Event nicht erhalten soll',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_events_family_time (family_id, created_at),
    INDEX idx_events_cleanup (created_at),
    
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Sessions-Tabelle für Database Session Handler
-- ============================================================================
-- Optional: Wird nur verwendet wenn SESSION_HANDLER=database in .env gesetzt ist.
-- Ermöglicht Sessions über mehrere Server hinweg (Horizontal Scaling).

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data TEXT NOT NULL,
    last_access INT UNSIGNED NOT NULL,
    
    INDEX idx_sessions_last_access (last_access)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Cleanup-Event für automatische Bereinigung (optional)
-- ============================================================================
-- Kann via MySQL Event Scheduler aktiviert werden:
-- SET GLOBAL event_scheduler = ON;

DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_old_events
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM events_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
    DELETE FROM sessions WHERE last_access < UNIX_TIMESTAMP() - 86400;
END//
DELIMITER ;

-- Hinweis: Event Scheduler muss in MySQL/MariaDB aktiviert sein:
-- SET GLOBAL event_scheduler = ON;
-- Oder in my.cnf: event_scheduler = ON
