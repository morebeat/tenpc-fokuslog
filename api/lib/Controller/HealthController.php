<?php

declare(strict_types=1);

namespace FokusLog\Controller;

/**
 * Health-Check Controller für CI/CD Monitoring.
 */
class HealthController extends BaseController
{
    /**
     * Gibt den Health-Status zurück.
     */
    public function check(): void
    {
        $this->respond(200, [
            'status' => 'ok',
            'timestamp' => time()
        ]);
    }
}
