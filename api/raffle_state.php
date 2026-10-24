<?php

require_once __DIR__ . '/../config.php';



header('Content-Type: application/json; charset=utf-8');

header('Cache-Control: no-store, no-cache, must-revalidate');

header('Access-Control-Allow-Origin: *');

expireStaleWidgetRecords();

$state = getRaffleStateForWidget();

echo json_encode($state, JSON_UNESCAPED_UNICODE);

