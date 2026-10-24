<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

expireStaleWidgetRecords();

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$state = getContainerDuelForWidget($lastId);

echo json_encode($state, JSON_UNESCAPED_UNICODE);
