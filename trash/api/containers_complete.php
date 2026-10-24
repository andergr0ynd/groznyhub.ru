<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => __('Войдите через Twitch')], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$duelId = isset($input['duel_id']) ? (int)$input['duel_id'] : 0;
if ($duelId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => __('Дуэль не указана')], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = completeContainerDuel($user['username'], $duelId);
if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
