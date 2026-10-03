<?php
namespace App\Services\Integration;

/**
 * Signed outbound client for calls TO the store. SSRF-safe (D1/§8): only the registered domain, https on 443,
 * DNS resolved here with every address vetted and the connection pinned to the vetted IP, no redirects,
 * short timeouts and a response size cap.
 */
final class Http
{
    /** @return array{ok:bool,status:int,json:?array,error:?string,body:string} */
    public static function store(array $conn, string $method, string $path, ?array $json = null, array $opts = []): array
    {
        $base = Util::testMode() && getenv('INTEGRATION_TEST_STORE_URL') ? rtrim((string)getenv('INTEGRATION_TEST_STORE_URL'), '/') : 'https://' . $conn['site_domain'];
        $url  = $base . '/api/erp/v1/' . ltrim($path, '/');
        $body = $json === null ? '' : Util::json($json);
        $headers = ['Accept: application/json', 'User-Agent: ByabsayeeERP/' . Util::MODULE_VERSION, 'X-Connection-Id: ' . $conn['connection_id']];
        if ($json !== null) $headers[] = 'Content-Type: application/json';
        if (!empty($opts['batch_id'])) $headers[] = 'X-Event-Batch-Id: ' . $opts['batch_id'];

        $key = Conn::apiKey($conn); $secret = Conn::secretToSite($conn);
        if (!$key || !$secret) return self::fail('The connection credentials are missing.');
        $ts = (string)time(); $nonce = bin2hex(random_bytes(16));
        $target = (string)parse_url($url, PHP_URL_PATH) . (($q = parse_url($url, PHP_URL_QUERY)) ? '?' . $q : '');
        $headers[] = 'Authorization: Bearer ' . $key;
        $headers[] = 'X-Timestamp: ' . $ts;
        $headers[] = 'X-Nonce: ' . $nonce;
        $headers[] = 'X-Signature: ' . Crypto::sign($secret, $method, $target, $ts, $nonce, $body);
        return self::request($method, $url, $body, $headers, (int)($opts['timeout'] ?? 20));
    }

    /** The domain-ownership callback: unsigned GET /.well-known/erp-verify?token=… */
    public static function verifyCall(array $conn, string $token): array
    {
        $base = Util::testMode() && getenv('INTEGRATION_TEST_STORE_URL') ? rtrim((string)getenv('INTEGRATION_TEST_STORE_URL'), '/') : 'https://' . $conn['site_domain'];
        return self::request('GET', $base . '/.well-known/erp-verify?token=' . rawurlencode($token), '', ['Accept: application/json', 'User-Agent: ByabsayeeERP/' . Util::MODULE_VERSION], 15);
    }

    private static function fail(string $m): array { return ['ok' => false, 'status' => 0, 'json' => null, 'error' => $m, 'body' => '']; }

    public static function request(string $method, string $url, string $body, array $headers, int $timeout = 20): array
    {
        $p = parse_url($url);
        $test = Util::testMode();
        if (!$p || empty($p['host'])) return self::fail('Invalid URL.');
        $host = strtolower($p['host']);
        $scheme = strtolower($p['scheme'] ?? '');
        $port = (int)($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!$test) {
            if ($scheme !== 'https' || $port !== 443) return self::fail('Only https on port 443 is allowed.');
            if ($e = Util::hostError($host)) return self::fail($e);
        }
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) $ips[] = $host;
        else {
            foreach ((array)@dns_get_record($host, DNS_A) as $r) if (!empty($r['ip'])) $ips[] = $r['ip'];
            foreach ((array)@dns_get_record($host, DNS_AAAA) as $r) if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
            if (!$ips) { $g = @gethostbynamel($host); if ($g) $ips = $g; }
        }
        if (!$ips) return self::fail('Could not resolve ' . $host . '.');
        if (!$test) foreach ($ips as $ip) if (!Util::ipIsPublic($ip)) return self::fail($host . ' resolves to a non-public address, which is not allowed.');
        $pin = $ips[0];

        $got = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST   => strtoupper($method),
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_RETURNTRANSFER  => false,
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_MAXREDIRS       => 0,
            CURLOPT_CONNECTTIMEOUT  => 8,
            CURLOPT_TIMEOUT         => $timeout,
            CURLOPT_SSL_VERIFYPEER  => !$test,
            CURLOPT_SSL_VERIFYHOST  => $test ? 0 : 2,
            CURLOPT_PROTOCOLS       => $test ? (CURLPROTO_HTTP | CURLPROTO_HTTPS) : CURLPROTO_HTTPS,
            CURLOPT_RESOLVE         => [$host . ':' . $port . ':' . (str_contains($pin, ':') ? '[' . $pin . ']' : $pin)],
            CURLOPT_WRITEFUNCTION   => function ($c, $chunk) use (&$got) { $got .= $chunk; return strlen($got) > 4 * 1024 * 1024 ? 0 : strlen($chunk); },
        ]);
        if ($body !== '' || in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($ok === false && $status === 0) return self::fail($err !== '' ? $err : 'The request failed.');
        $json = json_decode($got, true);
        if ($status >= 300 && $status < 400) return ['ok' => false, 'status' => $status, 'json' => null, 'error' => 'Redirects are not followed (the store answered with HTTP ' . $status . ').', 'body' => ''];
        $good = $status >= 200 && $status < 300;
        return ['ok' => $good, 'status' => $status, 'json' => is_array($json) ? $json : null,
            'error' => $good ? null : (is_array($json) ? ($json['error']['message'] ?? ($json['message'] ?? 'HTTP ' . $status)) : 'HTTP ' . $status), 'body' => $got];
    }
}
