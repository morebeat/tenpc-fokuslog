<?php
// Development router for the PHP built-in server.
if (PHP_SAPI !== 'cli-server') {
    return false;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = __DIR__ . $uri;

// Serve existing static files (HTML, JS, CSS, assets) directly.
if ($uri !== '/' && is_file($path)) {
    return false;
}

// Route API calls to api/index.php regardless of path depth.
if (strpos($uri, '/api') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/api/index.php';
    chdir(__DIR__ . '/api');
    require __DIR__ . '/api/index.php';
    return true;
}

// Fallback: serve the PWA entry point so deep-links work locally.
require __DIR__ . '/app/index.html';
return true;
