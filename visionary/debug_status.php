<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden\n";
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
echo "SESSION:\n";
print_r($_SESSION);
echo "\nCURRENT_USER (from current_user()):\n";
print_r(current_user());
echo "\nPHP SAPI: " . php_sapi_name() . "\n";
$cfg = @include __DIR__ . '/config.php';
$clientId = $cfg['github_client_id'] ?? '';
$hasClient = $clientId ? true : false;
echo "\nCONFIG:\n";
echo " base_url: " . ($cfg['base_url'] ?? '(none)') . "\n";
echo " github_client_id: " . ($hasClient ? (substr($clientId,0,6) . '...' ) : '(not set)') . "\n";

$dataDir = __DIR__ . '/data';
echo "\nDATA DIR: $dataDir\n";
echo " exists: " . (is_dir($dataDir) ? 'yes' : 'no') . "\n";
echo " writable: " . (is_writable($dataDir) ? 'yes' : 'no') . "\n";
$db = $dataDir . '/visionary.db';
if (file_exists($db)) {
    echo " DB: exists, size=" . filesize($db) . " bytes\n";
} else {
    echo " DB: not found\n";
}

echo "\nLast api_debug.log (if exists):\n";
$log = __DIR__ . '/data/api_debug.log';
if (file_exists($log)) {
    $lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $last = array_slice($lines, -200);
    echo implode("\n", $last);
} else {
    echo "(no log file)\n";
}
