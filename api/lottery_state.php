<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

$stage = isset($_GET['stage']) ? (int)$_GET['stage'] : 1;
$gameId = isset($_GET['game_id']) ? trim((string)$_GET['game_id']) : '';

echo json_encode(getLotteryPublicState($stage, $gameId !== '' ? $gameId : null), JSON_UNESCAPED_UNICODE);
