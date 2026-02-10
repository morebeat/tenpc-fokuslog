<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use FokusLog\ValidationException;
use FokusLog\Validator;
use RateLimiter;
use Throwable;

/**
 * Controller für Authentifizierung: Register, Login, Logout, Me.
 */
class AuthController extends BaseController
{
    /**
     * POST /register
     * Registrierung eines neuen Parents und seiner Familie.
     */
    public function register(): void
    {
        $data = $this->getJsonBody();
        // Rate Limiting für Register (verhindert Massen-Registrierungen)
        $limiter = new RateLimiter();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$limiter->check($ip, 10, 60)) {
            app_log('WARNING', 'register_ratelimit_exceeded', ['ip' => $ip]);
            $this->respond(429, ['error' => 'Zu viele Registrierungsversuche. Bitte warten Sie eine Minute.']);
        }

        try {
            $accountType = $data['account_type'] ?? 'family';

            $username = Validator::string($data, 'username', ['min' => 3, 'max' => 100]);
            $password = Validator::string($data, 'password', ['min' => self::MIN_PASSWORD_LENGTH, 'max' => 255]);
            $familyName = Validator::stringOptional($data, 'family_name', ['max' => 100]) ?? '';

            // Bei Einzelpersonen ist der Familienname optional/automatisch
            if ($accountType === 'individual' && $familyName === '') {
                $familyName = 'Privat';
            }

            if ($familyName === '') {
                throw new ValidationException('Das Feld \'family_name\' ist erforderlich.');
            }
        } catch (ValidationException $ve) {
            app_log('WARNING', 'register_validation_failed', ['error' => $ve->getMessage()]);
            $this->respond(400, ['error' => $ve->getMessage()]);
        }

        try {
            // Prüfe, ob Benutzername bereits existiert
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                app_log('WARNING', 'register_user_exists', ['username' => $username]);
                $this->respond(409, ['error' => 'Benutzername existiert bereits']);
            }

            $this->pdo->beginTransaction();

            // Familie anlegen
            $stmt = $this->pdo->prepare('INSERT INTO families (name) VALUES (?)');
            $stmt->execute([$familyName]);
            $familyId = (int)$this->pdo->lastInsertId();

