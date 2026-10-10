<?php
// =============================================================================
// app/Helpers/Router.php — URL Router
// =============================================================================
// The router looks at the URL the visitor requested (e.g. /login or /dashboard)
// and decides which PHP function to call.
//
// HOW IT WORKS:
// 1. You register routes:  $router->get('/login', [AuthController::class, 'showLogin'])
// 2. When someone visits /login, the router calls AuthController::showLogin()
// 3. The method (GET/POST) is checked — a form submission uses POST, page view uses GET
//
// URL PARAMETERS:
// You can define routes with placeholders: /books/{id}/edit
// The {id} part gets extracted and passed to your controller function
// =============================================================================

namespace App\Helpers;

class Router
{
    // Stores all registered routes
    private array $routes = [];

    // -------------------------------------------------------------------------
    // Register a GET route (viewing a page)
    // Usage: $router->get('/dashboard', [DashboardController::class, 'index'])
    // -------------------------------------------------------------------------
    public function get(string $path, array|callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    // -------------------------------------------------------------------------
    // Register a POST route (submitting a form)
    // Usage: $router->post('/login', [AuthController::class, 'login'])
    // -------------------------------------------------------------------------
    public function post(string $path, array|callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    // -------------------------------------------------------------------------
    // dispatch() — Called once per request to find and run the matching route
    // -------------------------------------------------------------------------
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Get the URL path, strip query string (?foo=bar), trim slashes
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');

        // Collect every route that matches, then run the MOST SPECIFIC one (most literal segments).
        // Registration order used to win, so /customers/{customer_id} swallowed /customers/search.
        $best = null; $bestScore = -1;
        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod !== $method) continue;

            $pattern = preg_replace('/\{([a-z_]+)\}/', '([^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';
            if (!preg_match($pattern, $uri, $matches)) continue;

            $score = 0;
            foreach (explode('/', trim($routePath, '/')) as $seg) {
                if ($seg !== '' && !preg_match('/^\{[a-z_]+\}$/', $seg)) $score++;
            }
            if ($score > $bestScore) {          // strict ">" keeps the first-registered route on a tie
                $bestScore = $score;
                $best = [$routePath, $handler, $matches];
            }
        }

        if ($best !== null) {
            [$routePath, $handler, $matches] = $best;
            array_shift($matches);
            preg_match_all('/\{([a-z_]+)\}/', $routePath, $paramNames);
            $params = $paramNames[1] ? array_combine($paramNames[1], $matches ?: []) : [];
            $this->call($handler, $params);
            return;
        }

        // No route matched → 404
        $this->notFound();
    }

    // -------------------------------------------------------------------------
    // call() — Invoke the controller method or closure
    // -------------------------------------------------------------------------
    private function call(array|callable $handler, array $params): void
    {
        if (is_callable($handler)) {
            // It's a closure: function($params) { ... }
            call_user_func($handler, $params);
        } else {
            // It's [ClassName::class, 'methodName']
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method($params);
        }
    }

    // -------------------------------------------------------------------------
    // 404 page
    // -------------------------------------------------------------------------
    private function notFound(): void
    {
        http_response_code(404);
        if (defined('INTEGRATION_API')) {   // machine API: JSON, never an HTML page
            echo json_encode(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Unknown route.']]);
            return;
        }
        require BASE_PATH . '/views/errors/404.php';
    }
}
