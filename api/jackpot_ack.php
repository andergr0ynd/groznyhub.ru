<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$roundId = isset($_POST['round_id']) ? (int)$_POST['round_id'] : 0;
if ($roundId <= 0) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input) && isset($input['round_id'])) {
        $roundId = (int)$input['round_id'];
    }
}

echo json_encode(['success' => ackJackpotWinner($roundId)], JSON_UNESCAPED_UNICODE);
