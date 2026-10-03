<?php
namespace App\Services\Integration;

/**
 * Small, dependency-free helpers shared by the whole integration module (protocol v1).
 * Everything that crosses the wire follows docs/INTEGRATION.md: UUIDs, UTC ISO-8601 with 'Z',
 * money as exact 2-decimal strings.
 */
final class Util
{
    public const API_VERSION    = 'v1';
    public const MODULE_VERSION = '1.0.0';
    /** What this book can do; unknown capabilities are ignored by the peer. */
    public const CAPABILITIES = ['categories','products','variants','stock','customers','orders','payments',
        'payment_methods','coupons','taxes','delivery_charges','returns','snapshot','changes','reconcile','invoices'];
    public const ENTITIES = ['category','product','customer','order','payment','payment_method','stock_movement','coupon','tax','delivery_charge','return'];
    public const OPS = ['create','update','archive','restore','cancel','void'];
    /** scope => entities it covers */
    public const SCOPE_ENTITIES = [
        'catalog'   => ['category','product'],
        'stock'     => ['stock_movement'],
        'customers' => ['customer'],
        'orders'    => ['order','return'],
        'payments'  => ['payment','payment_method'],
        'money'     => ['coupon','tax','delivery_charge'],
    ];
    public const TS_WINDOW = 300;
    public const BATCH_MAX = 50;

