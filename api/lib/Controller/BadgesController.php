<?php
declare(strict_types=1);

namespace FokusLog\Controller;

use PDO;
use Throwable;

/**
 * Controller für Badges/Abzeichen.
 */
class BadgesController extends BaseController
{
    /**
     * GET /badges
     * Gibt alle verfügbaren Badges und den Fortschritt des aktuellen Benutzers zurück.
     */
    public function index(): void
    {
        try {
            $user = $this->requireAuth();

            // Alle Badges laden
            $stmtAll = $this->pdo->prepare('SELECT id, name, description, required_streak, icon_class FROM badges ORDER BY required_streak ASC');
            $stmtAll->execute();
            $allBadges = $stmtAll->fetchAll();

            // Verdiente Badges des Users
            $stmtEarned = $this->pdo->prepare('SELECT badge_id FROM user_badges WHERE user_id = ?');
            $stmtEarned->execute([$user['id']]);
            $earnedBadgeIds = array_flip($stmtEarned->fetchAll(PDO::FETCH_COLUMN));

            // Kombinieren
            foreach ($allBadges as &$badge) {
                $badge['earned'] = isset($earnedBadgeIds[$badge['id']]);
            }

            $this->respond(200, [
                'badges' => $allBadges,
                'current_streak' => (int)$user['streak_current']
            ]);
        } catch (Throwable $e) {
            app_log('ERROR', 'badges_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden der Abzeichen: ' . $e->getMessage()]);
        }
    }
}
