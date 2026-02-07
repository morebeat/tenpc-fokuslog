<?php
declare(strict_types=1);

namespace FokusLog\Controller;

use Throwable;

/**
 * Controller für Glossar/Lexikon.
 */
class GlossaryController extends BaseController
{
    /**
     * GET /glossary
     * Gibt das Glossar/Lexikon zurück.
     */
    public function index(): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT slug, title, content, link, category FROM glossary ORDER BY title ASC');
            $stmt->execute();
            $entries = $stmt->fetchAll();
            
            $this->respond(200, ['glossary' => $entries]);
        } catch (Throwable $e) {
            app_log('ERROR', 'glossary_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden des Lexikons']);
        }
    }

    /**
     * GET /glossary/{slug}
     * Gibt einen einzelnen Glossar-Eintrag mit vollem Inhalt zurück.
     */
    public function show(string $slug): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT slug, title, content, full_content, link, category FROM glossary WHERE slug = ?');
            $stmt->execute([$slug]);
            $entry = $stmt->fetch();

            if (!$entry) {
                $this->respond(404, ['error' => 'Eintrag nicht gefunden']);
            }
            
            $this->respond(200, ['entry' => $entry]);
        } catch (Throwable $e) {
            app_log('ERROR', 'glossary_entry_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden des Eintrags']);
        }
    }
}
