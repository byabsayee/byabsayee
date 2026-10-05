<?php
namespace App\Controllers;

use App\Helpers\Database;
use App\Services\Integration\{Conflicts, Conn, ImportService, Lifecycle, Log, Mappers, Outbox, OrderService, Reconcile, Util};

/** Book Settings → Online store: connect a website, watch the sync, settle what needs a person. */
class IntegrationController
{
    private function book(array $p, string $action = 'manage'): array
    {
        if (guest()) redirect('/login');
        $book = book_for_user($p['id'], 'business');
        if (!$book) { http_response_code(404); require BASE_PATH . '/views/errors/404.php'; exit; }
        if (!book_can($book, 'integrations', $action)) abort_403();
        return $book;
    }

    private function back(array $book, array $flash): void { redirect('/books/' . $book['id'] . '/integrations', $flash); }

    private function conn(array $book): ?array { return Conn::forBook((int)$book['id']); }

    public function index(array $p): void
    {
        $book = $this->book($p, 'view');
        $conn = $this->conn($book);
        $creds = $_SESSION['integration_creds'] ?? null;
        unset($_SESSION['integration_creds']);
        $canManage = book_can($book, 'integrations', 'manage');
        $data = ['counts' => null, 'conflicts' => [], 'dead' => [], 'log' => [], 'shared' => Conn::DEFAULT_SHARED, 'site' => [], 'batches' => [], 'batch' => null, 'recon' => null];
        if ($conn) {
            $data['counts']    = Conn::counts((int)$conn['id']);
            $data['conflicts'] = Conflicts::open((int)$conn['id']);
            $data['dead']      = Database::query("SELECT id,entity,op,attempts,last_error,created_at FROM sync_outbox WHERE conn_id=? AND status='dead' ORDER BY id DESC LIMIT 30", [$conn['id']]);
            $data['log']       = Database::query('SELECT direction,kind,ok,summary,created_at FROM sync_log WHERE conn_id=? ORDER BY id DESC LIMIT 40', [$conn['id']]);
            $data['shared']    = Conn::shared($conn);
            $data['site']      = Util::jsonDecode($conn['site_info']);
            $data['batches']   = ImportService::recent((int)$conn['id']);
            $bid = (int)($_GET['batch'] ?? 0);
            $data['batch']     = $bid ? ImportService::get((int)$conn['id'], $bid) : null;
            $data['recon']     = !empty($conn['last_reconcile']) ? Util::jsonDecode($conn['last_reconcile']) : null;
        }
        $bookCurrency = Conn::bookCurrency($book);
        $hasInvoices  = Conn::bookHasInvoices((int)$book['id']);
        $weakKey      = \App\Services\Integration\Crypto::usesDefaultKey();
        extract($data);   // $counts, $conflicts, $dead, $log, $shared, $site for the view
        require BASE_PATH . '/views/books/integrations.php';
    }

    public function connect(array $p): void
    {
        $book = $this->book($p);
        csrf_verify();
        if (trim((string)($_POST['site_url'] ?? '')) === '') {   // the simple path: no address, just a connection code to paste into the website
            $r = Lifecycle::createOpen($book, (int)auth()['id']);
            if (is_string($r)) $this->back($book, ['error' => $r]);
            $_SESSION['integration_creds'] = $r['credentials'] + ['domain' => $r['conn']['site_domain'], 'connection_code' => $r['connection_code']];
            $this->back($book, ['success' => 'Your connection code is ready. Paste it into your website — it works once and expires in 30 minutes.']);
        }
        $tax = null;
        if (($_POST['authority'] ?? 'book') === 'book') {
            $tax = ['enabled' => !empty($_POST['tax_enabled']), 'rate' => number_format(max(0, min(100, (float)($_POST['tax_rate'] ?? 0))), 3, '.', ''),
                'inclusive' => !empty($_POST['tax_inclusive']), 'label' => mb_substr(trim((string)($_POST['tax_label'] ?? 'Tax')) ?: 'Tax', 0, 40)];
        }
        $r = Lifecycle::create($book, (string)($_POST['site_url'] ?? ''), (string)($_POST['authority'] ?? 'book'), (array)($_POST['scopes'] ?? []), (int)auth()['id'], $tax);
        if (is_string($r)) $this->back($book, ['error' => $r]);
        $_SESSION['integration_creds'] = $r['credentials'] + ['domain' => $r['conn']['site_domain']];
        $this->back($book, ['success' => 'Link created. Now pair it from the website — the details are shown below once.']);
    }

