<?php
declare(strict_types=1);

namespace FokusLog\Controller;

use Throwable;
use PDO;

/**
 * Controller für Tagebuch-Einträge und Gamification-Logik.
 */
class EntriesController extends BaseController
{
    /**
     * GET /entries
     * Filter: date_from, date_to, user_id, limit
     */
    public function index(): void
    {
        try {
            $user = $this->requireAuth();
            
            $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
            $dateFrom = filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_STRING);
            $dateTo = filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_STRING);
            $time = filter_input(INPUT_GET, 'time', FILTER_SANITIZE_STRING);
            $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

            // Berechtigungsprüfung: Darf der User die Einträge sehen?
            $targetUserId = $user['id'];
            if ($userId && $userId !== (int)$user['id']) {
                // Prüfen ob Parent/Adult und selbe Familie
                if (!in_array($user['role'], ['parent', 'adult'])) {
                    $this->respond(403, ['error' => 'Keine Berechtigung']);
                }
                // Check family connection
                $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
                $stmt->execute([$userId, $user['family_id']]);
                if (!$stmt->fetch()) {
                    $this->respond(404, ['error' => 'Benutzer nicht gefunden']);
                }
                $targetUserId = $userId;
            }

            $sql = "SELECT e.*, u.username, m.name as medication_name 
                    FROM entries e 
                    JOIN users u ON e.user_id = u.id 
                    LEFT JOIN medications m ON e.medication_id = m.id 
                    WHERE e.user_id = ?";
            $params = [$targetUserId];

            if ($dateFrom) {
                $sql .= " AND e.date >= ?";
                $params[] = $dateFrom;
            }
            if ($dateTo) {
                $sql .= " AND e.date <= ?";
                $params[] = $dateTo;
            }
            if ($time) {
                $sql .= " AND e.time = ?";
                $params[] = $time;
            }

            $sql .= " ORDER BY e.date DESC, FIELD(e.time, 'morning', 'noon', 'evening')";

