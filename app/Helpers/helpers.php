<?php
function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require BASE_PATH . '/config/app.php';
    }
    $keys  = explode('.', $key);
    $value = $config;
    foreach ($keys as $k) {
        if (!isset($value[$k])) return $default;
        $value = $value[$k];
    }
    return $value;
}

function invoiceNumToWords(int $n, string $code = 'BDT'): string {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
             'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
             'Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $cur  = ['BDT'=>'Taka','USD'=>'Dollar','EUR'=>'Euro','GBP'=>'Pound',
             'INR'=>'Rupee','SAR'=>'Riyal','AED'=>'Dirham'][$code] ?? $code;
    $conv = function(int $n) use ($ones,$tens,&$conv): string {
        if ($n<20)       return $ones[$n];
        if ($n<100)      return $tens[(int)($n/10)].($n%10?' '.$ones[$n%10]:'');
        if ($n<1000)     return $ones[(int)($n/100)].' Hundred'.($n%100?' '.$conv($n%100):'');
        if ($n<100000)   return $conv((int)($n/1000)).' Thousand'.($n%1000?' '.$conv($n%1000):'');
        if ($n<10000000) return $conv((int)($n/100000)).' Lakh'.($n%100000?' '.$conv($n%100000):'');
        return $conv((int)($n/10000000)).' Crore'.($n%10000000?' '.$conv($n%10000000):'');
    };
    return $n===0 ? 'Zero '.$cur : trim($conv($n)).' '.$cur;
}

function dd(mixed ...$vars): never
{
    echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:20px;font-size:13px;overflow:auto">';
    foreach ($vars as $var) { var_dump($var); }
    echo '</pre>';
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url, array $flash = []): never
{
    foreach ($flash as $key => $value) { flash($key, $value); }
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string)$token)) {
        $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                  || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if ($wantsJson) {
            json_response(['ok' => false, 'error' => 'Your session expired. Please reload the page and try again.'], 403);
        }
        // Send people back to the page they came from with a clear message instead of a dead-end text page.
        $back = '/';
        $ref  = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '') {
            $p = parse_url($ref);
            if (!empty($p['path']) && (empty($p['host']) || strcasecmp((string)$p['host'], explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]) === 0)) {
                $back = $p['path'] . (isset($p['query']) ? '?' . $p['query'] : '');
            }
        }
        if (!isset($_SESSION)) { http_response_code(403); die('Invalid CSRF token. Please go back and try again.'); }
        redirect($back, ['error' => 'That form was out of date or your session expired. Nothing was saved — please try again.']);
    }
}

function auth(): ?array
{
    return $_SESSION['user'] ?? null;
}

function guest(): bool
{
    return !isset($_SESSION['user']);
}

/**
 * The slice of a `users` row that is safe to keep in $_SESSION. Never keep the password hash,
 * the authenticator secret or the e-mail verification token in the session file.
 */
function session_user_from_row(array $row): array
{
    unset($row['password_hash'], $row['two_fa_secret'], $row['verification_token']);
    return $row;
}

/** Reload the logged-in user's row into the session (call after any change to the users table). */
function refresh_session_user(int $userId): void
{
    $row = \App\Helpers\Database::row('SELECT * FROM users WHERE id=?', [$userId]);
    if ($row) $_SESSION['user'] = session_user_from_row($row);
}

/** Register the current PHP session in "Active sessions" (idempotent). */
function track_session(int $userId): void
{
    try {
        \App\Helpers\Database::run(
            'INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_active_at)
             VALUES (?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), last_active_at=NOW(), ip_address=VALUES(ip_address)',
            [$userId, session_id(), $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255)]
        );
        $_SESSION['_sess_tracked'] = 1;
    } catch (\Throwable $e) { /* table may not exist yet */ }
}

/**
 * Physically destroy another PHP session (used by "sign out this device / all other devices"),
 * so that browser is logged out on its very next request. The current session is left untouched.
 */
function destroy_session_by_id(string $sid): void
{
    if ($sid === '' || $sid === session_id() || !preg_match('/^[A-Za-z0-9,-]{16,128}$/', $sid)) return;
    $current = session_id();
    session_write_close();
    session_id($sid);
    session_start(['use_cookies' => 0, 'use_only_cookies' => 1, 'cache_limiter' => '']);
    $_SESSION = [];
    session_destroy();
    session_id($current);
    session_start(['use_cookies' => 0, 'use_only_cookies' => 1, 'cache_limiter' => '']);
}

