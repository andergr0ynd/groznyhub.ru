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



try {

    $input = json_decode(file_get_contents('php://input') ?: '{}', true);

    if (!is_array($input)) {

        $input = [];

    }



    $gameId = trim((string)($input['game_id'] ?? $_POST['game_id'] ?? ''));

    $cellIndex = (int)($input['cell_index'] ?? $_POST['cell_index'] ?? 0);



    if ($gameId === '') {

        echo json_encode(['success' => false, 'error' => 'Нет game_id'], JSON_UNESCAPED_UNICODE);

        exit;

    }



    echo json_encode(revealLotteryGameCell($gameId, $cellIndex), JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log('lottery_reveal: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(['success' => false, 'error' => 'Ошибка сервера лотереи'], JSON_UNESCAPED_UNICODE);

}

