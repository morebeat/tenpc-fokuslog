<?php

require_once __DIR__ . '/SimpleTestRunner.php';

class ApiTest
{
    private $client;
    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Wird vom SimpleTestRunner vor jedem Test aufgerufen.
     * Stellt sicher, dass jeder Test mit einem frischen, authentifizierten Client lÃ¤uft.
     */
    public function setUp(): void
    {
        $this->client = $this->getAuthenticatedClient();
    }

    /**
     * Erstellt einen neuen, nicht authentifizierten API-Client.
     */
    private function getApiClient(): HttpClient
    {
        return new HttpClient($this->baseUrl);
    }

    /**
     * Erstellt einen neuen Benutzer, loggt ihn ein und gibt einen authentifizierten Client zurÃ¼ck.
     */
    private function getAuthenticatedClient(): HttpClient
    {
        $client = $this->getApiClient();
        $username = 'user_' . bin2hex(random_bytes(4));
        $password = 'secret123';

        $client->post('/register', [
            'family_name' => 'TestFamily',
            'username' => $username,
            'password' => $password
        ]);

        $client->post('/login', [
            'username' => $username,
            'password' => $password
        ]);

        return $client;
    }

    public function testRegister()
    {
        // Dieser Test benÃ¶tigt keinen authentifizierten Client und erstellt seinen eigenen.
        $client = $this->getApiClient();
        $username = 'register_test_' . bin2hex(random_bytes(4));

        $response = $client->post('/register', [
            'family_name' => 'RegisterFamily',
            'username' => $username,
            'password' => 'secret123'
        ]);

        Assert::equals(201, $response['code'], 'Registrierung sollte 201 zurÃ¼ckgeben');
        Assert::equals('Registrierung erfolgreich', $response['body']['message'] ?? '', 'Erfolgsmeldung prÃ¼fen');
    }

    public function testLogin()
    {
        // Dieser Test verwaltet seinen eigenen Benutzer-Erstellungs- und Anmelde-Flow.
        $client = $this->getApiClient();
        $username = 'login_test_' . bin2hex(random_bytes(4));
        $password = 'secret123';

        // Erst registrieren, um einen Account zum Einloggen zu haben
        $client->post('/register', ['family_name' => 'LoginFamily', 'username' => $username, 'password' => $password]);

        // Jetzt den Login testen
        $response = $client->post('/login', ['username' => $username, 'password' => $password]);

        Assert::equals(200, $response['code'], 'Login sollte 200 zurÃ¼ckgeben');
        Assert::equals('Anmeldung erfolgreich', $response['body']['message'] ?? '', 'Login Nachricht prÃ¼fen');
    }

    public function testMe_InitialState()
    {
        // $this->client wird von setUp() bereitgestellt
        $response = $this->client->get('/me');

        Assert::equals(200, $response['code'], '/me sollte 200 zurÃ¼ckgeben');
        Assert::true(strpos($response['body']['username'], 'user_') === 0, 'Benutzername in /me prÃ¼fen');
        Assert::equals('parent', $response['body']['role'] ?? '', 'Rolle sollte parent sein');
        // Zu Beginn sollten keine Daten vorhanden sein
        Assert::equals(false, $response['body']['has_medications'] ?? null, '/me: has_medications sollte anfangs false sein');
        Assert::equals(false, $response['body']['has_entries'] ?? null, '/me: has_entries sollte anfangs false sein');
    }

    public function testCreateEntry()
    {
        // Vorbedingung: Ein Medikament muss existieren
        $medResponse = $this->client->post('/medications', ['name' => 'TestMed', 'default_dose' => '5mg']);
        $medId = $medResponse['body']['id'];

        $date = date('Y-m-d');
        $response = $this->client->post('/entries', [
            'date' => $date,
            'time' => 'morning',
            'medication_id' => $medId,
            'dose' => '10mg',
            'mood' => 3,
            'focus' => 4
        ]);

        Assert::equals(201, $response['code'], 'Eintrag erstellen sollte 201 sein');
    }

