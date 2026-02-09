<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use Throwable;

/**
 * Controller fü¼r Tags.
 */
class TagsController extends BaseController
{
    /**
     * GET /tags
     * Gibt alle Tags der Familie zurü¼ck.
     */
    public function index(): void
    {
        try {
            $user = $this->requireAuth();

            $stmt = $this->pdo->prepare('SELECT id, name FROM tags WHERE family_id = ? ORDER BY name');
            $stmt->execute([$user['family_id']]);
            $tags = $stmt->fetchAll();

            $this->respond(200, ['tags' => $tags]);
        } catch (Throwable $e) {
            app_log('ERROR', 'tags_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden der Tags']);
        }
    }

    /**
     * POST /tags
     * Erstellt einen neuen Tag.
     */
    public function store(): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);

            $data = $this->getJsonBody();
            $name = trim($data['name'] ?? '');

            if ($name === '') {
                $this->respond(400, ['error' => 'Name ist erforderlich']);
            }

            $stmt = $this->pdo->prepare('INSERT INTO tags (family_id, name) VALUES (?, ?)');
            $stmt->execute([$user['family_id'], $name]);
            $newId = (int)$this->pdo->lastInsertId();

            $this->respond(201, ['id' => $newId, 'name' => $name]);
        } catch (Throwable $e) {
            app_log('ERROR', 'tags_create_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Erstellen des Tags']);
        }
    }

    /**
     * DELETE /tags/{id}
     * Lü¶scht einen Tag.
     */
    public function destroy(string $id): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);
            $tagId = (int)$id;

            $stmt = $this->pdo->prepare('DELETE FROM tags WHERE id = ? AND family_id = ?');
            $stmt->execute([$tagId, $user['family_id']]);

            $this->respond(204);
        } catch (Throwable $e) {
            app_log('ERROR', 'tags_delete_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Lü¶schen des Tags']);
        }
    }
}
