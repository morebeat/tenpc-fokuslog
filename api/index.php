<?php
declare(strict_types=1);

use InvalidArgumentException;
/**
 * Minimalist REST API für FokusLog.
 *
 * Dieses Skript dient als einziger Endpunkt für alle API‑Routen. Es nutzt
 * PHP‑Sessions für Authentifizierung und PDO für den Datenbankzugriff.
 * Jeder Request muss mit Content‑Type: application/json gesendet werden
 * (außer GET). Rückgaben erfolgen als JSON.
 */
// Fehler-Reporting aktivieren und in Datei umleiten
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '../logs/php_error.log');
error_reporting(E_ALL);

// Shutdown-Handler für fatale Fehler registrieren, damit immer JSON zurückkommt
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'Fatal Error: ' . $error['message']]);
    }
});

// Logger sicher einbinden
if (file_exists(__DIR__ . '/lib/logger.php')) {
    require_once __DIR__ . '/lib/logger.php';
} elseif (file_exists(__DIR__ . '/../app/lib/logger.php')) {
    require_once __DIR__ . '/../app/lib/logger.php';
} else {
    function app_log($level, $msg, $ctx = []) {
        error_log("[$level] $msg " . json_encode($ctx));
    }
}

require_once __DIR__ . '/lib/EntryPayload.php';

// Session-Sicherheit erhöhen
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', // Aktuelle Domain
    'secure' => true, // Nur über HTTPS senden
    'httponly' => true, // Kein Zugriff via JS
    'samesite' => 'Strict' // Schutz vor CSRF
]);
session_start();
header('Content-Type: application/json; charset=utf-8');

app_log('INFO', 'request', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'path'   => $_SERVER['REQUEST_URI'] ?? '',
    'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
    'user'   => $_SESSION['user_id'] ?? null
]);

// Lade Umgebungsvariablen aus der Root-.env
$envFile = __DIR__ . '/../.env';
$env = null;
$loadedEnvFile = null;

if (!is_file($envFile)) {
    app_log('CRITICAL', 'env_file_not_found', ['path' => $envFile]);
    http_response_code(500);
    echo json_encode(['error' => 'Keine .env-Datei gefunden. Erwarteter Pfad: ' . $envFile]);
    exit;
}

$parsed = parse_ini_file($envFile);
if ($parsed === false) {
    app_log('CRITICAL', 'env_file_parse_failed', ['path' => $envFile]);
    http_response_code(500);
    echo json_encode(['error' => 'Konnte .env nicht lesen. Bitte Encoding/Format pruefen. Pfad: ' . $envFile]);
    exit;
}

$env = $parsed;
$loadedEnvFile = $envFile;
app_log('INFO', 'env_file_loaded', ['path' => $envFile]);

// Erforderliche Variablen validieren
$requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($requiredVars as $var) {
    if (empty($env[$var])) {
        app_log('CRITICAL', 'env_var_missing', ['missing_var' => $var, 'loaded_from' => $loadedEnvFile]);
        http_response_code(500);
        echo json_encode(['error' => "Erforderliche Umgebungsvariable fehlt: $var (geladen aus: $loadedEnvFile)"]);
        exit;
    }
}

// Optional: Migration Token für admin/migrate Endpoint
$GLOBALS['MIGRATION_TOKEN'] = $env['MIGRATION_TOKEN'] ?? null;

// Optional: Backup Token für admin/backup Endpoint
$GLOBALS['BACKUP_TOKEN'] = $env['BACKUP_TOKEN'] ?? null;

