<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!isSiteAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Только для админов'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($input)) {
            $input = [];
        }
        $stage = (int)($input['stage'] ?? $_POST['stage'] ?? 1);
        $game = startLotteryGame($stage);
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Не удалось создать игру'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(getLotteryAdminLayout($stage, (string)$game['game_id'], false), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stage = isset($_GET['stage']) ? (int)$_GET['stage'] : 1;
    $gameId = isset($_GET['game_id']) ? trim((string)$_GET['game_id']) : '';
    $create = isset($_GET['create']) && (string)$_GET['create'] === '1';

    echo json_encode(
        getLotteryAdminLayout($stage, $gameId !== '' ? $gameId : null, $create),
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    error_log('lottery_layout: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера'], JSON_UNESCAPED_UNICODE);
}
