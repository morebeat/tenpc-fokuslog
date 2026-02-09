<?php
/**
 * FokusLog Notification Worker
 * 
 * Dieses Script sollte via Cron regelmäßig ausgeführt werden (z.B. alle 5 Minuten).
 * Es verarbeitet:
 * 1. Push-Benachrichtigungen für Erinnerungen (morgens/mittags/abends)
 * 2. E-Mail-Digests (wöchentliche Zusammenfassung)
 * 3. Alerts bei fehlenden Einträgen
 * 
 * Cron-Beispiel (alle 5 Minuten):
 * */5 * * * * php /path/to/fokuslog/scripts/notification-worker.php >> /path/to/fokuslog/logs/notifications.log 2>&1
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/bootstrap.php';

// Konfiguration
$envFile = __DIR__ . '/../.env';
if (!is_file($envFile)) {
    die("ERROR: .env file not found\n");
}
$env = parse_ini_file($envFile);
if (!$env) {
    die("ERROR: Could not parse .env file\n");
}

// Datenbankverbindung
$dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("ERROR: Database connection failed: " . $e->getMessage() . "\n");
}

// VAPID Keys für Web Push (aus .env oder generieren)
$vapidPublicKey = $env['VAPID_PUBLIC_KEY'] ?? null;
$vapidPrivateKey = $env['VAPID_PRIVATE_KEY'] ?? null;

echo "[" . date('Y-m-d H:i:s') . "] Notification Worker gestartet\n";

// 1. Push-Erinnerungen verarbeiten
processPushReminders($pdo, $vapidPublicKey, $vapidPrivateKey);

// 2. E-Mail-Digests verarbeiten
processEmailDigests($pdo, $env);

// 3. Alerts für fehlende Einträge
processMissingEntryAlerts($pdo, $env);

echo "[" . date('Y-m-d H:i:s') . "] Notification Worker beendet\n";


/**
 * Verarbeitet fällige Push-Erinnerungen.
 */
function processPushReminders(PDO $pdo, ?string $publicKey, ?string $privateKey): void
{
    $currentTime = date('H:i:s');
    $today = date('Y-m-d');
    $currentTimeSlot = getCurrentTimeSlot($currentTime);
    
    if (!$currentTimeSlot) {
        return; // Keine Erinnerung für diese Tageszeit
    }
    
    $timeColumn = "push_{$currentTimeSlot}_time";
    $enabledColumn = "push_{$currentTimeSlot}";
    
    // Finde alle Benutzer, die jetzt erinnert werden sollen
    $sql = "SELECT ns.*, u.username, u.id as user_id
            FROM notification_settings ns
            JOIN users u ON ns.user_id = u.id
            WHERE ns.push_enabled = 1 
              AND ns.{$enabledColumn} = 1
              AND ns.push_subscription IS NOT NULL
              AND TIME(ns.{$timeColumn}) BETWEEN TIME(SUBTIME(?, '00:05:00')) AND TIME(ADDTIME(?, '00:05:00'))";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentTime, $currentTime]);
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        // Prüfen ob heute schon ein Eintrag für diesen Slot existiert
        $checkStmt = $pdo->prepare('SELECT id FROM entries WHERE user_id = ? AND date = ? AND time = ?');
        $checkStmt->execute([$user['user_id'], $today, $currentTimeSlot]);
        
        if ($checkStmt->fetch()) {
            continue; // Eintrag existiert bereits
        }
        
        // Prüfen ob heute bereits eine Benachrichtigung für diesen Slot gesendet wurde
        $logStmt = $pdo->prepare("
            SELECT id FROM notification_log 
            WHERE user_id = ? AND type = 'push_reminder' AND DATE(sent_at) = ?
              AND JSON_EXTRACT(payload, '$.slot') = ?
        ");
        $logStmt->execute([$user['user_id'], $today, $currentTimeSlot]);
        
        if ($logStmt->fetch()) {
            continue; // Bereits benachrichtigt
        }
        
        // Push senden
        $message = getTimeSlotMessage($currentTimeSlot);
        $success = sendWebPush(
            json_decode($user['push_subscription'], true),
            [
                'title' => 'FokusLog Erinnerung',
                'body' => $message,
                'icon' => '/app/icons/icon-192.png',
                'badge' => '/app/icons/badge-72.png',
                'tag' => "reminder-$currentTimeSlot",
                'data' => [
                    'url' => "/app/entry.html?time=$currentTimeSlot",
                    'slot' => $currentTimeSlot
                ]
            ],
            $publicKey,
            $privateKey
        );
        
        // Log schreiben
        $logInsert = $pdo->prepare("
            INSERT INTO notification_log (user_id, type, payload) 
            VALUES (?, 'push_reminder', ?)
        ");
        $logInsert->execute([
            $user['user_id'],
            json_encode(['slot' => $currentTimeSlot, 'success' => $success])
        ]);
        
        echo "  Push gesendet an {$user['username']} ($currentTimeSlot): " . ($success ? 'OK' : 'FEHLER') . "\n";
    }
}


/**
 * Verarbeitet wöchentliche E-Mail-Digests.
 */
function processEmailDigests(PDO $pdo, array $env): void
{
    $currentDayOfWeek = (int)date('w'); // 0=Sonntag, 1=Montag, etc.
    $today = date('Y-m-d');
    
    // Finde alle Benutzer, die heute ihren Digest-Tag haben
    $sql = "SELECT ns.*, u.username, u.id as user_id, f.name as family_name
            FROM notification_settings ns
            JOIN users u ON ns.user_id = u.id
            JOIN families f ON u.family_id = f.id
            WHERE ns.email_weekly_digest = 1 
              AND ns.email_verified = 1
              AND ns.email IS NOT NULL
              AND ns.email_digest_day = ?
              AND u.role IN ('parent', 'adult')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentDayOfWeek]);
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        // Prüfen ob diese Woche bereits gesendet
        $logStmt = $pdo->prepare("
            SELECT id FROM notification_log 
            WHERE user_id = ? AND type = 'email_digest' AND DATE(sent_at) >= DATE_SUB(?, INTERVAL 6 DAY)
        ");
        $logStmt->execute([$user['user_id'], $today]);
        
        if ($logStmt->fetch()) {
            continue; // Diese Woche bereits gesendet
        }
        
        // Digest-Daten sammeln
        $digestData = collectDigestData($pdo, $user['user_id'], $user['family_id'] ?? 0);
        
        if (empty($digestData['entries'])) {
            continue; // Keine Einträge zu berichten
        }
        
        // E-Mail senden
        $success = sendDigestEmail($user, $digestData, $env);
        
        // Log schreiben
        $logInsert = $pdo->prepare("
            INSERT INTO notification_log (user_id, type, payload) 
            VALUES (?, 'email_digest', ?)
        ");
        $logInsert->execute([
            $user['user_id'],
            json_encode(['entry_count' => count($digestData['entries']), 'success' => $success])
        ]);
        
        echo "  E-Mail-Digest gesendet an {$user['email']}: " . ($success ? 'OK' : 'FEHLER') . "\n";
    }
}