// Datenbankverbindung herstellen
$dbHost = $env['DB_HOST'];
$dbName = $env['DB_NAME'];
$dbUser = $env['DB_USER'];
$dbPass = $env['DB_PASS'];

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    app_log('CRITICAL', 'db_connection_failed', [
        'error' => $e->getMessage()
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

/**
 * Hilfsfunktion zum Senden einer JSON‑Antwort und Beenden.
 */
function respond(int $code, array $data = []): void
{
    http_response_code($code);
    if ($data !== []) {
        echo json_encode($data);
    }
    exit;
}

/**
 * Lese JSON‑Body und gebe als Array zurück.
 */
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Hole aktuellen Benutzer aus der Session. Liefert null, wenn nicht eingeloggt.
 */
function currentUser(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Überprüft, ob Benutzer eingeloggt ist. Wenn nicht, 401.
 */
function requireAuth(PDO $pdo): array
{
    $user = currentUser($pdo);
    if (!$user) {
        app_log('WARNING', 'auth_required', [
            'path' => $_SERVER['REQUEST_URI'] ?? '',
            'ip'   => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        respond(401, ['error' => 'Nicht angemeldet']);
    }
    return $user;
}

/**
 * Überprüft, ob der Benutzer eine erlaubte Rolle hat. Andernfalls 403.
 */
function requireRole(array $user, array $roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        app_log('WARNING', 'access_denied_role', [
            'user_id'        => $user['id'],
            'user_role'      => $user['role'],
            'required_roles' => $roles
        ]);
        respond(403, ['error' => 'Zugriff verweigert']);
    }
}

/**
 * Fügt einen Eintrag in das Audit‑Log ein.
 */
function logAction(PDO $pdo, ?int $userId, string $action, $details = null): void
{
    try {
        $detailsJson = null;
        if ($details !== null) {
            if (is_string($details)) {
                $details = ['message' => $details];
            } elseif (!is_array($details)) {
                $details = ['value' => $details];
            }

            $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($detailsJson === false) {
                throw new RuntimeException('Invalid audit log details payload');
            }
        }

        $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, details) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $action, $detailsJson]);
    } catch (Throwable $e) {
        // Logging failure should not stop the application
        error_log('Audit Log Error: ' . $e->getMessage());
    }
}

// Ermitteln der Route
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// Pfad relativ zum Skript-Verzeichnis ermitteln (funktioniert auch in Unterordnern)
if (strpos($requestUri, $scriptDir) === 0) {
    $path = substr($requestUri, strlen($scriptDir));
} else {
    $path = $requestUri;
}
// Sicherstellen, dass der Pfad mit / beginnt
if (empty($path) || $path[0] !== '/') {
    $path = '/' . $path;
}

// Routing
switch ($path) {
    case '/register':
        if ($method === 'POST') {
            handleRegister($pdo);
        }
        break;
    case '/login':
        if ($method === 'POST') {
            handleLogin($pdo);
        }
        break;
    case '/logout':
        if ($method === 'POST') {
            handleLogout();
        }
        break;
    case '/me':
        if ($method === 'GET') {
            handleMe($pdo);
        }
        break;
    case '/users':
        if ($method === 'GET') {
            handleUsersGet($pdo);
        } elseif ($method === 'POST') {
            handleUsersPost($pdo);
        }
        break;
    case '/medications':
        if ($method === 'GET') {
            handleMedicationsGet($pdo);
        } elseif ($method === 'POST') {
            handleMedicationsPost($pdo);
        }
        break;
    case '/entries':
        if ($method === 'GET') {
            handleEntriesGet($pdo);
        } elseif ($method === 'POST') {
            handleEntriesPost($pdo);
        }
        break;
    case '/tags':
        if ($method === 'GET') {
            handleTagsGet($pdo);
        } elseif ($method === 'POST') {
            handleTagsPost($pdo);
        }
        break;
    case '/badges':
        if ($method === 'GET') {
            handleBadgesGet($pdo);
        }
        break;
    case '/glossary':
        if ($method === 'GET') {
            handleGlossaryGet($pdo);
        }
        break;
    case '/weight':
        if ($method === 'GET') {
            handleWeightGet($pdo);
        }
        break;
    case '/me/latest-weight':
        if ($method === 'GET') {
            handleMyLatestWeightGet($pdo);
        }
        break;
    case '/admin/migrate':
        if ($method === 'POST') {
            handleAdminMigrate($pdo);
        }
        break;
    case '/admin/backup':
        if ($method === 'POST') {
            handleAdminBackup($pdo);
        }
        break;
    default:
        // Routen mit dynamischer ID
        if (preg_match('#^/medications/(\d+)$#', $path, $matches)) {
            $medId = (int)$matches[1];
            if ($method === 'PUT') {
                handleMedicationsPut($pdo, $medId);
            } elseif ($method === 'DELETE') {
                handleMedicationsDelete($pdo, $medId);
            }
        } elseif (preg_match('#^/entries/(\d+)$#', $path, $matches)) {
            $entryId = (int)$matches[1];
            if ($method === 'DELETE') {
                handleEntriesDelete($pdo, $entryId);
            }
        } elseif (preg_match('#^/users/(\d+)$#', $path, $matches)) {
            $userId = (int)$matches[1];
            if ($method === 'GET') {
                handleUserGet($pdo, $userId);
            } elseif ($method === 'PUT') {
                handleUsersPut($pdo, $userId);
            } elseif ($method === 'DELETE') {
                handleUsersDelete($pdo, $userId);
            }
        } elseif (preg_match('#^/tags/(\d+)$#', $path, $matches)) {
            $tagId = (int)$matches[1];
            if ($method === 'DELETE') {
                handleTagsDelete($pdo, $tagId);
            }
        } elseif (preg_match('#^/glossary/([a-zA-Z0-9\-_]+)$#', $path, $matches)) {
            $slug = $matches[1];
            if ($method === 'GET') {
                handleGlossaryEntryGet($pdo, $slug);
            }
        } elseif ($path === '/users/me/password') {
            if ($method === 'POST') {
                handleMyPasswordPost($pdo);
            }
        } else {
            respond(404, ['error' => 'Endpoint nicht gefunden']);
        }
}

/**
 * Registrierung eines neuen Parents und seiner Familie.
 * Erwartet JSON: { "family_name": String, "username": String, "password": String }
 */
function handleRegister(PDO $pdo): void
{
    $data = getJsonBody();
    $accountType = $data['account_type'] ?? 'family';
    $familyName = trim($data['family_name'] ?? '');
    $username   = trim($data['username'] ?? '');
    $password   = $data['password'] ?? '';

    // Bei Einzelpersonen ist der Familienname optional/automatisch
    if ($accountType === 'individual' && $familyName === '') {
        $familyName = 'Privat';
    }

    if ($familyName === '' || $username === '' || $password === '') {
        app_log('WARNING', 'register_validation_failed', ['error' => 'missing_fields']);
        respond(400, ['error' => 'family_name, username und password sind erforderlich']);
    }
    if (strlen($password) < 8) {
        respond(400, ['error' => 'Das Passwort muss mindestens 8 Zeichen lang sein.']);
    }
    try {
        // Prüfe, ob Benutzername bereits existiert
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            app_log('WARNING', 'register_user_exists', ['username' => $username]);
            respond(409, ['error' => 'Benutzername existiert bereits']);
        }
        $pdo->beginTransaction();
        // Familie anlegen
        $stmt = $pdo->prepare('INSERT INTO families (name) VALUES (?)');
        $stmt->execute([$familyName]);
        $familyId = (int)$pdo->lastInsertId();
        // Parent anlegen
        $role = 'parent';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (family_id, username, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$familyId, $username, $passwordHash, $role]);
        logAction($pdo, null, 'register', 'new family and parent');
        $pdo->commit();
        app_log('INFO', 'register_success', ['username' => $username, 'family_name' => $familyName]);
        respond(201, ['message' => 'Registrierung erfolgreich']);
    } catch (Throwable $e) {
        error_log("Register Exception: " . $e->getMessage());
        try {
            app_log('ERROR', 'register_failed', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
        } catch (Throwable $t) {
            error_log("Logging failed: " . $t->getMessage());
        }
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(500, ['error' => 'Fehler bei der Registrierung']);
    }
}

/**
 * Anmeldung eines bestehenden Benutzers. Erwartet JSON: { "username": String, "password": String }
 */
function handleLogin(PDO $pdo): void
{
    try {
        $data = getJsonBody();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        if ($username === '' || $password === '') {
            app_log('WARNING', 'login_validation_failed', ['username' => $username]);
            respond(400, ['error' => 'username und password sind erforderlich']);
        }
        $stmt = $pdo->prepare('SELECT id, family_id, username, password_hash, role FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            app_log('WARNING', 'login_failed', ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            logAction($pdo, null, 'login_fail', $username);
            respond(401, ['error' => 'Ungültige Anmeldedaten']);
        }

        // Login erfolgreich
        $_SESSION['user_id'] = $user['id'];
        session_regenerate_id(true);
        app_log('INFO', 'login_success', ['user_id' => $user['id'], 'username' => $username]);
        logAction($pdo, $user['id'], 'login_success');
        respond(200, ['message' => 'Anmeldung erfolgreich']);
    } catch (Throwable $e) {
        error_log("Login Exception: " . $e->getMessage());
        try {
            app_log('ERROR', 'login_exception', [
                'username' => $username ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        } catch (Throwable $t) {
            error_log("Logging failed: " . $t->getMessage());
        }
        respond(500, ['error' => 'Fehler bei der Anmeldung']);
    }
}

/**
 * Logout – beendet die Session.
 */
function handleLogout(): void
{
    $userId = $_SESSION['user_id'] ?? null;
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    app_log('INFO', 'logout', ['user_id' => $userId]);
    respond(204);
}

/**
 * Liefert aktuelle Benutzerdaten.
 */
function handleMe(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);

        // Badges des Benutzers laden
        $stmtBadges = $pdo->prepare(
            'SELECT b.name, b.description, b.icon_class FROM user_badges ub JOIN badges b ON ub.badge_id = b.id WHERE ub.user_id = ? ORDER BY b.required_streak ASC'
        );
        $stmtBadges->execute([$user['id']]);
        $badges = $stmtBadges->fetchAll();

        // Anzahl der Familienmitglieder ermitteln
        $stmtFamily = $pdo->prepare('SELECT COUNT(id) as count FROM users WHERE family_id = ?');
        $stmtFamily->execute([$user['family_id']]);
        $familyCount = $stmtFamily->fetchColumn();

        respond(200, [
            'id'        => (int)$user['id'],
            'username'  => $user['username'],
            'role'      => $user['role'],
            'family_id' => (int)$user['family_id'],
            'family_member_count' => (int)$familyCount,
            'points'    => (int)($user['points'] ?? 0),
            'streak_current' => (int)($user['streak_current'] ?? 0),
            'badges'    => $badges
        ]);
    } catch (Throwable $e) {
        app_log('ERROR', 'me_failed', [
            'error' => $e->getMessage()
        ]);
        respond(500, ['error' => 'Fehler beim Abrufen der Benutzerdaten: ' . $e->getMessage()]);
    }
}

