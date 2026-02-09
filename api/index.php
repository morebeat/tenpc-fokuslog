<?php

declare(strict_types=1);

/**
 * FokusLog REST API - Haupteinstiegspunkt
 *
 * Dieses Skript dient als einziger Endpunkt fÃ¼r alle API-Routen. Es nutzt
 * PHP-Sessions fÃ¼r Authentifizierung und PDO fÃ¼r den Datenbankzugriff.
 * Jeder Request muss mit Content-Type: application/json gesendet werden
 * (auÃŸer GET). RÃ¼ckgaben erfolgen als JSON.
 */

// Fehler-Reporting aktivieren und in Datei umleiten
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL);

// Shutdown-Handler fÃ¼r fatale Fehler registrieren, damit immer JSON zurÃ¼ckkommt
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'Fatal Error: ' . $error['message']]);
    }
});

// Autoloader fÃ¼r Controller und Lib
spl_autoload_register(function ($class) {
    // Namespace-Prefix fÃ¼r FokusLog
    $prefix = 'FokusLog\\';
    $baseDir = __DIR__ . '/lib/';

    // PrÃ¼fen ob die Klasse den Prefix nutzt
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Nicht unser Namespace, nÃ¤chster Autoloader
        return;
    }

    // Relative Klassennamen bestimmen
    $relativeClass = substr($class, $len);

    // Pfad zur Datei
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Logger sicher einbinden
if (file_exists(__DIR__ . '/lib/logger.php')) {
    require_once __DIR__ . '/lib/logger.php';
} elseif (file_exists(__DIR__ . '/../app/lib/logger.php')) {
    require_once __DIR__ . '/../app/lib/logger.php';
} else {
    function app_log($level, $msg, $ctx = [])
    {
        $logFile = __DIR__ . '/../logs/app.log';
        $line = json_encode(['ts' => date('c'), 'level' => $level, 'msg' => $msg, 'ctx' => $ctx], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        if (@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log("[$level] $msg " . json_encode($ctx));
        }
    }
}

// Library-Klassen laden (nicht im Namespace)
require_once __DIR__ . '/lib/EntryPayload.php';
require_once __DIR__ . '/RateLimiter.php';

// Router laden
use FokusLog\Router;

// Session-Sicherheit erhÃ¶hen
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
// ZusÃ¤tzliche Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

app_log('INFO', 'request', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'path' => $_SERVER['REQUEST_URI'] ?? '',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user' => $_SESSION['user_id'] ?? null
]);

// Lade Umgebungsvariablen aus der Root-.env
$envFile = __DIR__ . '/../.env';
if (!is_file($envFile)) {
    app_log('CRITICAL', 'env_file_not_found', ['path' => $envFile]);
    http_response_code(500);
    echo json_encode(['error' => 'Keine .env-Datei gefunden. Erwarteter Pfad: ' . $envFile]);
    exit;
}

$env = parse_ini_file($envFile);
if ($env === false) {
    app_log('CRITICAL', 'env_file_parse_failed', ['path' => $envFile]);
    http_response_code(500);
    echo json_encode(['error' => 'Konnte .env nicht lesen. Bitte Encoding/Format pruefen. Pfad: ' . $envFile]);
    exit;
}
app_log('INFO', 'env_file_loaded', ['path' => $envFile]);

// Erforderliche Variablen validieren
$requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($requiredVars as $var) {
    if (empty($env[$var])) {
        app_log('CRITICAL', 'env_var_missing', ['missing_var' => $var, 'loaded_from' => $envFile]);
        http_response_code(500);
        echo json_encode(['error' => "Erforderliche Umgebungsvariable fehlt: $var (geladen aus: $envFile)"]);
        exit;
    }
}

// Optional: Migration/Backup Token fÃ¼r admin Endpoints
$GLOBALS['MIGRATION_TOKEN'] = $env['MIGRATION_TOKEN'] ?? null;
$GLOBALS['BACKUP_TOKEN'] = $env['BACKUP_TOKEN'] ?? null;

// Datenbankverbindung herstellen
$dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    app_log('CRITICAL', 'db_connection_failed', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Pfad ermitteln
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

if (strpos($requestUri, $scriptDir) === 0) {
    $path = substr($requestUri, strlen($scriptDir));
} else {
    $path = $requestUri;
}
if (empty($path) || $path[0] !== '/') {
    $path = '/' . $path;
}

$method = $_SERVER['REQUEST_METHOD'];

// Router konfigurieren
$router = new Router($pdo);

// Auth-Routen
$router->post('/register', 'AuthController', 'register');
$router->post('/login', 'AuthController', 'login');
$router->post('/logout', 'AuthController', 'logout');
$router->get('/me', 'AuthController', 'me');
$router->post('/users/me/password', 'AuthController', 'changePassword');

// User-Routen
$router->get('/users', 'UsersController', 'index');
$router->get('/users/{id}', 'UsersController', 'show');
$router->post('/users', 'UsersController', 'store');
$router->put('/users/{id}', 'UsersController', 'update');
$router->delete('/users/{id}', 'UsersController', 'destroy');

// Medications-Routen
$router->get('/medications', 'MedicationsController', 'index');
$router->post('/medications', 'MedicationsController', 'store');
$router->put('/medications/{id}', 'MedicationsController', 'update');
$router->delete('/medications/{id}', 'MedicationsController', 'destroy');

// Entries-Routen
$router->get('/entries', 'EntriesController', 'index');
$router->post('/entries', 'EntriesController', 'store');
$router->delete('/entries/{id}', 'EntriesController', 'destroy');

// Tags-Routen
$router->get('/tags', 'TagsController', 'index');
$router->post('/tags', 'TagsController', 'store');
$router->delete('/tags/{id}', 'TagsController', 'destroy');

// Badges-Routen
$router->get('/badges', 'BadgesController', 'index');

// Weight-Routen
$router->get('/weight', 'WeightController', 'index');
$router->get('/me/latest-weight', 'WeightController', 'latestWeight');

// Glossary-Routen (Hilfe-Inhalte fÃ¼r eigene und externe Anwendungen)
$router->get('/glossary', 'GlossaryController', 'index');
$router->get('/glossary/categories', 'GlossaryController', 'categories');
$router->get('/glossary/export', 'GlossaryController', 'export');
$router->post('/glossary/import', 'GlossaryController', 'import');
$router->get('/glossary/{slug}', 'GlossaryController', 'show');

// Report-Routen (Analyse & Export)
$router->get('/report/trends', 'ReportController', 'trends');
$router->get('/report/compare', 'ReportController', 'compare');
$router->get('/report/summary', 'ReportController', 'summary');
$router->get('/report/export/excel', 'ReportController', 'exportExcel');

// Notifications-Routen (Benachrichtigungen)
$router->get('/notifications/settings', 'NotificationsController', 'getSettings');
$router->put('/notifications/settings', 'NotificationsController', 'updateSettings');
$router->post('/notifications/push/subscribe', 'NotificationsController', 'subscribePush');
$router->post('/notifications/push/unsubscribe', 'NotificationsController', 'unsubscribePush');
$router->post('/notifications/email/verify', 'NotificationsController', 'verifyEmail');
$router->post('/notifications/email/resend-verification', 'NotificationsController', 'resendVerification');
$router->get('/notifications/status', 'NotificationsController', 'getStatus');

// Admin-Routen
$router->post('/admin/migrate', 'AdminController', 'migrate');
$router->post('/admin/backup', 'AdminController', 'backup');

// Request an Router weiterleiten
$router->dispatch($method, $path);
