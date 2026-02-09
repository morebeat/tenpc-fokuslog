<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use RateLimiter;
use Throwable;

/**
 * Controller fü¼r Authentifizierung: Register, Login, Logout, Me.
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
        $accountType = $data['account_type'] ?? 'family';
        $familyName = trim($data['family_name'] ?? '');
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        // Bei Einzelpersonen ist der Familienname optional/automatisch
        if ($accountType === 'individual' && $familyName === '') {
            $familyName = 'Privat';
        }

        if ($familyName === '' || $username === '' || $password === '') {
            app_log('WARNING', 'register_validation_failed', ['error' => 'missing_fields']);
            $this->respond(400, ['error' => 'family_name, username und password sind erforderlich']);
        }

        if (strlen($password) < 8) {
            $this->respond(400, ['error' => 'Das Passwort muss mindestens 8 Zeichen lang sein.']);
        }

        try {
            // Prü¼fe, ob Benutzername bereits existiert
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
                $this->respond(401, ['error' => 'Ungü¼ltige Anmeldedaten']);
            }

            // Login erfolgreich
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

            // Badges des Benutzers laden
            $stmtBadges = $this->pdo->prepare(
                'SELECT b.name, b.description, b.icon_class FROM user_badges ub JOIN badges b ON ub.badge_id = b.id WHERE ub.user_id = ? ORDER BY b.required_streak ASC'
            );
            $stmtBadges->execute([$user['id']]);
            $badges = $stmtBadges->fetchAll();

            // Anzahl der Familienmitglieder ermitteln
            $stmtFamily = $this->pdo->prepare('SELECT COUNT(id) as count FROM users WHERE family_id = ?');
            $stmtFamily->execute([$user['family_id']]);
            $familyCount = $stmtFamily->fetchColumn();

            // Prü¼fen, ob der User selbst Eintrü¤ge hat
            $stmt = $this->pdo->prepare("SELECT 1 FROM entries WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user['id']]);
            $hasEntries = (bool)$stmt->fetchColumn();

            // Wenn nicht, und der User ist 'parent' oder 'adult', prü¼fen wir, ob IRGENDJEMAND in der Familie Eintrü¤ge hat
            if (!$hasEntries && ($user['role'] === 'parent' || $user['role'] === 'adult')) {
                $stmtEntries = $this->pdo->prepare('SELECT 1 FROM entries e JOIN users u ON e.user_id = u.id WHERE u.family_id = ? LIMIT 1');
                $stmtEntries->execute([$user['family_id']]);
                $hasEntries = $stmtEntries->fetch() !== false;
            }

            // Medikamente werden immer familienweit geprü¼ft
            $stmtMeds = $this->pdo->prepare('SELECT 1 FROM medications WHERE family_id = ? LIMIT 1');
            $stmtMeds->execute([$user['family_id']]);
            $hasMedications = $stmtMeds->fetch() !== false;

            $this->respond(200, [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'family_id' => (int)$user['family_id'],
                'family_member_count' => (int)$familyCount,
                'points' => (int)($user['points'] ?? 0),
                'streak_current' => (int)($user['streak_current'] ?? 0),
                'badges' => $badges,
                'has_entries' => $hasEntries,
                'has_medications' => $hasMedications
            ]);
        } catch (Throwable $e) {
            app_log('ERROR', 'me_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Abrufen der Benutzerdaten: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /users/me/password
     * Passwort ü¤ndern.
     */
    public function changePassword(): void
    {
        try {
            $user = $this->requireAuth();
            $data = $this->getJsonBody();

            $currentPassword = $data['current_password'] ?? '';
            $newPassword = $data['new_password'] ?? '';
            $confirmPassword = $data['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $this->respond(400, ['error' => 'Alle Felder sind erforderlich.']);
            }

            if ($newPassword !== $confirmPassword) {
                $this->respond(400, ['error' => 'Das neue Passwort stimmt nicht mit der Bestü¤tigung ü¼berein.']);
            }

            if (strlen($newPassword) < 6) {
                $this->respond(400, ['error' => 'Das neue Passwort muss mindestens 6 Zeichen lang sein.']);
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

            $this->respond(200, ['message' => 'Passwort erfolgreich geü¤ndert.']);
        } catch (Throwable $e) {
            app_log('ERROR', 'password_change_exception', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()]);
        }
    }
}