/**
 * Gibt alle Benutzer der eigenen Familie zurück (nur für Parent).
 */
function handleUsersGet(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);
        $stmt = $pdo->prepare('SELECT id, username, role, created_at, gender, initial_weight FROM users WHERE family_id = ? ORDER BY created_at ASC');
        $stmt->execute([$user['family_id']]);
        $users = $stmt->fetchAll();
        respond(200, ['users' => $users]);
    } catch (Throwable $e) {
        app_log('ERROR', 'users_get_failed', [
            'error' => $e->getMessage()
        ]);
        respond(500, ['error' => 'Fehler beim Laden der Benutzer: ' . $e->getMessage()]);
    }
}

/**
 * Gibt einen einzelnen Benutzer der eigenen Familie zurück (nur für Parent/Adult).
 */
function handleUserGet(PDO $pdo, int $userId): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);

        // Prüfen, ob der angeforderte Benutzer zur Familie gehört
        $stmt = $pdo->prepare('SELECT id, username, role, created_at, gender, initial_weight FROM users WHERE id = ? AND family_id = ?');
        $stmt->execute([$userId, $user['family_id']]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            respond(404, ['error' => 'Benutzer nicht gefunden oder Zugriff verweigert']);
        }

        respond(200, ['user' => $targetUser]);

    } catch (Throwable $e) {
        app_log('ERROR', 'user_get_failed', [
            'error' => $e->getMessage(),
            'target_user_id' => $userId
        ]);
        respond(500, ['error' => 'Fehler beim Laden des Benutzers: ' . $e->getMessage()]);
    }
}
/**
 * Legt einen neuen Benutzer innerhalb der Familie an (nur Parent).
 * Erwartet JSON: { "username": String, "password": String, "role": 'child'|'teacher'|'adult', "gender"?: String, "initial_weight"?: Number }
 */
function handleUsersPost(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);
        $data = getJsonBody();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $role     = $data['role'] ?? '';
        $gender   = $data['gender'] ?? null;
        $initial_weight = (isset($data['initial_weight']) && $data['initial_weight'] !== '') ? $data['initial_weight'] : null;

        if ($username === '' || $password === '' || !in_array($role, ['child','teacher','adult'], true) || ($gender !== null && !in_array($gender, ['male', 'female', 'diverse', ''])) || ($initial_weight !== null && !is_numeric($initial_weight))) {
            app_log('WARNING', 'user_create_validation_failed', [
                'creator_id' => $user['id'],
                'username'   => $username,
                'role'       => $role,
                'gender'     => $gender
            ]);
            respond(400, ['error' => 'username, password, role sind erforderlich. gender/initial_weight sind ungültig.']);
        }
        // Prüfe Unique
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            app_log('WARNING', 'user_create_user_exists', ['creator_id' => $user['id'], 'username' => $username]);
            respond(409, ['error' => 'Benutzername existiert bereits']);
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (family_id, username, password_hash, role, gender, initial_weight) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['family_id'], $username, $passwordHash, $role, $gender ?: null, $initial_weight]);
        $newId = (int)$pdo->lastInsertId();
        app_log('INFO', 'user_create_success', ['creator_id' => $user['id'], 'new_user_id' => $newId, 'new_username' => $username, 'role' => $role]);
        logAction($pdo, $user['id'], 'user_create', 'new user ' . $username);
        respond(201, ['id' => $newId, 'username' => $username, 'role' => $role, 'gender' => $gender, 'initial_weight' => $initial_weight]);
    } catch (Throwable $e) {
        app_log('ERROR', 'user_create_failed', [
            'error' => $e->getMessage()
        ]);
        respond(500, ['error' => 'Fehler beim Erstellen des Benutzers: ' . $e->getMessage()]);
    }
}

/**
 * Aktualisiert einen Benutzer (nur Parent).
 * Erwartet JSON: { "username": String, "role": String, "password"?: String, "gender"?: String, "initial_weight"?: Number }
 */
function handleUsersPut(PDO $pdo, int $userId): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);

        $data = getJsonBody();
        $username = trim($data['username'] ?? '');
        $role = $data['role'] ?? '';
        $password = $data['password'] ?? '';
        $gender   = $data['gender'] ?? null;
        $initial_weight = (isset($data['initial_weight']) && $data['initial_weight'] !== '') ? $data['initial_weight'] : null;

        if ($username === '' || !in_array($role, ['child', 'teacher', 'adult'], true) || ($gender !== null && !in_array($gender, ['male', 'female', 'diverse', ''])) || ($initial_weight !== null && !is_numeric($initial_weight))) {
            respond(400, ['error' => 'username und role sind erforderlich. gender/initial_weight sind ungültig.']);
        }

        // Parent darf sich nicht selbst bearbeiten (Rolle ändern etc.)
        if ($userId === $user['id']) {
            app_log('WARNING', 'user_update_self_edit_forbidden', ['user_id' => $user['id']]);
            respond(403, ['error' => 'Sie können sich nicht selbst bearbeiten']);
        }
        
        // Prüfen, ob der zu bearbeitende Benutzer zur Familie gehört
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? AND family_id = ?');
        $stmt->execute([$userId, $user['family_id']]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            respond(404, ['error' => 'Benutzer nicht gefunden oder Zugriff verweigert']);
        }

        // Prüfen, ob der neue Benutzername bereits von einem anderen Benutzer verwendet wird
        if ($username !== $targetUser['username']) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                respond(409, ['error' => 'Benutzername existiert bereits']);
            }
        }
        
        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET username = ?, role = ?, password_hash = ?, gender = ?, initial_weight = ? WHERE id = ?');
            $stmt->execute([$username, $role, $passwordHash, $gender ?: null, $initial_weight, $userId]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET username = ?, role = ?, gender = ?, initial_weight = ? WHERE id = ?');
            $stmt->execute([$username, $role, $gender ?: null, $initial_weight, $userId]);
        }
        
        logAction($pdo, $user['id'], 'user_update', 'user ' . $userId);
        respond(200, ['id' => $userId, 'username' => $username, 'role' => $role, 'gender' => $gender, 'initial_weight' => $initial_weight]);

    } catch (Throwable $e) {
        app_log('ERROR', 'user_update_failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Aktualisieren des Benutzers: ' . $e->getMessage()]);
    }
}

/**
 * Löscht einen Benutzer (nur Parent).
 */
function handleUsersDelete(PDO $pdo, int $userId): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);

        if ($userId === (int)$user['id']) {
            app_log('WARNING', 'user_delete_self_delete_forbidden', ['user_id' => $user['id']]);
            respond(403, ['error' => 'Sie können sich nicht selbst löschen']);
        }

        // Prüfen, ob der zu löschende Benutzer zur Familie gehört
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
        $stmt->execute([$userId, $user['family_id']]);
        if (!$stmt->fetch()) {
            app_log('WARNING', 'user_delete_not_found_or_unauthorized', ['user_id' => $user['id'], 'delete_target_id' => $userId]);
            respond(404, ['error' => 'Benutzer nicht gefunden oder Zugriff verweigert']);
        }

        // Business Rule: Benutzer mit Einträgen dürfen nicht gelöscht werden
        $stmt = $pdo->prepare('SELECT id FROM entries WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            respond(409, ['error' => 'Benutzer kann nicht gelöscht werden, da Einträge vorhanden sind.']);
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);

        logAction($pdo, $user['id'], 'user_delete', 'user ' . $userId);
        respond(204);

    } catch (Throwable $e) {
        app_log('ERROR', 'user_delete_failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Löschen des Benutzers: ' . $e->getMessage()]);
    }
}