// FIX: asset() now uses the actual request host instead of APP_URL.
// This means CSS/JS links work from any IP or hostname without touching .env.
function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function asset(string $path): string
{
    // nginx itself speaks plain HTTP behind the tunnel/proxy, so also trust X-Forwarded-Proto —
    // otherwise CSS/JS links come out as http:// on an https:// page and browsers block them.
    $scheme = request_is_https() ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Format a date for on-screen display using the logged-in user's saved
 * date_format preference (Settings → Preferences). Falls back to 'd M Y'
 * for guests or if no value is stored yet.
 * Accepts a date/datetime string, or a unix timestamp.
 */
function fmt_date($value, ?string $format = null): string
{
    if (empty($value)) return '';
    $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
    if ($ts === false || $ts === null) return '';
    $format = $format ?? ($_SESSION['user']['date_format'] ?? 'd M Y');
    return date($format, $ts);
}

/**
 * Same as fmt_date() but appends a time portion — for "created at" / "updated at"
 * style timestamps. Date part respects the user's preference; time stays h:i A.
 */
function fmt_datetime($value, string $timeFormat = 'h:i A'): string
{
    if (empty($value)) return '';
    $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
    if ($ts === false || $ts === null) return '';
    $dateFormat = $_SESSION['user']['date_format'] ?? 'd M Y';
    return date($dateFormat, $ts) . ', ' . date($timeFormat, $ts);
}

function set_timezone_from_cookie(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $raw = $_COOKIE['byabsayee_tz'] ?? '';
    if ($raw === '') return;

    $tz = rawurldecode($raw);
    if (!preg_match('/^[A-Za-z0-9_\/+\-]+$/', $tz)) return;

    try {
        new \DateTimeZone($tz); // throws on invalid
        date_default_timezone_set($tz);
    } catch (\Throwable $e) {}
}
function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/** A real calendar date in Y-m-d, or $fallback (default: null) when the input is empty / malformed. */
function valid_date(?string $value, ?string $fallback = null): ?string
{
    $value = trim((string)$value);
    $d = \DateTime::createFromFormat('!Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : $fallback;
}

/** A time of day as H:i:s (accepts H:i or H:i:s), or null when empty / malformed. */
function valid_time(?string $value): ?string
{
    $value = trim((string)$value);
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) return null;
    return sprintf('%s:%s:%s', $m[1], $m[2], $m[3] ?? '00');
}

function format_money(float $amount, string $symbol = '৳'): string
{
    return $symbol . number_format($amount, 2);
}

function format_date(string $date): string
{
    return fmt_date($date);
}

function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function old(string $key, string $default = ''): string
{
    $value = $_SESSION['_old_input'][$key] ?? $default;
    unset($_SESSION['_old_input'][$key]);
    return e($value);
}

function set_old(array $data): void
{
    $_SESSION['_old_input'] = $data;
}

function activePage(string $page): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = trim($uri, '/');
    return ($uri === $page || str_starts_with($uri, $page . '/')) ? 'active' : '';
}
// =============================================================================
// Book membership helpers
// =============================================================================

/**
 * Fetch a book the current user owns OR is an active member of.
 * Returns null if not found / no access.
 * $type = 'business' | 'personal' | null (any)
 */
function book_for_user(string|int $id, ?string $type = null): ?array
{
    $uid = auth()['id'] ?? null;
    if (!$uid) return null;

    $typeClause = $type ? ' AND b.type=?' : '';
    $params     = $type
        ? [(int)$id, $type, $uid, $uid]
        : [(int)$id, $uid, $uid];

    return \App\Helpers\Database::row(
        "SELECT b.* FROM books b
         WHERE b.id=? AND b.deleted_at IS NULL{$typeClause}
           AND (
               b.user_id=?
               OR EXISTS (
                   SELECT 1 FROM book_members bm
                   WHERE bm.book_id=b.id AND bm.user_id=? AND bm.status='active'
               )
           )",
        $params
    );
}

/**
 * Return the current user's permissions array for a book.
 * Owners get a special ['__owner__' => true] marker.
 * Members get their json-decoded permissions array.
 * Non-members get [].
 */
function book_member_perms(array $book): array
{
    $uid = auth()['id'] ?? null;
    if (!$uid) return [];
    if ((int)$book['user_id'] === (int)$uid) return ['__owner__' => true];

    $m = \App\Helpers\Database::row(
        'SELECT permissions FROM book_members WHERE book_id=? AND user_id=? AND status="active"',
        [$book['id'], $uid]
    );
    return $m ? (json_decode($m['permissions'] ?? '{}', true) ?? []) : [];
}

/**
 * Check if the current user has a specific permission on a book.
 * Owners always pass. Non-members always fail.
 */
function book_can(array $book, string $module, string $action): bool
{
    $perms = book_member_perms($book);
    if (!empty($perms['__owner__'])) return true;
    return !empty($perms[$module][$action]);
}

/**
 * Halt with a 403 Forbidden response.
 */
function abort_403(): never
{
    http_response_code(403);
    require BASE_PATH . '/views/errors/403.php';
    exit;
}
