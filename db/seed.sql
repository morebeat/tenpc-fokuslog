-- FokusLog - Testdaten
--
-- Dieses Skript füllt die Datenbank mit Beispieldaten.
-- Es ist für die Verwendung mit `scripts/rebuild-database.sh` vorgesehen und basiert auf dem phpMyAdmin Export.

SET NAMES utf8mb4;

-- 1. Familie erstellen
INSERT INTO `families` (`id`, `name`, `created_at`) VALUES
(1, 'Familie Mustermann', '2026-02-05 21:28:23');

-- 2. Benutzer erstellen (Passwort für alle ist 'password')
-- password_hash for 'password'
SET @pw_hash = '$2y$10$k.v4.sgt0G4z2b.p2eX.A.a.gJkC6z.jY.p3g.f.Z.iX.g.h.i.j';

INSERT INTO `users` (`id`, `family_id`, `username`, `password_hash`, `role`, `gender`, `initial_weight`, `points`, `streak_current`, `last_entry_date`, `created_at`) VALUES
(1, 1, 'PapaMuster', @pw_hash, 'parent', 'male', 85.00, 0, 0, NULL, '2026-02-05 21:28:23'),
(2, 1, 'MaxMuster', @pw_hash, 'child', 'male', 42.50, 0, 0, NULL, '2026-02-05 21:28:23'),
(3, 1, 'FrauLehrer', @pw_hash, 'teacher', 'female', NULL, 0, 0, NULL, '2026-02-05 21:28:23');

-- 3. Medikamente anlegen
INSERT INTO `medications` (`id`, `family_id`, `name`, `default_dose`) VALUES
(1, 1, 'Medikinet', '10mg'),
(2, 1, 'Ritalin', '15mg');

-- 4. Tags anlegen (Reihenfolge aus Dump beibehalten)
INSERT INTO `tags` (`id`, `family_id`, `name`) VALUES
(2, 1, 'Hausaufgaben'),
(1, 1, 'Schule'),
(3, 1, 'Wochenende');

-- 5. Badges werden bereits in schema_v4.sql angelegt (global für alle)

-- 6. Einträge für das Kind 'MaxMuster' (user_id = 2) erstellen (Daten aus Dump)
INSERT INTO `entries` (`id`, `user_id`, `medication_id`, `dose`, `date`, `time`, `sleep`, `hyperactivity`, `mood`, `irritability`, `appetite`, `focus`, `weight`, `other_effects`, `side_effects`, `special_events`, `menstruation_phase`, `teacher_feedback`, `emotional_reactions`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '10mg', '2026-02-03', 'morning', 4, 3, 4, 2, 5, 3, 42.50, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 21:28:23', '2026-02-05 21:28:23'),
(2, 2, 1, '10mg', '2026-02-03', 'noon', NULL, 2, 5, 1, 3, 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 21:28:23', '2026-02-05 21:28:23'),
(3, 2, 1, '10mg', '2026-02-04', 'morning', 5, 2, 5, 1, 4, 5, 42.80, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 21:28:23', '2026-02-05 21:28:23'),
(4, 2, 1, '10mg', '2026-02-04', 'noon', NULL, 2, 4, 2, 2, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 21:28:23', '2026-02-05 21:28:23'),
(5, 2, 1, '10mg', '2026-02-05', 'morning', 3, 4, 3, 4, 5, 2, 42.70, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-05 21:28:23', '2026-02-05 21:28:23');

-- 7. Tags zu Einträgen hinzufügen
INSERT INTO `entry_tags` (`entry_id`, `tag_id`) VALUES
(1, 1), (2, 2), (3, 1), (4, 2), (5, 1);