/**
 * Gibt alle Tags der Familie zurück.
 */
function handleTagsGet(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        $stmt = $pdo->prepare('SELECT id, name FROM tags WHERE family_id = ? ORDER BY name');
        $stmt->execute([$user['family_id']]);
        $tags = $stmt->fetchAll();
        respond(200, ['tags' => $tags]);
    } catch (Throwable $e) {
        app_log('ERROR', 'tags_get_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Laden der Tags']);
    }
}

/**
 * Erstellt einen neuen Tag.
 */
function handleTagsPost(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);
        $data = getJsonBody();
        $name = trim($data['name'] ?? '');
        
        if ($name === '') {
            respond(400, ['error' => 'Name ist erforderlich']);
        }

        $stmt = $pdo->prepare('INSERT INTO tags (family_id, name) VALUES (?, ?)');
        $stmt->execute([$user['family_id'], $name]);
        $newId = (int)$pdo->lastInsertId();
        
        respond(201, ['id' => $newId, 'name' => $name]);
    } catch (Throwable $e) {
        app_log('ERROR', 'tags_create_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Erstellen des Tags']);
    }
}

/**
 * Löscht einen Tag.
 */
function handleTagsDelete(PDO $pdo, int $tagId): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);
        
        $stmt = $pdo->prepare('DELETE FROM tags WHERE id = ? AND family_id = ?');
        $stmt->execute([$tagId, $user['family_id']]);
        respond(204);
    } catch (Throwable $e) {
        app_log('ERROR', 'tags_delete_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Löschen des Tags']);
    }
}

/**
 * Gibt alle Medikamente der eigenen Familie zurück (Parent, Child, Teacher, Adult).
 */
function handleMedicationsGet(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        $stmt = $pdo->prepare('SELECT id, name, default_dose FROM medications WHERE family_id = ? ORDER BY name');
        $stmt->execute([$user['family_id']]);
        $meds = $stmt->fetchAll();
        respond(200, ['medications' => $meds]);
    } catch (Throwable $e) {
        app_log('ERROR', 'medications_get_failed', [
            'error' => $e->getMessage()
        ]);
        respond(500, ['error' => 'Fehler beim Laden der Medikamente: ' . $e->getMessage()]);
    }
}

/**
 * Legt ein neues Medikament an (nur Parent). Erwartet JSON: { "name": String, "default_dose": String }
 */
function handleMedicationsPost(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);
        $data = getJsonBody();
        $name = trim($data['name'] ?? '');
        $defaultDose = trim($data['default_dose'] ?? '');
        if ($name === '') {
            app_log('WARNING', 'med_create_validation_failed', ['creator_id' => $user['id'], 'error' => 'name_missing']);
            respond(400, ['error' => 'name ist erforderlich']);
        }
        $stmt = $pdo->prepare('INSERT INTO medications (family_id, name, default_dose) VALUES (?, ?, ?)');
        $stmt->execute([$user['family_id'], $name, $defaultDose !== '' ? $defaultDose : null]);
        $newId = (int)$pdo->lastInsertId();
        app_log('INFO', 'med_create_success', ['creator_id' => $user['id'], 'med_id' => $newId, 'med_name' => $name]);
        logAction($pdo, $user['id'], 'med_create', 'medication ' . $name);
        respond(201, ['id' => $newId, 'name' => $name, 'default_dose' => $defaultDose]);
    } catch (Throwable $e) {
        app_log('ERROR', 'med_create_failed', [
            'error' => $e->getMessage()
        ]);
        respond(500, ['error' => 'Fehler beim Erstellen des Medikaments: ' . $e->getMessage()]);
    }
}

/**
 * Aktualisiert ein vorhandenes Medikament (nur Parent).
 * Erwartet JSON: { "name": String, "default_dose": String }
 */
function handleMedicationsPut(PDO $pdo, int $medId): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);
        
        $data = getJsonBody();
        $name = trim($data['name'] ?? '');
        $defaultDose = trim($data['default_dose'] ?? '');

        if ($name === '') {
            app_log('WARNING', 'med_update_validation_failed', ['user_id' => $user['id'], 'med_id' => $medId]);
            respond(400, ['error' => 'name ist erforderlich']);
        }

        // Prüfen, ob das Medikament zur Familie des Benutzers gehört
        $stmt = $pdo->prepare('SELECT id FROM medications WHERE id = ? AND family_id = ?');
        $stmt->execute([$medId, $user['family_id']]);
        if (!$stmt->fetch()) {
            app_log('WARNING', 'med_update_not_found_or_unauthorized', ['user_id' => $user['id'], 'med_id' => $medId]);
            respond(404, ['error' => 'Medikament nicht gefunden oder Zugriff verweigert']);
        }

        $stmt = $pdo->prepare('UPDATE medications SET name = ?, default_dose = ? WHERE id = ?');
        $stmt->execute([$name, $defaultDose !== '' ? $defaultDose : null, $medId]);

        app_log('INFO', 'med_update_success', ['user_id' => $user['id'], 'med_id' => $medId, 'new_name' => $name]);
        logAction($pdo, $user['id'], 'med_update', 'medication ' . $medId);
        
        respond(200, ['id' => $medId, 'name' => $name, 'default_dose' => $defaultDose]);
    } catch (Throwable $e) {
        app_log('ERROR', 'med_update_failed', ['med_id' => $medId, 'error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Aktualisieren des Medikaments: ' . $e->getMessage()]);
    }
}

/**
 * Löscht ein Medikament (nur Parent).
 */
function handleMedicationsDelete(PDO $pdo, int $medId): void
{
    try {
        $user = requireAuth($pdo);
        requireRole($user, ['parent', 'adult']);

        // Prüfen, ob das Medikament zur Familie des Benutzers gehört
        $stmt = $pdo->prepare('SELECT id FROM medications WHERE id = ? AND family_id = ?');
        $stmt->execute([$medId, $user['family_id']]);
        if (!$stmt->fetch()) {
            app_log('WARNING', 'med_delete_not_found_or_unauthorized', ['user_id' => $user['id'], 'med_id' => $medId]);
            respond(404, ['error' => 'Medikament nicht gefunden oder Zugriff verweigert']);
        }

        // Business Rule: Medikamente mit Einträgen dürfen nicht gelöscht werden
        $stmt = $pdo->prepare('SELECT id FROM entries WHERE medication_id = ? LIMIT 1');
        $stmt->execute([$medId]);
        if ($stmt->fetch()) {
            respond(409, ['error' => 'Medikament kann nicht gelöscht werden, da es in Einträgen verwendet wird.']);
        }

        $stmt = $pdo->prepare('DELETE FROM medications WHERE id = ?');
        $stmt->execute([$medId]);

        app_log('INFO', 'med_delete_success', ['user_id' => $user['id'], 'med_id' => $medId]);
        logAction($pdo, $user['id'], 'med_delete', 'medication ' . $medId);

        respond(204);
    } catch (Throwable $e) {
        app_log('ERROR', 'med_delete_failed', ['med_id' => $medId, 'error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Löschen des Medikaments: ' . $e->getMessage()]);
    }
}

