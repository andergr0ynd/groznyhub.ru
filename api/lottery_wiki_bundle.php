<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'bundles' => listWikiJsonBundles(),
        'groups' => getWikiCatalogGroups(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isSiteAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Только для админов'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw !== false ? $raw : '', true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

$bundle = trim((string)($body['bundle'] ?? ''));
if (!isWikiCatalogBundle($bundle)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Укажите bundle: skins, cars или cases'], JSON_UNESCAPED_UNICODE);
    exit;
}

$replace = !isset($body['merge']) || !$body['merge'];
$result = importWikiJsonBundle($bundle, $replace);
if (empty($result['success'])) {
    http_response_code(empty($result['error']) ? 500 : 404);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