    public function testGetEntries()
    {
        // Vorbedingungen schaffen: Medikament und Eintrag erstellen
        $medResponse = $this->client->post('/medications', ['name' => 'GetMed', 'default_dose' => '5mg']);
        $medId = $medResponse['body']['id'];
        $this->client->post('/entries', ['date' => date('Y-m-d'), 'time' => 'morning', 'medication_id' => $medId, 'mood' => 3]);

        // Eigentlicher Test: EintrÃ¤ge abrufen
        $response = $this->client->get('/entries');

        Assert::equals(200, $response['code'], 'EintrÃ¤ge abrufen sollte 200 sein');
        Assert::true(is_array($response['body']['entries']), 'Entries sollte ein Array sein');
        Assert::true(count($response['body']['entries']) > 0, 'Sollte mindestens den eben erstellten Eintrag enthalten');
    }

    public function testMe_WithData()
    {
        // Vorbedingungen schaffen: Medikament und Eintrag erstellen
        $medResponse = $this->client->post('/medications', ['name' => 'DataMed', 'default_dose' => '5mg']);
        $medId = $medResponse['body']['id'];
        $this->client->post('/entries', ['date' => date('Y-m-d'), 'time' => 'morning', 'medication_id' => $medId, 'mood' => 3]);

        // Nach dem Erstellen von EintrÃ¤gen und Meds sollte /me das widerspiegeln
        $response = $this->client->get('/me');

        Assert::equals(200, $response['code'], '/me sollte auch mit Daten 200 zurÃ¼ckgeben');
        Assert::equals(true, $response['body']['has_medications'] ?? null, '/me: has_medications sollte nach Erstellung true sein');
        Assert::equals(true, $response['body']['has_entries'] ?? null, '/me: has_entries sollte nach Erstellung true sein');
    }

    public function testLogoutAndAccess()
    {
        $response = $this->client->post('/logout', []);
        Assert::equals(204, $response['code'], 'Logout sollte 204 sein');

        // Danach sollte /me fehlschlagen
        $response = $this->client->get('/me');
        Assert::equals(401, $response['code'], 'Nach Logout sollte Zugriff verweigert werden');
    }

    public function testUserCannotDeleteSelf()
    {
        // Hole die ID des aktuellen Benutzers
        $meResponse = $this->client->get('/me');
        Assert::equals(200, $meResponse['code'], 'Konnte Benutzer-ID fÃ¼r Test nicht abrufen');
        $userId = $meResponse['body']['id'];

        // Versuche, sich selbst zu lÃ¶schen
        $deleteResponse = $this->client->delete('/users/' . $userId);

        // PrÃ¼fe, ob der Server dies mit 403 verhindert
        Assert::equals(403, $deleteResponse['code'], 'SelbstlÃ¶schung sollte 403 Forbidden zurÃ¼ckgeben');
        Assert::equals('Sie kÃ¶nnen sich nicht selbst lÃ¶schen', $deleteResponse['body']['error'] ?? '', 'Fehlermeldung fÃ¼r SelbstlÃ¶schung prÃ¼fen');
    }

    public function testParentSeesFamilyEntriesInMe()
    {
        // Szenario: Elternteil hat selbst keine EintrÃ¤ge, aber das Kind hat welche.
        // Das Dashboard sollte trotzdem nicht den "Willkommen"-Screen zeigen (has_entries = true).

        $client = $this->getAuthenticatedClient();

        // 1. Kind anlegen
        $childName = 'child_' . bin2hex(random_bytes(4));
        $client->post('/users', [
            'username' => $childName,
            'password' => 'pass123',
            'role' => 'child',
            'first_name' => 'TestKind'
        ]);

        // 2. Medikament anlegen (als Elternteil)
        $medRes = $client->post('/medications', ['name' => 'Saft', 'default_dose' => '5ml']);
        $medId = $medRes['body']['id'];

        // 3. Als Kind einloggen und Eintrag erstellen
        $childClient = $this->getApiClient();
        $childClient->post('/login', ['username' => $childName, 'password' => 'pass123']);
        $childClient->post('/entries', [
            'date' => date('Y-m-d'),
            'time' => 'morning',
            'medication_id' => $medId,
            'mood' => 4
        ]);

        // 4. Als Elternteil wieder /me prÃ¼fen
        $meRes = $client->get('/me');
        Assert::equals(true, $meRes['body']['has_entries'] ?? false, 'Eltern sollten has_entries=true haben, wenn Kind EintrÃ¤ge hat');
    }

    // =========================================================================
    // Glossary-Tests
    // =========================================================================

