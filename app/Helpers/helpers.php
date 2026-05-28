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
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
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

// FIX: asset() now uses the actual request host instead of APP_URL.
// This means CSS/JS links work from any IP or hostname without touching .env.
function asset(string $path): string
{
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function now(): string
{
    return date('Y-m-d H:i:s');
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

function format_money(float $amount, string $symbol = '৳'): string
{
    return $symbol . number_format($amount, 2);
}

function format_date(string $date): string
{
    return date('d M Y', strtotime($date));
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
