<?php
require_once __DIR__ . '/ApiTest.php';

// Konfiguration: URL deiner lokalen API
// Wenn du den Server mit "php -S localhost:8000" im Hauptordner startest,
// ist die URL meist http://localhost:8000/api
$apiUrl = getenv('API_URL') ?: 'https://10.35.249.140/api';

echo "Führe Tests gegen $apiUrl aus...\n\n";

// Prüfen, ob Server erreichbar ist
if (@file_get_contents($apiUrl) === false && strpos($http_response_header[0] ?? '', '404') === false) {
    die("Fehler: API unter $apiUrl nicht erreichbar. Bitte starte den PHP-Server (z.B. 'php -S localhost:8000' im Projektordner).\n");
}

$runner = new SimpleTestRunner();
$test = new ApiTest($apiUrl);
$runner->run($test);