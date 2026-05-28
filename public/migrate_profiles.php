<?php
/**
 * Byabsayee — Migration: Profiles, Handles, 2FA, Email Verification
 * Adds:
 *   - user_handles            (@username system)
 *   - user_profiles           (extended personal profile)
 *   - user_education          (education history, multiple per user)
 *   - user_grades             (grade levels, e.g. SSC, HSC)
 *   - user_social_links       (social media links)
 *   - user_profile_visibility (per-field public visibility flags)
 *   - business_profiles       (extended business/book public profile)
 *   - business_handles        (@businessname system)
 *   - two_factor_auth         (2FA secrets and backup codes)
 *   - email_verifications     (email verification tokens)
 *   - whatsapp_verifications  (WhatsApp OTP tokens)
 *   - users: phone, whatsapp_verified, email_verified, two_fa_enabled, two_fa_method, two_fa_secret columns
 *
 * Run: yoursite.com/migrate_profiles.php — then DELETE this file.
 */
define('BASE_PATH', __DIR__ . '/..');
$env = BASE_PATH . '/.env';
if (file_exists($env)) {
    foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}
$pdo = new PDO(
    'mysql:host='.(getenv('DB_HOST')?:'db').';port='.(getenv('DB_PORT')?:'3306').';dbname='.(getenv('DB_NAME')?:'byabsayee_db').';charset=utf8mb4',
    getenv('DB_USER'), getenv('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$log = [];
function run($pdo, $sql, $d) { global $log; try { $pdo->exec($sql); $log[] = "✅ $d"; } catch (Exception $e) { $log[] = "⚠️ $d — " . $e->getMessage(); } }
function has($pdo, $t) { $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?"); $s->execute([$t]); return (bool)$s->fetchColumn(); }
function hasCol($pdo, $t, $c) { $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?"); $s->execute([$t, $c]); return (bool)$s->fetchColumn(); }

// ── users table: add missing columns ─────────────────────────────────────────
$userCols = [
    ['phone',              "ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER email"],
    ['phone_country_code', "ALTER TABLE users ADD COLUMN phone_country_code VARCHAR(10) NULL DEFAULT '+880' AFTER phone"],
    ['whatsapp_number',    "ALTER TABLE users ADD COLUMN whatsapp_number VARCHAR(30) NULL AFTER phone_country_code"],
    ['whatsapp_country_code',"ALTER TABLE users ADD COLUMN whatsapp_country_code VARCHAR(10) NULL DEFAULT '+880' AFTER whatsapp_number"],
    ['email_verified',     "ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp_country_code"],
    ['whatsapp_verified',  "ALTER TABLE users ADD COLUMN whatsapp_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email_verified"],
    ['two_fa_enabled',     "ALTER TABLE users ADD COLUMN two_fa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp_verified"],
    ['two_fa_method',      "ALTER TABLE users ADD COLUMN two_fa_method ENUM('email','whatsapp','app') NULL AFTER two_fa_enabled"],
    ['two_fa_secret',      "ALTER TABLE users ADD COLUMN two_fa_secret VARCHAR(64) NULL AFTER two_fa_method"],
    ['avatar',             "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER two_fa_secret"],
    ['blood_group',        "ALTER TABLE users ADD COLUMN blood_group VARCHAR(10) NULL AFTER avatar"],
    ['gender',             "ALTER TABLE users ADD COLUMN gender ENUM('male','female','other','prefer_not') NULL AFTER blood_group"],
    ['date_of_birth',      "ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER gender"],
];
foreach ($userCols as [$col, $sql]) {
    if (!hasCol($pdo, 'users', $col)) run($pdo, $sql, "Add users.$col");
    else $log[] = "ℹ️ users.$col already exists";
}

// ── user_handles ──────────────────────────────────────────────────────────────
if (!has($pdo, 'user_handles')) {
    run($pdo, "CREATE TABLE `user_handles` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `handle`     VARCHAR(50) NOT NULL COMMENT 'Unique @handle, lowercase, alphanumeric+underscore',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_handle` (`handle`),
        UNIQUE KEY `uq_user`   (`user_id`),
        INDEX `idx_user_id`    (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create user_handles');
}

// ── user_profiles ─────────────────────────────────────────────────────────────
if (!has($pdo, 'user_profiles')) {
    run($pdo, "CREATE TABLE `user_profiles` (
        `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`            INT UNSIGNED NOT NULL,
        `bio`                TEXT NULL,
        `address`            VARCHAR(500) NULL,
        `city`               VARCHAR(100) NULL,
        `country`            VARCHAR(100) NULL,
        `profile_banner`     VARCHAR(255) NULL,
        `profile_theme_color`VARCHAR(10) NULL DEFAULT '#1a6b4a',
        `relationship_status`ENUM('single','in_relationship','married','widowed','prefer_not') NULL,
        `expertise`          TEXT NULL COMMENT 'Comma separated or JSON',
        `experience_years`   TINYINT UNSIGNED NULL,
        `designation`        VARCHAR(200) NULL COMMENT 'Auto-filled from selected book',
        `selected_book_id`   INT UNSIGNED NULL COMMENT 'Primary business to show on profile',
        `working_since`      DATE NULL COMMENT 'When joined selected business',
        `website`            VARCHAR(255) NULL,
        `profile_cv_headline`VARCHAR(300) NULL,
        `public_email`       VARCHAR(255) NULL,
        `public_phone`       VARCHAR(50) NULL,
        `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`         DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_user_id` (`user_id`),
        INDEX `idx_user_id`    (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create user_profiles');
}

// ── user_education ────────────────────────────────────────────────────────────
if (!has($pdo, 'user_education')) {
    run($pdo, "CREATE TABLE `user_education` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`     INT UNSIGNED NOT NULL,
        `institute`   VARCHAR(300) NOT NULL,
        `subject`     VARCHAR(200) NULL,
        `from_year`   YEAR NULL,
        `to_year`     YEAR NULL,
        `is_current`  TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create user_education');
}

// ── user_grades ───────────────────────────────────────────────────────────────
if (!has($pdo, 'user_grades')) {
    run($pdo, "CREATE TABLE `user_grades` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`     INT UNSIGNED NOT NULL,
        `level`       VARCHAR(100) NOT NULL COMMENT 'e.g. SSC, HSC, Bachelor',
        `result`      VARCHAR(100) NULL COMMENT 'e.g. GPA 5.00, First Class',
        `board`       VARCHAR(200) NULL,
        `year`        YEAR NULL,
        `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create user_grades');
}

// ── user_social_links ─────────────────────────────────────────────────────────
if (!has($pdo, 'user_social_links')) {
    run($pdo, "CREATE TABLE `user_social_links` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `platform`   VARCHAR(50) NOT NULL COMMENT 'e.g. linkedin, github, facebook, twitter',
        `url`        VARCHAR(500) NOT NULL,
        `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create user_social_links');
}

// ── user_profile_visibility ───────────────────────────────────────────────────
if (!has($pdo, 'user_profile_visibility')) {
    run($pdo, "CREATE TABLE `user_profile_visibility` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`         INT UNSIGNED NOT NULL,
        `field_name`      VARCHAR(100) NOT NULL COMMENT 'Column/section name to show/hide',
        `is_visible`      TINYINT(1) NOT NULL DEFAULT 0,
        `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_user_field` (`user_id`, `field_name`),
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create user_profile_visibility');
}

// ── business_handles ─────────────────────────────────────────────────────────
if (!has($pdo, 'business_handles')) {
    run($pdo, "CREATE TABLE `business_handles` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `book_id`    INT UNSIGNED NOT NULL,
        `handle`     VARCHAR(80) NOT NULL COMMENT 'Unique @businessname',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_handle`  (`handle`),
        UNIQUE KEY `uq_book_id` (`book_id`),
        INDEX `idx_book_id`     (`book_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create business_handles');
}

// ── business_profiles ─────────────────────────────────────────────────────────
if (!has($pdo, 'business_profiles')) {
    run($pdo, "CREATE TABLE `business_profiles` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `book_id`          INT UNSIGNED NOT NULL,
        `tagline`          VARCHAR(300) NULL,
        `bio`              TEXT NULL,
        `logo`             VARCHAR(255) NULL,
        `banner`           VARCHAR(255) NULL,
        `theme_color`      VARCHAR(10) NULL DEFAULT '#1a6b4a',
        `founded_year`     YEAR NULL,
        `ceo_name`         VARCHAR(200) NULL,
        `employee_count`   VARCHAR(50) NULL COMMENT 'e.g. 10-50, 100+',
        `industry`         VARCHAR(200) NULL,
        `website`          VARCHAR(300) NULL,
        `whatsapp`         VARCHAR(30) NULL,
        `whatsapp_country` VARCHAR(10) NULL,
        `email`            VARCHAR(255) NULL,
        `phone`            VARCHAR(50) NULL,
        `address`          TEXT NULL,
        `city`             VARCHAR(100) NULL,
        `country`          VARCHAR(100) NULL,
        `page_about`       LONGTEXT NULL,
        `page_terms`       LONGTEXT NULL,
        `page_privacy`     LONGTEXT NULL,
        `social_facebook`  VARCHAR(300) NULL,
        `social_instagram` VARCHAR(300) NULL,
        `social_twitter`   VARCHAR(300) NULL,
        `social_linkedin`  VARCHAR(300) NULL,
        `social_youtube`   VARCHAR(300) NULL,
        `social_tiktok`    VARCHAR(300) NULL,
        `external_links`   JSON NULL COMMENT 'Array of {label, url}',
        `photos`           JSON NULL COMMENT 'Array of uploaded photo paths',
        `visibility_flags` JSON NULL COMMENT 'Which sections are public',
        `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_book_id` (`book_id`),
        INDEX `idx_book_id`    (`book_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create business_profiles');
}

// ── two_factor_auth ───────────────────────────────────────────────────────────
if (!has($pdo, 'two_factor_auth')) {
    run($pdo, "CREATE TABLE `two_factor_auth` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`      INT UNSIGNED NOT NULL,
        `secret`       VARCHAR(64) NULL COMMENT 'TOTP secret for app-based 2FA',
        `backup_codes` JSON NULL COMMENT 'Array of hashed backup codes',
        `otp_code`     VARCHAR(10) NULL COMMENT 'Last sent OTP (email/WhatsApp)',
        `otp_expires`  DATETIME NULL,
        `verified`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = 2FA fully set up',
        `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create two_factor_auth');
}

// ── email_verifications ───────────────────────────────────────────────────────
if (!has($pdo, 'email_verifications')) {
    run($pdo, "CREATE TABLE `email_verifications` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `token`      VARCHAR(64) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `used_at`    DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_token`   (`token`),
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create email_verifications');
}

// ── whatsapp_otps ─────────────────────────────────────────────────────────────
if (!has($pdo, 'whatsapp_otps')) {
    run($pdo, "CREATE TABLE `whatsapp_otps` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `phone`      VARCHAR(30) NOT NULL,
        `otp`        VARCHAR(10) NOT NULL,
        `purpose`    ENUM('verify','2fa') NOT NULL DEFAULT 'verify',
        `expires_at` DATETIME NOT NULL,
        `used_at`    DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_phone`   (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'Create whatsapp_otps');
}

?><!DOCTYPE html><html><head><meta charset="utf-8">
<title>Migration: Profiles — Byabsayee</title>
<style>body{font-family:system-ui,sans-serif;max-width:760px;margin:60px auto;padding:0 20px}
h1{color:#1a6b4a}ul{list-style:none;padding:0}li{padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;font-family:monospace}
.done{margin-top:24px;padding:18px;background:#f0fdf4;border-radius:10px;color:#166534;font-weight:600;font-size:16px}
.warn{color:#b45309;}.ok{color:#166534;}.info{color:#1e40af;}</style></head>
<body>
<h1>📦 Migration: Profiles, Handles & 2FA</h1>
<ul><?php foreach ($log as $l):
    $cls = str_starts_with($l,'✅') ? 'ok' : (str_starts_with($l,'⚠️') ? 'warn' : 'info');
?><li class="<?=$cls?>"><?= htmlspecialchars($l) ?></li><?php endforeach; ?></ul>
<div class="done">✅ Migration complete — <strong>Delete this file from your server immediately!</strong></div>
</body></html>
