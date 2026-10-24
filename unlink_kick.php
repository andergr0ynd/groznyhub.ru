<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => __('Не авторизован')], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    ensureKickProfileColumns();
    $db = getDB();
    $stmt = $db->prepare('UPDATE users SET kick_id = NULL, kick_username = NULL, kick_linked_at = NULL WHERE id = ?');
    $stmt->execute([(int)$user['id']]);
    echo json_encode(['success' => true, 'message' => __('Kick отвязан.')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('unlink_kick: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => __('Ошибка отвязки')], JSON_UNESCAPED_UNICODE);
}
