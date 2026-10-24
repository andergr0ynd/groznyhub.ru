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

$purchaseId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$issued = isset($_POST['issued']) ? (int)$_POST['issued'] : -1;

if ($purchaseId <= 0) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        if (isset($input['id'])) {
            $purchaseId = (int)$input['id'];
        }
        if (isset($input['issued'])) {
            $issued = (int)$input['issued'];
        }
    }
}

if ($purchaseId <= 0 || ($issued !== 0 && $issued !== 1)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

$success = setCasePurchaseIssued($purchaseId, $issued === 1);
echo json_encode(['success' => $success, 'issued' => $issued === 1], JSON_UNESCAPED_UNICODE);
