<?php
// One HTTP request through the real front controller (CLI). usage: php req.php METHOD URI '<json post>'
[$_, $method, $uri, $post] = $argv + [null, 'GET', '/', '{}'];
$u = parse_url($uri);
$_SERVER['REQUEST_METHOD'] = $method; $_SERVER['REQUEST_URI'] = $uri; $_SERVER['HTTP_HOST'] = 'localhost';
parse_str($u['query'] ?? '', $_GET);
$_POST = json_decode($post, true) ?: []; $_POST['_csrf'] = 'tok';
$_COOKIE['byabsayee_session'] = getenv('TEST_SID') ?: 'testsess';
register_shutdown_function(function () {
    $loc = null; foreach (headers_list() as $h) if (stripos($h, 'Location:') === 0) $loc = trim(substr($h, 9));
    echo "\n@@RESULT " . json_encode(['code' => http_response_code(), 'loc' => $loc, 'flash' => $_SESSION['_flash'] ?? null]) . "\n";
});
require dirname(__DIR__) . '/public/index.php';
