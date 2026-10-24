<?php
require_once '../config.php';
require_once '../includes/launcher_bans.php';

header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
if (!$user || !isSiteAdmin($user)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));
$db = getDB();

try {
    if ($action === 'ban') {
        $username = trim((string)($_POST['username'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $expiresAt = trim((string)($_POST['expires_at'] ?? ''));

        $target = launcherFindUserByUsername($db, $username);
        if (!$target) {
            throw new InvalidArgumentException('Пользователь не найден на GroznyHub (нужен вход через Twitch на сайте)');
        }

        if ((string)($target['twitch_id'] ?? '') === (string)($user['twitch_id'] ?? '')) {
            throw new InvalidArgumentException('Нельзя заблокировать самого себя');
        }

        $result = launcherBanUser(
            $db,
            $target,
            $reason,
            $user,
            $expiresAt !== '' ? $expiresAt : null
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'unban') {
        $twitchId = trim((string)($_POST['twitch_id'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));

        if ($twitchId === '' && $username !== '') {
            $result = launcherUnbanByUsername($db, $username);
        } else {
            $result = launcherUnbanUser($db, $twitchId);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new InvalidArgumentException('Неизвестное действие');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