/**
 * Verarbeitet Alerts für fehlende Einträge.
 */
function processMissingEntryAlerts(PDO $pdo, array $env): void
{
    $today = date('Y-m-d');
    
    // Finde alle Benutzer mit aktivierten Missing-Alerts
    $sql = "SELECT ns.*, u.username, u.id as user_id, u.last_entry_date
            FROM notification_settings ns
            JOIN users u ON ns.user_id = u.id
            WHERE ns.email_missing_alert = 1 
              AND ns.email_verified = 1
              AND ns.email IS NOT NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        // Tage seit letztem Eintrag berechnen
        if (empty($user['last_entry_date'])) {
            continue; // Noch nie einen Eintrag gemacht
        }
        
        $lastEntry = new DateTime($user['last_entry_date']);
        $now = new DateTime($today);
        $daysSince = $now->diff($lastEntry)->days;
        
        if ($daysSince < $user['email_missing_days']) {
            continue; // Schwelle noch nicht erreicht
        }
        
        // Prüfen ob kürzlich bereits benachrichtigt (innerhalb der letzten X Tage)
        $logStmt = $pdo->prepare("
            SELECT id FROM notification_log 
            WHERE user_id = ? AND type = 'email_missing_alert' 
              AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $logStmt->execute([$user['user_id'], $user['email_missing_days']]);
        
        if ($logStmt->fetch()) {
            continue; // Bereits kürzlich benachrichtigt
        }
        
        // Alert senden
        $success = sendMissingEntryAlert($user, $daysSince, $env);
        
        // Log schreiben
        $logInsert = $pdo->prepare("
            INSERT INTO notification_log (user_id, type, payload) 
            VALUES (?, 'email_missing_alert', ?)
        ");
        $logInsert->execute([
            $user['user_id'],
            json_encode(['days_since' => $daysSince, 'success' => $success])
        ]);
        
        echo "  Missing-Alert gesendet an {$user['email']} ($daysSince Tage): " . ($success ? 'OK' : 'FEHLER') . "\n";
    }
}


// ========== Hilfsfunktionen ==========

function getCurrentTimeSlot(string $time): ?string
{
    $hour = (int)substr($time, 0, 2);
    
    if ($hour >= 6 && $hour < 11) {
        return 'morning';
    } elseif ($hour >= 11 && $hour < 15) {
        return 'noon';
    } elseif ($hour >= 16 && $hour < 21) {
        return 'evening';
    }
    
    return null;
}

function getTimeSlotMessage(string $slot): string
{
    $messages = [
        'morning' => 'Guten Morgen! Zeit für deinen Morgeneintrag in FokusLog.',
        'noon' => 'Mittagszeit! Wie läuft dein Tag bisher? Mach jetzt deinen Eintrag.',
        'evening' => 'Guten Abend! Vergiss nicht, deinen Tageseintrag in FokusLog zu machen.'
    ];
    
    return $messages[$slot] ?? 'Zeit für deinen FokusLog-Eintrag!';
}

function sendWebPush(array $subscription, array $payload, ?string $publicKey, ?string $privateKey): bool
{
    if (!$publicKey || !$privateKey) {
        echo "    WARNUNG: VAPID Keys nicht konfiguriert\n";
        return false;
    }
    
    // Vereinfachte Web Push Implementierung
    // In Produktion sollte eine Library wie web-push-php verwendet werden
    $endpoint = $subscription['endpoint'] ?? '';
    $keys = $subscription['keys'] ?? [];
    
    if (empty($endpoint) || empty($keys)) {
        return false;
    }
    
    // Payload als JSON
    $payloadJson = json_encode($payload);
    
    // HTTP Request an den Push-Service
    $headers = [
        'Content-Type: application/json',
        'TTL: 86400',
        'Content-Length: ' . strlen($payloadJson)
    ];
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

function collectDigestData(PDO $pdo, int $userId, int $familyId): array
{
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    $today = date('Y-m-d');
    
    // Alle relevanten Benutzer für Parent-Rolle
    $userIds = [$userId];
    
    $userStmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $userRole = $userStmt->fetchColumn();
    
    if ($userRole === 'parent' && $familyId) {
        $familyStmt = $pdo->prepare('SELECT id FROM users WHERE family_id = ?');
        $familyStmt->execute([$familyId]);
        $userIds = $familyStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Einträge der letzten Woche
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT e.*, u.username, m.name as medication_name
            FROM entries e 
            JOIN users u ON e.user_id = u.id
            LEFT JOIN medications m ON e.medication_id = m.id
            WHERE e.user_id IN ($placeholders) AND e.date BETWEEN ? AND ?
            ORDER BY e.date DESC, FIELD(e.time, 'morning', 'noon', 'evening')";
    
    $params = array_merge($userIds, [$weekAgo, $today]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();
    
    // Durchschnitte berechnen
    $totals = ['mood' => 0, 'focus' => 0, 'sleep' => 0, 'count' => 0];
    foreach ($entries as $entry) {
        if ($entry['mood']) { $totals['mood'] += $entry['mood']; $totals['count']++; }
        if ($entry['focus']) { $totals['focus'] += $entry['focus']; }
        if ($entry['sleep']) { $totals['sleep'] += $entry['sleep']; }
    }
    
    $averages = [];
    if ($totals['count'] > 0) {
        $averages = [
            'mood' => round($totals['mood'] / $totals['count'], 1),
            'focus' => round($totals['focus'] / $totals['count'], 1),
            'sleep' => round($totals['sleep'] / $totals['count'], 1)
        ];
    }
    
    return [
        'entries' => $entries,
        'entry_count' => count($entries),
        'averages' => $averages,
        'period' => ['from' => $weekAgo, 'to' => $today]
    ];
}

function sendDigestEmail(array $user, array $digestData, array $env): bool
{
    $subject = 'FokusLog: Wöchentliche Zusammenfassung';
    
    $body = "Hallo {$user['username']},\n\n";
    $body .= "hier ist deine wöchentliche Zusammenfassung von FokusLog.\n\n";
    $body .= "Zeitraum: {$digestData['period']['from']} bis {$digestData['period']['to']}\n";
    $body .= "Anzahl Einträge: {$digestData['entry_count']}\n\n";
    
    if (!empty($digestData['averages'])) {
        $body .= "Durchschnittswerte:\n";
        $body .= "- Stimmung: {$digestData['averages']['mood']}/5\n";
        $body .= "- Fokus: {$digestData['averages']['focus']}/5\n";
        $body .= "- Schlaf: {$digestData['averages']['sleep']}/5\n\n";
    }
    
    $body .= "Details findest du in der App unter Auswertung.\n\n";
    $body .= "Dein FokusLog-Team";
    
    $headers = [
        'From: noreply@fokuslog.app',
        'Content-Type: text/plain; charset=utf-8'
    ];
    
    if (function_exists('mail')) {
        return mail($user['email'], $subject, $body, implode("\r\n", $headers));
    }
    
    return false;
}

function sendMissingEntryAlert(array $user, int $daysSince, array $env): bool
{
    $subject = 'FokusLog: Wir vermissen dich!';
    
    $body = "Hallo {$user['username']},\n\n";
    $body .= "uns ist aufgefallen, dass du seit $daysSince Tagen keinen Eintrag in FokusLog gemacht hast.\n\n";
    $body .= "Regelmäßige Einträge helfen dabei, Muster zu erkennen und die Behandlung zu optimieren.\n\n";
    $body .= "Mach jetzt einen schnellen Eintrag: https://fokuslog.app/app/entry.html\n\n";
    $body .= "Falls du die Erinnerungen nicht mehr erhalten möchtest, kannst du sie in den Einstellungen deaktivieren.\n\n";
    $body .= "Dein FokusLog-Team";
    
    $headers = [
        'From: noreply@fokuslog.app',
        'Content-Type: text/plain; charset=utf-8'
    ];
    
    if (function_exists('mail')) {
        return mail($user['email'], $subject, $body, implode("\r\n", $headers));
    }
    
    return false;
}
