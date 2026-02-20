-- ============================================================================
-- FokusLog Datenbankschema v3
-- ============================================================================
-- Ein komplettes Konzept für ADHS-Medikamentenmanagement und Symptomverfolgung.
-- Features:
--   • Multi-Mandanten-Architektur (Familien)
--   • Flexible Benutzerrolle (Eltern, Kinder, Lehrer, Single-User)
--   • Ausführliche Symptomverfolgung pro Eintrag
--   • Badge/Gamification-System
--   • Audit-Logging für Datenschutz & Compliance
--   • Datenschutzeinwilligungen
--
-- Kompatibilität: MySQL 5.7+, MariaDB 10.2+
-- ============================================================================

-- ============================================================================
-- 1. CORE TABELLEN
-- ============================================================================

-- Families (Haushalte / Mandanten)
-- ============================================================================
-- Jede Familie ist ein isolierter Datensatz mit eigenen Benutzern,
-- Medikamenten, Einträgen, Tags, Badges etc.
CREATE TABLE IF NOT EXISTS families (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    display_name VARCHAR(100) DEFAULT NULL COMMENT 'Optional: Angezeigter Name der Familie',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Families: Hauptmandanten-Tabelle';

-- Users (Benutzer)
-- ============================================================================
-- Benutzer gehören zu Familien und haben flexible Rollen.
-- Rollen:
--   parent   – Elternteil, Vollzugriff, kann andere Benutzer verwalten
--   child    – Kind, Zugriff auf eigene Einträge + Familie/Medikamente
--   teacher  – Lehrkraft, darf Einträge erstellen (z.B. in Schulkontext)
--   adult    – Single-User, erwachsener im Single-Modus (family_id = user_id)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    family_id INT UNSIGNED NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE COMMENT 'Eindeutiger Login-Name',
    email VARCHAR(100) DEFAULT NULL UNIQUE COMMENT 'Optional: E-Mail-Adresse',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Bcrypt oder Argon2id Hash',
    
    -- Persönliche Informationen
    first_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) DEFAULT NULL,
    gender ENUM('male', 'female', 'diverse', 'not_specified') DEFAULT 'not_specified',
    date_of_birth DATE DEFAULT NULL,
    
    -- Rolle und Berechtigungen
    role ENUM('parent', 'child', 'teacher', 'adult') NOT NULL DEFAULT 'child',
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE COMMENT 'E-Mail verifiziert?',
    
    -- Medikamenten- und Gewicht-Management
    initial_weight DECIMAL(6, 2) DEFAULT NULL COMMENT 'Ausgangsgewicht in kg',
    current_weight DECIMAL(6, 2) DEFAULT NULL COMMENT 'Aktuelles Gewicht in kg',
    target_weight DECIMAL(6, 2) DEFAULT NULL COMMENT 'Zielgewicht in kg',
    
    -- Gamification & Tracking
    points INT UNSIGNED DEFAULT 0 COMMENT 'Punkte aus Einträgen und Badges',
    streak_current INT UNSIGNED DEFAULT 0 COMMENT 'Aktuelle Serie (Tage in Folge)',
    streak_longest INT UNSIGNED DEFAULT 0 COMMENT 'Längste Serie bisher',
    last_entry_date DATE DEFAULT NULL COMMENT 'Datum des letzten Eintrags',
    
    -- Audit & Datenschutz
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME DEFAULT NULL,
    
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users: Benutzer und deren Profile';