/**
 * Erstellt einen neuen Eintrag (alle Rollen außer Teacher schreiben nur für sich selbst).
 * Teacher kann optional ein child_id angeben.
 * Erwartet JSON mit Feldern: date, time, medication_id, dose, sleep, hyperactivity,
 * mood, irritability, appetite, focus, other_effects, side_effects,
 * special_events, menstruation_phase, teacher_feedback, emotional_reactions,
 * optional child_id
 */
function handleEntriesPost(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        $data = getJsonBody();
        // Bestimme für wen der Eintrag gilt
        $targetUserId = (int)$user['id'];
        
        // Eltern/Erwachsene dürfen Einträge für Familienmitglieder bearbeiten/erstellen
        if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($data['target_user_id'])) {
            $tId = (int)$data['target_user_id'];
            // Prüfen, ob Ziel-User zur Familie gehört
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
            $stmt->execute([$tId, $user['family_id']]);
            if (!$stmt->fetch()) {
                respond(403, ['error' => 'Zugriff auf diesen Benutzer verweigert']);
            }
            $targetUserId = $tId;
        } elseif ($user['role'] === 'teacher') {
            if (empty($data['child_id'])) {
                app_log('WARNING', 'entry_create_validation_failed', ['user_id' => $user['id'], 'error' => 'child_id_missing_for_teacher']);
                respond(400, ['error' => 'child_id ist erforderlich für teacher']);
            }
            // Prüfen, ob das Kind zur gleichen Familie gehört
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ? AND role = ?');
            $stmt->execute([(int)$data['child_id'], $user['family_id'], 'child']);
            $child = $stmt->fetch();
            if (!$child) {
                app_log('WARNING', 'entry_create_invalid_child', ['user_id' => $user['id'], 'child_id' => $data['child_id']]);
                respond(403, ['error' => 'Ungültiges Kind für diesen Lehrer']);
            }
            $targetUserId = (int)$data['child_id'];
        }
        // Validierung & Normalisierung
        try {
            $date = EntryPayload::normalizeDate($data['date'] ?? '');
        } catch (InvalidArgumentException $e) {
            app_log('WARNING', 'entry_create_validation_failed', [
                'user_id'   => $user['id'],
                'error'     => 'invalid_date',
                'date_val'  => $data['date'] ?? null,
                'message'   => $e->getMessage(),
            ]);
            respond(400, ['error' => $e->getMessage()]);
        }

        try {
            $time = EntryPayload::normalizeTime($data['time'] ?? '');
        } catch (InvalidArgumentException $e) {
            app_log('WARNING', 'entry_create_validation_failed', ['user_id' => $user['id'], 'error' => 'invalid_time', 'time_val' => $data['time'] ?? null]);
            respond(400, ['error' => $e->getMessage()]);
        }
        // Prüfe max zwei Einträge pro Tag (unique index)
        try {
            // Upsert-Statement: Aktualisiert den Eintrag, falls er schon existiert (Business Rule: Laden & Speichern)
            $sql = 'INSERT INTO entries (user_id, medication_id, dose, date, time, sleep, hyperactivity, mood, irritability, appetite, focus, weight, other_effects, side_effects, special_events, menstruation_phase, teacher_feedback, emotional_reactions) 
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE 
                    id=LAST_INSERT_ID(id), medication_id=VALUES(medication_id), dose=VALUES(dose), sleep=VALUES(sleep), hyperactivity=VALUES(hyperactivity), mood=VALUES(mood), irritability=VALUES(irritability), appetite=VALUES(appetite), focus=VALUES(focus), weight=VALUES(weight), other_effects=VALUES(other_effects), side_effects=VALUES(side_effects), special_events=VALUES(special_events), menstruation_phase=VALUES(menstruation_phase), teacher_feedback=VALUES(teacher_feedback), emotional_reactions=VALUES(emotional_reactions)';
            
            $stmt = $pdo->prepare($sql);
            
            // Wenn bereits ein Eintrag mit Schlaf existiert, überschreibe sleep mit NULL
            $sleep = EntryPayload::intOrNull($data['sleep'] ?? null);
            
            // Prüfe existierende Schlafdaten für den Tag, aber ignoriere den aktuellen Slot (wichtig für Updates!)
            $check = $pdo->prepare('SELECT id FROM entries WHERE user_id = ? AND date = ? AND sleep IS NOT NULL AND time != ? LIMIT 1');
            $check->execute([$targetUserId, $date, $time]);
            if ($check->fetch()) {
                $sleep = null;
                app_log('INFO', 'entry_create_sleep_skipped', ['user_id' => $targetUserId, 'date' => $date, 'reason' => 'sleep_already_exists_other_slot']);
            }
            
            // Medication ID behandeln (0 vermeiden wegen FK)
            $medId = EntryPayload::normalizeMedicationId($data['medication_id'] ?? null);

            // Parameter vorbereiten
            $params = [
                $targetUserId,
                $medId,
                $data['dose'] ?? null,
                $date,
                $time,
                $sleep,
                EntryPayload::intOrNull($data['hyperactivity'] ?? null),
                EntryPayload::intOrNull($data['mood'] ?? null),
                EntryPayload::intOrNull($data['irritability'] ?? null),
                EntryPayload::intOrNull($data['appetite'] ?? null),
                EntryPayload::intOrNull($data['focus'] ?? null),
                EntryPayload::decimalOrNull($data['weight'] ?? null),
                $data['other_effects'] ?? null,
                $data['side_effects'] ?? null,
                $data['special_events'] ?? null,
                $data['menstruation_phase'] ?? null,
                $data['teacher_feedback'] ?? null,
                $data['emotional_reactions'] ?? null
            ];

            $stmt->execute($params);
            $entryId = (int)$pdo->lastInsertId();
            // Tags speichern

            if ($entryId) {
                // 2. Alte Tags entfernen
                $pdo->prepare('DELETE FROM entry_tags WHERE entry_id = ?')->execute([$entryId]);
                
                // 3. Neue Tags einfügen
                if (!empty($data['tags']) && is_array($data['tags'])) {
                    $stmtTag = $pdo->prepare('INSERT IGNORE INTO entry_tags (entry_id, tag_id) VALUES (?, ?)');
                    foreach ($data['tags'] as $tagId) $stmtTag->execute([$entryId, (int)$tagId]);
                }
            }
        } catch (PDOException $e) {
            app_log('ERROR', 'entry_create_failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            // Einzigartigkeitsverletzung
            if ((int)$e->getCode() === 23000) {
                app_log('WARNING', 'entry_create_duplicate', ['user_id' => $targetUserId, 'date' => $date, 'time' => $time]);
                respond(409, ['error' => 'Für diesen Zeitpunkt existiert bereits ein Eintrag']);
            }
            throw $e; // Weiterwerfen, um im äußeren Catch gefangen zu werden (oder hier exit)
            // Da respond() exit aufruft, ist throw hier eigentlich nicht nötig, aber sicherheitshalber:
            respond(500, ['error' => 'Fehler beim Speichern des Eintrags: ' . $e->getMessage()]);
        }

        // Gamification: Punkte und Streak aktualisieren (nur für Kinder)
        $pointsEarned = 0;
        $newStreak = 0;
        $newTotalPoints = 0;
        $newlyEarnedBadges = [];
        $nextBadge = null;

        // Wir laden den User neu, um aktuelle Stats zu haben (falls targetUserId != currentUser)
        $stmtUser = $pdo->prepare('SELECT role, points, streak_current, last_entry_date FROM users WHERE id = ?');
        $stmtUser->execute([$targetUserId]);
        $tUser = $stmtUser->fetch();

        if ($tUser && $tUser['role'] === 'child') {
            $today = date('Y-m-d');
            $lastDate = $tUser['last_entry_date'];
            $currentStreak = (int)$tUser['streak_current'];
            $currentPoints = (int)$tUser['points'];

            // Punktelogik: 10 Punkte pro Eintrag
            $pointsEarned = 10;
            $newTotalPoints = $currentPoints + $pointsEarned;

            // Streaklogik: Nur einmal pro Tag aktualisieren
            if ($lastDate !== $today) {
                if ($lastDate === date('Y-m-d', strtotime('-1 day'))) {
                    // Gestern war letzter Eintrag -> Streak +1
                    $newStreak = $currentStreak + 1;
                } else {
                    // Lücke > 1 Tag oder erster Eintrag -> Reset auf 1
                    $newStreak = 1;
                }
                // Update DB
                $stmtUpd = $pdo->prepare('UPDATE users SET points = ?, streak_current = ?, last_entry_date = ? WHERE id = ?');
                $stmtUpd->execute([$newTotalPoints, $newStreak, $today, $targetUserId]);
            } else {
                // Heute schon eingetragen -> Streak bleibt, Punkte addieren
                $newStreak = $currentStreak;
                $stmtUpd = $pdo->prepare('UPDATE users SET points = ? WHERE id = ?');
                $stmtUpd->execute([$newTotalPoints, $targetUserId]);
            }

            // Badge-Logik: Prüfe, ob neue Badges verdient wurden
            if ($newStreak > 0) {
                $stmtCheckBadges = $pdo->prepare(
                    'SELECT b.id, b.name, b.description, b.icon_class FROM badges b 
                     LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
                     WHERE b.required_streak <= ? AND ub.id IS NULL'
                );
                $stmtCheckBadges->execute([$targetUserId, $newStreak]);
                $earnableBadges = $stmtCheckBadges->fetchAll();

                $stmtAward = $pdo->prepare('INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)');
                foreach ($earnableBadges as $badge) {
                    $stmtAward->execute([$targetUserId, $badge['id']]);
                    $newlyEarnedBadges[] = $badge;
                }
            }

            // --- Spezial-Badges Logik (Wochenende, Tageszeit) ---
            $specialBadgeNames = [];
            
            // Wochenende prüfen (Samstag = 6, Sonntag = 7)
            if (date('N', strtotime($date)) >= 6) {
                $specialBadgeNames[] = 'Wochenend-Warrior';
            }
            
            // Tageszeit prüfen
            if ($time === 'morning') $specialBadgeNames[] = 'Früher Vogel';
            if ($time === 'evening') $specialBadgeNames[] = 'Nachteule';

            if (!empty($specialBadgeNames)) {
                $inQuery = implode(',', array_fill(0, count($specialBadgeNames), '?'));
                // Nur Badges laden, die keinen Streak erfordern (required_streak IS NULL)
                $stmtSpecial = $pdo->prepare("SELECT id, name, description, icon_class FROM badges WHERE name IN ($inQuery) AND required_streak IS NULL");
                $stmtSpecial->execute($specialBadgeNames);
                $potBadges = $stmtSpecial->fetchAll();

                foreach ($potBadges as $pBadge) {
                    // Prüfen, ob User das Badge schon hat
                    $stmtHas = $pdo->prepare('SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?');
                    $stmtHas->execute([$targetUserId, $pBadge['id']]);
                    if (!$stmtHas->fetch()) {
                        $stmtAward = $pdo->prepare('INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)');
                        $stmtAward->execute([$targetUserId, $pBadge['id']]);
                        $newlyEarnedBadges[] = $pBadge;
                    }
                }
            }

            // Nächstes Ziel ermitteln (für Fortschrittsbalken im Frontend)
            $stmtNext = $pdo->prepare('SELECT name, required_streak, icon_class FROM badges WHERE required_streak > ? ORDER BY required_streak ASC LIMIT 1');
            $stmtNext->execute([$newStreak]);
            $nextBadgeData = $stmtNext->fetch();
            if ($nextBadgeData) {
                $nextBadge = [
                    'name' => $nextBadgeData['name'],
                    'required_streak' => (int)$nextBadgeData['required_streak'],
                    'days_left' => (int)$nextBadgeData['required_streak'] - $newStreak
                ];
            }
        }

        app_log('INFO', 'entry_create_success', ['creator_id' => $user['id'], 'target_user_id' => $targetUserId, 'date' => $date, 'time' => $time]);
        logAction($pdo, $user['id'], 'entry_create', 'entry for user ' . $targetUserId);
        
        respond(201, [
            'message' => 'Eintrag gespeichert',
            'gamification' => ($tUser && $tUser['role'] === 'child') ? [
                'points_earned' => $pointsEarned,
                'total_points' => $newTotalPoints,
                'streak' => $newStreak,
                'new_badges' => $newlyEarnedBadges,
                'next_badge' => $nextBadge
            ] : null
        ]);
    } catch (Throwable $e) {
        // Falls der Fehler noch nicht behandelt wurde (z.B. durch respond() im inneren Block)
        app_log('ERROR', 'entry_create_exception', [
            'error' => $e->getMessage()
        ]);
        respond(500, ['error' => 'Fehler beim Speichern des Eintrags: ' . $e->getMessage()]);
    }
}

