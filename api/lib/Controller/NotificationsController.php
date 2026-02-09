<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use PDO;
use Throwable;

/**
 * Controller fü¼r Benachrichtigungseinstellungen und Push-Subscriptions.
 */
class NotificationsController extends BaseController
{
    /**
     * GET /notifications/settings
     * Ruft die Benachrichtigungseinstellungen des aktuellen Benutzers ab.
     */
    public function getSettings(): void
    {
        try {
            $user = $this->requireAuth();

            $stmt = $this->pdo->prepare('SELECT * FROM notification_settings WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $settings = $stmt->fetch();

            if (!$settings) {
                // Standardeinstellungen zurü¼ckgeben
                $settings = [
                    'push_enabled' => false,
                    'push_morning' => true,
                    'push_noon' => true,
                    'push_evening' => true,
                    'push_morning_time' => '08:00',
                    'push_noon_time' => '12:00',
                    'push_evening_time' => '18:00',
                    'email' => null,
                    'email_verified' => false,
                    'email_weekly_digest' => false,
                    'email_digest_day' => 0,
                    'email_missing_alert' => false,
                    'email_missing_days' => 3
                ];
            } else {
                // Sensible Felder entfernen
                unset($settings['id'], $settings['push_subscription'], $settings['email_verification_token']);
                $settings['push_morning_time'] = substr($settings['push_morning_time'], 0, 5);
                $settings['push_noon_time'] = substr($settings['push_noon_time'], 0, 5);
                $settings['push_evening_time'] = substr($settings['push_evening_time'], 0, 5);

                // Boolean-Konvertierung
                $settings['push_enabled'] = (bool)$settings['push_enabled'];
                $settings['push_morning'] = (bool)$settings['push_morning'];
                $settings['push_noon'] = (bool)$settings['push_noon'];
                $settings['push_evening'] = (bool)$settings['push_evening'];
                $settings['email_verified'] = (bool)$settings['email_verified'];
                $settings['email_weekly_digest'] = (bool)$settings['email_weekly_digest'];
                $settings['email_missing_alert'] = (bool)$settings['email_missing_alert'];
            }

            $this->respond(200, ['settings' => $settings]);
        } catch (Throwable $e) {
            app_log('ERROR', 'notification_settings_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden der Einstellungen']);
        }
    }

