<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use Throwable;

/**
 * Controller fÃ¼r Glossar/Lexikon.
 *
 * Stellt Hilfe-Inhalte in verschiedenen Formaten bereit fÃ¼r die
 * Verwendung in der eigenen App sowie in externen Anwendungen.
 */
class GlossaryController extends BaseController
{
    /**
     * GET /glossary
     * Gibt das Glossar/Lexikon zurÃ¼ck.
     *
     * Query-Parameter:
     * - category: Filter nach Kategorie (z.B. "Wissen", "Alltag")
     * - audience: Filter nach Zielgruppe (eltern, kinder, erwachsene, lehrer, aerzte, alle)
     * - search: Volltextsuche in Titel, Inhalt und Keywords
     * - format: Ausgabeformat (list, full, plain) - default: list
     * - limit: Maximale Anzahl EintrÃ¤ge
     * - offset: Offset fÃ¼r Pagination
     */
    public function index(): void
    {
        try {
            $category = $_GET['category'] ?? null;
            $audience = $_GET['audience'] ?? null;
            $search = $_GET['search'] ?? null;
            $format = $_GET['format'] ?? 'list';
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;
            $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

            // Base query - Felder je nach Format
            switch ($format) {
                case 'full':
                    $fields = 'slug, title, content, content_plain, content_sections, full_content, link, category, keywords, target_audience, reading_time_min';
                    break;
                case 'plain':
                    $fields = 'slug, title, content_plain as content, category, keywords, target_audience, reading_time_min';
                    break;
                case 'list':
                default:
                    $fields = 'slug, title, content, link, category, target_audience, reading_time_min';
                    break;
            }

            $sql = "SELECT $fields FROM glossary WHERE 1=1";
            $params = [];

            // Filter: Kategorie
            if ($category) {
                $sql .= ' AND category = ?';
                $params[] = $category;
            }

            // Filter: Zielgruppe (SET-Feld, kann mehrere Werte enthalten)
            if ($audience && $audience !== 'alle') {
                $sql .= ' AND (FIND_IN_SET(?, target_audience) > 0 OR target_audience = "alle")';
                $params[] = $audience;
            }

            // Filter: Volltextsuche
            if ($search && strlen($search) >= 2) {
                // PrÃ¼fe ob Fulltext-Index existiert, sonst LIKE-Fallback
                $sql .= ' AND (title LIKE ? OR content LIKE ? OR keywords LIKE ?)';
                $searchParam = '%' . $search . '%';
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            $sql .= ' ORDER BY title ASC';

            // Pagination
            if ($limit !== null) {
                $sql .= ' LIMIT ' . (int) $limit;
                if ($offset > 0) {
                    $sql .= ' OFFSET ' . (int) $offset;
                }
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll();

            // JSON-Felder dekodieren
            foreach ($entries as &$entry) {
                if (isset($entry['content_sections']) && is_string($entry['content_sections'])) {
                    $entry['content_sections'] = json_decode($entry['content_sections'], true);
                }
            }

            // Gesamtanzahl fÃ¼r Pagination
            $countSql = "SELECT COUNT(*) as total FROM glossary WHERE 1=1";
            $countParams = [];
            if ($category) {
                $countSql .= ' AND category = ?';
                $countParams[] = $category;
            }
            if ($audience && $audience !== 'alle') {
                $countSql .= ' AND (FIND_IN_SET(?, target_audience) > 0 OR target_audience = "alle")';
                $countParams[] = $audience;
            }
            if ($search && strlen($search) >= 2) {
                $countSql .= ' AND (title LIKE ? OR content LIKE ? OR keywords LIKE ?)';
                $searchParam = '%' . $search . '%';
                $countParams[] = $searchParam;
                $countParams[] = $searchParam;
                $countParams[] = $searchParam;
            }

            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = (int) $countStmt->fetchColumn();

            $this->respond(200, [
                'glossary' => $entries,
                'meta' => [
                    'total' => $total,
                    'count' => count($entries),
                    'offset' => $offset,
                    'format' => $format
                ]
            ]);
        } catch (Throwable $e) {
            app_log('ERROR', 'glossary_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden des Lexikons']);
        }
    }

    /**
     * GET /glossary/categories
     * Gibt alle verfÃ¼gbaren Kategorien zurÃ¼ck.
     */
    public function categories(): void
    {
        try {
            $stmt = $this->pdo->query('SELECT DISTINCT category, COUNT(*) as count FROM glossary GROUP BY category ORDER BY category');
            $categories = $stmt->fetchAll();

            $this->respond(200, ['categories' => $categories]);
        } catch (Throwable $e) {
            app_log('ERROR', 'glossary_categories_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden der Kategorien']);
        }
    }

