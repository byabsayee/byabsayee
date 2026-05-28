<?php
// =============================================================================
// public/index.php — Front Controller (Entry Point)
// =============================================================================
// THIS IS THE ONLY PHP FILE NGINX EVER CALLS DIRECTLY.
// Every single request — whether someone visits /login, /dashboard, /api/users —
// comes through this file first.
//
// nginx is configured with:  try_files $uri $uri/ /index.php?$query_string
// That means: if a real file doesn't exist, send the request here.
//
// This file does 4 things:
//   1. Sets up the environment (paths, error handling, autoloading)
//   2. Starts the session
//   3. Loads all route definitions
//   4. Tells the router to handle the current request
// =============================================================================

// ---- 1. DEFINE THE BASE PATH ------------------------------------------------
// BASE_PATH is the root of your project (one level above /public)
// Other files use this to find config/, app/, views/, etc.
define('BASE_PATH', dirname(__DIR__));

// ---- 2. ERROR HANDLING ------------------------------------------------------
// In development: show all errors
// In production: log them, never display to users
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Global exception handler — prevents blank pages from uncaught exceptions.
// Shows a clean error message and logs the real details.
set_exception_handler(function (\Throwable $e): void {
    // Discard any partial output buffer so the error page renders cleanly
    while (ob_get_level() > 0) ob_end_clean();
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    $msg = htmlspecialchars($e->getMessage());
    $file = htmlspecialchars(basename($e->getFile()));
    $line = (int)$e->getLine();
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
    <title>Error — Byabsayee</title>
    <style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8f9fa}
    .box{background:#fff;border-radius:12px;padding:40px;max-width:560px;box-shadow:0 4px 20px rgba(0,0,0,.08);text-align:center}
    h2{color:#c0392b;margin:0 0 12px}p{color:#555;margin:6px 0}code{font-size:12px;background:#f0f0f0;padding:2px 6px;border-radius:4px}
    a{color:#1a6b4a;font-weight:600;text-decoration:none}</style></head>
    <body><div class='box'>
    <h2>⚠ Something went wrong</h2>
    <p>$msg</p>
    <p><code>$file : $line</code></p>
    <p style='margin-top:20px'><a href='javascript:history.back()'>← Go back</a></p>
    </div></body></html>";
});

// ---- 3. AUTOLOADER ----------------------------------------------------------
// PHP "autoloading" means: when you write  new App\Controllers\AuthController()
// PHP automatically finds and loads the right file without you needing require()
//
// Our simple autoloader: converts  App\Controllers\AuthController
// to file path:  /app/Controllers/AuthController.php

spl_autoload_register(function (string $class): void {
    // Remove the leading "App\" namespace prefix
    $relative = str_replace('App\\', '', $class);

    // Convert namespace separators to directory separators
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ---- 4. LOAD HELPER FUNCTIONS -----------------------------------------------
// These are global functions like e(), redirect(), flash(), auth(), etc.
require_once BASE_PATH . '/app/Helpers/helpers.php';

// Apply user's browser timezone immediately — must be before ANY date() call.
// JavaScript writes 'byabsayee_tz' cookie with the IANA timezone string.
// All date(), now(), and strftime() calls in the same request will use this.
set_timezone_from_cookie();

// ---- 5. LOAD COMPOSER AUTOLOADER (mPDF, PHPMailer) --------------------------
// Only if vendor/ directory exists (after running composer install)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// ---- 6. START SESSION -------------------------------------------------------
session_set_cookie_params([
    'lifetime' => config('session.lifetime'),
    'path'     => '/',
    'domain'   => '',        // blank = current host only, works across devices
    'secure'   => false,     // true only if HTTPS
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name(config('session.name'));
session_start();

// ---- 7. SET UP ROUTER -------------------------------------------------------
use App\Helpers\Router;

$router = new Router();

// Load all route definitions (keeps this file clean)
require_once BASE_PATH . '/routes.php';

// ---- 8. DISPATCH THE REQUEST ------------------------------------------------
$router->dispatch();
