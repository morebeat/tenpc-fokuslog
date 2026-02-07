#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be executed via the CLI." . PHP_EOL);
    exit(1);
}

$rootDir = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';

$options = getopt('', [
    'env::',
    'schema::',
    'migrations::',
    'seed-file::',
    'api-url::',
    'with-tests',
    'with-seed',
    'create-db',
    'skip-schema',
    'skip-migrations',
    'skip-help',
    'skip-tests',
    'dry-run',
    'quiet',
    'help'
]);

if (isset($options['help'])) {
    echo "FokusLog bootstrap\n";
    echo "Usage: php scripts/bootstrap.php [options]\n\n";
    echo "Options:\n";
    echo "  --env=PATH            Path to .env file (default: project/.env)\n";
    echo "  --schema=PATH         SQL schema file (default: db/schema_v4.sql)\n";
    echo "  --migrations=PATH     Path to SQL migrations directory (default: db/migrations)\n";
    echo "  --seed-file=PATH      Seed file to run when --with-seed is set (default: db/seed.sql)\n";
    echo "  --create-db           Create the database if it does not exist\n";
    echo "  --with-seed           Apply the seed file after the schema\n";
    echo "  --with-tests          Execute api/run_tests.php at the end\n";
    echo "  --api-url=URL         Override API URL for tests (default: http://localhost:8000/api)\n";
    echo "  --skip-schema         Skip schema import\n";
    echo "  --skip-migrations     Skip migrations\n";
    echo "  --skip-help           Skip help/glossary import\n";
    echo "  --skip-tests          Skip tests even if --with-tests is provided\n";
    echo "  --dry-run             Show actions without executing SQL or child processes\n";
    echo "  --quiet               Reduce informational output\n";
    echo "  --help                Show this help text\n";
    exit(0);
}

$config = [
    'rootDir' => $rootDir,
    'envPath' => $options['env'] ?? ($rootDir . '/.env'),
    'schemaPath' => $options['schema'] ?? ($rootDir . '/db/schema_v4.sql'),
    'migrationsDir' => $options['migrations'] ?? ($rootDir . '/db/migrations'),
    'seedPath' => $options['seed-file'] ?? ($rootDir . '/db/seed.sql'),
    'apiUrl' => $options['api-url'] ?? (getenv('API_URL') ?: 'http://localhost:8000/api'),
    'runSchema' => !isset($options['skip-schema']),
    'runMigrations' => !isset($options['skip-migrations']),
    'runHelp' => !isset($options['skip-help']),
    'runTests' => isset($options['with-tests']) && !isset($options['skip-tests']),
    'runSeed' => isset($options['with-seed']),
    'createDatabase' => isset($options['create-db']),
    'dryRun' => isset($options['dry-run']),
    'quiet' => isset($options['quiet'])
];

try {
    (new BootstrapRunner($config))->run();
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . PHP_EOL);
    exit(1);
}

