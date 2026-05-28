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

        // Try each registered route until one matches
        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {

            // Method must match (GET vs POST)
            if ($routeMethod !== $method) continue;

            // Convert route pattern to a regex
            // e.g. /books/{id}/edit  →  /books/([^/]+)/edit
            $pattern = preg_replace('/\{([a-z_]+)\}/', '([^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';

            // Check if the URL matches the pattern
            if (!preg_match($pattern, $uri, $matches)) continue;

            // $matches[0] is the full match, [1], [2]... are the parameters
            array_shift($matches);

            // Get parameter names from the route definition
            preg_match_all('/\{([a-z_]+)\}/', $routePath, $paramNames);
            $params = array_combine($paramNames[1], $matches ?: []);

            // Call the handler
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
        require BASE_PATH . '/views/errors/404.php';
    }
}
