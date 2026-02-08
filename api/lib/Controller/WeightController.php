<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use Throwable;

/**
 * Controller fÃ¼r Gewichtsdaten.
 */
class WeightController extends BaseController
{
    /**
     * GET /weight
     * Gibt den Gewichtsverlauf fÃ¼r einen Benutzer zurÃ¼ck.
     */
    public function index(): void
    {
        try {
            $user = $this->requireAuth();

            if ($user['role'] === 'teacher') {
                $this->respond(403, ['error' => 'Lehrer dÃ¼rfen keine Gewichtsdaten einsehen']);
            }

            $params = $this->getQueryParams();
            $targetUserId = (int)$user['id'];

            if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($params['user_id'])) {
                $uid = (int)$params['user_id'];
                $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
                $stmt->execute([$uid, $user['family_id']]);
                if ($stmt->fetch()) {
                    $targetUserId = $uid;
                }
            }

            $dateFrom = $params['date_from'] ?? null;
            $dateTo = $params['date_to'] ?? null;

            $sql = 'SELECT weight, date FROM entries WHERE user_id = ? AND weight IS NOT NULL';
            $bindings = [$targetUserId];

            if ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                $sql .= ' AND date >= ?';
                $bindings[] = $dateFrom;
            }
            if ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $sql .= ' AND date <= ?';
                $bindings[] = $dateTo;
            }

            $sql .= ' ORDER BY date ASC';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            $weights = $stmt->fetchAll();

            $this->respond(200, ['weights' => $weights]);
        } catch (Throwable $e) {
            app_log('ERROR', 'weight_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden der Gewichtsdaten: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /me/latest-weight
     * Gibt das letzte eingetragene Gewicht oder das Initialgewicht zurÃ¼ck.
     */
    public function latestWeight(): void
    {
        try {
            $user = $this->requireAuth();

            $stmt = $this->pdo->prepare('SELECT weight FROM entries WHERE user_id = ? AND weight IS NOT NULL ORDER BY date DESC, id DESC LIMIT 1');
            $stmt->execute([$user['id']]);
            $latestEntry = $stmt->fetch();

            $weight = $latestEntry['weight'] ?? $user['initial_weight'] ?? null;

            $this->respond(200, ['weight' => $weight]);
        } catch (Throwable $e) {
            app_log('ERROR', 'latest_weight_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden des Gewichts.']);
        }
    }
}

