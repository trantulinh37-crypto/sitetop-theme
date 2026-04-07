<?php
/**
 * GitHub Webhook - Auto Deploy
 * GitHub gọi URL này khi main được push → server tự pull code mới
 */
date_default_timezone_set( 'Asia/Ho_Chi_Minh' );

// Secret key để xác thực webhook (phải match với GitHub webhook secret)
$secret = 'linkngon-deploy-2026';

// Verify GitHub signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if ($signature) {
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $signature)) {
        http_response_code(403);
        die('Invalid signature');
    }
}

// Only process push events to main
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if ($event === 'push') {
    $data = json_decode($payload, true);
    $branch = $data['ref'] ?? '';
    if ($branch !== 'refs/heads/main') {
        die('Not main branch');
    }
}

// Run git pull
$repo_path = __DIR__;
$output = [];
$return = 0;
exec("cd " . escapeshellarg($repo_path) . " && git pull origin main 2>&1", $output, $return);

header('Content-Type: application/json');
echo json_encode([
    'success' => $return === 0,
    'output' => implode("\n", $output),
    'time' => date('Y-m-d H:i:s')
]);