    public function verify(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->conn($book);
        if (!$c || $c['status'] !== 'verifying') $this->back($book, ['error' => 'Nothing is waiting to be verified. Pair from the website first.']);
        [$ok, $msg] = Lifecycle::verifyAndComplete($c);
        $this->back($book, [$ok ? 'success' : 'error' => $msg]);
    }

    public function syncNow(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->conn($book);
        if (!$c || $c['status'] !== 'active') $this->back($book, ['error' => 'The link is not active.']);
        Database::run("UPDATE sync_outbox SET next_attempt_at=UTC_TIMESTAMP() WHERE conn_id=? AND status='pending'", [$c['id']]);
        $s = Outbox::flush($c, 10);
        $this->back($book, ['success' => "Sync ran: {$s['sent']} sent, {$s['failed']} will retry, {$s['dead']} failed for good."]);
    }

    public function pause(array $p): void { $book = $this->book($p); csrf_verify(); if ($c = $this->conn($book)) Lifecycle::pause($c); $this->back($book, ['success' => 'Sync paused. Nothing is lost — changes wait in the queue.']); }
    public function resume(array $p): void { $book = $this->book($p); csrf_verify(); if ($c = $this->conn($book)) Lifecycle::resume($c); $this->back($book, ['success' => 'Sync resumed.']); }

    public function rotate(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->conn($book);
        [$ok, $msg] = $c ? Lifecycle::rotate($c) : [false, 'No link.'];
        $this->back($book, [$ok ? 'success' : 'error' => $msg]);
    }

    public function disconnect(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->conn($book);
        $msg = $c ? Lifecycle::disconnect($c) : 'Nothing to disconnect.';
        $this->back($book, ['success' => $msg . ' All records stay in the book. You can reconnect the same website later and sync resumes.']);
    }

    public function remove(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->conn($book);
        if (!$c || !Lifecycle::remove($c)) $this->back($book, ['error' => 'Only a disconnected link can be removed.']);
        $this->back($book, ['success' => 'Link removed. The book is free to connect a different website.']);
    }

    public function settings(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->conn($book);
        if (!$c || !in_array($c['status'], ['active', 'paused'], true)) $this->back($book, ['error' => 'Connect a website first.']);
        $s = Conn::shared($c);
        $s['tax'] = ['enabled' => !empty($_POST['tax_enabled']), 'rate' => number_format(max(0, min(100, (float)($_POST['tax_rate'] ?? 0))), 3, '.', ''),
            'inclusive' => !empty($_POST['tax_inclusive']), 'label' => mb_substr(trim((string)($_POST['tax_label'] ?? 'Tax')) ?: 'Tax', 0, 40)];
        foreach (['inside_dhaka', 'suburbs', 'outside_dhaka', 'free_weight_kg', 'extra_per_kg'] as $k) $s['delivery'][$k] = Util::money(max(0, (float)($_POST['d_' . $k] ?? $s['delivery'][$k])));
        Conn::setShared((int)$c['id'], $s);
        $c = Conn::find((int)$c['id']);
        Outbox::emit($c, 'tax', 1);
        foreach ([1, 2, 3] as $z) Outbox::emit($c, 'delivery_charge', $z);
        $this->back($book, ['success' => 'Tax and delivery settings saved and sent to the website.']);
    }

    // ─── history import (optional) ────────────────────────────────────────────

    private function activeConn(array $book): array
    {
        $c = $this->conn($book);
        if (!$c || !in_array($c['status'], ['active', 'paused'], true)) $this->back($book, ['error' => 'Connect a website first.']);
        return $c;
    }

    public function importPlan(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->activeConn($book);
        [$ok, $msg, $bid] = ImportService::plan($c, (string)($_POST['direction'] ?? ''), (array)($_POST['entities'] ?? []),
            trim((string)($_POST['date_from'] ?? '')) ?: null, trim((string)($_POST['date_to'] ?? '')) ?: null, (string)(auth()['name'] ?? 'user'));
        redirect('/books/' . $book['id'] . '/integrations' . ($ok ? '?batch=' . $bid : '') . '#import', [$ok ? 'success' : 'error' => $msg]);
    }

