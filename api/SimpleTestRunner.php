<?php

class SimpleTestRunner
{
    private $passed = 0;
    private $failed = 0;
    private $failedTests = [];

    public function run($testClass)
    {
        $methods = get_class_methods($testClass);
        echo "Starte Tests fü¼r " . get_class($testClass) . "...\n\n";

        foreach ($methods as $method) {
            if (strpos($method, 'test') === 0) {
                try {
                    // Hook: setUp() vor jedem Test ausfü¼hren, falls vorhanden
                    if (method_exists($testClass, 'setUp')) {
                        $testClass->setUp();
                    }

                    $testClass->$method();
                    echo "âœ… $method: OK\n";
                    $this->passed++;
                } catch (Exception $e) {
                    echo "âŒ $method: FEHLGESCHLAGEN - " . $e->getMessage() . "\n";
                    $this->failed++;
                    $this->failedTests[] = [
                        'method' => $method,
                        'message' => $e->getMessage()
                    ];
                } finally {
                    // Hook: tearDown() nach jedem Test ausfü¼hren, falls vorhanden
                    if (method_exists($testClass, 'tearDown')) {
                        $testClass->tearDown();
                    }
                }
            }
        }

        echo "\nErgebnis: {$this->passed} bestanden, {$this->failed} fehlgeschlagen.\n";
        if ($this->failed > 0) {
            echo "\n--- Zusammenfassung der fehlgeschlagenen Tests ---\n";
            foreach ($this->failedTests as $failure) {
                echo "\nTest: {$failure['method']}\n";
                echo "  Fehler: {$failure['message']}\n";
            }
            echo "---------------------------------------------------\n\n";
            exit(1);
        }
    }
}

class Assert
{
    public static function equals($expected, $actual, $message = '')
    {
        if ($expected !== $actual) {
            $exportExpected = var_export($expected, true);
            $exportActual = var_export($actual, true);
            throw new Exception("$message (Erwartet: $exportExpected, Erhalten: $exportActual)");
        }
    }

    public static function true($condition, $message = '')
    {
        if ($condition !== true) {
            throw new Exception("$message (Bedingung nicht erfü¼llt)");
        }
    }
}

class HttpClient
{
    private $baseUrl;
    private $cookieJar;

    public function __construct($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        // Temporü¤re Datei fü¼r Cookies (Session-Simulation)
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'cookie_');
    }

    public function __destruct()
    {
        if (file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    public function post($endpoint, $data)
    {
        return $this->request('POST', $endpoint, $data);
    }

    public function get($endpoint, $params = [])
    {
        $queryString = $params ? '?' . http_build_query($params) : '';
        return $this->request('GET', $endpoint . $queryString);
    }

    public function delete($endpoint)
    {
        return $this->request('DELETE', $endpoint);
    }

    private function request($method, $endpoint, $data = null)
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Header setzen
        $headers = ['Content-Type: application/json'];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data !== null && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('Curl Fehler: ' . curl_error($ch));
        }
        curl_close($ch);

        return ['code' => $httpCode, 'body' => json_decode($response, true)];
    }
}
