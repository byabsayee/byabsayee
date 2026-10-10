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
    // Only show technical details when explicitly running in development; otherwise a reference id the owner can find in the log.
    $ref  = substr(bin2hex(random_bytes(4)), 0, 8);
    error_log("[error-ref $ref] " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $dev  = (getenv('APP_ENV') === 'development');
    $msg  = $dev ? htmlspecialchars($e->getMessage()) : 'An unexpected error occurred. Please go back and try again.';
    $file = $dev ? htmlspecialchars(basename($e->getFile())) : 'ref';
    $line = $dev ? (int)$e->getLine() : $ref;
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
$__apiPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (strncmp($__apiPath, '/api/v1/integrations/', 21) === 0) {
    // Machine-to-machine API (online-store integration): no session, no cookies, no CSRF, always JSON.
    define('INTEGRATION_API', true);
    date_default_timezone_set('UTC');
} else {
    set_timezone_from_cookie();
}

// ---- 5. LOAD COMPOSER AUTOLOADER (mPDF, PHPMailer) --------------------------
// Only if vendor/ directory exists (after running composer install)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// ---- 5b. MACHINE API ---------------------------------------------------------
// Answers with JSON for everything, including uncaught errors and fatals, and never touches the session.
if (defined('INTEGRATION_API')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    set_exception_handler(function (\Throwable $e): void {
        error_log('[integration api] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) http_response_code(500);
        echo json_encode(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'The book could not process this request.']]);
    });
    register_shutdown_function(function (): void {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && !headers_sent()) {
            error_log('[integration api fatal] ' . $e['message']);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'The book could not process this request.']]);
        }
    });
    $router = new \App\Helpers\Router();
    require_once BASE_PATH . '/routes.php';
    $router->dispatch();
    exit;
}

// ---- 6. START SESSION -------------------------------------------------------
session_set_cookie_params([
    'lifetime' => config('session.lifetime'),
    'path'     => '/',
    'domain'   => '',        // blank = current host only, works across devices
    'secure'   => request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name(config('session.name'));
session_start();

// ---- 6b. APPLY LOGGED-IN USER'S SAVED TIMEZONE (overrides the browser-guess cookie) ----
// The cookie above is set by JS on every page load from the browser's locale, which is
// only a fallback. If the user has explicitly picked a timezone in Settings, that wins.
if (!empty($_SESSION['user']['timezone'])) {
    try {
        new \DateTimeZone($_SESSION['user']['timezone']); // throws on invalid
        date_default_timezone_set($_SESSION['user']['timezone']);
    } catch (\Throwable $e) { /* keep the cookie-based guess */ }
}

// ---- 6c. SESSION HEARTBEAT + REVOCATION CHECK ------------------------------
// 1. Every signed-in request checks that this session is still listed in "Active sessions".
//    If another device removed it ("Sign out" / "Sign out all other devices"), this one is logged out now.
//    (Previously the heartbeat silently re-created the row, so remote sign-out never took effect.)
// 2. Keeps last-active up to date, throttled to once a minute.
if (!empty($_SESSION['user']['id']) && empty($_GET['_error'])) {
    try {
        require_once BASE_PATH . '/app/Helpers/Database.php';
        $__row = \App\Helpers\Database::row('SELECT id, last_active_at FROM user_sessions WHERE session_id=?', [session_id()]);
        if (!$__row && !empty($_SESSION['_sess_tracked'])) {
            // Row is gone → this session was revoked from another device.
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            redirect('/login', ['error' => 'You were signed out from another device. Please sign in again.']);
        }
        if (!$__row) {
            track_session((int)$_SESSION['user']['id']);          // session that pre-dates tracking: register it once
        } elseif (empty($_SESSION['_sess_last_ping']) || (time() - $_SESSION['_sess_last_ping']) >= 60) {
            \App\Helpers\Database::run(
                'UPDATE user_sessions SET last_active_at=NOW(), ip_address=? WHERE id=?',
                [$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $__row['id']]
            );
            $_SESSION['_sess_last_ping'] = time();
            $_SESSION['_sess_tracked']   = 1;
        } else {
            $_SESSION['_sess_tracked']   = 1;
        }
    } catch (\Throwable $e) { /* ignore — table may not exist */ }
}

// ---- 7. HANDLE NGINX ERROR ROUTING -----------------------------------------
// Nginx routes 403/413/404 errors to /index.php?_error=N for custom pages.
if (isset($_GET['_error'])) {
    $errorCode = (int)$_GET['_error'];
    if ($errorCode === 413) {
        http_response_code(413);
        $pageTitle = '413 — File Too Large';
        ob_start();
        ?>
        <div style="min-height:60vh;display:flex;align-items:center;justify-content:center">
            <div style="text-align:center;max-width:440px">
                <div style="font-size:56px;margin-bottom:16px">📎</div>
                <h1 style="font-size:26px;margin-bottom:8px">File Too Large</h1>
                <p style="color:var(--text-muted);margin-bottom:24px;font-size:15px">
                    The file you tried to upload exceeds the maximum allowed size.<br>
                    Please reduce the file size and try again.
                </p>
                <a href="javascript:history.back()" class="btn btn-secondary" style="margin-right:8px">← Go Back</a>
                <a href="/books" class="btn btn-primary">My Books</a>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require BASE_PATH . '/views/partials/layout.php';
        exit;
    }
    if ($errorCode === 403) {
        http_response_code(403);
        require BASE_PATH . '/views/errors/403.php';
        exit;
    }
    if ($errorCode === 404) {
        http_response_code(404);
        require BASE_PATH . '/views/errors/404.php';
        exit;
    }
}

// ---- 8. SET UP ROUTER -------------------------------------------------------
use App\Helpers\Router;

$router = new Router();

// Load all route definitions (keeps this file clean)
require_once BASE_PATH . '/routes.php';

// ---- 9. DISPATCH THE REQUEST ------------------------------------------------
$router->dispatch();