    public function importAction(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->activeConn($book);
        $id = (int)$p['bid']; $a = (string)$p['action']; $msg = null;
        if ($a === 'start')         { $msg = ImportService::start($c, $id); ImportService::step($c, $id, 25); }
        elseif ($a === 'step')      { ImportService::step($c, $id, 100); $msg = 'Processed another chunk.'; }
        elseif ($a === 'pause')     { ImportService::pause((int)$c['id'], $id); $msg = 'Import paused.'; }
        elseif ($a === 'resume')    { ImportService::resume((int)$c['id'], $id); $msg = 'Import resumed. It continues in the background.'; }
        elseif ($a === 'rollback')  { $msg = ImportService::rollback($c, $id, (string)(auth()['name'] ?? 'user')); }
        else $this->back($book, ['error' => 'Unknown action.']);
        redirect('/books/' . $book['id'] . '/integrations?batch=' . $id . '#import', ['success' => $msg]);
    }

    public function reconcileNow(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->conn($book);
        if (!$c || $c['status'] !== 'active') $this->back($book, ['error' => 'The link is not active.']);
        $r = Reconcile::run($c);
        $this->back($book, $r['ok'] ? ['success' => 'Reconcile finished: ' . $r['drift_total'] . ' difference(s), ' . $r['stock']['corrected'] . ' stock correction(s).'] : ['error' => 'Reconcile could not finish: ' . $r['error']]);
    }

    public function retry(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->conn($book);
        if ($c) { if (!empty($_POST['id'])) Outbox::retry((int)$c['id'], (int)$_POST['id']); else Outbox::retryAllDead((int)$c['id']); }
        $this->back($book, ['success' => 'Queued for another try.']);
    }

    public function discard(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        if ($c = $this->conn($book)) Outbox::discard((int)$c['id'], (int)($_POST['id'] ?? 0));
        $this->back($book, ['success' => 'Discarded. The row is kept in the log, not deleted.']);
    }

    public function resolve(array $p): void
    {
        $book = $this->book($p); csrf_verify();
        $c = $this->conn($book);
        if (!$c) $this->back($book, ['error' => 'No link.']);
        $cid = (int)$p['cid']; $action = (string)($_POST['action'] ?? 'dismiss'); $by = (string)(auth()['name'] ?? 'user');
        $row = Database::row('SELECT kind FROM sync_conflicts WHERE id=? AND conn_id=?', [$cid, $c['id']]);
        if (!$row) $this->back($book, ['error' => 'Conflict not found.']);
        if ($row['kind'] === 'customer_match' && in_array($action, ['link', 'separate'], true)) {
            $msg = Conflicts::resolveCustomerMatch($c, $cid, $action, $by);
            $this->back($book, ['success' => $msg]);
        }
        Conflicts::resolveRow($cid, 'acknowledged', $by);
        $this->back($book, ['success' => 'Marked as reviewed.']);
    }

    /** Move an online order along (processing → shipped → delivered) or cancel it; the website is told. */
    public function fulfilment(array $p): void
    {
        if (guest()) redirect('/login');
        $book = book_for_user($p['id'], 'business');
        if (!$book || !book_can($book, 'invoices', 'edit')) abort_403();
        csrf_verify();
        $inv = Database::row('SELECT * FROM invoices WHERE id=? AND book_id=? AND type="sale" AND deleted_at IS NULL AND (source="online_store" OR sync_to_store=1)', [$p['invoice_id'], $book['id']]);
        $to = (string)($_POST['status'] ?? '');
        $back = '/books/' . $book['id'] . '/invoices/' . $p['invoice_id'];
        if (!$inv || !in_array($to, OrderService::FULFILMENT, true)) redirect($back, ['error' => 'That order cannot be changed.']);
        $conn = Conn::linkedForBook((int)$book['id']);
        $pdo = Database::get(); $pdo->beginTransaction();
        try {
            OrderService::setFulfilment($conn ?: ['id' => 0, 'book_id' => $book['id']], (int)$inv['id'], $to);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); redirect($back, ['error' => 'Could not change the order: ' . $e->getMessage()]); }
        if ($conn) Outbox::emit($conn, 'order', (int)$inv['id'], $to === 'cancelled' ? 'cancel' : 'update');
        redirect($back, ['success' => 'Order marked ' . $to . '.']);
    }
}