/**
 * Löscht einen Eintrag.
 */
function handleEntriesDelete(PDO $pdo, int $entryId): void
{
    try {
        $user = requireAuth($pdo);
        
        // Prüfen, ob der Eintrag existiert und dem User (oder seiner Familie) gehört
        $stmt = $pdo->prepare('SELECT e.user_id, u.family_id FROM entries e JOIN users u ON e.user_id = u.id WHERE e.id = ?');
        $stmt->execute([$entryId]);
        $entryData = $stmt->fetch();

        if (!$entryData) {
            respond(404, ['error' => 'Eintrag nicht gefunden']);
        }

        // Berechtigungsprüfung: Nur eigener Eintrag oder Parent der gleichen Familie
        if ($user['role'] !== 'parent' && $user['role'] !== 'adult' && $entryData['user_id'] !== $user['id']) {
            respond(403, ['error' => 'Zugriff verweigert']);
        }
        if (($user['role'] === 'parent' || $user['role'] === 'adult') && $entryData['family_id'] !== $user['family_id']) {
            respond(403, ['error' => 'Zugriff verweigert']);
        }
        
        $stmt = $pdo->prepare('DELETE FROM entries WHERE id = ?');
        $stmt->execute([$entryId]);
        
        logAction($pdo, $user['id'], 'entry_delete', 'entry ' . $entryId);
        respond(204);
    } catch (Throwable $e) {
        app_log('ERROR', 'entry_delete_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Löschen des Eintrags']);
    }
}

