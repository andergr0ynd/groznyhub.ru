<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isSiteAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $row = getClientErrorById($id);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Запись не найдена'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'item' => [
            'id' => (int)$row['id'],
            'token' => (string)$row['public_token'],
            'source' => (string)$row['source'],
            'level' => (string)$row['level'],
            'message' => (string)$row['message'],
            'stack_trace' => (string)($row['stack_trace'] ?? ''),
            'meta' => clientErrorFormatMeta($row['meta_json'] ?? null),
            'client_version' => (string)($row['client_version'] ?? ''),
            'username' => (string)($row['username'] ?? ''),
            'user_agent' => (string)($row['user_agent'] ?? ''),
            'ip_address' => (string)($row['ip_address'] ?? ''),
            'created_at' => (string)$row['created_at'],
            'public_url' => clientErrorPublicUrl((string)$row['public_token']),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

$filters = [
    'source' => trim((string)($_GET['source'] ?? '')),
    'level' => trim((string)($_GET['level'] ?? '')),
    'username' => trim((string)($_GET['username'] ?? '')),
];

$items = getClientErrorLogs(array_filter($filters), $limit, $offset);
echo json_encode([
    'success' => true,
    'page' => $page,
    'items' => array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'token' => (string)$row['public_token'],
            'source' => (string)$row['source'],
            'level' => (string)$row['level'],
            'message' => (string)$row['message'],
            'client_version' => (string)($row['client_version'] ?? ''),
            'username' => (string)($row['username'] ?? ''),
            'created_at' => (string)$row['created_at'],
            'public_url' => clientErrorPublicUrl((string)$row['public_token']),
        ];
    }, $items),
], JSON_UNESCAPED_UNICODE);
