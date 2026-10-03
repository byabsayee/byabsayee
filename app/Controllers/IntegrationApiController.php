<?php
namespace App\Controllers;

use App\Helpers\Database;
use App\Services\Integration\{ApiAuth, ApiError, Conn, Crypto, Inbox, InvoiceBridge, Lifecycle, Log, RateLimit, Snapshot, Util};

/**
 * /api/v1/integrations/*  — the machine API the online store talks to (protocol v1, docs/INTEGRATION.md).
 * No session, no CSRF, JSON in and JSON out; every failure carries a stable error code.
 */
class IntegrationApiController
{
    private function out(int $status, array $body, array $headers = [], ?callable $after = null): never
    {
        http_response_code($status);
        foreach ($headers as $h) header($h);
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($after) {
            if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); $after(); }
            // Without FastCGI (dev server) the worker / "Verify now" button finishes the job instead.
        }
        exit;
    }

    private function guard(callable $fn): void
    {
        try { $fn(); }
        catch (ApiError $e) {
            $this->out($e->httpStatus, ['ok' => false, 'error' => array_merge(['code' => $e->errCode, 'message' => $e->getMessage()], $e->extra)],
                isset($e->extra['retry_after']) ? ['Retry-After: ' . (int)$e->extra['retry_after']] : []);
        }
    }

    private function json(string $body): array
    {
        $j = $body === '' ? [] : json_decode($body, true);
        if (!is_array($j)) throw new ApiError('invalid_json', 'The request body is not valid JSON.', 400);
        return $j;
    }

    private function book(array $conn): array { return Database::row('SELECT * FROM books WHERE id=?', [$conn['book_id']]); }

    // ── POST connect/handshake ───────────────────────────────────────────────
    public function handshake(array $p = []): void
    {
        $this->guard(function () {
            $cid = (string)($_SERVER['HTTP_X_CONNECTION_ID'] ?? '');
            if (Util::isUuid($cid)) {                                   // manual credentials: a normal signed request
                ['conn' => $conn, 'body' => $body] = ApiAuth::authenticate(['pending', 'verifying']);
                $req = $this->json($body);
                $out = Lifecycle::handshake($conn, $req, false);
                $this->out(200, $out, [], fn () => $this->finish($conn['id']));
            }
            // Pairing-code flow: nothing to sign with yet, so the code itself is the credential (single use, 30 min).
            $ip = Util::clientIp();
            if (!Util::requestIsHttps() && !Util::testMode()) throw new ApiError('https_required', 'This endpoint only accepts HTTPS.', 400);
            if (!RateLimit::ok('pair:' . $ip, 10)) throw new ApiError('rate_limited', 'Too many pairing attempts.', 429, ['retry_after' => 60]);
            $raw = (string)file_get_contents('php://input', false, null, 0, ApiAuth::MAX_BODY + 1);
            if (strlen($raw) > ApiAuth::MAX_BODY) throw new ApiError('payload_too_large', 'Request body too large.', 413);
            $req = $this->json($raw);
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($req['pairing_code'] ?? '')));
            $conn = null;
            if (strlen($code) === 12) {
                $canon = implode('-', str_split($code, 4));
                $conn = Database::row("SELECT * FROM integration_connections WHERE pairing_hash=? AND pairing_expires_at > UTC_TIMESTAMP() AND status IN ('pending','verifying')", [hash('sha256', $canon)]);
            }
            if (!$conn) {
                RateLimit::ok('badpair:' . $ip, 5);
                Log::write(null, 'in', 'auth', 'Rejected pairing attempt (bad or expired code)', false, null, 401, ['ip' => $ip]);
                throw new ApiError('bad_pairing_code', 'The pairing code is wrong, expired or already used.', 401);
            }
            $out = Lifecycle::handshake($conn, $req, true);
            $this->out(200, $out, [], fn () => $this->finish($conn['id']));
        });
    }

    /** After the handshake response is on its way: prove the domain and complete the link. */
    private function finish(int $connId): void
    {
        try { usleep(700000); Lifecycle::verifyAndComplete(Conn::find($connId)); }
        catch (\Throwable $e) { error_log('[integration verify] ' . $e->getMessage()); }
    }

    // ── POST events ──────────────────────────────────────────────────────────
    public function events(array $p = []): void
    {
        $this->guard(function () {
            ['conn' => $conn, 'body' => $body] = ApiAuth::authenticate(['active']);
            $this->out(200, Inbox::batch($conn, $this->json($body)));
        });
    }

    // ── GET snapshot/{entity} ────────────────────────────────────────────────
    public function snapshot(array $p): void
    {
        $this->guard(function () use ($p) {
            ['conn' => $conn] = ApiAuth::authenticate(['active', 'verifying']);
            $this->out(200, Snapshot::page($conn, (string)$p['entity'], (int)($_GET['cursor'] ?? 0), (int)($_GET['limit'] ?? 100)));
        });
    }

    // ── GET changes ──────────────────────────────────────────────────────────
    public function changes(array $p = []): void
    {
        $this->guard(function () {
            ['conn' => $conn] = ApiAuth::authenticate(['active']);
            $this->out(200, Snapshot::changes($conn, (int)($_GET['cursor'] ?? 0), (int)($_GET['limit'] ?? 100)));
        });
    }

    // ── GET status ───────────────────────────────────────────────────────────
    public function status(array $p = []): void
    {
        $this->guard(function () {
            ['conn' => $conn] = ApiAuth::authenticate(['active', 'verifying']);
            $this->out(200, Snapshot::status($conn, $this->book($conn)));
        });
    }

    // ── POST connect/rotate (store-initiated) ────────────────────────────────
    public function rotate(array $p = []): void
    {
        $this->guard(function () {
            ['conn' => $conn, 'body' => $body] = ApiAuth::authenticate(['active']);
            $this->json($body);
            $this->out(200, Lifecycle::rotateInbound($conn));
        });
    }

    // ── POST disconnect (store-initiated) ────────────────────────────────────
    public function disconnect(array $p = []): void
    {
        $this->guard(function () {
            ['conn' => $conn] = ApiAuth::authenticate(['active', 'verifying']);
            Lifecycle::markRevoked($conn, 'The website disconnected.');
            $this->out(200, ['ok' => true, 'status' => 'revoked']);
        });
    }
    public function invoicePdf(array $p): void { $p['pdf'] = true; $this->invoice($p); }

    // ── GET invoice/{order_uuid}  (+ /pdf) ───────────────────────────────────
    /** The Book's invoice for one of the store's orders: JSON facts, or the PDF itself. */
    public function invoice(array $p): void
    {
        $this->guard(function () use ($p) {
            ['conn' => $conn] = ApiAuth::authenticate(['active']);
            if (!Conn::scopeAllows($conn, 'order')) throw new ApiError('scope_denied', 'The connection has no scope for orders.', 403);
            $uuid = (string)($p['uuid'] ?? '');
            if (!Util::isUuid($uuid)) throw new ApiError('invalid_request', 'The order id must be a UUID.', 400);
            $info = InvoiceBridge::forOrderUuid($conn, $uuid);
            if (!$info) throw new ApiError('not_found', 'There is no invoice for this order here.', 404);
            if (!empty($p['pdf'])) {
                $pdf = InvoiceBridge::render($conn, $info['invoice_id']);
                if ($pdf === null || $pdf === '') throw new ApiError('server_error', 'The invoice could not be rendered.', 500);
                http_response_code(200);
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($pdf));
                header('Cache-Control: no-store');
                echo $pdf;
                exit;
            }
            $this->out(200, ['ok' => true, 'invoice' => $info]);
        });
    }
}