/**
 * Gibt Einträge zurück. Child/Adult sehen nur eigene, Parent kann über Parameter user_id alle in der Familie sehen.
 * Optional: date_from, date_to im Format YYYY-MM-DD.
 */
function handleEntriesGet(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        if ($user['role'] === 'teacher') {
            app_log('WARNING', 'entries_get_denied_for_teacher', ['user_id' => $user['id']]);
            respond(403, ['error' => 'Lehrer dürfen keine Einträge einsehen']);
        }
        $params = $_GET;
        $targetUserId = (int)$user['id'];
        if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($params['user_id'])) {
            // nur erlauben, wenn der Benutzer in derselben Familie ist
            $uid = (int)$params['user_id'];
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
            $stmt->execute([$uid, $user['family_id']]);
            if ($stmt->fetch()) {
                $targetUserId = $uid;
            } else {
                app_log('WARNING', 'entries_get_access_denied', [
                    'reason'          => 'target_user_not_in_family',
                    'requesting_user' => $user['id'],
                    'target_user'     => $uid
                ]);
            }
        }
        $dateFrom = $params['date_from'] ?? null;
        $dateTo   = $params['date_to'] ?? null;
        $timeSlot = $params['time'] ?? null;
        $limit    = isset($params['limit']) ? (int)$params['limit'] : null;
        $sql = 'SELECT e.*, m.name AS medication_name, u.username, GROUP_CONCAT(t.name SEPARATOR ", ") as tags, GROUP_CONCAT(t.id) as tag_ids
                FROM entries e 
                LEFT JOIN medications m ON e.medication_id = m.id 
                LEFT JOIN users u ON e.user_id = u.id 
                LEFT JOIN entry_tags et ON e.id = et.entry_id
                LEFT JOIN tags t ON et.tag_id = t.id
                WHERE e.user_id = ?';
        $bindings = [$targetUserId];
        if ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $sql .= ' AND e.date >= ?';
            $bindings[] = $dateFrom;
        }
        if ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $sql .= ' AND e.date <= ?';
            $bindings[] = $dateTo;
        }
        if ($timeSlot && in_array($timeSlot, ['morning','noon','evening'], true)) {
            $sql .= ' AND e.time = ?';
            $bindings[] = $timeSlot;
        }
        $sql .= ' GROUP BY e.id';
        $sql .= ' ORDER BY e.date DESC, FIELD(e.time, "morning","noon","evening")';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        app_log('INFO', 'entries_get', [
            'user_id'        => $user['id'],
            'target_user_id' => $targetUserId,
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo
        ]);
        $entries = $stmt->fetchAll();
        respond(200, ['entries' => $entries]);
    } catch (Throwable $e) {
        app_log('ERROR', 'entries_get_failed', [
            'error' => $e->getMessage()
        ]);
        respond(500, ['error' => 'Fehler beim Laden der Einträge: ' . $e->getMessage()]);
    }
}

/**
 * Gibt alle verfügbaren Badges und den Fortschritt des aktuellen Benutzers zurück.
 */
function handleBadgesGet(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);

        // 1. Alle existierenden Badges aus der DB holen
        $stmtAll = $pdo->prepare('SELECT id, name, description, required_streak, icon_class FROM badges ORDER BY required_streak ASC');
        $stmtAll->execute();
        $allBadges = $stmtAll->fetchAll();

        // 2. IDs der vom User bereits verdienten Badges holen
        $stmtEarned = $pdo->prepare('SELECT badge_id FROM user_badges WHERE user_id = ?');
        $stmtEarned->execute([$user['id']]);
        // Wandelt das Ergebnis in ein assoziatives Array um (z.B. [3 => true, 7 => true]) für schnellen Zugriff
        $earnedBadgeIds = array_flip($stmtEarned->fetchAll(PDO::FETCH_COLUMN));

        // 3. Daten kombinieren: Jedem Badge die Info "verdient: ja/nein" hinzufügen
        foreach ($allBadges as &$badge) {
            $badge['earned'] = isset($earnedBadgeIds[$badge['id']]);
        }

        // 4. Antwort mit allen Badges und dem aktuellen Streak des Users senden
        respond(200, [
            'badges' => $allBadges,
            'current_streak' => (int)$user['streak_current']
        ]);

    } catch (Throwable $e) {
        app_log('ERROR', 'badges_get_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Laden der Abzeichen: ' . $e->getMessage()]);
    }
}

/**
 * Gibt den Gewichtsverlauf für einen Benutzer zurück.
 * Akzeptiert user_id, date_from, date_to als GET-Parameter.
 */
function handleWeightGet(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        if ($user['role'] === 'teacher') {
            respond(403, ['error' => 'Lehrer dürfen keine Gewichtsdaten einsehen']);
        }

        $params = $_GET;
        $targetUserId = (int)$user['id'];

        if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($params['user_id'])) {
            $uid = (int)$params['user_id'];
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
            $stmt->execute([$uid, $user['family_id']]);
            if ($stmt->fetch()) {
                $targetUserId = $uid;
            }
        }

        $dateFrom = $params['date_from'] ?? null;
        $dateTo   = $params['date_to'] ?? null;

        $sql = 'SELECT weight, date FROM entries WHERE user_id = ? AND weight IS NOT NULL';
        $bindings = [$targetUserId];

        if ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $sql .= ' AND date >= ?';
            $bindings[] = $dateFrom;
        }
        if ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $sql .= ' AND date <= ?';
            $bindings[] = $dateTo;
        }

        $sql .= ' ORDER BY date ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $weights = $stmt->fetchAll();

        respond(200, ['weights' => $weights]);

    } catch (Throwable $e) {
        app_log('ERROR', 'weight_get_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Laden der Gewichtsdaten: ' . $e->getMessage()]);
    }
}

/**
 * Ermöglicht dem aktuell angemeldeten Benutzer, sein Passwort zu ändern.
 */
function handleMyPasswordPost(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        $data = getJsonBody();

        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            respond(400, ['error' => 'Alle Felder sind erforderlich.']);
        }

        if ($newPassword !== $confirmPassword) {
            respond(400, ['error' => 'Das neue Passwort stimmt nicht mit der Bestätigung überein.']);
        }
        
        if (strlen($newPassword) < 6) { // Basic validation
             respond(400, ['error' => 'Das neue Passwort muss mindestens 6 Zeichen lang sein.']);
        }

        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            app_log('WARNING', 'password_change_fail', ['user_id' => $user['id'], 'reason' => 'wrong_current_password']);
            respond(403, ['error' => 'Das aktuelle Passwort ist nicht korrekt.']);
        }

        // Hash and update new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newPasswordHash, $user['id']]);

        logAction($pdo, $user['id'], 'password_change_success');
        app_log('INFO', 'password_change_success', ['user_id' => $user['id']]);
        
        respond(200, ['message' => 'Passwort erfolgreich geändert.']);

    } catch (Throwable $e) {
        app_log('ERROR', 'password_change_exception', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()]);
    }
}

/**
 * Gibt das letzte eingetragene Gewicht oder das Initialgewicht zurück.
 */
