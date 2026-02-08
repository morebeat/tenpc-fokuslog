<?php

declare(strict_types=1);

namespace FokusLog\Controller;

use Throwable;

/**
 * Controller fÃ¼r Medikamentenverwaltung.
 */
class MedicationsController extends BaseController
{
    /**
     * GET /medications
     * Gibt alle Medikamente der eigenen Familie zurÃ¼ck.
     */
    public function index(): void
    {
        try {
            $user = $this->requireAuth();

            $stmt = $this->pdo->prepare('SELECT id, name, default_dose FROM medications WHERE family_id = ? ORDER BY name');
            $stmt->execute([$user['family_id']]);
            $meds = $stmt->fetchAll();

            $this->respond(200, ['medications' => $meds]);
        } catch (Throwable $e) {
            app_log('ERROR', 'medications_get_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Laden der Medikamente: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /medications
     * Legt ein neues Medikament an (nur Parent/Adult).
     */
    public function store(): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);

            $data = $this->getJsonBody();
            $name = trim($data['name'] ?? '');
            $defaultDose = trim($data['default_dose'] ?? '');

            if ($name === '') {
                app_log('WARNING', 'med_create_validation_failed', ['creator_id' => $user['id'], 'error' => 'name_missing']);
                $this->respond(400, ['error' => 'name ist erforderlich']);
            }

            $stmt = $this->pdo->prepare('INSERT INTO medications (family_id, name, default_dose) VALUES (?, ?, ?)');
            $stmt->execute([$user['family_id'], $name, $defaultDose !== '' ? $defaultDose : null]);
            $newId = (int)$this->pdo->lastInsertId();

            app_log('INFO', 'med_create_success', ['creator_id' => $user['id'], 'med_id' => $newId, 'med_name' => $name]);
            $this->logAction($user['id'], 'med_create', 'medication ' . $name);

            $this->respond(201, ['id' => $newId, 'name' => $name, 'default_dose' => $defaultDose]);
        } catch (Throwable $e) {
            app_log('ERROR', 'med_create_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Erstellen des Medikaments: ' . $e->getMessage()]);
        }
    }

    /**
     * PUT /medications/{id}
     * Aktualisiert ein vorhandenes Medikament (nur Parent/Adult).
     */
    public function update(string $id): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);
            $medId = (int)$id;

            $data = $this->getJsonBody();
            $name = trim($data['name'] ?? '');
            $defaultDose = trim($data['default_dose'] ?? '');

            if ($name === '') {
                app_log('WARNING', 'med_update_validation_failed', ['user_id' => $user['id'], 'med_id' => $medId]);
                $this->respond(400, ['error' => 'name ist erforderlich']);
            }

            // PrÃ¼fen, ob das Medikament zur Familie des Benutzers gehÃ¶rt
            $stmt = $this->pdo->prepare('SELECT id FROM medications WHERE id = ? AND family_id = ?');
            $stmt->execute([$medId, $user['family_id']]);
            if (!$stmt->fetch()) {
                app_log('WARNING', 'med_update_not_found_or_unauthorized', ['user_id' => $user['id'], 'med_id' => $medId]);
                $this->respond(404, ['error' => 'Medikament nicht gefunden oder Zugriff verweigert']);
            }

            $stmt = $this->pdo->prepare('UPDATE medications SET name = ?, default_dose = ? WHERE id = ?');
            $stmt->execute([$name, $defaultDose !== '' ? $defaultDose : null, $medId]);

            app_log('INFO', 'med_update_success', ['user_id' => $user['id'], 'med_id' => $medId, 'new_name' => $name]);
            $this->logAction($user['id'], 'med_update', 'medication ' . $medId);

            $this->respond(200, ['id' => $medId, 'name' => $name, 'default_dose' => $defaultDose]);
        } catch (Throwable $e) {
            app_log('ERROR', 'med_update_failed', ['med_id' => $id, 'error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Aktualisieren des Medikaments: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /medications/{id}
     * LÃ¶scht ein Medikament (nur Parent/Adult).
     */
    public function destroy(string $id): void
    {
        try {
            $user = $this->requireAuth();
            $this->requireRole($user, ['parent', 'adult']);
            $medId = (int)$id;

            // PrÃ¼fen, ob das Medikament zur Familie des Benutzers gehÃ¶rt
            $stmt = $this->pdo->prepare('SELECT id FROM medications WHERE id = ? AND family_id = ?');
            $stmt->execute([$medId, $user['family_id']]);
            if (!$stmt->fetch()) {
                app_log('WARNING', 'med_delete_not_found_or_unauthorized', ['user_id' => $user['id'], 'med_id' => $medId]);
                $this->respond(404, ['error' => 'Medikament nicht gefunden oder Zugriff verweigert']);
            }

            // Business Rule: Medikamente mit EintrÃ¤gen dÃ¼rfen nicht gelÃ¶scht werden
            $stmt = $this->pdo->prepare('SELECT id FROM entries WHERE medication_id = ? LIMIT 1');
            $stmt->execute([$medId]);
            if ($stmt->fetch()) {
                $this->respond(409, ['error' => 'Medikament kann nicht gelÃ¶scht werden, da es in EintrÃ¤gen verwendet wird.']);
            }

            $stmt = $this->pdo->prepare('DELETE FROM medications WHERE id = ?');
            $stmt->execute([$medId]);

            app_log('INFO', 'med_delete_success', ['user_id' => $user['id'], 'med_id' => $medId]);
            $this->logAction($user['id'], 'med_delete', 'medication ' . $medId);

            $this->respond(204);
        } catch (Throwable $e) {
            app_log('ERROR', 'med_delete_failed', ['med_id' => $id, 'error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim LÃ¶schen des Medikaments: ' . $e->getMessage()]);
        }
    }
}
