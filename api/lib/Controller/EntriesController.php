<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use EntryPayload;
use InvalidArgumentException;
use PDOException;
use Throwable;

/**
 * Controller fÃ¼r EintrÃ¤ge (TagebucheintrÃ¤ge).
 */
class EntriesController extends BaseController
{
    /**
     * GET /entries
     * Gibt EintrÃ¤ge zurÃ¼ck. Child/Adult sehen nur eigene, Parent kann alle in der Familie sehen.
     */
    public function index(): void
    {
        try {
            $user = $this->requireAuth();

            if ($user['role'] === 'teacher') {
                app_log('WARNING', 'entries_get_denied_for_teacher', ['user_id' => $user['id']]);
                $this->respond(403, ['error' => 'Lehrer dÃ¼rfen keine EintrÃ¤ge einsehen']);
            }

            $params = $this->getQueryParams();
            $targetUserId = (int)$user['id'];

            if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($params['user_id'])) {
                $uid = (int)$params['user_id'];
                $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
                $stmt->execute([$uid, $user['family_id']]);
                if ($stmt->fetch()) {
                    $targetUserId = $uid;
                } else {
                    app_log('WARNING', 'entries_get_access_denied', [
                        'reason' => 'target_user_not_in_family',
                        'requesting_user' => $user['id'],
                        'target_user' => $uid
                    ]);
                }
            }

            $dateFrom = $params['date_from'] ?? null;
            $dateTo = $params['date_to'] ?? null;
            $timeSlot = $params['time'] ?? null;
            $limit = isset($params['limit']) ? (int)$params['limit'] : null;

            $sql = 'SELECT e.*, m.name AS medication_name, u.username, GROUP_CONCAT(t.name SEPARATOR ", ") as tags, GROUP_CONCAT(t.id) as tag_ids
                    FROM entries e
                    LEFT JOIN medications m ON e.medication_id = m.id
                    LEFT JOIN users u ON e.user_id = u.id
                    LEFT JOIN entry_tags et ON e.id = et.entry_id
                    LEFT JOIN tags t ON et.tag_id = t.id
                    WHERE e.user_id = ?';
            $bindings = [$targetUserId];

            if ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                $sql .= ' AND e.date >= ?';
                $bindings[] = $dateFrom;
            }
            if ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $sql .= ' AND e.date <= ?';
                $bindings[] = $dateTo;
            }
            if ($timeSlot && in_array($timeSlot, ['morning', 'noon', 'evening'], true)) {
                $sql .= ' AND e.time = ?';
                $bindings[] = $timeSlot;
            }

            $sql .= ' GROUP BY e.id';
            $sql .= ' ORDER BY e.date DESC, FIELD(e.time, "morning","noon","evening")';

            if ($limit > 0) {
                $sql .= ' LIMIT ' . $limit;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);

            app_log('INFO', 'entries_get', [
                'user_id' => $user['id'],
                'target_user_id' => $targetUserId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);

            $entries = $stmt->fetchAll();
            $this->respond(200, ['entries' => $entries]);
        } catch (Throwable $e) {
            app_log('ERROR', 'entries_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden der EintrÃ¤ge: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /entries
     * Erstellt einen neuen Eintrag.
     */
    public function store(): void
    {
        try {
            $user = $this->requireAuth();
            $data = $this->getJsonBody();

            $targetUserId = (int)$user['id'];

            // Eltern/Erwachsene dÃ¼rfen EintrÃ¤ge fÃ¼r Familienmitglieder bearbeiten/erstellen
            if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($data['target_user_id'])) {
                $tId = (int)$data['target_user_id'];
                $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
                $stmt->execute([$tId, $user['family_id']]);
                if (!$stmt->fetch()) {
                    $this->respond(403, ['error' => 'Zugriff auf diesen Benutzer verweigert']);
                }
                $targetUserId = $tId;
            } elseif ($user['role'] === 'teacher') {
                if (empty($data['child_id'])) {
                    app_log('WARNING', 'entry_create_validation_failed', ['user_id' => $user['id'], 'error' => 'child_id_missing_for_teacher']);
                    $this->respond(400, ['error' => 'child_id ist erforderlich fÃ¼r teacher']);
                }
                $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ? AND role = ?');
                $stmt->execute([(int)$data['child_id'], $user['family_id'], 'child']);
                $child = $stmt->fetch();
                if (!$child) {
                    app_log('WARNING', 'entry_create_invalid_child', ['user_id' => $user['id'], 'child_id' => $data['child_id']]);
                    $this->respond(403, ['error' => 'UngÃ¼ltiges Kind fÃ¼r diesen Lehrer']);
                }
                $targetUserId = (int)$data['child_id'];
            }

            // Validierung & Normalisierung
            try {
                $date = EntryPayload::normalizeDate($data['date'] ?? '');
            } catch (InvalidArgumentException $e) {
                app_log('WARNING', 'entry_create_validation_failed', [
                    'user_id' => $user['id'],
                    'error' => 'invalid_date',
                    'date_val' => $data['date'] ?? null,
                    'message' => $e->getMessage(),
                ]);
                $this->respond(400, ['error' => $e->getMessage()]);
            }

            try {
                $time = EntryPayload::normalizeTime($data['time'] ?? '');
            } catch (InvalidArgumentException $e) {
                app_log('WARNING', 'entry_create_validation_failed', ['user_id' => $user['id'], 'error' => 'invalid_time', 'time_val' => $data['time'] ?? null]);
                $this->respond(400, ['error' => $e->getMessage()]);
            }

            try {
                $sql = 'INSERT INTO entries (user_id, medication_id, dose, date, time, sleep, hyperactivity, mood, irritability, appetite, focus, weight, other_effects, side_effects, special_events, menstruation_phase, teacher_feedback, emotional_reactions)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE
                        id=LAST_INSERT_ID(id), medication_id=VALUES(medication_id), dose=VALUES(dose), sleep=VALUES(sleep), hyperactivity=VALUES(hyperactivity), mood=VALUES(mood), irritability=VALUES(irritability), appetite=VALUES(appetite), focus=VALUES(focus), weight=VALUES(weight), other_effects=VALUES(other_effects), side_effects=VALUES(side_effects), special_events=VALUES(special_events), menstruation_phase=VALUES(menstruation_phase), teacher_feedback=VALUES(teacher_feedback), emotional_reactions=VALUES(emotional_reactions)';

                $stmt = $this->pdo->prepare($sql);

                $sleep = EntryPayload::intOrNull($data['sleep'] ?? null);

                // PrÃ¼fe existierende Schlafdaten fÃ¼r den Tag
                $check = $this->pdo->prepare('SELECT id FROM entries WHERE user_id = ? AND date = ? AND sleep IS NOT NULL AND time != ? LIMIT 1');
                $check->execute([$targetUserId, $date, $time]);
                if ($check->fetch()) {
                    $sleep = null;
                    app_log('INFO', 'entry_create_sleep_skipped', ['user_id' => $targetUserId, 'date' => $date, 'reason' => 'sleep_already_exists_other_slot']);
                }

                $medId = EntryPayload::normalizeMedicationId($data['medication_id'] ?? null);

                $params = [
                    $targetUserId,
                    $medId,
                    $data['dose'] ?? null,
                    $date,
                    $time,
                    $sleep,
                    EntryPayload::intOrNull($data['hyperactivity'] ?? null),
                    EntryPayload::intOrNull($data['mood'] ?? null),
                    EntryPayload::intOrNull($data['irritability'] ?? null),
                    EntryPayload::intOrNull($data['appetite'] ?? null),
                    EntryPayload::intOrNull($data['focus'] ?? null),
                    EntryPayload::decimalOrNull($data['weight'] ?? null),
                    $data['other_effects'] ?? null,
                    $data['side_effects'] ?? null,
                    $data['special_events'] ?? null,
                    $data['menstruation_phase'] ?? null,
                    $data['teacher_feedback'] ?? null,
                    $data['emotional_reactions'] ?? null
                ];

                $stmt->execute($params);
                $entryId = (int)$this->pdo->lastInsertId();

                // Tags speichern
                if ($entryId) {
                    $this->pdo->prepare('DELETE FROM entry_tags WHERE entry_id = ?')->execute([$entryId]);

                    if (!empty($data['tags']) && is_array($data['tags'])) {
                        $stmtTag = $this->pdo->prepare('INSERT IGNORE INTO entry_tags (entry_id, tag_id) VALUES (?, ?)');
                        foreach ($data['tags'] as $tagId) {
                            $stmtTag->execute([$entryId, (int)$tagId]);
                        }
                    }
                }
            } catch (PDOException $e) {
                app_log('ERROR', 'entry_create_failed', ['error' => $e->getMessage(), 'code' => $e->getCode()]);
                if ((int)$e->getCode() === 23000) {
                    app_log('WARNING', 'entry_create_duplicate', ['user_id' => $targetUserId, 'date' => $date, 'time' => $time]);
                    $this->respond(409, ['error' => 'FÃ¼r diesen Zeitpunkt existiert bereits ein Eintrag']);
                }
                $this->respond(500, ['error' => 'Fehler beim Speichern des Eintrags: ' . $e->getMessage()]);
            }

            // Gamification
            $gamificationResult = $this->processGamification($targetUserId, $date, $time);

            app_log('INFO', 'entry_create_success', ['creator_id' => $user['id'], 'target_user_id' => $targetUserId, 'date' => $date, 'time' => $time]);
            $this->logAction($user['id'], 'entry_create', 'entry for user ' . $targetUserId);

            $this->respond(201, [
                'message' => 'Eintrag gespeichert',
                'gamification' => $gamificationResult
            ]);
        } catch (Throwable $e) {
            app_log('ERROR', 'entry_create_exception', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Speichern des Eintrags: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /entries/{id}
     * LÃ¶scht einen Eintrag.
     */
    public function destroy(string $id): void
    {
        try {
            $user = $this->requireAuth();
            $entryId = (int)$id;

            $stmt = $this->pdo->prepare('SELECT e.user_id, u.family_id FROM entries e JOIN users u ON e.user_id = u.id WHERE e.id = ?');
            $stmt->execute([$entryId]);
            $entryData = $stmt->fetch();

            if (!$entryData) {
                $this->respond(404, ['error' => 'Eintrag nicht gefunden']);
            }

            // BerechtigungsprÃ¼fung
            if ($user['role'] !== 'parent' && $user['role'] !== 'adult' && $entryData['user_id'] !== $user['id']) {
                $this->respond(403, ['error' => 'Zugriff verweigert']);
            }
            if (($user['role'] === 'parent' || $user['role'] === 'adult') && $entryData['family_id'] !== $user['family_id']) {
                $this->respond(403, ['error' => 'Zugriff verweigert']);
            }

            $stmt = $this->pdo->prepare('DELETE FROM entries WHERE id = ?');
            $stmt->execute([$entryId]);

            $this->logAction($user['id'], 'entry_delete', 'entry ' . $entryId);
            $this->respond(204);
        } catch (Throwable $e) {
            app_log('ERROR', 'entry_delete_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim LÃ¶schen des Eintrags']);
        }
    }

    /**
     * Verarbeitet Gamification-Logik (Punkte, Streaks, Badges).
     */
    private function processGamification(int $targetUserId, string $date, string $time): ?array
    {
        $stmtUser = $this->pdo->prepare('SELECT role, points, streak_current, last_entry_date FROM users WHERE id = ?');
        $stmtUser->execute([$targetUserId]);
        $tUser = $stmtUser->fetch();

        if (!$tUser || $tUser['role'] !== 'child') {
            return null;
        }

        $pointsEarned = 10;
        $today = date('Y-m-d');
        $lastDate = $tUser['last_entry_date'];
        $currentStreak = (int)$tUser['streak_current'];
        $currentPoints = (int)$tUser['points'];
        $newTotalPoints = $currentPoints + $pointsEarned;
        $newStreak = $currentStreak;

        // Streak-Logik
        if ($lastDate !== $today) {
            if ($lastDate === date('Y-m-d', strtotime('-1 day'))) {
                $newStreak = $currentStreak + 1;
            } else {
                $newStreak = 1;
            }
            $stmtUpd = $this->pdo->prepare('UPDATE users SET points = ?, streak_current = ?, last_entry_date = ? WHERE id = ?');
            $stmtUpd->execute([$newTotalPoints, $newStreak, $today, $targetUserId]);
        } else {
            $stmtUpd = $this->pdo->prepare('UPDATE users SET points = ? WHERE id = ?');
            $stmtUpd->execute([$newTotalPoints, $targetUserId]);
        }

        // Badge-Logik
        $newlyEarnedBadges = [];
        if ($newStreak > 0) {
            $stmtCheckBadges = $this->pdo->prepare(
                'SELECT b.id, b.name, b.description, b.icon_class FROM badges b
                 LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
                 WHERE b.required_streak <= ? AND ub.id IS NULL'
            );
            $stmtCheckBadges->execute([$targetUserId, $newStreak]);
            $earnableBadges = $stmtCheckBadges->fetchAll();

            $stmtAward = $this->pdo->prepare('INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)');
            foreach ($earnableBadges as $badge) {
                $stmtAward->execute([$targetUserId, $badge['id']]);
                $newlyEarnedBadges[] = $badge;
            }
        }

        // Spezial-Badges
        $specialBadgeNames = [];
        if (date('N', strtotime($date)) >= 6) {
            $specialBadgeNames[] = 'Wochenend-Warrior';
        }
        if ($time === 'morning') {
            $specialBadgeNames[] = 'Früher Vogel';
        }
        if ($time === 'evening') {
            $specialBadgeNames[] = 'Nachteule';
        }

        if (!empty($specialBadgeNames)) {
            $inQuery = implode(',', array_fill(0, count($specialBadgeNames), '?'));
            $stmtSpecial = $this->pdo->prepare("SELECT id, name, description, icon_class FROM badges WHERE name IN ($inQuery) AND required_streak IS NULL");
            $stmtSpecial->execute($specialBadgeNames);
            $potBadges = $stmtSpecial->fetchAll();

            foreach ($potBadges as $pBadge) {
                $stmtHas = $this->pdo->prepare('SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?');
                $stmtHas->execute([$targetUserId, $pBadge['id']]);
                if (!$stmtHas->fetch()) {
                    $stmtAward = $this->pdo->prepare('INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)');
                    $stmtAward->execute([$targetUserId, $pBadge['id']]);
                    $newlyEarnedBadges[] = $pBadge;
                }
            }
        }

        // NÃ¤chstes Badge
        $nextBadge = null;
        $stmtNext = $this->pdo->prepare('SELECT name, required_streak, icon_class FROM badges WHERE required_streak > ? ORDER BY required_streak ASC LIMIT 1');
        $stmtNext->execute([$newStreak]);
        $nextBadgeData = $stmtNext->fetch();
        if ($nextBadgeData) {
            $nextBadge = [
                'name' => $nextBadgeData['name'],
                'required_streak' => (int)$nextBadgeData['required_streak'],
                'days_left' => (int)$nextBadgeData['required_streak'] - $newStreak
            ];
        }

        return [
            'points_earned' => $pointsEarned,
            'total_points' => $newTotalPoints,
            'streak' => $newStreak,
            'new_badges' => $newlyEarnedBadges,
            'next_badge' => $nextBadge
        ];
    }
}
