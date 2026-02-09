-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 10.35.249.140:3306
-- Erstellungszeit: 08. Feb 2026 um 11:55
-- Server-Version: 8.0.44
-- PHP-Version: 8.4.14 

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Datenbank: `k246389_fokuslog-prod`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'Benutzer, der die Aktion durchgeführt hat',
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Aktion (z.B. LOGIN, EXPORT, DELETE_ENTRY)',
  `resource_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Typ der Ressource (z.B. USER, ENTRY, MEDICATION)',
  `resource_id` int UNSIGNED DEFAULT NULL COMMENT 'ID der betroffenen Ressource',
  `details` json DEFAULT NULL COMMENT 'Zusätzliche Details als JSON',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Client-IP (IPv4/IPv6)',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Browser/Client-Info',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit Log: Sicherheits- und Compliance-Trail';

--
-- Daten für Tabelle `audit_log`
--


--
-- Tabellenstruktur für Tabelle `badges`
--

CREATE TABLE `badges` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `required_streak` int UNSIGNED NOT NULL COMMENT 'Tage in Folge erforderlich',
  `icon_class` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'badge-default' COMMENT 'CSS-Klasse für Icon',
  `icon_emoji` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Emoji-Darstellung',
  `points_reward` int UNSIGNED DEFAULT '0' COMMENT 'Punkte beim Freischalten',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Badges: Vordefinierte Achievements';

--
-- Daten für Tabelle `badges`
--

INSERT INTO `badges` (`id`, `name`, `description`, `required_streak`, `icon_class`, `icon_emoji`, `points_reward`, `created_at`) VALUES
(1, '3-Tage-Serie', 'Drei Tage in Folge einen Eintrag gemacht!', 3, 'badge-bronze', '🥉', 10, '2026-02-07 11:39:08'),
(2, 'Wochen-Held', 'Sieben Tage in Folge durchgehalten!', 7, 'badge-silver', '🥈', 25, '2026-02-07 11:39:08'),
(3, 'Halbmond', 'Fünfzehn Tage am Stück dabei!', 15, 'badge-gold', '⭐', 50, '2026-02-07 11:39:08'),
(4, 'Monats-Meister', 'Einen ganzen Monat jeden Tag eingetragen!', 30, 'badge-platinum', '🏆', 100, '2026-02-07 11:39:08');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `consents`
--

CREATE TABLE `consents` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `consent_type` enum('privacy_policy','terms_of_service','data_processing','marketing') COLLATE utf8mb4_unicode_ci DEFAULT 'privacy_policy',
  `version` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Version der Datenschutzerklärung',
  `consented` tinyint(1) DEFAULT '1',
  `consented_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `consent_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP-Adresse bei Zustimmung (IPv4/IPv6)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Consents: Datenschutzerklärung & AGB Zustimmungen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `entries`
--

CREATE TABLE `entries` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `medication_id` int UNSIGNED DEFAULT NULL,
  `date` date NOT NULL COMMENT 'Datum des Eintrags',
  `time` enum('morning','noon','evening') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tageszeit',
  `dose` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tatsächlich eingenommene Dosis',
  `dose_time` time DEFAULT NULL COMMENT 'Uhrzeit der Einnahme',
  `sleep` tinyint UNSIGNED DEFAULT NULL COMMENT 'Schlaf-Qualität (1=schlecht, 10=ausgezeichnet)',
  `hyperactivity` tinyint UNSIGNED DEFAULT NULL COMMENT 'Hyperaktivität (1=sehr aktiv, 10=ruhig)',
  `mood` tinyint UNSIGNED DEFAULT NULL COMMENT 'Stimmung (1=niedergeschlagen, 10=ausgezeichnet)',
  `irritability` tinyint UNSIGNED DEFAULT NULL COMMENT 'Reizbarkeit (1=sehr reizbar, 10=ruhig)',
  `appetite` tinyint UNSIGNED DEFAULT NULL COMMENT 'Appetit (1=kein Appetit, 10=normal/gut)',
  `focus` tinyint UNSIGNED DEFAULT NULL COMMENT 'Fokus/Konzentration (1=sehr schlecht, 10=ausgezeichnet)',
  `weight` decimal(6,2) DEFAULT NULL COMMENT 'Körpergewicht in kg',
  `side_effects` text COLLATE utf8mb4_unicode_ci COMMENT 'Nebenwirkungen beobachtet?',
  `other_effects` text COLLATE utf8mb4_unicode_ci COMMENT 'Sonstige Effekte/Beobachtungen',
  `special_events` text COLLATE utf8mb4_unicode_ci COMMENT 'Besondere Ereignisse (z.B. Schulausflug)',
  `menstruation_phase` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Menstruations-Phase (relevant bei weiblichen Personen)',
  `teacher_feedback` text COLLATE utf8mb4_unicode_ci COMMENT 'Rückmeldung von Lehrer/Betreuer',
  `emotional_reactions` text COLLATE utf8mb4_unicode_ci COMMENT 'Emotionale Reaktionen/Gefühle',
  `private_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Private Notizen (nur für Ersteller sichtbar)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entries: Tägliche Einträge mit Symptom-Ratings';

