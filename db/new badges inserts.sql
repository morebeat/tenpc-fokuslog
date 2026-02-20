-- 1. Neue Streak-Badges (Funktionieren sofort mit bestehendem Code)
INSERT INTO badges (name, description, icon_class, required_streak) VALUES 
('2-Wochen-Profi', '14 Tage am Stück! Du bist auf dem besten Weg.', '🔥', 14),
('Monats-Meister', '30 Tage Fokus! Ein ganzer Monat geschafft.', '🏆', 30),
('Quartals-König', '90 Tage Disziplin. Du bist unaufhaltsam!', '👑', 90),
('Jahres-Legende', '365 Tage. Ein Jahr voller Erfolge!', '🌟', 365);

-- 2. Spezial-Badges (Benötigen Code-Anpassung, siehe unten)
-- Wir setzen required_streak auf NULL, damit sie nicht versehentlich durch Streaks vergeben werden.
INSERT INTO badges (name, description, icon_class, required_streak) VALUES 
('Wochenend-Warrior', 'Auch am Wochenende an dich gedacht!', '🏖️', NULL),
('Nachteule', 'Einen Eintrag spät am Abend gemacht.', '🦉', NULL),
('Früher Vogel', 'Schon morgens alles erledigt!', '🌅', NULL);
