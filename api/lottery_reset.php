<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = [];
}

$stage = (int)($input['stage'] ?? $_POST['stage'] ?? 1);
$game = startLotteryGame($stage);

if (!$game) {
    echo json_encode(['success' => false, 'error' => 'Не удалось сбросить игру'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array_merge(
    ['success' => true, 'message' => 'Новая игра'],
    getLotteryPublicState($stage, $game['game_id'])
), JSON_UNESCAPED_UNICODE);
