<?php

declare(strict_types=1);

namespace FokusLog;

use PDO;
use SessionHandlerInterface;

/**
 * Konfigurierbarer Session-Handler für FokusLog.
 * 
 * Unterstützt mehrere Backends:
 * - 'files': PHP-Standard (default)
 * - 'redis': Redis-Server (erfordert phpredis Extension)
 * - 'database': MySQL/MariaDB-basierte Sessions
 * 
 * Konfiguration via .env:
 * SESSION_HANDLER=redis
 * SESSION_REDIS_HOST=127.0.0.1
 * SESSION_REDIS_PORT=6379
 * SESSION_REDIS_PREFIX=fokuslog_sess_
 * 
 * Oder für Database:
 * SESSION_HANDLER=database
 * (nutzt bestehende PDO-Verbindung)
 */
class SessionHandler
{
    /**
     * Konfiguriert und startet die Session mit optionalem Handler.
     * 
     * @param array{
     *   handler?: string,
     *   redis_host?: string,
     *   redis_port?: int,
     *   redis_prefix?: string,
     *   pdo?: PDO
     * } $config Konfiguration aus .env
     */
    public static function start(array $config): void
    {
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        
        // Session-Cookie-Parameter setzen
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        $handler = $config['handler'] ?? 'files';

        switch ($handler) {
            case 'redis':
                self::initRedis($config);
                break;
            case 'database':
                if (isset($config['pdo'])) {
                    self::initDatabase($config['pdo']);
                }
                break;
            case 'files':
            default:
                // PHP-Standard verwenden
                break;
        }

        session_start();
    }

    /**
     * Initialisiert Redis als Session-Handler.
     */
    private static function initRedis(array $config): void
    {
        if (!extension_loaded('redis')) {
            app_log('WARNING', 'session_handler', [
                'message' => 'Redis extension nicht verfügbar, fallback auf files'
            ]);
            return;
        }

        $host = $config['redis_host'] ?? '127.0.0.1';
        $port = (int)($config['redis_port'] ?? 6379);
        $prefix = $config['redis_prefix'] ?? 'fokuslog_sess_';

        // Redis als Session-Handler konfigurieren
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', "tcp://{$host}:{$port}?prefix={$prefix}");
        
        app_log('INFO', 'session_handler', [
            'handler' => 'redis',
            'host' => $host,
            'port' => $port
        ]);
    }

    /**
     * Initialisiert Database als Session-Handler.
     */
    private static function initDatabase(PDO $pdo): void
    {
        $handler = new DatabaseSessionHandler($pdo);
        session_set_save_handler($handler, true);
        
        app_log('INFO', 'session_handler', ['handler' => 'database']);
    }
}

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