            if ($limit) {
                $sql .= " LIMIT " . (int)$limit;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll();

            $this->respond(200, ['entries' => $entries]);
        } catch (Throwable $e) {
            app_log('ERROR', 'entries_index_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden der Einträge']);
        }
    }

    /**
     * POST /entries
     * Erstellt oder aktualisiert einen Eintrag und triggert Gamification.
     */
    public function store(): void
    {
        try {
            $user = $this->requireAuth();
            $data = $this->getJsonBody();

            // Validierung
            if (empty($data['date']) || empty($data['time'])) {
                $this->respond(400, ['error' => 'Datum und Zeit sind erforderlich']);
            }

            // Ziel-User bestimmen (Eltern können für Kinder schreiben)
            $targetUserId = $user['id'];
            if (!empty($data['target_user_id'])) {
                $reqUserId = (int)$data['target_user_id'];
                if ($reqUserId !== $user['id']) {
                    if (!in_array($user['role'], ['parent', 'adult'])) {
                        $this->respond(403, ['error' => 'Nur Eltern können Einträge für andere erstellen']);
                    }
                    // Family Check
                    $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
                    $stmt->execute([$reqUserId, $user['family_id']]);
                    if (!$stmt->fetch()) {
                        $this->respond(403, ['error' => 'Zugriff verweigert']);
                    }
                    $targetUserId = $reqUserId;
                }
            }

            // Prüfen ob Eintrag existiert (Update vs Insert)
            $stmt = $this->pdo->prepare('SELECT id FROM entries WHERE user_id = ? AND date = ? AND time = ?');
            $stmt->execute([$targetUserId, $data['date'], $data['time']]);
            $existing = $stmt->fetch();

            $fields = [
                'medication_id' => !empty($data['medication_id']) ? $data['medication_id'] : null,
                'dose' => $data['dose'] ?? null,
                'mood' => isset($data['mood']) ? (int)$data['mood'] : null,
                'focus' => isset($data['focus']) ? (int)$data['focus'] : null,
                'sleep' => isset($data['sleep']) ? (int)$data['sleep'] : null,
                'appetite' => isset($data['appetite']) ? (int)$data['appetite'] : null,
                'irritability' => isset($data['irritability']) ? (int)$data['irritability'] : null,
                'hyperactivity' => isset($data['hyperactivity']) ? (int)$data['hyperactivity'] : null,
                'side_effects' => $data['side_effects'] ?? null,
                'other_effects' => $data['other_effects'] ?? null,
                'special_events' => $data['special_events'] ?? null,
                'teacher_feedback' => $data['teacher_feedback'] ?? null,
                'emotional_reactions' => $data['emotional_reactions'] ?? null,
                'weight' => !empty($data['weight']) ? (float)$data['weight'] : null,
                'tags' => isset($data['tags']) && is_array($data['tags']) ? implode(',', $data['tags']) : null
            ];

            if ($existing) {
                // Update
                $setParts = [];
                $params = [];
                foreach ($fields as $key => $val) {
                    $setParts[] = "$key = ?";
                    $params[] = $val;
                }
                $params[] = $existing['id'];
                $sql = "UPDATE entries SET " . implode(', ', $setParts) . " WHERE id = ?";
                $this->pdo->prepare($sql)->execute($params);
                $entryId = $existing['id'];
                $action = 'updated';
            } else {
                // Insert
                $cols = ['user_id', 'date', 'time'];
                $vals = [$targetUserId, $data['date'], $data['time']];
                foreach ($fields as $key => $val) {
                    $cols[] = $key;
                    $vals[] = $val;
                }
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO entries (" . implode(', ', $cols) . ") VALUES ($placeholders)";
                $this->pdo->prepare($sql)->execute($vals);
                $entryId = (int)$this->pdo->lastInsertId();
                $action = 'created';
            }

            // Tags verknüpfen (Many-to-Many)
            if (isset($data['tags']) && is_array($data['tags'])) {
                $this->pdo->prepare('DELETE FROM entry_tags WHERE entry_id = ?')->execute([$entryId]);
                $stmtTag = $this->pdo->prepare('INSERT INTO entry_tags (entry_id, tag_id) VALUES (?, ?)');
                foreach ($data['tags'] as $tagId) {
                    $stmtTag->execute([$entryId, (int)$tagId]);
                }
            }

            // --- GAMIFICATION LOGIK ---
            // Berechnet Punkte, Streak und prüft auf neue Badges für den Ziel-User
            $gamification = $this->processGamification($targetUserId);

            $this->logAction($user['id'], 'entry_' . $action, "Entry ID: $entryId");
            
            $this->respond(201, [
                'message' => 'Eintrag gespeichert',
                'id' => $entryId,
                'gamification' => $gamification
            ]);

        } catch (Throwable $e) {
            app_log('ERROR', 'entry_store_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Speichern: ' . $e->getMessage()]);
        }
    }

    public function destroy(string $id): void
    {
        try {
            $user = $this->requireAuth();
            $entryId = (int)$id;

            $stmt = $this->pdo->prepare('SELECT user_id FROM entries WHERE id = ?');
            $stmt->execute([$entryId]);
            $entry = $stmt->fetch();

            if (!$entry) {
                $this->respond(404, ['error' => 'Eintrag nicht gefunden']);
            }

            if ($entry['user_id'] !== $user['id']) {
                 if (!in_array($user['role'], ['parent', 'adult'])) {
                     $this->respond(403, ['error' => 'Keine Berechtigung']);
                 }
                 $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
                 $stmt->execute([$entry['user_id'], $user['family_id']]);
                 if (!$stmt->fetch()) {
                     $this->respond(403, ['error' => 'Keine Berechtigung']);
                 }
            }

            $this->pdo->prepare('DELETE FROM entries WHERE id = ?')->execute([$entryId]);
            $this->logAction($user['id'], 'entry_deleted', "Entry ID: $entryId");
            $this->respond(204);

        } catch (Throwable $e) {
            $this->respond(500, ['error' => 'Fehler beim Löschen']);
        }
    }

    /**
     * Berechnet Punkte, Streak und prüft auf neue Badges.
     */
    private function processGamification(int $userId): array
    {
        // 1. Punkte vergeben (10 Punkte pro Eintrag)
        $points = 10;
        $stmt = $this->pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->execute([$points, $userId]);

        // 2. Streak berechnen (Tage in Folge)
        $stmt = $this->pdo->prepare("SELECT DISTINCT date FROM entries WHERE user_id = ? AND date <= CURDATE() ORDER BY date DESC");
        $stmt->execute([$userId]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $streak = 0;
        if (!empty($dates)) {
            $today = new \DateTime();
            $yesterday = (new \DateTime())->modify('-1 day');
            $lastEntryDate = new \DateTime($dates[0]);
            
            // Streak zählt nur, wenn der letzte Eintrag heute oder gestern war
            if ($lastEntryDate->format('Y-m-d') === $today->format('Y-m-d') || 
                $lastEntryDate->format('Y-m-d') === $yesterday->format('Y-m-d')) {
                
                $streak = 1;
                $current = $lastEntryDate;
                for ($i = 1; $i < count($dates); $i++) {
                    $prev = new \DateTime($dates[$i]);
                    if ($current->diff($prev)->days === 1) {
                        $streak++;
                        $current = $prev;
                    } else {
                        break;
                    }
                }
            }
        }

        // Streak speichern
        $stmt = $this->pdo->prepare("UPDATE users SET streak_current = ? WHERE id = ?");
        $stmt->execute([$streak, $userId]);

        // 3. Badges prüfen
        $newBadges = [];
        // Hole Badges, die der User noch NICHT hat, aber deren Streak er erreicht hat
        $stmt = $this->pdo->prepare("
            SELECT * FROM badges 
            WHERE required_streak <= ? 
            AND id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = ?)
        ");
        $stmt->execute([$streak, $userId]);
        $earnedBadges = $stmt->fetchAll();

        if ($earnedBadges) {
            $insertStmt = $this->pdo->prepare("INSERT INTO user_badges (user_id, badge_id, earned_at) VALUES (?, ?, NOW())");
            foreach ($earnedBadges as $badge) {
                $insertStmt->execute([$userId, $badge['id']]);
                $newBadges[] = [
                    'name' => $badge['name'],
                    'description' => $badge['description'],
                    'icon_class' => $badge['icon_class']
                ];
            }
        }
        
        // Nächstes Badge für Progress Bar
        $stmt = $this->pdo->prepare("SELECT * FROM badges WHERE required_streak > ? ORDER BY required_streak ASC LIMIT 1");
        $stmt->execute([$streak]);
        $nextBadge = $stmt->fetch();
        
        $nextBadgeData = null;
        if ($nextBadge) {
            $nextBadgeData = [
                'name' => $nextBadge['name'],
                'required_streak' => $nextBadge['required_streak'],
                'days_left' => $nextBadge['required_streak'] - $streak
            ];
        }

        return [
            'points_earned' => $points,
            'streak' => $streak,
            'new_badges' => $newBadges,
            'next_badge' => $nextBadgeData
        ];
    }
}