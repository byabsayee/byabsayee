<?php
// Phase 5: the four settings pages share one structure. php tests/settings_ui.php  (needs the DB seeded by tests/e2e.php)
$ok = 0; $bad = 0;
function check(string $n, $c, $d = '') { global $ok, $bad; if ($c) { $ok++; echo "  ✓ $n\n"; } else { $bad++; echo "  ✗ $n  $d\n"; } }
function get(string $uri, array $flash = []): array {
    @mkdir('/tmp/sess');
    // write the session in a child process so this script never starts a session after output
    $code = "session_save_path('/tmp/sess'); session_name('byabsayee_session'); session_id('testsess'); session_start();"
          . "\$_SESSION = ['user' => ['id' => 1, 'name' => 'Owner', 'email' => 'o@x.t'], '_csrf_token' => 'tok', '_flash' => " . var_export($flash, true) . "]; session_write_close();";
    shell_exec('php -r ' . escapeshellarg($code));
    $cmd = 'DB_HOST=127.0.0.1 DB_NAME=byab DB_USER=b DB_PASS=b php -d session.save_path=/tmp/sess -d display_errors=1 -d error_reporting=E_ALL ' . escapeshellarg(__DIR__ . '/req.php') . ' GET ' . escapeshellarg($uri);
    $out = shell_exec($cmd . ' 2>&1');
    preg_match('/@@RESULT (.*)/', $out, $m);
    $code = $m ? (json_decode($m[1], true)['code'] ?? 200) : 0;   // CLI leaves it false when nothing set one → plain 200
    return ['code' => $code === false ? 200 : $code, 'body' => $out];
}
$legacy = ['pe-wrap','pe-nav','pe-panel','bpe-wrap','bpe-nav','bpe-panel','settings-wrap','settings-nav','settings-panel','class="stab','s-sec"','class="sc"','sc-head','class="sf"','form-group','toggle-row','alert alert-','nav-group-label','class="nav-group"'];
$pages = [
  'App Settings / preferences'   => '/settings?tab=preferences',
  'App Settings / notifications' => '/settings?tab=notifications',
  'App Settings / about'         => '/settings?tab=about',
  'App Settings / faq'           => '/settings?tab=faq',
  'App Settings / help'          => '/settings?tab=help',
  'App Settings / contact'       => '/settings?tab=contact',
  'Profile / basic'      => '/profile?tab=basic',
  'Profile / profile'    => '/profile?tab=profile',
  'Profile / education'  => '/profile?tab=education',
  'Profile / experience' => '/profile?tab=experience',
  'Profile / social'     => '/profile?tab=social',
  'Profile / visibility' => '/profile?tab=visibility',
  'Profile / security'   => '/profile?tab=security',
  'Business Profile / info'       => '/books/1/business-profile?tab=info',
  'Business Profile / pages'      => '/books/1/business-profile?tab=pages',
  'Business Profile / social'     => '/books/1/business-profile?tab=social',
  'Business Profile / photos'     => '/books/1/business-profile?tab=photos',
  'Business Profile / visibility' => '/books/1/business-profile?tab=visibility',
  'Book Settings' => '/books/1/edit',
];
foreach ($pages as $name => $uri) {
    echo "\n$name\n";
    $r = get($uri); $b = $r['body'];
    check('200', $r['code'] === 200, 'code=' . $r['code']);
    check('no PHP warnings/errors', !preg_match('/(Warning|Notice|Deprecated|Fatal error|Parse error)\b/', $b), substr(strip_tags(substr($b, 0, 400)), 0, 200));
    check('shared shell present', str_contains($b, 'class="st-wrap"') && str_contains($b, 'class="st-nav"') && substr_count($b, 'st-panel') >= 1);
    check('settings.css linked', str_contains($b, 'css/settings.css'));
    check('page header present', substr_count($b, 'class="page-header"') === 1 && substr_count($b, '<h1') === 1);
    $hit = array_filter($legacy, fn($l) => str_contains($b, $l));
    check('no legacy class names left', !$hit, implode(', ', $hit));
    check('panels use .st-head', substr_count($b, 'class="st-head"') + substr_count($b, 'class="st-head ') >= 1 || str_contains($uri, 'tab=about'));
    check('no per-page <style> redefining shell', !preg_match('/<style>.*?(^|\n)\s*\.(st-wrap|st-nav|st-panel|st-head|fg|fg-row|vis-row|toggle-switch)\s*[{,]/s', $b));
}
echo "\nFlash messages show once, styled, via the layout\n";
$r = get('/settings?tab=preferences', ['success' => 'Saved ok', 'error' => 'Boom']);
check('success flash rendered', substr_count($r['body'], 'flash flash-success') === 1 && str_contains($r['body'], 'Saved ok'));
check('error flash rendered',   substr_count($r['body'], 'flash flash-error') === 1 && str_contains($r['body'], 'Boom'));
echo "\n== $ok passed, $bad failed ==\n";
exit($bad ? 1 : 0);
