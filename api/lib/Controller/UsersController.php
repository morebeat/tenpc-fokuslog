<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use Throwable;

/**
 * Controller für Benutzerverwaltung.
 */
class UsersController extends BaseController
{
    /**
     * GET /users
     * Gibt alle Benutzer der eigenen Familie zurück (nur für Parent/Adult).
     */
    public function index(): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);

            $stmt = $this->pdo->prepare('SELECT id, username, role, created_at, gender, initial_weight FROM users WHERE family_id = ? ORDER BY created_at ASC');
            $stmt->execute([$user['family_id']]);
            $users = $stmt->fetchAll();

            $this->respond(200, ['users' => $users]);
        } catch (Throwable $e) {
            app_log('ERROR', 'users_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden der Benutzer: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /users/{id}
     * Gibt einen einzelnen Benutzer der eigenen Familie zurück.
     */
    public function show(string $id): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);
            $userId = (int)$id;

            $stmt = $this->pdo->prepare('SELECT id, username, role, created_at, gender, initial_weight FROM users WHERE id = ? AND family_id = ?');
            $stmt->execute([$userId, $user['family_id']]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                $this->respond(404, ['error' => 'Benutzer nicht gefunden oder Zugriff verweigert']);
            }

            $this->respond(200, ['user' => $targetUser]);
        } catch (Throwable $e) {
            app_log('ERROR', 'user_get_failed', ['error' => $e->getMessage(), 'target_user_id' => $id]);
            $this->respond(500, ['error' => 'Fehler beim Laden des Benutzers: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /users
     * Legt einen neuen Benutzer innerhalb der Familie an (nur Parent/Adult).
     */
    public function store(): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);

            $data = $this->getJsonBody();
            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? '';
            $gender = $data['gender'] ?? null;
            $initialWeight = (isset($data['initial_weight']) && $data['initial_weight'] !== '') ? $data['initial_weight'] : null;

            if (
                $username === ''
                || $password === ''
                || !in_array($role, ['child', 'teacher', 'adult'], true)
                || ($gender !== null && !in_array($gender, ['male', 'female', 'diverse', '']))
                || ($initialWeight !== null && !is_numeric($initialWeight))
            ) {
                app_log('WARNING', 'user_create_validation_failed', [
                    'creator_id' => $user['id'],
                    'username' => $username,
                    'role' => $role,
                    'gender' => $gender
                ]);
                $this->respond(400, ['error' => 'username, password, role sind erforderlich. gender/initial_weight sind ungültig.']);
            }

            // Prüfe Unique
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                app_log('WARNING', 'user_create_user_exists', ['creator_id' => $user['id'], 'username' => $username]);
                $this->respond(409, ['error' => 'Benutzername existiert bereits']);
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare('INSERT INTO users (family_id, username, password_hash, role, gender, initial_weight) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['family_id'], $username, $passwordHash, $role, $gender ?: null, $initialWeight]);
            $newId = (int)$this->pdo->lastInsertId();

            app_log('INFO', 'user_create_success', ['creator_id' => $user['id'], 'new_user_id' => $newId, 'new_username' => $username, 'role' => $role]);
            $this->logAction($user['id'], 'user_create', 'new user ' . $username);

            $this->respond(201, ['id' => $newId, 'username' => $username, 'role' => $role, 'gender' => $gender, 'initial_weight' => $initialWeight]);
        } catch (Throwable $e) {
            app_log('ERROR', 'user_create_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Erstellen des Benutzers: ' . $e->getMessage()]);
        }
    }

    /**
     * PUT /users/{id}
     * Aktualisiert einen Benutzer (nur Parent/Adult).
     */
    public function update(string $id): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);
            $userId = (int)$id;

            $data = $this->getJsonBody();
            $username = trim($data['username'] ?? '');
            $role = $data['role'] ?? '';
            $password = $data['password'] ?? '';
            $gender = $data['gender'] ?? null;
            $initialWeight = (isset($data['initial_weight']) && $data['initial_weight'] !== '') ? $data['initial_weight'] : null;

            if (
                $username === ''
                || !in_array($role, ['child', 'teacher', 'adult'], true)
                || ($gender !== null && !in_array($gender, ['male', 'female', 'diverse', '']))
                || ($initialWeight !== null && !is_numeric($initialWeight))
            ) {
                $this->respond(400, ['error' => 'username und role sind erforderlich. gender/initial_weight sind ungültig.']);
            }

            // Parent darf sich nicht selbst bearbeiten
            if ($userId === (int)$user['id']) {
                app_log('WARNING', 'user_update_self_edit_forbidden', ['user_id' => $user['id']]);
                $this->respond(403, ['error' => 'Sie können sich nicht selbst bearbeiten']);
            }

            // Prüfen, ob der zu bearbeitende Benutzer zur Familie gehört
            $stmt = $this->pdo->prepare('SELECT id, username FROM users WHERE id = ? AND family_id = ?');
            $stmt->execute([$userId, $user['family_id']]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                $this->respond(404, ['error' => 'Benutzer nicht gefunden oder Zugriff verweigert']);
            }

            // Prüfen, ob der neue Benutzername bereits von einem anderen Benutzer verwendet wird
            if ($username !== $targetUser['username']) {
                $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ?');
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $this->respond(409, ['error' => 'Benutzername existiert bereits']);
                }
            }

            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $this->pdo->prepare('UPDATE users SET username = ?, role = ?, password_hash = ?, gender = ?, initial_weight = ? WHERE id = ?');
                $stmt->execute([$username, $role, $passwordHash, $gender ?: null, $initialWeight, $userId]);
            } else {
                $stmt = $this->pdo->prepare('UPDATE users SET username = ?, role = ?, gender = ?, initial_weight = ? WHERE id = ?');
                $stmt->execute([$username, $role, $gender ?: null, $initialWeight, $userId]);
            }

            $this->logAction($user['id'], 'user_update', 'user ' . $userId);
            $this->respond(200, ['id' => $userId, 'username' => $username, 'role' => $role, 'gender' => $gender, 'initial_weight' => $initialWeight]);
        } catch (Throwable $e) {
            app_log('ERROR', 'user_update_failed', ['user_id' => $id, 'error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Aktualisieren des Benutzers: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /users/{id}
     * Löscht einen Benutzer (nur Parent/Adult).
     */
    public function destroy(string $id): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);
            $userId = (int)$id;

            if ($userId === (int)$user['id']) {
                app_log('WARNING', 'user_delete_self_delete_forbidden', ['user_id' => $user['id']]);
                $this->respond(403, ['error' => 'Sie können sich nicht selbst löschen']);
            }

            // Prüfen, ob der zu löschende Benutzer zur Familie gehört
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
            $stmt->execute([$userId, $user['family_id']]);
            if (!$stmt->fetch()) {
                app_log('WARNING', 'user_delete_not_found_or_unauthorized', ['user_id' => $user['id'], 'delete_target_id' => $userId]);
                $this->respond(404, ['error' => 'Benutzer nicht gefunden oder Zugriff verweigert']);
            }

            // Business Rule: Benutzer mit Einträgen dürfen nicht gelöscht werden
            $stmt = $this->pdo->prepare('SELECT id FROM entries WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            if ($stmt->fetch()) {
                $this->respond(409, ['error' => 'Benutzer kann nicht gelöscht werden, da Einträge vorhanden sind.']);
            }

            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);

            $this->logAction($user['id'], 'user_delete', 'user ' . $userId);
            $this->respond(204);
        } catch (Throwable $e) {
            app_log('ERROR', 'user_delete_failed', ['user_id' => $id, 'error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Löschen des Benutzers: ' . $e->getMessage()]);
        }
    }
}