    /**
     * GET /glossary/{slug}
     * Gibt einen einzelnen Glossar-Eintrag mit vollem Inhalt zurÃ¼ck.
     *
     * Query-Parameter:
     * - format: Ausgabeformat (full, plain, sections) - default: full
     */
    public function show(string $slug): void
    {
        try {
            $format = $_GET['format'] ?? 'full';

            $stmt = $this->pdo->prepare('
                SELECT slug, title, content, content_plain, content_sections, full_content,
                       link, category, keywords, target_audience, reading_time_min,
                       created_at, updated_at
                FROM glossary WHERE slug = ?
            ');
            $stmt->execute([$slug]);
            $entry = $stmt->fetch();

            if (!$entry) {
                $this->respond(404, ['error' => 'Eintrag nicht gefunden']);
                return;
            }

            // JSON-Feld dekodieren
            if (isset($entry['content_sections']) && is_string($entry['content_sections'])) {
                $entry['content_sections'] = json_decode($entry['content_sections'], true);
            }

            // Format-spezifische Ausgabe
            switch ($format) {
                case 'plain':
                    // Nur Plaintext-Version
                    $result = [
                        'slug' => $entry['slug'],
                        'title' => $entry['title'],
                        'content' => $entry['content_plain'],
                        'category' => $entry['category'],
                        'keywords' => $entry['keywords'],
                        'reading_time_min' => $entry['reading_time_min']
                    ];
                    break;

                case 'sections':
                    // Nur strukturierte Abschnitte
                    $result = [
                        'slug' => $entry['slug'],
                        'title' => $entry['title'],
                        'sections' => $entry['content_sections'],
                        'category' => $entry['category']
                    ];
                    break;

                case 'full':
                default:
                    $result = $entry;
                    break;
            }

            $this->respond(200, ['entry' => $result]);
        } catch (Throwable $e) {
            app_log('ERROR', 'glossary_entry_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden des Eintrags']);
        }
    }

    /**
     * POST /glossary/import
     * Triggert den Import der Hilfe-Dateien (nur fÃ¼r Admins).
     */
    public function import(): void
    {
        try {
            // Authentifizierung prÃ¼fen
            $user = $this->requireAuth();

            // Nur Parents (als "Admins" der Familie) dÃ¼rfen importieren
            if ($user['role'] !== 'parent') {
                $this->respond(403, ['error' => 'Keine Berechtigung']);
                return;
            }

            // Import-Skript einbinden und ausfÃ¼hren
            $importScript = __DIR__ . '/../../../app/help/import_help.php';

            if (!file_exists($importScript)) {
                $this->respond(500, ['error' => 'Import-Skript nicht gefunden']);
                return;
            }

            // Umgebungsvariablen laden
            $envFile = __DIR__ . '/../../.env';
            if (!file_exists($envFile)) {
                $envFile = __DIR__ . '/../../../.env';
            }

            if (!file_exists($envFile)) {
                $this->respond(500, ['error' => '.env nicht gefunden']);
                return;
            }

            $env = parse_ini_file($envFile);
            $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
            $pdo = new \PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]);

            // HelpImporter Klasse laden
            require_once $importScript;

            $helpDir = __DIR__ . '/../../../app/help';
            $importer = new \HelpImporter($pdo, $helpDir);

            $force = isset($_POST['force']) && $_POST['force'];
            $stats = $importer->setForce($force)->run();

            $this->logAction((int)$user['id'], 'GLOSSARY_IMPORT', $stats);

            $this->respond(200, [
                'message' => 'Import abgeschlossen',
                'stats' => $stats
            ]);
        } catch (Throwable $e) {
            app_log('ERROR', 'glossary_import_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Import fehlgeschlagen: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /glossary/export
     * Exportiert alle Glossar-EintrÃ¤ge in einem Format fÃ¼r externe Nutzung.
     *
     * Query-Parameter:
     * - format: json (default), csv
     * - include: comma-separated list of fields to include
     */
    public function export(): void
    {
        try {
            $format = $_GET['format'] ?? 'json';
            $include = isset($_GET['include']) ? explode(',', $_GET['include']) : null;

            // Alle verfÃ¼gbaren Felder
            $allFields = ['slug', 'title', 'content', 'content_plain', 'full_content',
                         'link', 'category', 'keywords', 'target_audience', 'reading_time_min'];

            // Felder filtern falls angegeben
            if ($include) {
                $fields = array_intersect($allFields, $include);
                if (empty($fields)) {
                    $fields = $allFields;
                }
            } else {
                // Standard: Alle auÃŸer full_content (zu groÃŸ)
                $fields = ['slug', 'title', 'content', 'content_plain', 'link',
                          'category', 'keywords', 'target_audience', 'reading_time_min'];
            }

            $fieldList = implode(', ', $fields);
            $stmt = $this->pdo->query("SELECT $fieldList FROM glossary ORDER BY category, title");
            $entries = $stmt->fetchAll();

            if ($format === 'csv') {
                // CSV-Export
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="fokuslog-glossary.csv"');

                $output = fopen('php://output', 'w');
                // BOM für Excel UTF-8
                fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
                // Header
                fputcsv($output, $fields, ';');
                // Daten
                foreach ($entries as $entry) {
                    $row = [];
                    foreach ($fields as $field) {
                        $row[] = $entry[$field] ?? '';
                    }
                    fputcsv($output, $row, ';');
                }
                fclose($output);
                exit;
            }

            // JSON-Export (default)
            $this->respond(200, [
                'export' => [
                    'generated_at' => date('c'),
                    'count' => count($entries),
                    'fields' => $fields
                ],
                'entries' => $entries
            ]);
        } catch (Throwable $e) {
            app_log('ERROR', 'glossary_export_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Export fehlgeschlagen']);
        }
    }
}
