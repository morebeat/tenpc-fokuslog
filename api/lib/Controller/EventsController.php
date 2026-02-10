<?php

declare(strict_types=1);

namespace FokusLog\Controller;

/**
 * Server-Sent Events (SSE) Controller für Echtzeit-Updates.
 * 
 * Ermöglicht Clients, sich für Events wie neue Einträge zu registrieren.
 * Eltern können so sehen, wenn ihr Kind einen Eintrag erstellt.
 * 
 * Verwendung im Frontend:
 * ```js
 * const sub = utils.subscribe('/api/events', {
 *   'entry.created': (e) => {
 *     const data = JSON.parse(e.data);
 *     utils.toast(`Neuer Eintrag von ${data.username}`);
 *   }
 * });
 * ```
 */
class EventsController extends BaseController
{
    /** Heartbeat-Intervall in Sekunden */
    private const HEARTBEAT_INTERVAL = 30;
    
    /** Maximale Verbindungsdauer in Sekunden */
    private const MAX_CONNECTION_TIME = 300;
    
    /** Poll-Intervall für neue Events in Sekunden */
    private const POLL_INTERVAL = 2;

    /**
     * SSE-Stream für den aktuellen Benutzer.
     * GET /api/events
     * 
     * Query-Parameter:
     * - last_event_id: ID des letzten empfangenen Events (für Reconnect)
     */
    public function stream(): void
    {
        $user = $this->requireAuth();
        
        // SSE-Headers setzen
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx-Buffering deaktivieren
        
        // Output-Buffering deaktivieren
        if (ob_get_level()) {
            ob_end_flush();
        }
        
        // Session schließen um andere Requests nicht zu blockieren
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        $lastEventId = isset($_GET['last_event_id']) ? (int)$_GET['last_event_id'] : 0;
        $startTime = time();
        $lastHeartbeat = $startTime;
        $lastPoll = $startTime;
        
        // Initial-Event senden
        $this->sendEvent('connected', [
            'user_id' => $user['id'],
            'timestamp' => date('c')
        ]);
        
        // Event-Loop
        while (true) {
            // Verbindung prüfen
            if (connection_aborted()) {
                break;
            }
            
            // Maximale Verbindungsdauer erreicht?
            if ((time() - $startTime) > self::MAX_CONNECTION_TIME) {
                $this->sendEvent('reconnect', [
                    'reason' => 'max_connection_time',
                    'retry' => 1000
                ]);
                break;
            }
            
            $now = time();
            
            // Heartbeat senden
            if (($now - $lastHeartbeat) >= self::HEARTBEAT_INTERVAL) {
                $this->sendComment('heartbeat');
                $lastHeartbeat = $now;
            }
            
            // Auf neue Events prüfen
            if (($now - $lastPoll) >= self::POLL_INTERVAL) {
                $events = $this->pollEvents($user, $lastEventId);
                
                foreach ($events as $event) {
                    $this->sendEvent($event['type'], $event['data'], $event['id']);
                    $lastEventId = max($lastEventId, $event['id']);
                }
                
                $lastPoll = $now;
            }
            
            // Kurz warten
            usleep(500000); // 500ms
        }
        
        exit;
    }

    /**
     * Neues Event in die Queue schreiben.
     * Wird von anderen Controllern aufgerufen (z.B. EntriesController).
     * 
     * @param string $type Event-Typ (z.B. 'entry.created')
     * @param array $data Event-Daten
     * @param int $familyId Ziel-Familie (alle Mitglieder erhalten das Event)
     * @param int|null $excludeUserId User-ID, die das Event nicht erhalten soll
     */
    public function pushEvent(string $type, array $data, int $familyId, ?int $excludeUserId = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO events_queue (family_id, type, data, exclude_user_id, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $familyId,
            $type,
            json_encode($data),
            $excludeUserId
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Holt neue Events für einen Benutzer.
     */
    private function pollEvents(array $user, int $lastEventId): array
    {
        // Prüfen ob events_queue Tabelle existiert
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, type, data FROM events_queue
                 WHERE family_id = ?
                   AND id > ?
                   AND (exclude_user_id IS NULL OR exclude_user_id != ?)
                   AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY id ASC
                 LIMIT 10'
            );
            $stmt->execute([$user['family_id'], $lastEventId, $user['id']]);
            
            $events = [];
            while ($row = $stmt->fetch()) {
                $events[] = [
                    'id' => (int)$row['id'],
                    'type' => $row['type'],
                    'data' => json_decode($row['data'], true) ?? []
                ];
            }
            
            return $events;
        } catch (\PDOException $e) {
            // Tabelle existiert nicht - leeres Array zurückgeben
            return [];
        }
    }

    /**
     * Sendet ein SSE-Event.
     */
    private function sendEvent(string $type, array $data, ?int $id = null): void
    {
        if ($id !== null) {
            echo "id: {$id}\n";
        }
        echo "event: {$type}\n";
        echo "data: " . json_encode($data) . "\n\n";
        flush();
    }

    /**
     * Sendet einen SSE-Kommentar (für Heartbeat).
     */
    private function sendComment(string $text): void
    {
        echo ": {$text}\n\n";
        flush();
    }

    /**
     * Alte Events aus der Queue löschen.
     * Sollte periodisch aufgerufen werden (z.B. via Cron).
     */
    public function cleanup(): void
    {
        $this->requireAuth();
        
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM events_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            );
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            $this->respond(200, [
                'deleted' => $deleted,
                'message' => "Alte Events bereinigt"
            ]);
        } catch (\PDOException $e) {
            $this->respond(200, ['deleted' => 0, 'message' => 'Tabelle nicht vorhanden']);
        }
    }
}
