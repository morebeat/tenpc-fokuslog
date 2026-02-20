<?php

declare(strict_types=1);

namespace FokusLog;

use PDO;
use SessionHandlerInterface;

/**
 * PDO-basierter Session-Handler.
 *
 * Erfordert Tabelle:
 * CREATE TABLE sessions (
 *   id VARCHAR(128) NOT NULL PRIMARY KEY,
 *   data TEXT NOT NULL,
 *   last_access INT UNSIGNED NOT NULL,
 *   INDEX idx_last_access (last_access)
 * );
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $lifetime;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->lifetime = (int)ini_get('session.gc_maxlifetime');
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT data FROM sessions WHERE id = ? AND last_access > ?'
        );
        $stmt->execute([$id, time() - $this->lifetime]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            'REPLACE INTO sessions (id, data, last_access) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM sessions WHERE last_access < ?'
        );
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
