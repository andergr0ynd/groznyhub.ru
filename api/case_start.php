<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

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

$saleId = startCaseSale($user['username']);

echo json_encode([
    'success' => true,
    'sale_id' => $saleId,
    'duration_seconds' => CASE_SALE_DURATION_SECONDS,
], JSON_UNESCAPED_UNICODE);
