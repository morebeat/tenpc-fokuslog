<?php
declare(strict_types=1);

namespace FokusLog\Controller;

use PDO;

/**
 * Basis-Controller mit gemeinsamen Methoden für alle Controller.
 */
abstract class BaseController
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Sendet eine JSON-Antwort und beendet das Script.
     */
    protected function respond(int $code, array $data = []): void
    {
        http_response_code($code);
        if ($data !== []) {
            echo json_encode($data);
        }
        exit;
    }

    /**
     * Liest JSON-Body und gibt Array zurück.
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Holt aktuellen Benutzer aus Session.
     */
    protected function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Prüft ob Benutzer eingeloggt ist, sonst 401.
     */
    protected function requireAuth(): array
    {
        $user = $this->currentUser();
        if (!$user) {
            app_log('WARNING', 'auth_required', [
                'path' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            $this->respond(401, ['error' => 'Nicht angemeldet']);
        }
        return $user;
    }

    /**
     * Prüft ob Benutzer eine der erlaubten Rollen hat, sonst 403.
     */
    protected function requireRole(array $user, array $roles): void
    {
        if (!in_array($user['role'], $roles, true)) {
            app_log('WARNING', 'access_denied_role', [
                'user_id' => $user['id'],
                'user_role' => $user['role'],
                'required_roles' => $roles
            ]);
            $this->respond(403, ['error' => 'Zugriff verweigert']);
        }
    }

    /**
     * Fügt Eintrag ins Audit-Log ein.
     */
    protected function logAction(?int $userId, string $action, $details = null): void
    {
        try {
            $detailsJson = null;
            if ($details !== null) {
                if (is_string($details)) {
                    $details = ['message' => $details];
                } elseif (!is_array($details)) {
                    $details = ['value' => $details];
                }
                $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($detailsJson === false) {
                    throw new \RuntimeException('Invalid audit log details payload');
                }
            }
            $stmt = $this->pdo->prepare('INSERT INTO audit_log (user_id, action, details) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $action, $detailsJson]);
        } catch (\Throwable $e) {
            error_log('Audit Log Error: ' . $e->getMessage());
        }
    }

    /**
     * Gibt GET-Parameter zurück.
     */
    protected function getQueryParams(): array
    {
        return $_GET;
    }
}
