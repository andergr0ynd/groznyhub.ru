<?php

require_once __DIR__ . '/../config.php';



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



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



$payload = json_decode(file_get_contents('php://input'), true);

if (!is_array($payload)) {

    $payload = $_POST;

}



$action = strtolower(trim((string)($payload['action'] ?? '')));



switch ($action) {

    case 'start':

        $keyword = trim((string)($payload['keyword'] ?? ''));

        $duration = isset($payload['duration_seconds']) ? (int)$payload['duration_seconds'] : null;

        if ($duration !== null && $duration <= 0) {

            $duration = null;

        }

        $result = startRaffleRound($user['username'], $keyword, $duration);

        break;



    case 'stop':

        $result = stopRaffleRound();

        break;



    case 'pick':

        $result = pickRaffleWinner(false, isset($payload['round_id']) ? (int)$payload['round_id'] : null);

        break;



    case 'repick':

        $result = pickRaffleWinner(true, isset($payload['round_id']) ? (int)$payload['round_id'] : null);

        break;



    case 'confirm':

        $result = confirmRaffleWinner(isset($payload['round_id']) ? (int)$payload['round_id'] : null);

        break;



    default:

        http_response_code(400);

        echo json_encode(['success' => false, 'error' => 'Неизвестное действие'], JSON_UNESCAPED_UNICODE);

        exit;

}



if (!empty($result['success'])) {

    $result['state'] = getRaffleStateForWidget();

}



echo json_encode($result, JSON_UNESCAPED_UNICODE);

