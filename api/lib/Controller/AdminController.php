<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use Throwable;

/**
 * Controller für Admin-Funktionen (Migration, Backup).
 */
class AdminController extends BaseController
{
    /**
     * POST /admin/migrate
     * Führt Datenbank-Migrationen aus.
     */
    public function migrate(): void
    {
        try {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $expectedToken = $GLOBALS['MIGRATION_TOKEN'] ?? null;

            if (!$expectedToken) {
                app_log('ERROR', 'migration_no_token_configured', []);
                $this->respond(500, ['error' => 'Migration token nicht konfiguriert']);
            }

            if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
                app_log('WARNING', 'migration_missing_token', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
                $this->respond(401, ['error' => 'Authorization token erforderlich']);
            }

            if ($matches[1] !== $expectedToken) {
                app_log('WARNING', 'migration_invalid_token', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
                $this->respond(403, ['error' => 'Ungültiger token']);
            }

            $data = $this->getJsonBody();
            $reset = $data['reset'] ?? false;
            $seed = $data['seed'] ?? false;

            if ($reset) {
                app_log('WARNING', 'migration_reset_requested', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            }

            $migrationResults = [];

            // 1. Reset: Leere alle Tabellen
            if ($reset) {
                $truncateTables = [
                    'user_badges',
                    'entry_tags',
                    'audit_log',
                    'entries',
                    'badges',
                    'tags',
                    'medications',
                    'users',
                    'consents',
                    'families'
                ];

                foreach ($truncateTables as $table) {
                    try {
                        $this->pdo->exec("TRUNCATE TABLE $table");
                        $migrationResults["reset_$table"] = 'ok';
                    } catch (Throwable $e) {
                        $migrationResults["reset_$table"] = 'error: ' . $e->getMessage();
                    }
                }
            }

            // 2. Migrationen: Erstelle Indexes
            $indexStatements = [
                'CREATE INDEX idx_users_family_id ON users(family_id)',
                'CREATE INDEX idx_medications_family_id ON medications(family_id)',
                'CREATE INDEX idx_entries_user_id ON entries(user_id)',
                'CREATE INDEX idx_entries_user_date ON entries(user_id, date)',
                'CREATE INDEX idx_entries_medication_id ON entries(medication_id)',
                'CREATE INDEX idx_user_badges_user_id ON user_badges(user_id)',
                'CREATE INDEX idx_user_badges_badge_id ON user_badges(badge_id)',
                'CREATE INDEX idx_entry_tags_entry_id ON entry_tags(entry_id)',
                'CREATE INDEX idx_entry_tags_tag_id ON entry_tags(tag_id)',
                'CREATE INDEX idx_tags_family_id ON tags(family_id)',
                'CREATE INDEX idx_audit_log_user_id ON audit_log(user_id)',
                'CREATE INDEX idx_audit_log_created_at ON audit_log(created_at)',
                'CREATE INDEX idx_consents_user_id ON consents(user_id)'
            ];

            foreach ($indexStatements as $sql) {
                try {
                    @$this->pdo->exec($sql);
                    $migrationResults["index_" . substr($sql, 21, 20)] = 'ok';
                } catch (Throwable $e) {
                    $migrationResults["index_" . substr($sql, 21, 20)] = 'exists';
                }
            }

            // 3. Seed: Lade Test-Datensätze
            if ($seed) {
                $seedFile = __DIR__ . '/../../db/seed.sql';
                if (!is_file($seedFile)) {
                    $migrationResults['seed'] = 'error: seed.sql not found';
                } else {
                    try {
                        $seedSql = file_get_contents($seedFile);
                        $this->pdo->exec($seedSql);
                        $migrationResults['seed'] = 'ok';
                        app_log('INFO', 'migration_seed_loaded', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
                    } catch (Throwable $e) {
                        $migrationResults['seed'] = 'error: ' . $e->getMessage();
                        app_log('ERROR', 'migration_seed_failed', ['error' => $e->getMessage()]);
                    }
                }
            }

            app_log('INFO', 'migration_completed', [
                'reset' => $reset,
                'seed' => $seed,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);

            $this->respond(200, [
                'message' => 'Migrationen erfolgreich ausgeführt',
                'reset' => $reset,
                'seed' => $seed,
                'migrations' => $migrationResults
            ]);
        } catch (Throwable $e) {
            app_log('ERROR', 'migration_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler bei der Migration: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /admin/backup
     * Erstellt ein SQL-Backup der Datenbank.
     */
    public function backup(): void
    {
        try {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $expectedToken = $GLOBALS['BACKUP_TOKEN'] ?? null;

            if (!$expectedToken) {
                app_log('WARNING', 'backup_no_token_configured', []);
                $this->respond(500, ['error' => 'Backup token nicht konfiguriert']);
            }

            if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
                app_log('WARNING', 'backup_missing_token', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
                $this->respond(401, ['error' => 'Authorization token erforderlich']);
            }

            if ($matches[1] !== $expectedToken) {
                app_log('WARNING', 'backup_invalid_token', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
                $this->respond(403, ['error' => 'Ungültiger token']);
            }

            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $stmt = $this->pdo->prepare("SELECT DATABASE()");
            $stmt->execute();
            $dbName = $stmt->fetchColumn();

            $timestamp = date('YmdHis');
            $backupFile = $backupDir . "/backup_{$dbName}_{$timestamp}.sql";

            try {
                $this->pdo->beginTransaction();

                $tables = $this->pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

                $sqlBackup = "-- FokusLog Database Backup\n";
                $sqlBackup .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
                $sqlBackup .= "-- Database: $dbName\n\n";
                $sqlBackup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

                foreach ($tables as $table) {
                    $createStmt = $this->pdo->query("SHOW CREATE TABLE $table");
                    $createRow = $createStmt->fetch(\PDO::FETCH_ASSOC);
                    $sqlBackup .= $createRow['Create Table'] . ";\n\n";

                    $dataStmt = $this->pdo->query("SELECT * FROM $table");
                    while ($row = $dataStmt->fetch(\PDO::FETCH_ASSOC)) {
                        $cols = implode(', ', array_keys($row));
                        $vals = implode(', ', array_map(function ($v) {
                            return $v === null ? 'NULL' : $this->pdo->quote($v);
                        }, array_values($row)));
                        $sqlBackup .= "INSERT INTO $table ($cols) VALUES ($vals);\n";
                    }
                    $sqlBackup .= "\n";
                }

                $sqlBackup .= "SET FOREIGN_KEY_CHECKS=1;\n";

                $this->pdo->commit();

                if (!file_put_contents($backupFile, $sqlBackup)) {
                    throw new \Exception("Konnte Backup-Datei nicht schreiben: $backupFile");
                }

                // Komprimiere
                $compressedFile = $backupFile . '.gz';
                if (!function_exists('gzencode')) {
                    @exec("gzip '$backupFile'");
                    if (!is_file($compressedFile)) {
                        $compressedFile = $backupFile;
                    }
                } else {
                    $compressed = gzencode(file_get_contents($backupFile), 9);
                    file_put_contents($compressedFile, $compressed);
                    unlink($backupFile);
                    $backupFile = $compressedFile;
                }

                $fileSize = filesize($backupFile);

                // Aufräumen: Entferne Backups älter als 30 Tage
                $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
                foreach (glob($backupDir . "/backup_*.sql*") as $file) {
                    if (filemtime($file) < $thirtyDaysAgo) {
                        @unlink($file);
                    }
                }

                app_log('INFO', 'backup_completed', [
                    'database' => $dbName,
                    'file' => basename($backupFile),
                    'size' => $fileSize,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);

                $this->respond(200, [
                    'message' => 'Backup erfolgreich erstellt',
                    'filename' => basename($backupFile),
                    'size' => $fileSize,
                    'timestamp' => $timestamp
                ]);
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        } catch (Throwable $e) {
            app_log('ERROR', 'backup_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Backup: ' . $e->getMessage()]);
        }
    }
}