    /**
     * PUT /notifications/settings
     * Aktualisiert die Benachrichtigungseinstellungen.
     */
    public function updateSettings(): void
    {
        try {
            $user = $this->requireAuth();
            $data = $this->getJsonBody();

            // Prü¼fen ob Einstellungen existieren
            $stmt = $this->pdo->prepare('SELECT id FROM notification_settings WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $exists = $stmt->fetch();

            // Erlaubte Felder
            $allowedFields = [
                'push_morning', 'push_noon', 'push_evening',
                'push_morning_time', 'push_noon_time', 'push_evening_time',
                'email', 'email_weekly_digest', 'email_digest_day',
                'email_missing_alert', 'email_missing_days'
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Zeit-Konvertierung
            foreach (['push_morning_time', 'push_noon_time', 'push_evening_time'] as $timeField) {
                if (isset($updateData[$timeField]) && preg_match('/^\d{2}:\d{2}$/', $updateData[$timeField])) {
                    $updateData[$timeField] = $updateData[$timeField] . ':00';
                }
            }

            // E-Mail-Validierung
            if (isset($updateData['email']) && $updateData['email'] !== null) {
                if (!filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->respond(400, ['error' => 'Ungü¼ltige E-Mail-Adresse']);
                }
                // Bei ü„nderung der E-Mail: Verifizierung zurü¼cksetzen
                $updateData['email_verified'] = 0;
                $updateData['email_verification_token'] = bin2hex(random_bytes(32));
            }

            if ($exists) {
                // Update
                $setParts = [];
                $values = [];
                foreach ($updateData as $field => $value) {
                    $setParts[] = "`$field` = ?";
                    $values[] = $value;
                }
                $values[] = $user['id'];

                $sql = 'UPDATE notification_settings SET ' . implode(', ', $setParts) . ' WHERE user_id = ?';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($values);
            } else {
                // Insert
                $updateData['user_id'] = $user['id'];
                $fields = array_keys($updateData);
                $placeholders = array_fill(0, count($fields), '?');

                $sql = 'INSERT INTO notification_settings (`' . implode('`, `', $fields) . '`) VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_values($updateData));
            }

            $this->logAction($user['id'], 'notification_settings_updated', array_keys($updateData));

            // Neue Einstellungen zurückgeben
            $this->getSettings();
        } catch (Throwable $e) {
            app_log('ERROR', 'notification_settings_update_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Speichern der Einstellungen']);
        }
    }

    /**
     * POST /notifications/push/subscribe
     * Speichert eine Push-Subscription.
     */
    public function subscribePush(): void
    {
        try {
            $user = $this->requireAuth();
            $data = $this->getJsonBody();

            if (empty($data['subscription'])) {
                $this->respond(400, ['error' => 'Subscription-Daten fehlen']);
            }

            $subscription = json_encode($data['subscription']);

            // Upsert
            $stmt = $this->pdo->prepare('
                INSERT INTO notification_settings (user_id, push_enabled, push_subscription)
                VALUES (?, 1, ?)
                ON DUPLICATE KEY UPDATE push_enabled = 1, push_subscription = VALUES(push_subscription)
            ');
            $stmt->execute([$user['id'], $subscription]);

            $this->logAction($user['id'], 'push_subscribed');
            $this->respond(200, ['success' => true, 'message' => 'Push-Benachrichtigungen aktiviert']);
        } catch (Throwable $e) {
            app_log('ERROR', 'push_subscribe_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Aktivieren der Push-Benachrichtigungen']);
        }
    }

    /**
     * POST /notifications/push/unsubscribe
     * Deaktiviert Push-Benachrichtigungen.
     */
    public function unsubscribePush(): void
    {
        try {
            $user = $this->requireAuth();

            $stmt = $this->pdo->prepare('
                UPDATE notification_settings
                SET push_enabled = 0, push_subscription = NULL
                WHERE user_id = ?
            ');
            $stmt->execute([$user['id']]);

            $this->logAction($user['id'], 'push_unsubscribed');
            $this->respond(200, ['success' => true, 'message' => 'Push-Benachrichtigungen deaktiviert']);
        } catch (Throwable $e) {
            app_log('ERROR', 'push_unsubscribe_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Deaktivieren']);
        }
    }

    /**
     * POST /notifications/email/verify
     * Verifiziert eine E-Mail-Adresse mit Token.
     */
    public function verifyEmail(): void
    {
        try {
            $data = $this->getJsonBody();

            if (empty($data['token'])) {
                $this->respond(400, ['error' => 'Verifizierungstoken fehlt']);
            }

            $stmt = $this->pdo->prepare('
                UPDATE notification_settings
                SET email_verified = 1, email_verification_token = NULL
                WHERE email_verification_token = ?
            ');
            $stmt->execute([$data['token']]);

            if ($stmt->rowCount() === 0) {
                $this->respond(400, ['error' => 'Ungü¼ltiger oder abgelaufener Token']);
            }

            $this->respond(200, ['success' => true, 'message' => 'E-Mail-Adresse verifiziert']);
        } catch (Throwable $e) {
            app_log('ERROR', 'email_verify_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler bei der Verifizierung']);
        }
    }

    /**
     * POST /notifications/email/resend-verification
     * Sendet Verifizierungs-E-Mail erneut.
     */
    public function resendVerification(): void
    {
        try {
            $user = $this->requireAuth();

            $stmt = $this->pdo->prepare('SELECT email, email_verified FROM notification_settings WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $settings = $stmt->fetch();

            if (!$settings || empty($settings['email'])) {
                $this->respond(400, ['error' => 'Keine E-Mail-Adresse hinterlegt']);
            }

            if ($settings['email_verified']) {
                $this->respond(400, ['error' => 'E-Mail bereits verifiziert']);
            }

            // Neuen Token generieren
            $token = bin2hex(random_bytes(32));
            $stmt = $this->pdo->prepare('UPDATE notification_settings SET email_verification_token = ? WHERE user_id = ?');
            $stmt->execute([$token, $user['id']]);

            // E-Mail senden (Implementierung in separatem Service)
            $this->sendVerificationEmail($settings['email'], $token, $user['username']);

            $this->respond(200, ['success' => true, 'message' => 'Verifizierungs-E-Mail gesendet']);
        } catch (Throwable $e) {
            app_log('ERROR', 'resend_verification_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Senden der Verifizierungs-E-Mail']);
        }
    }

    /**
     * GET /notifications/status
     * Gibt Status der fehlenden Eintrü¤ge zurü¼ck (fü¼r Dashboard-Anzeige).
     */
    public function getStatus(): void
    {
        try {
            $user = $this->requireAuth();

            // Letzter Eintrag
            $stmt = $this->pdo->prepare('SELECT MAX(date) as last_date FROM entries WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $result = $stmt->fetch();
            $lastEntryDate = $result['last_date'];

            $daysSinceEntry = null;
            $todayMissing = [];

            if ($lastEntryDate) {
                $lastDate = new \DateTime($lastEntryDate);
                $today = new \DateTime();
                $daysSinceEntry = $today->diff($lastDate)->days;
            }

            // Fehlende Eintrü¤ge fü¼r heute
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare('SELECT time FROM entries WHERE user_id = ? AND date = ?');
            $stmt->execute([$user['id'], $today]);
            $todayEntries = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $allSlots = ['morning', 'noon', 'evening'];
            $todayMissing = array_diff($allSlots, $todayEntries);

            // Benachrichtigungseinstellungen
            $stmt = $this->pdo->prepare('SELECT push_enabled, email_weekly_digest, email_verified FROM notification_settings WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $settings = $stmt->fetch();

            $this->respond(200, [
                'last_entry_date' => $lastEntryDate,
                'days_since_entry' => $daysSinceEntry,
                'today_missing_slots' => array_values($todayMissing),
                'notifications' => [
                    'push_enabled' => (bool)($settings['push_enabled'] ?? false),
                    'email_enabled' => (bool)($settings['email_weekly_digest'] ?? false) && (bool)($settings['email_verified'] ?? false)
                ]
            ]);
        } catch (Throwable $e) {
            app_log('ERROR', 'notification_status_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden des Status']);
        }
    }

    /**
     * Sendet Verifizierungs-E-Mail (Helper).
     */
    private function sendVerificationEmail(string $email, string $token, string $username): void
    {
        $baseUrl = $this->getBaseUrl();
        $verifyUrl = "$baseUrl/app/verify-email.html?token=$token";

        $subject = 'FokusLog: E-Mail-Adresse bestü¤tigen';
        $body = "Hallo $username,\n\n";
        $body .= "bitte bestü¤tige deine E-Mail-Adresse fü¼r FokusLog-Benachrichtigungen:\n\n";
        $body .= "$verifyUrl\n\n";
        $body .= "Wenn du diese E-Mail nicht angefordert hast, kannst du sie ignorieren.\n\n";
        $body .= "Dein FokusLog-Team";

        $headers = [
            'From: noreply@fokuslog.app',
            'Content-Type: text/plain; charset=utf-8'
        ];

        if (function_exists('mail')) {
            mail($email, $subject, $body, implode("\r\n", $headers));
        }

        app_log('INFO', 'verification_email_sent', ['email' => $email]);
    }

    /**
     * Ermittelt Base-URL.
     */
    private function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "$protocol://$host";
    }
}
