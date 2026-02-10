<?php

declare(strict_types=1);

namespace FokusLog\Controller;

/**
 * Health-Check Controller für CI/CD Monitoring und Load Balancer.
 *
 * GET /health — Prüft DB-Verbindung, gibt PHP-Version und App-Infos zurück.
 * Gibt HTTP 200 (ok) oder HTTP 503 (degraded/unhealthy) zurück.
 */
class HealthController extends BaseController
{
    /**
     * Gibt den Health-Status zurück.
     * HTTP 200 bei ok, HTTP 503 bei Datenbankproblemen.
     */
    public function check(): void
    {
        $dbStatus = $this->checkDatabase();
        $status = $dbStatus['ok'] ? 'ok' : 'degraded';
        $httpCode = $dbStatus['ok'] ? 200 : 503;

        $this->respond($httpCode, [
            'status'     => $status,
            'timestamp'  => time(),
            'php_version' => PHP_VERSION,
            'database'   => $dbStatus['ok'] ? 'connected' : 'error: ' . $dbStatus['error'],
        ]);
    }

    /**
     * Testet die Datenbankverbindung mit einer minimalen Query.
     *
     * @return array{ok: bool, error: string}
     */
    private function checkDatabase(): array
    {
        try {
            $this->pdo->query('SELECT 1');
            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
