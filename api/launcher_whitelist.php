<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/launcher_whitelist_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$expectedKey = trim((string)(getenv('LAUNCHER_WHITELIST_KEY') ?: ''));
$providedKey = trim((string)($_SERVER['HTTP_X_GROZNYHUB_KEY'] ?? $_GET['key'] ?? ''));

if ($expectedKey === '' || $providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDB();
    $usernames = launcherWhitelistCollectAllNames($db);

    echo json_encode([
        'success' => true,
        'usernames' => $usernames,
        'count' => count($usernames),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('launcher_whitelist.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal error'], JSON_UNESCAPED_UNICODE);
}
