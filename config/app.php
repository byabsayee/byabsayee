<?php
// =============================================================================
// config/app.php — Central configuration for Byabsayee
// =============================================================================
// This file reads settings from environment variables (set in docker-compose).
// Never hardcode passwords here. Always use getenv().
// =============================================================================

return [

    // -------------------------------------------------------------------------
    // Application
    // -------------------------------------------------------------------------
    'name'    => getenv('APP_NAME') ?: 'Byabsayee',
    'url'     => getenv('APP_URL')  ?: 'http://localhost',
    'env'     => getenv('APP_ENV')  ?: 'production',

    // A secret key used for signing tokens, CSRF, etc.
    // Generate with: php -r "echo bin2hex(random_bytes(32));"
    'key'     => getenv('APP_KEY')  ?: 'changeme',

    // -------------------------------------------------------------------------
    // Database (MariaDB)
    // -------------------------------------------------------------------------
    'db' => [
        'host'    => getenv('DB_HOST') ?: 'mariadb',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'byabsayee_db',
        'user'    => getenv('DB_USER') ?: 'byabsayee_user',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',  // supports Bengali text and emojis
    ],

    // -------------------------------------------------------------------------
    // File uploads
    // -------------------------------------------------------------------------
    'upload' => [
        'path'      => getenv('UPLOAD_PATH') ?: '/Sites/byabsayee/uploads',
        'max_size'  => 10 * 1024 * 1024,  // 10 MB in bytes
        'allowed'   => ['jpg','jpeg','png','gif','webp','pdf'],
    ],

    // -------------------------------------------------------------------------
    // Email (SMTP)
    // -------------------------------------------------------------------------
    'mail' => [
        'host'      => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port'      => getenv('SMTP_PORT') ?: 587,
        'user'      => getenv('SMTP_USER') ?: '',
        'pass'      => getenv('SMTP_PASS') ?: '',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'Byabsayee',
        'encrypt'   => 'tls',   // 'tls' for port 587, 'ssl' for port 465
    ],

    // -------------------------------------------------------------------------
    // Session
    // -------------------------------------------------------------------------
    'session' => [
        'name'     => getenv('SESSION_NAME') ?: 'byabsayee_session',
        'lifetime' => 60 * 60 * 24 * 30,  // 30 days in seconds
        'secure' => false,   // only send cookie over HTTPS
        'httponly' => true,   // JS cannot read the cookie (prevents XSS theft)
        'samesite' => 'Lax',  // CSRF protection
    ],

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------
    'per_page' => 25,

];
