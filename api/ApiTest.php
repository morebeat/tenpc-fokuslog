<?php
require_once __DIR__ . '/SimpleTestRunner.php';

class ApiTest
{
    private $client;
    private $testUser;
    private $testPass = 'secret123';
    private $familyName = 'TestFamilie';

    public function __construct($baseUrl)
    {
        $this->client = new HttpClient($baseUrl);
        // Zufälliger Benutzername, um Konflikte bei mehrmaligem Ausführen zu vermeiden
        $this->testUser = 'user_' . bin2hex(random_bytes(4));
    }

    public function testRegister()
    {
        $response = $this->client->post('/register', [
            'family_name' => $this->familyName,
            'username' => $this->testUser,
            'password' => $this->testPass
        ]);

        Assert::equals(201, $response['code'], 'Registrierung sollte 201 zurückgeben');
        Assert::equals('Registrierung erfolgreich', $response['body']['message'] ?? '', 'Erfolgsmeldung prüfen');
    }

    public function testLogin()
    {
        $response = $this->client->post('/login', [
            'username' => $this->testUser,
            'password' => $this->testPass
        ]);

        Assert::equals(200, $response['code'], 'Login sollte 200 zurückgeben');
        Assert::equals('Anmeldung erfolgreich', $response['body']['message'] ?? '', 'Login Nachricht prüfen');
    }

    public function testMe()
    {
        // Setzt voraus, dass testLogin vorher lief (Cookies werden im Client gespeichert)
        $response = $this->client->get('/me');
        
        Assert::equals(200, $response['code'], '/me sollte 200 zurückgeben');
        Assert::equals($this->testUser, $response['body']['username'] ?? '', 'Benutzername in /me prüfen');
        Assert::equals('parent', $response['body']['role'] ?? '', 'Rolle sollte parent sein');
    }

    public function testCreateMedication()
    {
        $response = $this->client->post('/medications', [
            'name' => 'Ritalin',
            'default_dose' => '10mg'
        ]);

        Assert::equals(201, $response['code'], 'Medikament erstellen sollte 201 sein');
        return $response['body']['id']; // ID für spätere Tests zurückgeben
    }

    public function testCreateEntry()
    {
        // Wir erstellen erst ein Medikament, um eine ID zu haben
        $medId = $this->testCreateMedication();

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
        $response = $this->client->get('/entries');
        
        Assert::equals(200, $response['code'], 'Einträge abrufen sollte 200 sein');
        Assert::true(is_array($response['body']['entries']), 'Entries sollte ein Array sein');
        Assert::true(count($response['body']['entries']) > 0, 'Sollte mindestens den eben erstellten Eintrag enthalten');
    }

    public function testLogout()
    {
        $response = $this->client->post('/logout', []);
        Assert::equals(204, $response['code'], 'Logout sollte 204 sein');

        // Danach sollte /me fehlschlagen
        $response = $this->client->get('/me');
        Assert::equals(401, $response['code'], 'Nach Logout sollte Zugriff verweigert werden');
    }
}