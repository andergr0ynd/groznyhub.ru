<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'token required'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $row = getClientErrorByToken($token);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ошибка не найдена'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'error' => [
            'id' => (int)$row['id'],
            'token' => (string)$row['public_token'],
            'source' => (string)$row['source'],
            'level' => (string)$row['level'],
            'message' => (string)$row['message'],
            'client_version' => (string)($row['client_version'] ?? ''),
            'username' => (string)($row['username'] ?? ''),
            'created_at' => (string)$row['created_at'],
            'url' => clientErrorPublicUrl((string)$row['public_token']),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Некорректный JSON');
    }

    $result = logClientError($payload);
    echo json_encode([
        'success' => true,
        'id' => $result['id'],
        'token' => $result['token'],
        'url' => $result['url'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('client_error.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