            // Parent anlegen
            $role = 'parent';
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare('INSERT INTO users (family_id, username, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$familyId, $username, $passwordHash, $role]);

            $this->logAction(null, 'register', 'new family and parent');
            $this->pdo->commit();

            app_log('INFO', 'register_success', ['username' => $username, 'family_name' => $familyName]);
            $this->respond(201, ['message' => 'Registrierung erfolgreich']);
        } catch (Throwable $e) {
            error_log("Register Exception: " . $e->getMessage());
            try {
                app_log('ERROR', 'register_failed', [
                    'username' => $username,
                    'error' => $e->getMessage()
                ]);
            } catch (Throwable $t) {
                error_log("Logging failed: " . $t->getMessage());
            }
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->respond(500, ['error' => 'Fehler bei der Registrierung']);
        }
    }

    /**
     * POST /login
     * Anmeldung eines bestehenden Benutzers.
     */
    public function login(): void
    {
        try {
            $data = $this->getJsonBody();
            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';

            if ($username === '' || $password === '') {
                app_log('WARNING', 'login_validation_failed', ['username' => $username]);
                $this->respond(400, ['error' => 'username und password sind erforderlich']);
            }

            // Rate Limiting Check
            $limiter = new RateLimiter();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!$limiter->check($ip)) {
                app_log('WARNING', 'login_ratelimit_exceeded', ['ip' => $ip, 'username' => $username]);
                sleep(1);
                $this->respond(429, ['error' => 'Zu viele Anmeldeversuche. Bitte warten Sie eine Minute.']);
            }

            $stmt = $this->pdo->prepare('SELECT id, family_id, username, password_hash, role FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $limiter->increment($ip);
                app_log('WARNING', 'login_failed', ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
                $this->logAction(null, 'login_fail', $username);
                $this->respond(401, ['error' => 'Ungültige Anmeldedaten']);
            }

            // Login erfolgreich — Zähler zurücksetzen
            $limiter->reset($ip);
            $_SESSION['user_id'] = $user['id'];
            session_regenerate_id(true);
            app_log('INFO', 'login_success', ['user_id' => $user['id'], 'username' => $username]);
            $this->logAction($user['id'], 'login_success');
            $this->respond(200, ['message' => 'Anmeldung erfolgreich']);
        } catch (Throwable $e) {
            error_log("Login Exception: " . $e->getMessage());
            try {
                app_log('ERROR', 'login_exception', [
                    'username' => $username ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            } catch (Throwable $t) {
                error_log("Logging failed: " . $t->getMessage());
            }
            $this->respond(500, ['error' => 'Fehler bei der Anmeldung']);
        }
    }

    /**
     * POST /logout
     * Beendet die Session.
     */
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        app_log('INFO', 'logout', ['user_id' => $userId]);
        $this->respond(204);
    }

    /**
     * GET /me
     * Liefert aktuelle Benutzerdaten.
     */
    public function me(): void
    {
        try {
            $user = $this->requireAuth();

            // Query 1: Badges und Familienmitgliederzahl in einem Schritt
            $stmtBadges = $this->pdo->prepare(
                'SELECT b.name, b.description, b.icon_class
                 FROM user_badges ub
                 JOIN badges b ON ub.badge_id = b.id
                 WHERE ub.user_id = ?
                 ORDER BY b.required_streak ASC'
            );
            $stmtBadges->execute([$user['id']]);
            $badges = $stmtBadges->fetchAll();

            $stmtFamily = $this->pdo->prepare(
                'SELECT COUNT(*) FROM users WHERE family_id = ? AND is_active = 1'
            );
            $stmtFamily->execute([$user['family_id']]);
            $familyCount = (int)$stmtFamily->fetchColumn();

            // Query 2: has_entries und has_medications in einem einzigen Query
            // Für Parent/Adult: familienweit; für Child/Teacher: nur eigene User-ID
            if (in_array($user['role'], ['parent', 'adult'], true)) {
                $stmtStats = $this->pdo->prepare('
                    SELECT
                        (SELECT COUNT(*) FROM entries e
                         JOIN users u ON e.user_id = u.id
                         WHERE u.family_id = ? LIMIT 1) AS has_entries,
                        (SELECT COUNT(*) FROM medications
                         WHERE family_id = ? AND is_active = 1 LIMIT 1) AS has_medications
                ');
                $stmtStats->execute([$user['family_id'], $user['family_id']]);
            } else {
                $stmtStats = $this->pdo->prepare('
                    SELECT
                        (SELECT COUNT(*) FROM entries WHERE user_id = ? LIMIT 1) AS has_entries,
                        (SELECT COUNT(*) FROM medications
                         WHERE family_id = ? AND is_active = 1 LIMIT 1) AS has_medications
                ');
                $stmtStats->execute([$user['id'], $user['family_id']]);
            }

            $stats = $stmtStats->fetch();

            $this->respond(200, [
                'id'                  => (int)$user['id'],
                'username'            => $user['username'],
                'role'                => $user['role'],
                'family_id'           => (int)$user['family_id'],
                'family_member_count' => $familyCount,
                'points'              => (int)($user['points'] ?? 0),
                'streak_current'      => (int)($user['streak_current'] ?? 0),
                'badges'              => $badges,
                'has_entries'         => (bool)($stats['has_entries'] ?? false),
                'has_medications'     => (bool)($stats['has_medications'] ?? false),
            ]);
        } catch (Throwable $e) {
            app_log('ERROR', 'me_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Abrufen der Benutzerdaten']);
        }
    }

    /**
     * POST /users/me/password
     * Passwort ändern.
     */
    public function changePassword(): void
    {
        try {
            $user = $this->requireAuth();

            // Rate Limiting gegen Brute-Force auf Passwortänderung
            $limiter = new RateLimiter();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!$limiter->check($ip, 5, 60)) {
                app_log('WARNING', 'password_change_ratelimit', ['ip' => $ip, 'user_id' => $user['id']]);
                $this->respond(429, ['error' => 'Zu viele Versuche. Bitte warten Sie eine Minute.']);
            }

            $data = $this->getJsonBody();

            $currentPassword = $data['current_password'] ?? '';
            $newPassword = $data['new_password'] ?? '';
            $confirmPassword = $data['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $this->respond(400, ['error' => 'Alle Felder sind erforderlich.']);
            }

            if ($newPassword !== $confirmPassword) {
                $this->respond(400, ['error' => 'Das neue Passwort stimmt nicht mit der Bestätigung überein.']);
            }

            if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
                $this->respond(400, ['error' => 'Das neue Passwort muss mindestens ' . self::MIN_PASSWORD_LENGTH . ' Zeichen lang sein.']);
            }

            if (!password_verify($currentPassword, $user['password_hash'])) {
                app_log('WARNING', 'password_change_fail', ['user_id' => $user['id'], 'reason' => 'wrong_current_password']);
                $this->respond(403, ['error' => 'Das aktuelle Passwort ist nicht korrekt.']);
            }

            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$newPasswordHash, $user['id']]);

            $this->logAction($user['id'], 'password_change_success');
            app_log('INFO', 'password_change_success', ['user_id' => $user['id']]);

            $this->respond(200, ['message' => 'Passwort erfolgreich geändert.']);
        } catch (Throwable $e) {
            app_log('ERROR', 'password_change_exception', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()]);
        }
    }
}
