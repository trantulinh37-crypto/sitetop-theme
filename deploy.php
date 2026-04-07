<?php
date_default_timezone_set( 'Asia/Ho_Chi_Minh' );
$secret    = 'linkngon_top_deploy_key';
$repo_path = '/home/wlcjwhje/linkngon.top/wp-content/themes/linkngon-theme';
$branch    = 'main';
$log_file  = '/home/wlcjwhje/linkngon.top/deploy-log.txt';

function deploy_log($msg) { global $log_file; file_put_contents($log_file, "[".date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND|LOCK_EX); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die('Method not allowed'); }

$payload = file_get_contents('php://input');
if (empty($payload)) { http_response_code(400); deploy_log('ERROR: Empty payload'); die('Empty payload'); }

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (!hash_equals($expected, $signature)) { http_response_code(403); deploy_log('ERROR: Invalid signature'); die('Invalid signature'); }

$data = json_decode($payload, true);
$ref = $data['ref'] ?? '';
if ($ref !== 'refs/heads/' . $branch) { deploy_log("SKIP: Push to $ref, not $branch"); echo "Skipped"; exit; }

$pusher = $data['pusher']['name'] ?? 'unknown';
$message = $data['head_commit']['message'] ?? '';
deploy_log("DEPLOY START: $pusher pushed " . count($data['commits'] ?? []) . " commit(s) - $message");

$output = []; $return = 0;
exec("cd $repo_path && git fetch origin $branch 2>&1 && git reset --hard origin/$branch 2>&1", $output, $return);
$out = implode("\n", $output);
deploy_log("Git output (exit code $return):\n$out");
deploy_log($return === 0 ? "DEPLOY SUCCESS" : "DEPLOY FAILED (exit code $return)");
http_response_code($return === 0 ? 200 : 500);
echo $return === 0 ? "OK: $out" : "FAILED: $out";