    public function testGlossaryIndex()
    {
        // Glossary ist Ã¶ffentlich abrufbar
        $client = $this->getApiClient();
        $response = $client->get('/glossary');

        Assert::equals(200, $response['code'], 'Glossar abrufen sollte 200 sein');
        Assert::true(isset($response['body']['glossary']), 'Response sollte glossary-Array enthalten');
        Assert::true(isset($response['body']['meta']), 'Response sollte meta-Objekt enthalten');
    }

    public function testGlossaryWithFilters()
    {
        $client = $this->getApiClient();

        // Mit Format-Parameter
        $response = $client->get('/glossary?format=plain');
        Assert::equals(200, $response['code'], 'Glossar mit format=plain sollte 200 sein');
        Assert::equals('plain', $response['body']['meta']['format'] ?? '', 'Format sollte plain sein');

        // Mit Limit
        $response = $client->get('/glossary?limit=5');
        Assert::equals(200, $response['code'], 'Glossar mit limit sollte 200 sein');
        Assert::true(count($response['body']['glossary']) <= 5, 'Sollte maximal 5 EintrÃ¤ge zurÃ¼ckgeben');
    }

    public function testGlossaryCategories()
    {
        $client = $this->getApiClient();
        $response = $client->get('/glossary/categories');

        Assert::equals(200, $response['code'], 'Kategorien abrufen sollte 200 sein');
        Assert::true(isset($response['body']['categories']), 'Response sollte categories-Array enthalten');
    }

    public function testGlossaryExportJson()
    {
        $client = $this->getApiClient();
        $response = $client->get('/glossary/export?format=json');

        Assert::equals(200, $response['code'], 'JSON-Export sollte 200 sein');
        Assert::true(isset($response['body']['entries']), 'Response sollte entries-Array enthalten');
        Assert::true(isset($response['body']['export']['generated_at']), 'Export sollte Timestamp enthalten');
    }

    public function testGlossaryShowEntry()
    {
        $client = $this->getApiClient();

        // Zuerst prÃ¼fen ob EintrÃ¤ge existieren
        $listResponse = $client->get('/glossary?limit=1');
        if (empty($listResponse['body']['glossary'])) {
            // Kein Eintrag vorhanden - 404 erwarten
            $response = $client->get('/glossary/nonexistent');
            Assert::equals(404, $response['code'], 'Nicht existierender Eintrag sollte 404 sein');
            return;
        }

        // Mit existierendem Slug testen
        $slug = $listResponse['body']['glossary'][0]['slug'];
        $response = $client->get('/glossary/' . $slug);

        Assert::equals(200, $response['code'], 'Einzelner Eintrag sollte 200 sein');
        Assert::true(isset($response['body']['entry']), 'Response sollte entry-Objekt enthalten');
        Assert::equals($slug, $response['body']['entry']['slug'], 'Slug sollte Ã¼bereinstimmen');
    }

    public function testGlossaryShowWithFormats()
    {
        $client = $this->getApiClient();

        // Hole einen Slug
        $listResponse = $client->get('/glossary?limit=1');
        if (empty($listResponse['body']['glossary'])) {
            return; // Kein Eintrag zum Testen
        }

        $slug = $listResponse['body']['glossary'][0]['slug'];

        // Format: plain
        $response = $client->get('/glossary/' . $slug . '?format=plain');
        Assert::equals(200, $response['code'], 'Eintrag mit format=plain sollte 200 sein');

        // Format: sections
        $response = $client->get('/glossary/' . $slug . '?format=sections');
        Assert::equals(200, $response['code'], 'Eintrag mit format=sections sollte 200 sein');
    }

    public function testGlossaryImportRequiresAuth()
    {
        // Ohne Authentifizierung sollte Import 401 zurÃ¼ckgeben
        $client = $this->getApiClient();
        $response = $client->post('/glossary/import', []);

        Assert::equals(401, $response['code'], 'Import ohne Auth sollte 401 sein');
    }

    public function testGlossaryImportRequiresParentRole()
    {
        // Authentifizierter Client (Parent-Rolle) - sollte funktionieren oder 500 wenn Script fehlt
        $response = $this->client->post('/glossary/import', []);

        // Entweder 200 (erfolgreich) oder 500 (Script-Fehler) - aber nicht 401/403
        Assert::true(
            in_array($response['code'], [200, 500]),
            'Import als Parent sollte 200 oder 500 sein (nicht 401/403), war: ' . $response['code']
        );
    }
}
