<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
if (!$user || !isset($user['admin']) || (int)$user['admin'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isJackpotPlayAllowed()) {
    echo json_encode([
        'success' => false,
        'error' => 'Рулетка доступна только во время стрима',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$roundId = startJackpotRound($user['username']);

if ($roundId === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Банк рулетки исчерпан — игра недоступна',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'round_id' => $roundId,
    'duration_seconds' => JACKPOT_DURATION_SECONDS,
], JSON_UNESCAPED_UNICODE);