    public static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
    }

    /** RFC 4122 name-based (v5) UUID — must match the store's implementation byte for byte. */
    public static function uuid5(string $namespaceUuid, string $name): string
    {
        $ns = hex2bin(str_replace('-', '', $namespaceUuid));
        $h  = sha1($ns . $name);
        $b  = hex2bin(substr($h, 0, 32));
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x50);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $x = bin2hex($b);
        return substr($x,0,8).'-'.substr($x,8,4).'-'.substr($x,12,4).'-'.substr($x,16,4).'-'.substr($x,20,12);
    }

    public static function isUuid($v): bool
    {
        return is_string($v) && (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $v);
    }

    public static function token(int $bytes = 24): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /** UTC ISO-8601 with milliseconds, e.g. 2026-09-30T10:15:00.123Z */
    public static function nowIso(): string
    {
        $t = microtime(true);
        return gmdate('Y-m-d\TH:i:s', (int)$t) . sprintf('.%03dZ', (int)(($t - floor($t)) * 1000));
    }

    public static function utcNow(): string { return gmdate('Y-m-d H:i:s'); }

    public static function isoFromDb(?string $utc): ?string
    {
        if (!$utc || str_starts_with($utc, '0000')) return null;
        $d = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new \DateTimeZone('UTC'));
        return $d ? $d->format('Y-m-d\TH:i:s') . '.000Z' : null;
    }

    public static function dbFromIso($iso): ?string
    {
        if (!is_string($iso) || $iso === '') return null;
        try { return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
        catch (\Throwable $e) { return null; }
    }

    /** Convert a UTC DB/ISO time to a local 'Y-m-d H:i:s' in the given IANA zone. */
    public static function toZone(?string $iso, string $tz): ?string
    {
        if (!$iso) return null;
        try { return (new \DateTimeImmutable($iso, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz))->format('Y-m-d H:i:s'); }
        catch (\Throwable $e) { return null; }
    }

    /** A local wall-clock time in $tz -> UTC DB datetime. */
    public static function fromZone(?string $local, string $tz): ?string
    {
        if (!$local) return null;
        try { return (new \DateTimeImmutable($local, new \DateTimeZone($tz)))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
        catch (\Throwable $e) { return null; }
    }

    public static function ts(?string $iso): ?float
    {
        if (!$iso) return null;
        try { return (float)(new \DateTimeImmutable($iso))->format('U.u'); } catch (\Throwable $e) { return null; }
    }

    /** Exact decimal string with 2 places — money never travels as a float. */
    public static function money($v): string { return number_format(round((float)$v, 2), 2, '.', ''); }

    public static function json($v): string
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    public static function jsonDecode(?string $s): array
    {
        if ($s === null || $s === '') return [];
        $d = json_decode($s, true);
        return is_array($d) ? $d : [];
    }

    /** Digits only; +880/880 prefixes become the local 0-prefixed form (same rule as the store). */
    public static function phoneNorm(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string)$phone);
        if ($d === '') return null;
        if (str_starts_with($d, '00880')) $d = substr($d, 4);
        if (str_starts_with($d, '880') && strlen($d) >= 12) $d = '0' . substr($d, 3);
        if (strlen($d) === 10 && $d[0] === '1') $d = '0' . $d;
        return substr($d, 0, 20);
    }

    /** Top-level fields of $new that differ from $old (arrays compare as a whole). */
    public static function diff(array $old, array $new): array
    {
        $out = [];
        foreach ($new as $k => $v) {
            if (!array_key_exists($k, $old) || self::json($old[$k]) !== self::json($v)) $out[$k] = $v;
        }
        return $out;
    }

    // ─── D1: only a dedicated public HTTPS host ────────────────────────────────

    public static function hostError(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '') return 'The address is empty.';
        if (filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^\[.*\]$/', $host) || preg_match('/^\d+$/', $host)) return 'IP addresses are not allowed — use a domain name.';
        if ($host === 'localhost' || preg_match('/\.(local|localhost|internal|lan|home|corp|test|invalid|example)$/', $host)) return 'Local and internal hostnames are not allowed.';
        if (!preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) return 'That is not a valid public domain name.';
        return null;
    }

    /** @return array{host:string}|string  the host on success, an error message on failure */
    public static function parseSiteUrl(string $url)
    {
        $url = trim($url);
        if ($url !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) return 'Enter the full address, starting with https://';
        $p = parse_url($url);
        if (!$p || empty($p['host'])) return 'Enter a full address such as https://shop.example.com';
        if (strtolower($p['scheme'] ?? '') !== 'https') return 'Only https:// addresses are allowed.';
        if (isset($p['user']) || isset($p['pass'])) return 'The address must not contain a username or password.';
        if (isset($p['port']) && (int)$p['port'] !== 443) return 'Only the standard HTTPS port (443) is allowed.';
        $path = trim($p['path'] ?? '', '/');
        if ($path !== '' || isset($p['query']) || isset($p['fragment'])) return 'Use a domain or subdomain dedicated to the website — no path after the domain.';
        if ($e = self::hostError($p['host'])) return $e;
        return ['host' => strtolower($p['host'])];
    }

    /** Is this IP publicly routable? Blocks private, loopback, link-local, CGNAT, metadata, mapped-IPv6 tricks. */
    public static function ipIsPublic(string $ip): bool
    {
        if (stripos($ip, '::ffff:') === 0 && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $ip = substr($ip, 7);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $l = ip2long($ip);
            foreach (['100.64.0.0/10','169.254.0.0/16','192.0.0.0/24','198.18.0.0/15','192.0.2.0/24','198.51.100.0/24','203.0.113.0/24','224.0.0.0/3'] as $cidr) {
                [$net, $bits] = explode('/', $cidr);
                $mask = -1 << (32 - (int)$bits);
                if (($l & $mask) === (ip2long($net) & $mask)) return false;
            }
            return true;
        }
        $bin = @inet_pton($ip);
        if ($bin === false) return false;
        $b0 = ord($bin[0]); $b1 = ord($bin[1]);
        if (($b0 & 0xfe) === 0xfc) return false;                 // fc00::/7 unique local
        if ($b0 === 0xfe && ($b1 & 0xc0) === 0x80) return false; // fe80::/10 link-local
        if ($b0 === 0xff) return false;                           // multicast
        return true;
    }

    /**
     * Dev-only escape hatch so the protocol can be tested against a store on localhost.
     * Needs BOTH APP_ENV=testing and INTEGRATION_TEST_MODE=allow-insecure-localhost; never set in production.
     */
    public static function testMode(): bool
    {
        return getenv('INTEGRATION_TEST_MODE') === 'allow-insecure-localhost' && (getenv('APP_ENV') ?: '') === 'testing';
    }

    public static function requestIsHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    }

    public static function clientIp(): string
    {
        // Behind Cloudflare / a reverse proxy the real client is in these headers; REMOTE_ADDR is the proxy.
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $k) {
            if (!empty($_SERVER[$k]) && filter_var(trim($_SERVER[$k]), FILTER_VALIDATE_IP)) return trim($_SERVER[$k]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
