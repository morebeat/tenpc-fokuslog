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
     * Stellt sicher, dass jeder Test mit einem frischen, authentifizierten Client läuft.
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
     * Erstellt einen neuen Benutzer, loggt ihn ein und gibt einen authentifizierten Client zurück.
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
        // Dieser Test benötigt keinen authentifizierten Client und erstellt seinen eigenen.
        $client = $this->getApiClient();
        $username = 'register_test_' . bin2hex(random_bytes(4));

        $response = $client->post('/register', [
            'family_name' => 'RegisterFamily',
            'username' => $username,
            'password' => 'secret123'
        ]);

        Assert::equals(201, $response['code'], 'Registrierung sollte 201 zurückgeben');
        Assert::equals('Registrierung erfolgreich', $response['body']['message'] ?? '', 'Erfolgsmeldung prüfen');
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

        Assert::equals(200, $response['code'], 'Login sollte 200 zurückgeben');
        Assert::equals('Anmeldung erfolgreich', $response['body']['message'] ?? '', 'Login Nachricht prüfen');
    }

    public function testMe_InitialState()
    {
        // $this->client wird von setUp() bereitgestellt
        $response = $this->client->get('/me');
        
        Assert::equals(200, $response['code'], '/me sollte 200 zurückgeben');
        Assert::true(strpos($response['body']['username'], 'user_') === 0, 'Benutzername in /me prüfen');
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

        // Eigentlicher Test: Einträge abrufen
        $response = $this->client->get('/entries');
        
        Assert::equals(200, $response['code'], 'Einträge abrufen sollte 200 sein');
        Assert::true(is_array($response['body']['entries']), 'Entries sollte ein Array sein');
        Assert::true(count($response['body']['entries']) > 0, 'Sollte mindestens den eben erstellten Eintrag enthalten');
    }

    public function testMe_WithData()
    {
        // Vorbedingungen schaffen: Medikament und Eintrag erstellen
        $medResponse = $this->client->post('/medications', ['name' => 'DataMed', 'default_dose' => '5mg']);
        $medId = $medResponse['body']['id'];
        $this->client->post('/entries', ['date' => date('Y-m-d'), 'time' => 'morning', 'medication_id' => $medId, 'mood' => 3]);

        // Nach dem Erstellen von Einträgen und Meds sollte /me das widerspiegeln
        $response = $this->client->get('/me');
        
        Assert::equals(200, $response['code'], '/me sollte auch mit Daten 200 zurückgeben');
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
        Assert::equals(200, $meResponse['code'], 'Konnte Benutzer-ID für Test nicht abrufen');
        $userId = $meResponse['body']['id'];

        // Versuche, sich selbst zu löschen
        $deleteResponse = $this->client->delete('/users/' . $userId);

        // Prüfe, ob der Server dies mit 403 verhindert
        Assert::equals(403, $deleteResponse['code'], 'Selbstlöschung sollte 403 Forbidden zurückgeben');
        Assert::equals('Sie können sich nicht selbst löschen', $deleteResponse['body']['error'] ?? '', 'Fehlermeldung für Selbstlöschung prüfen');
    }

    public function testParentSeesFamilyEntriesInMe()
    {
        // Szenario: Elternteil hat selbst keine Einträge, aber das Kind hat welche.
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

        // 4. Als Elternteil wieder /me prüfen
        $meRes = $client->get('/me');
        Assert::equals(true, $meRes['body']['has_entries'] ?? false, 'Eltern sollten has_entries=true haben, wenn Kind Einträge hat');
    }
}