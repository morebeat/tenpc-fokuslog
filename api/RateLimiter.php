<?php

declare(strict_types=1);

class RateLimiter
{
    private string $storageDir;

    public function __construct()
    {
        // Nutzt das System-Temp-Verzeichnis oder einen logs Ordner
        $this->storageDir = sys_get_temp_dir();
    }

    /**
     * Prü¼ft, ob eine IP blockiert ist.
     * Limit: 5 Versuche pro 60 Sekunden.
     */
    public function check(string $ip, int $limit = 5, int $seconds = 60): bool
    {
        $file = $this->getFilename($ip);
        if (!file_exists($file)) {
            return true;
        }

        $data = json_decode(file_get_contents($file), true);

        // Wenn das Zeitfenster abgelaufen ist, Reset
        if (time() - $data['start_time'] > $seconds) {
            unlink($file);
            return true;
        }

        return $data['attempts'] < $limit;
    }

    public function increment(string $ip): void
    {
        $file = $this->getFilename($ip);
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : ['attempts' => 0, 'start_time' => time()];
        $data['attempts']++;
        file_put_contents($file, json_encode($data));
    }

    private function getFilename(string $ip): string
    {
        return $this->storageDir . '/rate_limit_' . md5($ip) . '.json';
    }
}
