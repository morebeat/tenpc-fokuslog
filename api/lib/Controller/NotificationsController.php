<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use PDO;
use Throwable;

/**
 * Controller für Benachrichtigungseinstellungen und Push-Subscriptions.
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
                // Standardeinstellungen zurückgeben
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

            // Prüfen ob Einstellungen existieren
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
                    $this->respond(400, ['error' => 'Ungültige E-Mail-Adresse']);
                }
                // Bei Änderung der E-Mail: Verifizierung zurücksetzen
                $updateData['email_verified'] = 0;
                $updateData['email_verification_token'] = bin2hex(random_bytes(32));
            }

            // Explizite Queries ohne dynamisch eingesetzte Feldnamen (kein SQL-Injection-Risiko)
            $params = [
                ':user_id'                  => $user['id'],
                ':push_morning'             => $updateData['push_morning'] ?? null,
                ':push_noon'                => $updateData['push_noon'] ?? null,
                ':push_evening'             => $updateData['push_evening'] ?? null,
                ':push_morning_time'        => $updateData['push_morning_time'] ?? null,
                ':push_noon_time'           => $updateData['push_noon_time'] ?? null,
                ':push_evening_time'        => $updateData['push_evening_time'] ?? null,
                ':email'                    => $updateData['email'] ?? null,
                ':email_verified'           => $updateData['email_verified'] ?? null,
                ':email_verification_token' => $updateData['email_verification_token'] ?? null,
                ':email_weekly_digest'      => $updateData['email_weekly_digest'] ?? null,
                ':email_digest_day'         => $updateData['email_digest_day'] ?? null,
                ':email_missing_alert'      => $updateData['email_missing_alert'] ?? null,
                ':email_missing_days'       => $updateData['email_missing_days'] ?? null,
            ];

            if ($exists) {
                $stmt = $this->pdo->prepare('
                    UPDATE notification_settings SET
                        push_morning              = COALESCE(:push_morning,              push_morning),
                        push_noon                 = COALESCE(:push_noon,                 push_noon),
                        push_evening              = COALESCE(:push_evening,              push_evening),
                        push_morning_time         = COALESCE(:push_morning_time,         push_morning_time),
                        push_noon_time            = COALESCE(:push_noon_time,            push_noon_time),
                        push_evening_time         = COALESCE(:push_evening_time,         push_evening_time),
                        email                     = COALESCE(:email,                     email),
                        email_verified            = COALESCE(:email_verified,            email_verified),
                        email_verification_token  = COALESCE(:email_verification_token,  email_verification_token),
                        email_weekly_digest       = COALESCE(:email_weekly_digest,       email_weekly_digest),
                        email_digest_day          = COALESCE(:email_digest_day,          email_digest_day),
                        email_missing_alert       = COALESCE(:email_missing_alert,       email_missing_alert),
                        email_missing_days        = COALESCE(:email_missing_days,        email_missing_days)
                    WHERE user_id = :user_id
                ');
            } else {
                $stmt = $this->pdo->prepare('
                    INSERT INTO notification_settings
                        (user_id, push_morning, push_noon, push_evening,
                         push_morning_time, push_noon_time, push_evening_time,
                         email, email_verified, email_verification_token,
                         email_weekly_digest, email_digest_day,
                         email_missing_alert, email_missing_days)
                    VALUES
                        (:user_id,
                         :push_morning, :push_noon, :push_evening,
                         :push_morning_time, :push_noon_time, :push_evening_time,
                         :email, :email_verified, :email_verification_token,
                         :email_weekly_digest, :email_digest_day,
                         :email_missing_alert, :email_missing_days)
                ');
            }

            $stmt->execute($params);

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
                $this->respond(400, ['error' => 'Ungültiger oder abgelaufener Token']);
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
     * Gibt Status der fehlenden Einträge zurück (für Dashboard-Anzeige).
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

            // Fehlende Einträge für heute
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

        $subject = 'FokusLog: E-Mail-Adresse bestätigen';
        $body = "Hallo $username,\n\n";
        $body .= "bitte bestätige deine E-Mail-Adresse für FokusLog-Benachrichtigungen:\n\n";
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

    /**
     * GET /notifications/vapid-key
     * Gibt den VAPID Public Key für Push-Subscriptions zurück.
     */
    public function getVapidKey(): void
    {
        $vapidPublicKey = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?: null;

        if ($vapidPublicKey) {
            $this->respond(200, ['vapid_public_key' => $vapidPublicKey]);
        } else {
            $this->respond(404, ['error' => 'VAPID public key not configured']);
        }
    }
}
