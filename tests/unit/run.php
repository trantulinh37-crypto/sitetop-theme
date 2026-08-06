<?php
require_once __DIR__ . '/bootstrap.php';
echo "=== SiteTop.net V2 Unit Tests ===\n\n";
$files = glob(__DIR__ . '/test-*.php'); $suites = 0;
foreach ($files as $f) { $suites++; echo "Running: " . basename($f) . "\n"; include $f; }
echo "\n=== Results ===\nSuites: $suites | Passed: " . $GLOBALS['test_results']['passed'] . " | Failed: " . $GLOBALS['test_results']['failed'] . "\n";
if (!empty($GLOBALS['test_results']['errors'])) { echo "\nErrors:\n"; foreach ($GLOBALS['test_results']['errors'] as $e) echo "  ✗ $e\n"; }
echo "\n" . ($GLOBALS['test_results']['failed'] === 0 ? '✅ ALL PASSED' : '❌ FAILURES') . "\n";
