<?php
/**
 * Cleanup-Script für alte Events und Sessions.
 * 
 * Verwendung via Cron (jede Stunde):
 * 0 * * * * php /path/to/scripts/cleanup-events.php
 * 
 * Oder manuell:
 * php scripts/cleanup-events.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Umgebungsvariablen laden
require_once __DIR__ . '/../api/lib/EnvLoader.php';

$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    echo "FEHLER: .env nicht gefunden\n";
    exit(1);
}

$env = \FokusLog\EnvLoader::load($envFile);

// DB-Verbindung
try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $env['DB_HOST'],
        $env['DB_NAME']
    );
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "DB-Verbindung fehlgeschlagen: " . $e->getMessage() . "\n";
    exit(1);
}

$deleted = 0;

// Events älter als 1 Stunde löschen
try {
    $stmt = $pdo->prepare(
        'DELETE FROM events_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $stmt->execute();
    $deleted += $stmt->rowCount();
    echo "Events gelöscht: " . $stmt->rowCount() . "\n";
} catch (PDOException $e) {
    echo "events_queue: " . $e->getMessage() . "\n";
}

// Sessions älter als 24 Stunden löschen
try {
    $stmt = $pdo->prepare(
        'DELETE FROM sessions WHERE last_access < UNIX_TIMESTAMP() - 86400'
    );
    $stmt->execute();
    $deleted += $stmt->rowCount();
    echo "Sessions gelöscht: " . $stmt->rowCount() . "\n";
} catch (PDOException $e) {
    echo "sessions: " . $e->getMessage() . "\n";
}

echo "Cleanup abgeschlossen. Gesamt gelöscht: {$deleted}\n";