function handleMyLatestWeightGet(PDO $pdo): void
{
    try {
        $user = requireAuth($pdo);
        $stmt = $pdo->prepare('SELECT weight FROM entries WHERE user_id = ? AND weight IS NOT NULL ORDER BY date DESC, id DESC LIMIT 1');
        $stmt->execute([$user['id']]);
        $latestEntry = $stmt->fetch();
        $weight = $latestEntry['weight'] ?? $user['initial_weight'] ?? null;
        respond(200, ['weight' => $weight]);
    } catch (Throwable $e) {
        app_log('ERROR', 'latest_weight_get_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Laden des Gewichts.']);
    }
}

/**
 * Führt Datenbank-Migrationen aus. Benötigt einen gültigen Authorization-Token.
 * POST /admin/migrate
 * Header: Authorization: Bearer MIGRATION_TOKEN
 * Body: { "reset": false, "seed": false }
 *   - reset: Leert die Datenbank komplett (nur dev!)
 *   - seed: Lädt Test-Datensätze (nur dev!)
 */
function handleAdminMigrate(PDO $pdo): void
{
    try {
        // Verifiziere Authorization-Token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $expectedToken = $GLOBALS['MIGRATION_TOKEN'] ?? null;
        
        if (!$expectedToken) {
            app_log('ERROR', 'migration_no_token_configured', []);
            respond(500, ['error' => 'Migration token nicht konfiguriert']);
        }
        
        if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            app_log('WARNING', 'migration_missing_token', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            respond(401, ['error' => 'Authorization token erforderlich']);
        }
        
        if ($matches[1] !== $expectedToken) {
            app_log('WARNING', 'migration_invalid_token', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            respond(403, ['error' => 'Ungültiger token']);
        }

        $data = getJsonBody();
        $reset = $data['reset'] ?? false;
        $seed = $data['seed'] ?? false;

        // Warnung vor Reset
        if ($reset) {
            app_log('WARNING', 'migration_reset_requested', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        }

        $migrationResults = [];
        
        // 1. Reset: Leere alle Tabellen in korrekter Reihenfolge
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
                    $pdo->exec("TRUNCATE TABLE $table");
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
                @$pdo->exec($sql);
                $migrationResults["index_" . substr($sql, 21, 20)] = 'ok';
            } catch (Throwable $e) {
                // Index existiert wahrscheinlich bereits
                $migrationResults["index_" . substr($sql, 21, 20)] = 'exists';
            }
        }

        // 3. Seed: Lade Test-Datensätze
        if ($seed) {
            $seedFile = __DIR__ . '/../db/seed.sql';
            if (!is_file($seedFile)) {
                $migrationResults['seed'] = 'error: seed.sql not found';
            } else {
                try {
                    $seedSql = file_get_contents($seedFile);
                    // Führe Seed SQL aus (kann mehrere Statements enthalten)
                    $pdo->exec($seedSql);
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

        respond(200, [
            'message' => 'Migrationen erfolgreich ausgeführt',
            'reset' => $reset,
            'seed' => $seed,
            'migrations' => $migrationResults
        ]);

    } catch (Throwable $e) {
        app_log('ERROR', 'migration_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler bei der Migration: ' . $e->getMessage()]);
    }
}

/**
 * Erstellt ein SQL-Backup der Datenbank.
 * POST /admin/backup
 * Header: Authorization: Bearer BACKUP_TOKEN
 */
function handleAdminBackup(PDO $pdo): void
{
    try {
        // Verifiziere Authorization-Token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $expectedToken = $GLOBALS['BACKUP_TOKEN'] ?? null;
        
        if (!$expectedToken) {
            app_log('WARNING', 'backup_no_token_configured', []);
            respond(500, ['error' => 'Backup token nicht konfiguriert']);
        }
        
        if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            app_log('WARNING', 'backup_missing_token', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            respond(401, ['error' => 'Authorization token erforderlich']);
        }
        
        if ($matches[1] !== $expectedToken) {
            app_log('WARNING', 'backup_invalid_token', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            respond(403, ['error' => 'Ungültiger token']);
        }

        // Erstelle Backup-Verzeichnis
        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Hole Datenbank-Infos
        $stmt = $pdo->prepare("SELECT DATABASE()");
        $stmt->execute();
        $dbName = $stmt->fetchColumn();

        // Erstelle Backup-Dateiname
        $timestamp = date('YmdHis');
        $backupFile = $backupDir . "/backup_{$dbName}_{$timestamp}.sql";

        // Hole alle Datenbank-Inhalte
        try {
            // Starte Transaction für konsistentes Backup
            $pdo->beginTransaction();
            
            // Hole alle Tabellennamen
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            $sqlBackup = "-- FokusLog Database Backup\n";
            $sqlBackup .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
            $sqlBackup .= "-- Database: $dbName\n\n";
            $sqlBackup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // CREATE TABLE Statement
                $createStmt = $pdo->query("SHOW CREATE TABLE $table");
                $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
                $sqlBackup .= $createRow['Create Table'] . ";\n\n";
                
                // INSERT DATA
                $dataStmt = $pdo->query("SELECT * FROM $table");
                while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols = implode(', ', array_keys($row));
                    $vals = implode(', ', array_map(function($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote($v);
                    }, array_values($row)));
                    $sqlBackup .= "INSERT INTO $table ($cols) VALUES ($vals);\n";
                }
                $sqlBackup .= "\n";
            }
            
            $sqlBackup .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            $pdo->commit();
            
            // Schreibe Backup-Datei
            if (!file_put_contents($backupFile, $sqlBackup)) {
                throw new Exception("Konnte Backup-Datei nicht schreiben: $backupFile");
            }

            // Komprimiere
            $compressedFile = $backupFile . '.gz';
            if (!function_exists('gzencode')) {
                // Fallback: nutze exec wenn gzencode nicht verfügbar
                @exec("gzip '$backupFile'");
                if (!is_file($compressedFile)) {
                    // Wenn gzip fehlschlägt, behalte unkomprimiert
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

            respond(200, [
                'message' => 'Backup erfolgreich erstellt',
                'filename' => basename($backupFile),
                'size' => $fileSize,
                'timestamp' => $timestamp
            ]);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

    } catch (Throwable $e) {
        app_log('ERROR', 'backup_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Backup: ' . $e->getMessage()]);
    }

}

/**
 * Gibt das Glossar/Lexikon zurück (Öffentlich).
 */
function handleGlossaryGet(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare('SELECT slug, title, content, link, category FROM glossary ORDER BY title ASC');
        $stmt->execute();
        $entries = $stmt->fetchAll();
        respond(200, ['glossary' => $entries]);
    } catch (Throwable $e) {
        app_log('ERROR', 'glossary_get_failed', ['error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Laden des Lexikons']);
    }
}

/**
 * Gibt einen einzelnen Glossar-Eintrag mit vollem Inhalt zurück.
 */
function handleGlossaryEntryGet(PDO $pdo, string $slug): void
{
    try {
        $stmt = $pdo->prepare('SELECT slug, title, content, full_content, link, category FROM glossary WHERE slug = ?');
        $stmt->execute([$slug]);
        $entry = $stmt->fetch();

        if (!$entry) {
            respond(404, ['error' => 'Eintrag nicht gefunden']);
        }
        respond(200, ['entry' => $entry]);
    } catch (Throwable $e) {
        app_log('ERROR', 'glossary_entry_get_failed', ['slug' => $slug, 'error' => $e->getMessage()]);
        respond(500, ['error' => 'Fehler beim Laden des Eintrags']);
    }
}