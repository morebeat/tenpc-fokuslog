<?php
/**
 * Deploy-Webhook für automatisches Deployment via Git Pull
 * 
 * POST /api/deploy.php?token=YOUR_TOKEN
 */


// Logging explizit ins Dateisystem
ini_set('error_log', __DIR__ . '/../logs/deploy.log');
header('Content-Type: application/json');

// Lade .env Datei

$envFile = __DIR__ . '/../.env';
$env = [];
if (is_file($envFile)) {
    $env = parse_ini_file($envFile) ?: [];
    error_log("[Deploy] ENV geladen: " . print_r($env, true));
} else {
    http_response_code(403);
    error_log("[Deploy] Warnung: .env Datei nicht gefunden. " . $envFile);
    echo json_encode("[Deploy] Warnung: .env Datei nicht gefunden. " . $envFile);
}

// Input lesen (JSON oder POST)
$inputData = $_POST;
if (empty($inputData)) {
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?? [];
}

// Token aus GET oder Input
$token = $_GET['token'] ?? $inputData['token'] ?? '';

if (empty($env['DEPLOY_TOKEN'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Deployment Token nicht konfiguriert.']);
    exit;
}
$expectedToken = $env['DEPLOY_TOKEN'];

// Log attempt
error_log("[Deploy] Deployment-Versuch von IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Verifiziere Token
// if (empty($token) || !hash_equals($expectedToken, $token)) {
//     http_response_code(403);
//     error_log("[Deploy] Zugriff verweigert: Ungültiger Token.");
//     echo json_encode(['error' => 'Ungültiger Token ' .  $token . " extccpected " . $expectedToken]);
//     exit;
// }
 
// Optional: Neuer .env Inhalt aus Request (z.B. von GitHub Secrets)
$newEnvContent = $inputData['env_content'] ?? null;

// Deployment-Directory
$deployDir = dirname(__DIR__);

// Überprüfe ob .git existiert
if (!is_dir($deployDir . '/.git')) {
    http_response_code(400);
    echo json_encode(['error' => 'Git-Repository nicht vorhanden: $deployDir ']);
    exit;
}

// Backup .env
$envFile = $deployDir . '/.env';
$envBackup = $deployDir . '/.env.backup';
if (is_file($envFile)) {
    copy($envFile, $envBackup);
}

// Führe git pull aus
chdir($deployDir);
$output = [];
$return = 0;

error_log("[Deploy] Starting git fetch...");
exec('git fetch origin 2>&1', $output, $return);

if ($return !== 0) {
    http_response_code(500);
    error_log("[Deploy] Git fetch failed: " . implode("\n", $output));
    echo json_encode(['error' => 'Git fetch fehlgeschlagen', 'output' => $output]);
    exit;
}

$output = [];
$return = 0;
exec('git reset --hard origin/HEAD 2>&1', $output, $return);

if ($return !== 0) {
    http_response_code(500);
    error_log("[Deploy] Git reset failed: " . implode("\n", $output));
    echo json_encode(['error' => 'Git reset fehlgeschlagen', 'output' => $output]);
    exit;
}

exec('git clean -fd 2>&1', $output, $return);

// Stelle .env wieder her oder schreibe neue
$migrationOutput = [];
if ($newEnvContent) {
    // Neue .env aus Request schreiben
    if (file_put_contents($envFile, $newEnvContent) !== false) {
        $migrationOutput[] = '.env Datei wurde aktualisiert.';
        if (is_file($envBackup)) unlink($envBackup);
    } else {
        $migrationOutput[] = 'Fehler: Konnte neue .env nicht schreiben.';
        if (is_file($envBackup)) { copy($envBackup, $envFile); unlink($envBackup); }
    }
} elseif (is_file($envBackup)) {
    copy($envBackup, $envFile);
    unlink($envBackup);
}

// Hilfe-Inhalte in Glossary-Tabelle importieren
$helpImportScript = $deployDir . '/app/help/import_help.php';
$helpImportOutput = [];
if (is_file($helpImportScript)) {
    error_log("[Deploy] Starting help/glossary import...");
    
    // Output buffering, da HelpImporter echo verwendet
    ob_start();
    
    // HelpImporter Klasse laden
    require_once $helpImportScript;
    
    // .env neu laden falls aktualisiert
    $env = parse_ini_file($envFile) ?: [];
    
    try {
        $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $importer = new HelpImporter($pdo, dirname($helpImportScript));
        $stats = $importer->setForce(false)->run();
        
        // Buffered output ins Log schreiben
        $bufferedOutput = ob_get_clean();
        if ($bufferedOutput) {
            error_log("[Deploy] Help import output: " . substr($bufferedOutput, 0, 1000));
        }
        
        $helpImportOutput[] = sprintf(
            'Help Import: %d importiert, %d aktualisiert, %d übersprungen, %d gelöscht',
            $stats['imported'], $stats['updated'], $stats['skipped'], $stats['deleted']
        );
        error_log("[Deploy] Help import completed: " . json_encode($stats));
    } catch (Throwable $e) {
        ob_end_clean();
        $helpImportOutput[] = 'Help Import fehlgeschlagen: ' . $e->getMessage();
        error_log("[Deploy] Help import failed: " . $e->getMessage());
    }
} else {
    $helpImportOutput[] = 'Help Import-Skript nicht gefunden.';
    error_log("[Deploy] Help import script not found: " . $helpImportScript);
}

// Hole aktuellen Commit
exec('git rev-parse --short HEAD', $commit);
$commitHash = trim($commit[0] ?? 'unknown');

if (empty($migrationOutput)) {
    $migrationOutput[] = 'Keine neuen Migrationen gefunden.';
}

error_log("[Deploy] Erfolg: Commit $commitHash deployed. Migrationen: " . implode(', ', $migrationOutput) . " Help: " . implode(', ', $helpImportOutput));

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Deployment erfolgreich',
    'migrations' => $migrationOutput,
    'help_import' => $helpImportOutput,
    'commit' => $commitHash,
    'timestamp' => date('Y-m-d H:i:s')
]);
