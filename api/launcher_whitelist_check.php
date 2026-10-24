<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/launcher_whitelist_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Некорректный JSON');
    }

    $twitchId = trim((string)($payload['twitch_id'] ?? ''));
    $username = trim((string)($payload['username'] ?? ''));

    if ($twitchId === '' || $username === '') {
        throw new InvalidArgumentException('twitch_id и username обязательны');
    }

    $db = getDB();
    $result = launcherWhitelistCheckAccess($db, $twitchId, $username);

    echo json_encode([
        'success' => true,
        'allowed' => (bool)$result['allowed'],
        'reason' => $result['reason'],
        'message' => $result['message'],
        'username' => $result['username'] ?? launcherWhitelistMinecraftName($username),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('launcher_whitelist_check.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
