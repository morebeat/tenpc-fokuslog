<?php
/**
 * Import-Skript für FokusLog Hilfe-Seiten
 * 
 * Scannt app/help/ auf HTML-Dateien, extrahiert strukturierte Inhalte
 * und speichert sie in der glossary-Tabelle für Wiederverwendung in
 * anderen Anwendungen und Layouts.
 * 
 * Features:
 * - Vollständiger HTML-Inhalt (full_content)
 * - Reiner Text ohne HTML (content_plain) 
 * - Strukturierte Abschnitte als JSON (content_sections)
 * - Automatische Keyword-Extraktion aus Überschriften
 * - Zielgruppen-Erkennung
 * - Change-Detection via File-Hash
 * - Geschätzte Lesezeit
 * 
 * Ausführen via CLI: php app/help/import_help.php [--force] [--dry-run]
 */

declare(strict_types=1);

class HelpImporter
{
    private PDO $pdo;
    private string $helpDir;
    private bool $dryRun = false;
    private bool $force = false;
    private array $processedSlugs = [];
    private array $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'deleted' => 0];
    private array $categoryMap = [];
    
    // Zielgruppen-Keywords für automatische Erkennung
    private array $audienceKeywords = [
        'eltern' => ['eltern', 'mutter', 'vater', 'familie', 'erziehung'],
        'kinder' => ['kind', 'kinder', 'jugend', 'schüler'],
        'erwachsene' => ['erwachsen', 'beruf', 'arbeit', 'selbst'],
        'lehrer' => ['lehrer', 'lehrkraft', 'schule', 'unterricht', 'pädagog'],
        'aerzte' => ['arzt', 'ärztin', 'medizin', 'diagnose', 'behandlung', 'therapie']
    ];

    public function __construct(PDO $pdo, string $helpDir)
    {
        $this->pdo = $pdo;
        $this->helpDir = rtrim($helpDir, '/\\');
    }

    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    public function setForce(bool $force): self
    {
        $this->force = $force;
        return $this;
    }

    /**
     * Hauptmethode: Führt den Import durch
     */
    public function run(): array
    {
        $this->log("Starte Import der Hilfe-Inhalte...");
        
        // 1. Kategorie-Map aus index.html aufbauen
        $this->buildCategoryMap();
        
        // 2. Alle HTML-Dateien im Verzeichnis scannen
        $files = $this->scanHelpFiles();
        $this->log("Gefundene HTML-Dateien: " . count($files));
        
        // 3. Jede Datei verarbeiten
        foreach ($files as $file) {
            $this->processFile($file);
        }
        
        // 4. Veraltete Einträge löschen
        if (!$this->dryRun) {
            $this->cleanupOldEntries();
        }
        
        $this->log("\nFertig! Statistik:");
        $this->log("  Importiert: {$this->stats['imported']}");
        $this->log("  Aktualisiert: {$this->stats['updated']}");
        $this->log("  Übersprungen: {$this->stats['skipped']}");
        $this->log("  Gelöscht: {$this->stats['deleted']}");
        
        return $this->stats;
    }

    /**
     * Baut die Kategorie-Map aus index.html auf
     */
    private function buildCategoryMap(): void
    {
        $indexFile = $this->helpDir . '/index.html';
        if (!file_exists($indexFile)) {
            $this->log("Warnung: index.html nicht gefunden, verwende 'Allgemein' als Kategorie");
            return;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTMLFile($indexFile);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($doc);
        $links = $xpath->query('//ul[@class="tree-links"]//a');
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (strpos($href, 'http') === 0) continue;
            
            $slug = pathinfo($href, PATHINFO_FILENAME);
            $category = $this->findCategoryForLink($link);
            $this->categoryMap[$slug] = $category;
        }
        
        $this->log("Kategorie-Map erstellt: " . count($this->categoryMap) . " Einträge");
    }

    /**
     * Ermittelt die Kategorie für einen Link aus der Baumstruktur
     */
    private function findCategoryForLink(DOMNode $link): string
    {
        $category = 'Allgemein';
        $emojis = ['📱', '🧭', '📚', '🛡️', '👩‍⚕️', '📈', '📊', '👥', '🚀', '🤝', '🧘', '🏠', '💊', '🧠', '❓', '⚠️'];
        
        $p = $link->parentNode;
        while ($p) {
            if ($p->nodeName === 'details') {
                $class = $p->getAttribute('class') ?? '';
                if (strpos($class, 'tree-level') !== false) {
                    foreach ($p->childNodes as $child) {
                        if ($child->nodeName === 'summary') {
                            $rawCat = trim($child->textContent);
                            $category = trim(str_replace($emojis, '', $rawCat));
                            break 2;
                        }
                    }
                }
            }
            $p = $p->parentNode;
        }
        
        return $category;
    }

    /**
     * Scannt das Hilfe-Verzeichnis nach HTML-Dateien
     */
    private function scanHelpFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->helpDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $relativePath = str_replace($this->helpDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                
                // index.html, lexikon.html und Assets überspringen
                $skipFiles = ['index.html', 'lexikon.html'];
                $skipDirs = ['assets/', 'css/', 'js/', 'guide/'];
                
                $skip = false;
                foreach ($skipDirs as $dir) {
                    if (strpos($relativePath, $dir) === 0) {
                        $skip = true;
                        break;
                    }
                }
                
                if (!$skip && !in_array(basename($relativePath), $skipFiles)) {
                    $files[] = $file->getPathname();
                }
            }
        }
        
        sort($files);
        return $files;
    }

    /**
     * Verarbeitet eine einzelne HTML-Datei
     */
    private function processFile(string $filePath): void
    {
        $relativePath = str_replace($this->helpDir . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace('\\', '/', $relativePath);
        $slug = pathinfo($relativePath, PATHINFO_FILENAME);
        
        // Prüfe ob Datei sich geändert hat (via Hash)
        $fileHash = md5_file($filePath);
        if (!$this->force && $this->isFileUnchanged($slug, $fileHash)) {
            $this->stats['skipped']++;
            $this->processedSlugs[] = $slug;
            return;
        }
        
        // HTML parsen
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTMLFile($filePath);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);
        
        // Daten extrahieren
        $data = $this->extractData($doc, $xpath, $relativePath, $slug);
        $data['file_hash'] = $fileHash;
        $data['source_file'] = $relativePath;
        
        // In DB speichern
        if (!$this->dryRun) {
            $this->saveToDatabase($data);
        }
        
        $this->processedSlugs[] = $slug;
        $this->log("Verarbeitet: {$data['title']} ({$data['category']})");
    }

    /**
     * Extrahiert alle relevanten Daten aus einem HTML-Dokument
     */
    private function extractData(DOMDocument $doc, DOMXPath $xpath, string $relativePath, string $slug): array
    {
        // Titel
        $h1 = $doc->getElementsByTagName('h1')->item(0);
        $title = $h1 ? $this->cleanText($h1->textContent) : $this->slugToTitle($slug);
        
        // Kategorie
        $category = $this->categoryMap[$slug] ?? 'Allgemein';
        
        // Lead/Kurztext
        $content = '';
        $lead = $xpath->query('//*[contains(@class, "lead")]')->item(0);
        if ($lead) {
            $content = $this->cleanText($lead->textContent);
        } else {
            $p = $xpath->query('//main//p')->item(0);
            if ($p) {
                $content = $this->cleanText($p->textContent);
            }
        }
        
        // Vollständiger HTML-Inhalt
        $fullContent = '';
        $mainNode = $doc->getElementsByTagName('main')->item(0);
        if ($mainNode) {
            foreach ($mainNode->childNodes as $child) {
                $fullContent .= $doc->saveHTML($child);
            }
        }
        $fullContent = trim($fullContent);
        
        // Reiner Text (ohne HTML)
        $contentPlain = $this->htmlToPlainText($fullContent);
        
        // Strukturierte Abschnitte extrahieren
        $sections = $this->extractSections($xpath);
        
        // Keywords aus Überschriften
        $keywords = $this->extractKeywords($xpath, $title);
        
        // Zielgruppen erkennen
        $targetAudience = $this->detectTargetAudience($contentPlain, $relativePath);
        
        // Lesezeit schätzen (ca. 200 Wörter/Minute)
        $wordCount = str_word_count($contentPlain);
        $readingTime = max(1, (int) ceil($wordCount / 200));
        
        return [
            'slug' => $slug,
            'title' => $title,
            'content' => $content,
            'content_plain' => $contentPlain,
            'content_sections' => $sections,
            'full_content' => $fullContent,
            'link' => 'help/' . $relativePath,
            'category' => $category,
            'keywords' => $keywords,
            'target_audience' => $targetAudience,
            'reading_time_min' => $readingTime
        ];
    }

    /**
     * Extrahiert strukturierte Abschnitte (alltag/wissen)
     */
    private function extractSections(DOMXPath $xpath): array
    {
        $sections = [];
        
        // Suche nach help-section Elementen (alltag/wissen)
        $sectionNodes = $xpath->query('//*[contains(@class, "help-section")]');
        
        foreach ($sectionNodes as $section) {
            $class = $section->getAttribute('class');
            $type = 'general';
            
            if (strpos($class, 'alltag') !== false) {
                $type = 'alltag';
            } elseif (strpos($class, 'wissen') !== false) {
                $type = 'wissen';
            }
            
            // Überschrift finden
            $heading = '';
            foreach (['h1', 'h2', 'h3'] as $tag) {
                $h = $xpath->query(".//{$tag}", $section)->item(0);
                if ($h) {
                    $heading = $this->cleanText($h->textContent);
                    break;
                }
            }
            
            // Inhalt als HTML
            $html = '';
            foreach ($section->childNodes as $child) {
                $html .= $section->ownerDocument->saveHTML($child);
            }
            
            $sections[] = [
                'type' => $type,
                'heading' => $heading,
                'html' => trim($html),
                'text' => $this->cleanText(strip_tags($html))
            ];
        }
        
        // Fallback: Wenn keine help-section gefunden, main als einen Abschnitt nehmen
        if (empty($sections)) {
            $main = $xpath->query('//main')->item(0);
            if ($main) {
                $html = '';
                foreach ($main->childNodes as $child) {
                    $html .= $main->ownerDocument->saveHTML($child);
                }
                $sections[] = [
                    'type' => 'general',
                    'heading' => '',
                    'html' => trim($html),
                    'text' => $this->cleanText(strip_tags($html))
                ];
            }
        }
        
        return $sections;
    }

    /**
     * Extrahiert Keywords aus Überschriften
     */
    private function extractKeywords(DOMXPath $xpath, string $title): string
    {
        $keywords = [$this->cleanText(preg_replace('/[^\p{L}\p{N}\s]/u', '', $title))];
        
        // Alle Überschriften h1-h3 sammeln
        foreach (['h1', 'h2', 'h3'] as $tag) {
            $headings = $xpath->query("//{$tag}");
            foreach ($headings as $h) {
                $text = $this->cleanText($h->textContent);
                // Emojis und Sonderzeichen entfernen
                $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
                if (strlen($text) > 2) {
                    $keywords[] = $text;
                }
            }
        }
        
        // Strong/b-Tags für wichtige Begriffe
        $strongs = $xpath->query('//strong|//b');
        foreach ($strongs as $s) {
            $text = $this->cleanText($s->textContent);
            if (strlen($text) > 2 && strlen($text) < 50) {
                $keywords[] = $text;
            }
        }
        
        // Deduplizieren und limitieren
        $keywords = array_unique(array_filter($keywords));
        $keywords = array_slice($keywords, 0, 20);
        
        return implode(', ', $keywords);
    }

    /**
     * Erkennt Zielgruppen basierend auf Inhalt und Dateiname
     */
    private function detectTargetAudience(string $text, string $filename): string
    {
        $audiences = [];
        $textLower = mb_strtolower($text);
        $filenameLower = mb_strtolower($filename);
        
        foreach ($this->audienceKeywords as $audience => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($textLower, $keyword) !== false || strpos($filenameLower, $keyword) !== false) {
                    $audiences[] = $audience;
                    break;
                }
            }
        }
        
        // Wenn keine spezifische Zielgruppe erkannt, 'alle' zurückgeben
        if (empty($audiences)) {
            return 'alle';
        }
        
        return implode(',', array_unique($audiences));
    }

    /**
     * Konvertiert HTML zu reinem Text
     */
    private function htmlToPlainText(string $html): string
    {
        // Zeilenumbrüche für Block-Elemente
        $html = preg_replace('/<(p|div|br|h[1-6]|li)[^>]*>/i', "\n", $html);
        // Tags entfernen
        $text = strip_tags($html);
        // Mehrfache Leerzeichen/Zeilenumbrüche reduzieren
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        return trim($text);
    }

    /**
     * Bereinigt Text (Whitespace, Emojis normalisieren)
     */
    private function cleanText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Konvertiert Slug zu lesbarem Titel
     */
    private function slugToTitle(string $slug): string
    {
        $title = str_replace(['_', '-'], ' ', $slug);
        return ucfirst($title);
    }

    /**
     * Prüft ob die Datei unverändert ist
     */
    private function isFileUnchanged(string $slug, string $hash): bool
    {
        $stmt = $this->pdo->prepare('SELECT file_hash FROM glossary WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row && $row['file_hash'] === $hash;
    }

    /**
     * Speichert Daten in der Datenbank
     */
    private function saveToDatabase(array $data): void
    {
        // Prüfen ob Eintrag existiert
        $stmt = $this->pdo->prepare('SELECT id FROM glossary WHERE slug = ?');
        $stmt->execute([$data['slug']]);
        $exists = $stmt->fetch();
        
        $sectionsJson = json_encode($data['content_sections'], JSON_UNESCAPED_UNICODE);
        
        if ($exists) {
            $sql = "UPDATE glossary SET 
                title = ?, content = ?, content_plain = ?, content_sections = ?,
                full_content = ?, link = ?, category = ?, keywords = ?,
                target_audience = ?, reading_time_min = ?, source_file = ?,
                file_hash = ?, last_imported_at = NOW()
                WHERE slug = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['title'], $data['content'], $data['content_plain'], $sectionsJson,
                $data['full_content'], $data['link'], $data['category'], $data['keywords'],
                $data['target_audience'], $data['reading_time_min'], $data['source_file'],
                $data['file_hash'], $data['slug']
            ]);
            $this->stats['updated']++;
        } else {
            $sql = "INSERT INTO glossary 
                (slug, title, content, content_plain, content_sections, full_content, 
                 link, category, keywords, target_audience, reading_time_min, 
                 source_file, file_hash, last_imported_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['slug'], $data['title'], $data['content'], $data['content_plain'],
                $sectionsJson, $data['full_content'], $data['link'], $data['category'],
                $data['keywords'], $data['target_audience'], $data['reading_time_min'],
                $data['source_file'], $data['file_hash']
            ]);
            $this->stats['imported']++;
        }
    }

    /**
     * Entfernt veraltete Einträge aus der Datenbank
     */
    private function cleanupOldEntries(): void
    {
        if (empty($this->processedSlugs)) return;
        
        $placeholders = implode(',', array_fill(0, count($this->processedSlugs), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM glossary WHERE slug NOT IN ($placeholders)");
        $stmt->execute($this->processedSlugs);
        
        $this->stats['deleted'] = $stmt->rowCount();
        if ($this->stats['deleted'] > 0) {
            $this->log("Bereinigt: {$this->stats['deleted']} veraltete Einträge entfernt");
        }
    }

    private function log(string $message): void
    {
        echo $message . "\n";
    }
}

// ============================================================================
// CLI-Ausführung
// ============================================================================

if (php_sapi_name() === 'cli' || defined('IMPORT_HELP_RUN')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    
    // Argumente parsen
    $options = getopt('', ['force', 'dry-run', 'help']);
    
    if (isset($options['help'])) {
        echo "Verwendung: php import_help.php [--force] [--dry-run]\n";
        echo "  --force    Alle Dateien neu importieren (Hash ignorieren)\n";
        echo "  --dry-run  Nur simulieren, keine DB-Änderungen\n";
        exit(0);
    }
    
    // Umgebungsvariablen laden
    $envFile = __DIR__ . '/../../.env';
    if (!file_exists($envFile)) {
        die("Fehler: .env Datei nicht gefunden unter $envFile\n");
    }
    $env = parse_ini_file($envFile);
    
    // Datenbank verbinden
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
    
    // Import ausführen
    $importer = new HelpImporter($pdo, __DIR__);
    $importer
        ->setForce(isset($options['force']))
        ->setDryRun(isset($options['dry-run']))
        ->run();
}
?>