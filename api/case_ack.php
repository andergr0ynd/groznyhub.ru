<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$purchaseId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($purchaseId <= 0) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input) && isset($input['id'])) {
        $purchaseId = (int)$input['id'];
    }
}

if ($purchaseId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid purchase id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$success = ackCasePurchase($purchaseId);
echo json_encode(['success' => $success], JSON_UNESCAPED_UNICODE);
