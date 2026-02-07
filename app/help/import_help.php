<?php
/**
 * Import-Skript für FokusLog Hilfe-Seiten
 * 
 * Liest app/help/index.html und die verlinkten Dateien aus,
 * extrahiert Titel, Lead-Text und Kategorie und speichert sie in der DB.
 * 
 * Ausführen via CLI: php scripts/import_help.php
 */

// Fehler anzeigen
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "Starte Import der Hilfe-Inhalte...\n";

// 1. Umgebungsvariablen laden
$envFile = __DIR__ . '/../../.env';
if (!file_exists($envFile)) {
    die("Fehler: .env Datei nicht gefunden unter $envFile\n");
}
$env = parse_ini_file($envFile);

// 2. Datenbank verbinden
try {
    $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Datenbankverbindung hergestellt.\n";
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage() . "\n");
}

// 3. index.html parsen (für Struktur & Kategorien)
$indexFile = __DIR__ . '/index.html';
if (!file_exists($indexFile)) {
    die("Fehler: index.html nicht gefunden unter $indexFile\n");
}

$doc = new DOMDocument();
// Warnungen bei HTML5-Tags unterdrücken
libxml_use_internal_errors(true);
$doc->loadHTMLFile($indexFile);
libxml_clear_errors();

$xpath = new DOMXPath($doc);

// Alle Links in der Baumstruktur finden
$links = $xpath->query('//ul[@class="tree-links"]//a');
echo "Gefundene Links: " . $links->length . "\n";

$count = 0;
$processedSlugs = [];

foreach ($links as $link) {
    $href = $link->getAttribute('href');
    $titleInIndex = trim($link->textContent);
    
    // Externe Links überspringen
    if (strpos($href, 'http') === 0) continue;

    // Kategorie ermitteln (Eltern-Element <details class="tree-level"> suchen)
    $category = 'Allgemein';
    $p = $link->parentNode;
    while ($p) {
        if ($p->nodeName === 'details') {
            $class = $p->getAttribute('class');
            if (strpos($class, 'tree-level') !== false) {
                // Summary finden
                foreach ($p->childNodes as $child) {
                    if ($child->nodeName === 'summary') {
                        // Emojis entfernen für saubere Kategorie-Namen
                        $rawCat = $child->textContent;
                        $category = trim(str_replace(['📱', '🧭', '📚', '🛡️', '👩‍⚕️', '📈', '📊', '👥', '🚀', '🤝', '🧘', '🏠'], '', $rawCat));
                        break 2;
                    }
                }
            }
        }
        $p = $p->parentNode;
    }

    // 4. Ziel-Datei parsen
    $filePath = __DIR__ . '/' . $href;
    if (!file_exists($filePath)) {
        echo "Warnung: Datei $filePath nicht gefunden.\n";
        continue;
    }

    $fileDoc = new DOMDocument();
    libxml_use_internal_errors(true);
    $fileDoc->loadHTMLFile($filePath);
    libxml_clear_errors();
    $fileXpath = new DOMXPath($fileDoc);

    // Titel aus h1 holen (oder Fallback auf Index-Titel)
    $h1 = $fileDoc->getElementsByTagName('h1')->item(0);
    $title = $h1 ? trim($h1->textContent) : $titleInIndex;
    // Emojis im Titel entfernen (optional, hier lassen wir sie drin oder entfernen sie)
    // $title = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $title);

    // Inhalt: Lead-Text oder erster Paragraph
    $content = '';
    $lead = $fileXpath->query('//*[contains(@class, "lead")]')->item(0);
    if ($lead) {
        $content = trim($lead->textContent);
    } else {
        $p = $fileXpath->query('//main//p')->item(0);
        if ($p) $content = trim($p->textContent);
    }

    // NEU: Vollen HTML-Inhalt extrahieren (Inner HTML von <main>)
    $fullContent = '';
    $mainNode = $fileDoc->getElementsByTagName('main')->item(0);
    if ($mainNode) {
        foreach ($mainNode->childNodes as $child) {
            $fullContent .= $fileDoc->saveHTML($child);
        }
    }
    $fullContent = trim($fullContent);

    $slug = pathinfo($href, PATHINFO_FILENAME);
    $linkUrl = 'help/' . $href;

    // 5. In DB speichern (Update wenn vorhanden)
    $stmt = $pdo->prepare("
        INSERT INTO glossary (slug, title, content, full_content, link, category) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        title = VALUES(title), 
        content = VALUES(content), 
        full_content = VALUES(full_content),
        link = VALUES(link), 
        category = VALUES(category)
    ");
    
    $stmt->execute([$slug, $title, $content, $fullContent, $linkUrl, $category]);
    echo "Importiert: $title ($category)\n";
    $processedSlugs[] = $slug;
    $count++;
}

// 6. Veraltete Einträge löschen (Cleanup)
if (!empty($processedSlugs)) {
    // Erstelle Platzhalter für das SQL-Statement (?,?,?...)
    $placeholders = implode(',', array_fill(0, count($processedSlugs), '?'));
    $stmtDelete = $pdo->prepare("DELETE FROM glossary WHERE slug NOT IN ($placeholders)");
    $stmtDelete->execute($processedSlugs);
    $deleted = $stmtDelete->rowCount();
    if ($deleted > 0) {
        echo "Bereinigt: $deleted veraltete Einträge aus der Datenbank entfernt.\n";
    }
}

echo "Fertig! $count Einträge importiert.\n";
?>