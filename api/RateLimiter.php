<?php

declare(strict_types=1);

class RateLimiter
{
    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = sys_get_temp_dir();
    }

    /**
     * Prüft ob eine IP das Limit überschritten hat.
     * Limit: 5 Versuche pro 60 Sekunden (Standard).
     */
    public function check(string $ip, int $limit = 5, int $seconds = 60): bool
    {
        $file = $this->getFilename($ip);
        if (!file_exists($file)) {
            return true;
        }

        $data = $this->readLocked($file);
        if ($data === null) {
            return true;
        }

        if (time() - $data['start_time'] > $seconds) {
            @unlink($file);
            return true;
        }

        return $data['attempts'] < $limit;
    }

    /**
     * Zählt einen fehlgeschlagenen Versuch atomar hoch.
     * Verwendet exklusive Dateisperren gegen Race Conditions.
     */
    public function increment(string $ip): void
    {
        $file = $this->getFilename($ip);
        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return;
        }

        $content = stream_get_contents($fp);
        $data = ($content !== false && $content !== '')
            ? json_decode($content, true)
            : null;

        if (!is_array($data)) {
            $data = ['attempts' => 0, 'start_time' => time()];
        }

        $data['attempts']++;

        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, (string)json_encode($data));

        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * Setzt den Zähler für eine IP zurück (nach erfolgreichem Login).
     */
    public function reset(string $ip): void
    {
        $file = $this->getFilename($ip);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    private function getFilename(string $ip): string
    {
        return $this->storageDir . '/rate_limit_' . md5($ip) . '.json';
    }

    /**
     * Liest eine Rate-Limit-Datei sicher mit Shared-Lock.
     *
     * @return array<string, mixed>|null
     */
    private function readLocked(string $file): ?array
    {
        $fp = fopen($file, 'r');
        if ($fp === false) {
            return null;
        }

        if (!flock($fp, LOCK_SH)) {
            fclose($fp);
            return null;
        }

        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false || $content === '') {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }
}
