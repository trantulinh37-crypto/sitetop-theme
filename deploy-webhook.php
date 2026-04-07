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
        die(json_encode(['success' => false, 'error' => 'Invalid signature']));
    }
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

// Hardcoded path (same as deploy.php) — __DIR__ có thể sai nếu file bị symlink
$repo_path = '/home/wlcjwhje/linkngon.top/wp-content/themes/linkngon-theme';

$output = [];
$return = 0;
exec("cd " . escapeshellarg($repo_path) . " && git fetch origin +refs/heads/main:refs/remotes/origin/main 2>&1 && git reset --hard origin/main 2>&1", $output, $return);

header('Content-Type: application/json');
echo json_encode([
    'success' => $return === 0,
    'exit_code' => $return,
    'output' => implode("\n", $output),
    'repo_path' => $repo_path,
    'time' => date('Y-m-d H:i:s')
]);
