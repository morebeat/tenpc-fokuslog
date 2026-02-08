<?php
declare(strict_types=1);

namespace FokusLog;

use PDO;

/**
 * Einfacher Router fÃ¼r die FokusLog API.
 */
class Router
{
    private array $routes = [];
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Registriert eine Route.
     *
     * @param string $method HTTP-Methode (GET, POST, PUT, DELETE)
     * @param string $pattern URL-Pattern (z.B. '/users/{id}')
     * @param string $controller Controller-Klassenname
     * @param string $action Methodenname im Controller
     */
    public function add(string $method, string $pattern, string $controller, string $action): self
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'controller' => $controller,
            'action' => $action,
        ];
        return $this;
    }

    /**
     * Shortcut fÃ¼r GET-Routen.
     */
    public function get(string $pattern, string $controller, string $action): self
    {
        return $this->add('GET', $pattern, $controller, $action);
    }

    /**
     * Shortcut fÃ¼r POST-Routen.
     */
    public function post(string $pattern, string $controller, string $action): self
    {
        return $this->add('POST', $pattern, $controller, $action);
    }

    /**
     * Shortcut fÃ¼r PUT-Routen.
     */
    public function put(string $pattern, string $controller, string $action): self
    {
        return $this->add('PUT', $pattern, $controller, $action);
    }

    /**
     * Shortcut fÃ¼r DELETE-Routen.
     */
    public function delete(string $pattern, string $controller, string $action): self
    {
        return $this->add('DELETE', $pattern, $controller, $action);
    }

    /**
     * FÃ¼hrt das Routing aus.
     *
     * @param string $method HTTP-Methode
     * @param string $path URL-Pfad
     */
    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['pattern'], $path);
            if ($params !== null) {
                $this->executeController($route['controller'], $route['action'], $params);
                return;
            }
        }

        // Keine Route gefunden
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint nicht gefunden']);
        exit;
    }

    /**
     * PrÃ¼ft ob eine Route zum Pfad passt und extrahiert Parameter.
     *
     * @return array|null Parameter-Array oder null wenn keine Ãœbereinstimmung
     */
    private function matchRoute(string $pattern, string $path): ?array
    {
        // Konvertiere {param} zu benannten Regex-Gruppen
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // Nur benannte Gruppen zurÃ¼ckgeben (keine numerischen Keys)
            return array_filter($matches, fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    /**
     * Instanziiert Controller und fÃ¼hrt Action aus.
     */
    private function executeController(string $controllerClass, string $action, array $params): void
    {
        $fullClass = "FokusLog\\Controller\\{$controllerClass}";

        if (!class_exists($fullClass)) {
            http_response_code(500);
            echo json_encode(['error' => "Controller nicht gefunden: {$controllerClass}"]);
            exit;
        }

        $controller = new $fullClass($this->pdo);

        if (!method_exists($controller, $action)) {
            http_response_code(500);
            echo json_encode(['error' => "Action nicht gefunden: {$action}"]);
            exit;
        }

        // Action mit Parametern aufrufen
        $controller->$action(...array_values($params));
    }
}