final class BootstrapRunner
{
    private array $config;
    private bool $dryRun;
    private bool $quiet;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->dryRun = $config['dryRun'];
        $this->quiet = $config['quiet'];
    }

    public function run(): void
    {
        $this->log('INFO', 'Starting FokusLog bootstrap');
        $env = $this->loadEnv($this->config['envPath']);
        $this->log('INFO', 'Environment loaded from ' . $this->config['envPath']);

        if ($this->config['createDatabase']) {
            $this->createDatabase($env);
        }

        $pdo = $this->connect($env);
        $statementCount = 0;

        if ($this->config['runSchema']) {
            $statementCount += $this->runSqlFile($pdo, $this->config['schemaPath']);
        }

        if ($this->config['runSeed']) {
            $statementCount += $this->runSqlFile($pdo, $this->config['seedPath']);
        }

        if ($this->config['runMigrations']) {
            $statementCount += $this->runMigrations($pdo, $this->config['migrationsDir']);
        }

        if ($this->config['runHelp']) {
            $this->runHelpImport();
        }

        if ($this->config['runTests']) {
            $this->runApiTests();
        }

        $this->log('SUCCESS', sprintf('Bootstrap complete (%d SQL statements executed)', $statementCount));
    }

    private function connect(array $env): PDO
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_NAME']);
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    }

    private function createDatabase(array $env): void
    {
        $this->log('INFO', 'Ensuring database ' . $env['DB_NAME'] . ' exists');
        if ($this->dryRun) {
            $this->log('INFO', '[dry-run] CREATE DATABASE skipped');
            return;
        }
        $dsn = sprintf('mysql:host=%s;charset=utf8mb4', $env['DB_HOST']);
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $env['DB_NAME']));
        $this->log('SUCCESS', 'Database ensured');
    }

    private function runSqlFile(PDO $pdo, string $path): int
    {
        if (!is_file($path)) {
            throw new RuntimeException('SQL file not found: ' . $path);
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Unable to read SQL file: ' . $path);
        }
        $statements = $this->splitSqlStatements($sql);
        if ($this->dryRun) {
            $this->log('INFO', sprintf('[dry-run] %d statements parsed from %s', count($statements), $path));
            return 0;
        }
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        $this->log('SUCCESS', sprintf('Executed %d statements from %s', count($statements), $path));
        return count($statements);
    }

    private function runMigrations(PDO $pdo, string $directory): int
    {
        if (!is_dir($directory)) {
            $this->log('INFO', 'Migrations directory not found, skipping: ' . $directory);
            return 0;
        }
        $files = array_values(array_filter(scandir($directory) ?: [], function ($file) use ($directory) {
            return is_file($directory . DIRECTORY_SEPARATOR . $file) && $this->endsWith($file, '.sql');
        }));
        sort($files);
        $count = 0;
        foreach ($files as $file) {
            $count += $this->runSqlFile($pdo, $directory . DIRECTORY_SEPARATOR . $file);
        }
        return $count;
    }

    private function runHelpImport(): void
    {
        $script = $this->config['rootDir'] . '/app/help/import_help.php';
        if (!is_file($script)) {
            $this->log('WARNING', 'Help import script not found, skipping: ' . $script);
            return;
        }
        $this->log('INFO', 'Running help/glossary import');
        if ($this->dryRun) {
            $this->log('INFO', '[dry-run] help import skipped');
            return;
        }
        $this->runCommand([PHP_BINARY, $script]);
    }

    private function runApiTests(): void
    {
        $script = $this->config['rootDir'] . '/api/run_tests.php';
        if (!is_file($script)) {
            $this->log('WARNING', 'Test runner not found, skipping: ' . $script);
            return;
        }
        $this->log('INFO', 'Executing API regression tests');
        if ($this->dryRun) {
            $this->log('INFO', '[dry-run] tests skipped');
            return;
        }
        $env = array_merge($_ENV, [
            'API_URL' => $this->config['apiUrl']
        ]);
        $this->runCommand([PHP_BINARY, $script], $env);
    }

    private function runCommand(array $commandParts, array $env = []): void
    {
        $commandLine = implode(' ', array_map(static function ($part) {
            return escapeshellarg((string)$part);
        }, $commandParts));
        $processEnv = array_merge($_ENV, $env);
        $descriptorSpec = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];
        $process = proc_open($commandLine, $descriptorSpec, $pipes, $this->config['rootDir'], $processEnv);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start process: ' . $commandLine);
        }
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf('Command failed (%d): %s', $exitCode, $commandLine));
        }
    }

    private function loadEnv(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Environment file not found: ' . $path);
        }
        $data = parse_ini_file($path);
        if ($data === false) {
            throw new RuntimeException('Unable to parse environment file: ' . $path);
        }
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new RuntimeException('Missing required env key: ' . $key);
            }
        }
        return $data;
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $inLineComment = false;
        $inBlockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inSingle && !$inDouble) {
                if ($char === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === "'" && !$inDouble) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inSingle = !$inSingle;
                }
            } elseif ($char === '"' && !$inSingle) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inDouble = !$inDouble;
                }
            }

            if ($char === ';' && !$inSingle && !$inDouble) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    private function log(string $level, string $message): void
    {
        if ($this->quiet && $level === 'INFO') {
            return;
        }
        fwrite(STDOUT, sprintf('[%s] %s%s', $level, $message, PHP_EOL));
    }

    private function endsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        if (strlen($needle) > strlen($haystack)) {
            return false;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}