-- Medications (Medikamente pro Familie)
-- ============================================================================
-- Medikamenten-Liste. Jede Familie kann ihre eigenen Medikamente definieren.
-- Dies ermöglicht Personalisierung (z.B. verschiedene ADHS-Medikamente).
CREATE TABLE IF NOT EXISTS medications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    family_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'Medikamentenname (z.B. Ritalin, Concerta)',
    generic_name VARCHAR(100) DEFAULT NULL COMMENT 'Generischer Name (z.B. Methylphenidat)',
    type ENUM('stimulant', 'non_stimulant', 'supplement', 'other') DEFAULT 'stimulant',
    
    -- Dosierungs-Standard
    default_dose VARCHAR(50) DEFAULT NULL COMMENT 'Standard-Dosis (z.B. 10mg, 0.5 tablets)',
    dosage_form VARCHAR(50) DEFAULT NULL COMMENT 'Darreichungsform (z.B. Tablet, Kapsel, Liquid)',
    
    -- Verwaltung
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT DEFAULT NULL COMMENT 'Notizen (z.B. "Morgens mit Frühstück")',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    UNIQUE KEY uq_meds_family_name (family_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medications: Medikamentenliste pro Familie';

-- Entries (Einträge / Logs)
-- ============================================================================
-- Das Herzstück: Täglich-Einträge pro Benutzer mit Symptom-Ratings,
-- Dosierungs-Info, Gewicht, Notizen, etc.
CREATE TABLE IF NOT EXISTS entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    medication_id INT UNSIGNED DEFAULT NULL,
    
    -- Zeitpunkt und Dosis
    date DATE NOT NULL COMMENT 'Datum des Eintrags',
    time ENUM('morning', 'noon', 'evening') NOT NULL COMMENT 'Tageszeit',
    dose VARCHAR(50) DEFAULT NULL COMMENT 'Tatsächlich eingenommene Dosis',
    dose_time TIME DEFAULT NULL COMMENT 'Uhrzeit der Einnahme',
    
    -- Symptom-Ratings (1-10 oder NULL)
    sleep TINYINT UNSIGNED DEFAULT NULL COMMENT 'Schlaf-Qualität (1=schlecht, 10=ausgezeichnet)',
    hyperactivity TINYINT UNSIGNED DEFAULT NULL COMMENT 'Hyperaktivität (1=sehr aktiv, 10=ruhig)',
    mood TINYINT UNSIGNED DEFAULT NULL COMMENT 'Stimmung (1=niedergeschlagen, 10=ausgezeichnet)',
    irritability TINYINT UNSIGNED DEFAULT NULL COMMENT 'Reizbarkeit (1=sehr reizbar, 10=ruhig)',
    appetite TINYINT UNSIGNED DEFAULT NULL COMMENT 'Appetit (1=kein Appetit, 10=normal/gut)',
    focus TINYINT UNSIGNED DEFAULT NULL COMMENT 'Fokus/Konzentration (1=sehr schlecht, 10=ausgezeichnet)',
    
    -- Gewicht-Tracking
    weight DECIMAL(6, 2) DEFAULT NULL COMMENT 'Körpergewicht in kg',
    
    -- Freitexte für Details & Beobachtungen
    side_effects TEXT DEFAULT NULL COMMENT 'Nebenwirkungen beobachtet?',
    other_effects TEXT DEFAULT NULL COMMENT 'Sonstige Effekte/Beobachtungen',
    special_events TEXT DEFAULT NULL COMMENT 'Besondere Ereignisse (z.B. Schulausflug)',
    menstruation_phase VARCHAR(20) DEFAULT NULL COMMENT 'Menstruations-Phase (relevant bei weiblichen Personen)',
    teacher_feedback TEXT DEFAULT NULL COMMENT 'Rückmeldung von Lehrer/Betreuer',
    emotional_reactions TEXT DEFAULT NULL COMMENT 'Emotionale Reaktionen/Gefühle',
    private_notes TEXT DEFAULT NULL COMMENT 'Private Notizen (nur für Ersteller sichtbar)',
    
    -- Audit & Verwaltung
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE SET NULL,
    UNIQUE KEY uq_entry_slot (user_id, date, time) COMMENT 'Max 1 Eintrag pro Slot'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entries: Tägliche Einträge mit Symptom-Ratings';

-- ============================================================================
-- 2. KATEGORISIERUNG & TAGS
-- ============================================================================

-- Tags (Beobachtungs-Kategorien)
-- ============================================================================
-- Flexible, benutzerdefinierte Tags zur Kategorisierung von Einträgen
-- (z.B. "Schultest", "Stress", "Schlecht geschlafen", etc.)
CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    family_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL COMMENT 'Tag-Name (z.B. "Schultest", "Stress")',
    description VARCHAR(255) DEFAULT NULL,
    color_hex CHAR(7) DEFAULT '#808080' COMMENT 'Farbe für UI (z.B. #FF5733)',
    icon_class VARCHAR(50) DEFAULT NULL COMMENT 'CSS-Klasse oder Icon-Name',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tags_family_name (family_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tags: Benutzerdefinierte Kategorien pro Familie';

-- Entry Tags (Verknüpfung Einträge ↔ Tags)
-- ============================================================================
-- Many-to-Many: Ein Eintrag kann mehrere Tags haben.
CREATE TABLE IF NOT EXISTS entry_tags (
    entry_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (entry_id, tag_id),
    FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entry Tags: Many-to-Many Verknüpfung';

-- ============================================================================
-- 3. GAMIFICATION & BADGES
-- ============================================================================

-- Badges (Achievements)
-- ============================================================================
-- Vordefinierte Badges, die Benutzer durch Streaks verdienen können.
CREATE TABLE IF NOT EXISTS badges (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    required_streak INT UNSIGNED NOT NULL UNIQUE COMMENT 'Tage in Folge erforderlich',
    icon_class VARCHAR(50) DEFAULT 'badge-default' COMMENT 'CSS-Klasse für Icon',
    icon_emoji VARCHAR(10) DEFAULT NULL COMMENT 'Emoji-Darstellung',
    points_reward INT UNSIGNED DEFAULT 0 COMMENT 'Punkte beim Freischalten',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Badges: Vordefinierte Achievements';

-- User Badges (Erworbene Badges)
-- ============================================================================
-- Dokumentiert, welcher Benutzer welche Badges verdient hat.
CREATE TABLE IF NOT EXISTS user_badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    badge_id INT UNSIGNED NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_badge (user_id, badge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User Badges: Erworbene Achievements';

-- ============================================================================
-- 4. DATENSCHUTZ & COMPLIANCE
-- ============================================================================

-- Consents (Datenschutzeinwilligungen)
-- ============================================================================
-- Dokumentiert Zeitpunkt und Version von Datenschutzerklärung & AGB.
CREATE TABLE IF NOT EXISTS consents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    consent_type ENUM('privacy_policy', 'terms_of_service', 'data_processing', 'marketing') DEFAULT 'privacy_policy',
    version VARCHAR(10) DEFAULT NULL COMMENT 'Version der Datenschutzerklärung',
    consented BOOLEAN DEFAULT TRUE,
    consented_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consent_ip VARCHAR(45) DEFAULT NULL COMMENT 'IP-Adresse bei Zustimmung (IPv4/IPv6)',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Consents: Datenschutzerklärung & AGB Zustimmungen';

-- Audit Log (Audit-Trail)
-- ============================================================================
-- Dokumentiert sicherheitsrelevante Aktionen (Login, Datenexport, Löschung, etc.)
-- für Compliance & Debugging.
CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL COMMENT 'Benutzer, der die Aktion durchgeführt hat',
    action VARCHAR(50) NOT NULL COMMENT 'Aktion (z.B. LOGIN, EXPORT, DELETE_ENTRY)',
    resource_type VARCHAR(50) DEFAULT NULL COMMENT 'Typ der Ressource (z.B. USER, ENTRY, MEDICATION)',
    resource_id INT UNSIGNED DEFAULT NULL COMMENT 'ID der betroffenen Ressource',
    details JSON DEFAULT NULL COMMENT 'Zusätzliche Details als JSON',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'Client-IP (IPv4/IPv6)',
    user_agent VARCHAR(255) DEFAULT NULL COMMENT 'Browser/Client-Info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created_at (created_at),
    INDEX idx_resource (resource_type, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit Log: Sicherheits- und Compliance-Trail';

-- ============================================================================
-- 5. PERFORMANCE INDEXES
-- ============================================================================
-- Häufig abgefragte Spalten und Kombinationen für optimale Query-Performance

-- Users
CREATE INDEX idx_users_family_id ON users(family_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_is_active ON users(is_active);

-- Medications
CREATE INDEX idx_medications_family_id ON medications(family_id);
CREATE INDEX idx_medications_is_active ON medications(is_active);

-- Entries (Die wichtigsten für Performance!)
CREATE INDEX idx_entries_user_id ON entries(user_id);
CREATE INDEX idx_entries_user_date ON entries(user_id, date);
CREATE INDEX idx_entries_date ON entries(date);
CREATE INDEX idx_entries_medication_id ON entries(medication_id);
CREATE INDEX idx_entries_user_time ON entries(user_id, time);
CREATE INDEX idx_entries_created_at ON entries(created_at);

-- Entry Tags
CREATE INDEX idx_entry_tags_entry_id ON entry_tags(entry_id);
CREATE INDEX idx_entry_tags_tag_id ON entry_tags(tag_id);

-- Tags
CREATE INDEX idx_tags_family_id ON tags(family_id);
CREATE INDEX idx_tags_is_active ON tags(is_active);

-- User Badges
CREATE INDEX idx_user_badges_user_id ON user_badges(user_id);
CREATE INDEX idx_user_badges_badge_id ON user_badges(badge_id);
CREATE INDEX idx_user_badges_earned_at ON user_badges(earned_at);

-- Consents
CREATE INDEX idx_consents_user_id ON consents(user_id);
CREATE INDEX idx_consents_type ON consents(consent_type);

-- Audit Log (bereits als UNIQUE/INDEX definiert)
CREATE INDEX idx_audit_log_action ON audit_log(action);
CREATE INDEX idx_audit_log_resource ON audit_log(resource_type, resource_id);

-- ============================================================================
-- 6. STANDARD-DATEN (SEEDS)
-- ============================================================================

-- Standard-Badges einfügen
INSERT INTO badges (name, description, required_streak, icon_class, icon_emoji, points_reward) VALUES
('3-Tage-Serie', 'Drei Tage in Folge einen Eintrag gemacht!', 3, 'badge-bronze', '🥉', 10),
('Wochen-Held', 'Sieben Tage in Folge durchgehalten!', 7, 'badge-silver', '🥈', 25),
('Halbmond', 'Fünfzehn Tage am Stück dabei!', 15, 'badge-gold', '⭐', 50),
('Monats-Meister', 'Einen ganzen Monat jeden Tag eingetragen!', 30, 'badge-platinum', '🏆', 100)
ON DUPLICATE KEY UPDATE name=name;

-- ============================================================================
-- Notizen zur Nutzung:
-- ============================================================================
-- • Entry-Ratings (sleep, mood, etc.) sind TINYINT UNSIGNED (0-10)
-- • JSON in audit_log für flexible Daten
-- • Timestamps mit ON UPDATE CURRENT_TIMESTAMP für automatisches Update
-- • Engine=InnoDB für Transaktionen und FK-Constraints
-- • Charset=utf8mb4 für vollständige Unicode-Unterstützung (Emojis!)
-- ============================================================================