--
-- Daten für Tabelle `entries`
--


--
-- Tabellenstruktur für Tabelle `entry_tags`
--

CREATE TABLE `entry_tags` (
  `entry_id` int UNSIGNED NOT NULL,
  `tag_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entry Tags: Many-to-Many Verknüpfung';



--
-- Tabellenstruktur für Tabelle `families`
--

CREATE TABLE `families` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional: Angezeigter Name der Familie',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Families: Hauptmandanten-Tabelle';



--
-- Tabellenstruktur für Tabelle `glossary`
--

CREATE TABLE `glossary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_plain` text COLLATE utf8mb4_unicode_ci COMMENT 'Reiner Text ohne HTML-Tags für Previews',
  `content_sections` json DEFAULT NULL COMMENT 'Strukturierte Abschnitte als JSON',
  `full_content` mediumtext COLLATE utf8mb4_unicode_ci,
  `keywords` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Komma-getrennte Stichwörter für Suche',
  `target_audience` set('eltern','kinder','erwachsene','lehrer','aerzte','alle') DEFAULT 'alle' COMMENT 'Zielgruppen für die Seite',
  `reading_time_min` tinyint UNSIGNED DEFAULT NULL COMMENT 'Geschätzte Lesezeit in Minuten',
  `source_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ursprüngliche Datei (relativ zu app/help/)',
  `file_hash` char(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MD5-Hash der Quelldatei für Change-Detection',
  `last_imported_at` timestamp NULL DEFAULT NULL COMMENT 'Zeitpunkt des letzten Imports',
  `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Allgemein',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  FULLTEXT KEY `idx_glossary_search` (`title`, `content`, `keywords`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hilfe-Inhalte für eigene und externe Anwendungen';



--
-- Tabellenstruktur für Tabelle `medications`
--

CREATE TABLE `medications` (
  `id` int UNSIGNED NOT NULL,
  `family_id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Medikamentenname (z.B. Ritalin, Concerta)',
  `generic_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Generischer Name (z.B. Methylphenidat)',
  `type` enum('stimulant','non_stimulant','supplement','other') COLLATE utf8mb4_unicode_ci DEFAULT 'stimulant',
  `default_dose` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Standard-Dosis (z.B. 10mg, 0.5 tablets)',
  `dosage_form` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Darreichungsform (z.B. Tablet, Kapsel, Liquid)',
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Notizen (z.B. "Morgens mit Frühstück")',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medications: Medikamentenliste pro Familie';


--
-- Tabellenstruktur für Tabelle `notification_log`
--

CREATE TABLE `notification_log` (
  `id` int NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `notification_settings`
--

CREATE TABLE `notification_settings` (
  `id` int NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `push_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `push_subscription` text,
  `push_morning` tinyint(1) NOT NULL DEFAULT '1',
  `push_morning_time` time NOT NULL DEFAULT '08:00:00',
  `push_noon` tinyint(1) NOT NULL DEFAULT '1',
  `push_noon_time` time NOT NULL DEFAULT '12:00:00',
  `push_evening` tinyint(1) NOT NULL DEFAULT '1',
  `push_evening_time` time NOT NULL DEFAULT '18:00:00',
  `email` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_weekly_digest` tinyint(1) NOT NULL DEFAULT '0',
  `email_digest_day` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=Sonntag',
  `email_missing_alert` tinyint(1) NOT NULL DEFAULT '0',
  `email_missing_days` int NOT NULL DEFAULT '3'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tags`
--

CREATE TABLE `tags` (
  `id` int UNSIGNED NOT NULL,
  `family_id` int UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tag-Name (z.B. "Schultest", "Stress")',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_hex` char(7) COLLATE utf8mb4_unicode_ci DEFAULT '#808080' COMMENT 'Farbe für UI (z.B. #FF5733)',
  `icon_class` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CSS-Klasse oder Icon-Name',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tags: Benutzerdefinierte Kategorien pro Familie';


--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `family_id` int UNSIGNED NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Eindeutiger Login-Name',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional: E-Mail-Adresse',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Bcrypt oder Argon2id Hash',
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female','diverse','not_specified') COLLATE utf8mb4_unicode_ci DEFAULT 'not_specified',
  `date_of_birth` date DEFAULT NULL,
  `role` enum('parent','child','teacher','adult') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'child',
  `is_active` tinyint(1) DEFAULT '1',
  `is_verified` tinyint(1) DEFAULT '0' COMMENT 'E-Mail verifiziert?',
  `initial_weight` decimal(6,2) DEFAULT NULL COMMENT 'Ausgangsgewicht in kg',
  `current_weight` decimal(6,2) DEFAULT NULL COMMENT 'Aktuelles Gewicht in kg',
  `target_weight` decimal(6,2) DEFAULT NULL COMMENT 'Zielgewicht in kg',
  `points` int UNSIGNED DEFAULT '0' COMMENT 'Punkte aus Einträgen und Badges',
  `streak_current` int UNSIGNED DEFAULT '0' COMMENT 'Aktuelle Serie (Tage in Folge)',
  `streak_longest` int UNSIGNED DEFAULT '0' COMMENT 'Längste Serie bisher',
  `last_entry_date` date DEFAULT NULL COMMENT 'Datum des letzten Eintrags',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users: Benutzer und deren Profile';



--
-- Tabellenstruktur für Tabelle `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `badge_id` int UNSIGNED NOT NULL,
  `earned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User Badges: Erworbene Achievements';

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_resource` (`resource_type`,`resource_id`),
  ADD KEY `idx_audit_log_action` (`action`),
  ADD KEY `idx_audit_log_resource` (`resource_type`,`resource_id`);

--
-- Indizes für die Tabelle `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `required_streak` (`required_streak`);

--
-- Indizes für die Tabelle `consents`
--
ALTER TABLE `consents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consents_user_id` (`user_id`),
  ADD KEY `idx_consents_type` (`consent_type`);

--
-- Indizes für die Tabelle `entries`
--
ALTER TABLE `entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_entry_slot` (`user_id`,`date`,`time`) COMMENT 'Max 1 Eintrag pro Slot',
  ADD KEY `idx_entries_user_id` (`user_id`),
  ADD KEY `idx_entries_user_date` (`user_id`,`date`),
  ADD KEY `idx_entries_date` (`date`),
  ADD KEY `idx_entries_medication_id` (`medication_id`),
  ADD KEY `idx_entries_user_time` (`user_id`,`time`),
  ADD KEY `idx_entries_created_at` (`created_at`);

--
-- Indizes für die Tabelle `entry_tags`
--
ALTER TABLE `entry_tags`
  ADD PRIMARY KEY (`entry_id`,`tag_id`),
  ADD KEY `idx_entry_tags_entry_id` (`entry_id`),
  ADD KEY `idx_entry_tags_tag_id` (`tag_id`);

--
-- Indizes für die Tabelle `families`
--
ALTER TABLE `families`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_meds_family_name` (`family_id`,`name`),
  ADD KEY `idx_medications_family_id` (`family_id`),
  ADD KEY `idx_medications_is_active` (`is_active`);

--
-- Indizes für die Tabelle `notification_log`
--
ALTER TABLE `notification_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_sent` (`user_id`,`sent_at`);

--
-- Indizes für die Tabelle `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tags_family_name` (`family_id`,`name`),
  ADD KEY `idx_tags_family_id` (`family_id`),
  ADD KEY `idx_tags_is_active` (`is_active`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_family_id` (`family_id`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_is_active` (`is_active`);

--
-- Indizes für die Tabelle `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_badge` (`user_id`,`badge_id`),
  ADD KEY `idx_user_badges_user_id` (`user_id`),
  ADD KEY `idx_user_badges_badge_id` (`badge_id`),
  ADD KEY `idx_user_badges_earned_at` (`earned_at`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `consents`
--
ALTER TABLE `consents`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `entries`
--
ALTER TABLE `entries`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT für Tabelle `families`
--
ALTER TABLE `families`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `glossary`
--
ALTER TABLE `glossary`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `notification_log`
--
ALTER TABLE `notification_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `consents`
--
ALTER TABLE `consents`
  ADD CONSTRAINT `consents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `entries`
--
ALTER TABLE `entries`
  ADD CONSTRAINT `entries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entries_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `entry_tags`
--
ALTER TABLE `entry_tags`
  ADD CONSTRAINT `entry_tags_ibfk_1` FOREIGN KEY (`entry_id`) REFERENCES `entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entry_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `medications`
--
ALTER TABLE `medications`
  ADD CONSTRAINT `medications_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `notification_log`
--
ALTER TABLE `notification_log`
  ADD CONSTRAINT `fk_nl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD CONSTRAINT `fk_ns_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `tags`
--
ALTER TABLE `tags`
  ADD CONSTRAINT `tags_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE;
COMMIT;
