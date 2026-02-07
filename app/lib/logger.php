<?php
// api/lib/logger.php

function app_log(string $level, string $message, array $context = []): void
{
    $debug = getenv('DEBUG_LOG') ?: ($_ENV['DEBUG_LOG'] ?? '0');
    if ($debug !== '1') {
      //  return;
    }

    $logFile = __DIR__ . '/../../logs/app.log';

    // Sensible Felder entfernen
    $redactKeys = [
        'password', 'password_hash',
        'other_effects', 'side_effects',
        'teacher_feedback', 'emotional_reactions'
    ];

    foreach ($redactKeys as $key) {
        if (isset($context[$key])) {
            $context[$key] = '[redacted]';
        }
    }

    $entry = [
        'ts'    => date('c'),
        'level' => $level,
        'msg'   => $message,
        'ctx'   => $context
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    // Fallback: wenn Datei nicht beschreibbar → PHP error_log
    if (@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('[FokusLog] ' . $line);
    }
}
