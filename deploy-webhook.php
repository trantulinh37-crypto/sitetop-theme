<?php
/**
 * GitHub Webhook - Auto Deploy
 * GitHub gọi URL này khi main được push → server tự pull code mới
 */
date_default_timezone_set( 'Asia/Ho_Chi_Minh' );

$secret = 'linkngon-deploy-2026';

// Verify GitHub signature — REQUIRED. Reject if missing OR invalid (was skippable when
// the header was simply omitted, letting unauthenticated callers trigger git pull).
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (!$signature || !hash_equals($expected, $signature)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Invalid signature']));
}

// Only process push events to main
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if ($event === 'push') {
    $data = json_decode($payload, true);
    $branch = $data['ref'] ?? '';
    if ($branch !== 'refs/heads/main') {
        header('Content-Type: application/json');
        die(json_encode(['success' => true, 'skipped' => true, 'reason' => "Not main branch: $branch"]));
    }
}

$repo_path = '/home/cogfjvaa/traffictop.net/wp-content/themes/traffictop-theme';

// Ensure on main branch (not detached HEAD), then pull
$output = [];
$return = 0;
exec("cd " . escapeshellarg($repo_path) . " && git checkout main 2>&1; git pull --ff-only origin main 2>&1", $output, $return);

// Clear OPcache after deploy so site uses fresh code
if (function_exists('opcache_reset')) {
    opcache_reset();
}

header('Content-Type: application/json');
echo json_encode([
    'success' => $return === 0,
    'output' => implode("\n", $output),
    'time' => date('Y-m-d H:i:s')
]);
