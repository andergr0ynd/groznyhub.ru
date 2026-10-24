<?php

function ensureLauncherBansTable(): void
{
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS launcher_bans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                twitch_id VARCHAR(255) NOT NULL,
                username VARCHAR(255) NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                banned_by VARCHAR(255) DEFAULT NULL,
                banned_by_twitch_id VARCHAR(255) DEFAULT NULL,
                banned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME DEFAULT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY uniq_launcher_bans_twitch (twitch_id),
                INDEX idx_launcher_bans_active (active),
                INDEX idx_launcher_bans_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        error_log('ensureLauncherBansTable: ' . $e->getMessage());
    }
}

function launcherDeactivateExpiredBan(PDO $db, array $ban): void
{
    if (empty($ban['expires_at'])) {
        return;
    }

    $expiresAt = strtotime((string)$ban['expires_at']);
    if ($expiresAt !== false && $expiresAt <= time()) {
        $stmt = $db->prepare('UPDATE launcher_bans SET active = 0 WHERE id = ?');
        $stmt->execute([(int)$ban['id']]);
    }
}

function launcherGetActiveBan(PDO $db, $twitchId): ?array
{
    ensureLauncherBansTable();

    $twitchId = trim((string)$twitchId);
    if ($twitchId === '') {
        return null;
    }

    $stmt = $db->prepare('
        SELECT *
        FROM launcher_bans
        WHERE twitch_id = ? AND active = 1
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->execute([$twitchId]);
    $ban = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ban) {
        return null;
    }

    launcherDeactivateExpiredBan($db, $ban);

    $stmt->execute([$twitchId]);
    $ban = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ban || (int)($ban['active'] ?? 0) !== 1) {
        return null;
    }

    if (!empty($ban['expires_at'])) {
        $expiresAt = strtotime((string)$ban['expires_at']);
        if ($expiresAt !== false && $expiresAt <= time()) {
            return null;
        }
    }

    return $ban;
}

function launcherIsTwitchIdBanned(PDO $db, $twitchId): bool
{
    return launcherGetActiveBan($db, $twitchId) !== null;
}

function launcherGetBannedTwitchIds(PDO $db): array
{
    ensureLauncherBansTable();

    $stmt = $db->query('
        SELECT id, twitch_id, expires_at
        FROM launcher_bans
        WHERE active = 1
    ');

    $ids = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['expires_at'])) {
            $expiresAt = strtotime((string)$row['expires_at']);
            if ($expiresAt !== false && $expiresAt <= time()) {
                launcherDeactivateExpiredBan($db, $row);
                continue;
            }
        }
        $ids[(string)$row['twitch_id']] = true;
    }

    return $ids;
}

function launcherFindUserByUsername(PDO $db, $username): ?array
{
    $username = normalizeTwitchUsername($username);
    if ($username === '') {
        return null;
    }

    $stmt = $db->prepare('
        SELECT id, twitch_id, username, display_name
        FROM users
        WHERE LOWER(username) = ?
        LIMIT 1
    ');
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function launcherBanUser(PDO $db, array $targetUser, string $reason, array $adminUser, ?string $expiresAt = null): array
{
    ensureLauncherBansTable();

    $twitchId = trim((string)($targetUser['twitch_id'] ?? ''));
    $username = normalizeTwitchUsername($targetUser['username'] ?? '');
    if ($twitchId === '' || $username === '') {
        return ['success' => false, 'error' => 'У пользователя нет Twitch ID'];
    }

    if (launcherIsTwitchIdBanned($db, $twitchId)) {
        return ['success' => false, 'error' => 'Пользователь уже заблокирован'];
    }

    $reason = trim($reason);
    if ($reason === '') {
        $reason = 'Нарушение правил сервера';
    }
    $reason = mb_substr($reason, 0, 255);

    $expiresValue = null;
    if ($expiresAt !== null && trim($expiresAt) !== '') {
        $timestamp = strtotime($expiresAt);
        if ($timestamp === false) {
            return ['success' => false, 'error' => 'Некорректная дата окончания бана'];
        }
        $expiresValue = date('Y-m-d H:i:s', $timestamp);
    }

    $adminUsername = normalizeTwitchUsername($adminUser['username'] ?? $adminUser['display_name'] ?? 'admin');
    $adminTwitchId = trim((string)($adminUser['twitch_id'] ?? ''));

    $stmt = $db->prepare('
        INSERT INTO launcher_bans (
            twitch_id, username, reason, banned_by, banned_by_twitch_id, expires_at, active
        ) VALUES (?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            reason = VALUES(reason),
            banned_by = VALUES(banned_by),
            banned_by_twitch_id = VALUES(banned_by_twitch_id),
            banned_at = CURRENT_TIMESTAMP,
            expires_at = VALUES(expires_at),
            active = 1
    ');
    $stmt->execute([
        $twitchId,
        $username,
        $reason,
        $adminUsername,
        $adminTwitchId !== '' ? $adminTwitchId : null,
        $expiresValue,
    ]);

    return [
        'success' => true,
        'twitch_id' => $twitchId,
        'username' => $username,
        'reason' => $reason,
        'expires_at' => $expiresValue,
    ];
}

function launcherUnbanUser(PDO $db, $twitchId): array
{
    ensureLauncherBansTable();

    $twitchId = trim((string)$twitchId);
    if ($twitchId === '') {
        return ['success' => false, 'error' => 'Twitch ID не указан'];
    }

    $stmt = $db->prepare('UPDATE launcher_bans SET active = 0 WHERE twitch_id = ? AND active = 1');
    $stmt->execute([$twitchId]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'error' => 'Активный бан не найден'];
    }

    return ['success' => true, 'twitch_id' => $twitchId];
}

function launcherUnbanByUsername(PDO $db, $username): array
{
    $user = launcherFindUserByUsername($db, $username);
    if (!$user) {
        return ['success' => false, 'error' => 'Пользователь не найден на GroznyHub'];
    }

    return launcherUnbanUser($db, $user['twitch_id']);
}

function launcherListActiveBans(PDO $db, int $limit = 200): array
{
    ensureLauncherBansTable();

    $limit = max(1, min($limit, 500));
    $stmt = $db->query('
        SELECT *
        FROM launcher_bans
        WHERE active = 1
        ORDER BY banned_at DESC
        LIMIT ' . (int)$limit
    );

    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['expires_at'])) {
            $expiresAt = strtotime((string)$row['expires_at']);
            if ($expiresAt !== false && $expiresAt <= time()) {
                launcherDeactivateExpiredBan($db, $row);
                continue;
            }
        }
        $items[] = $row;
    }

    return $items;
}

function launcherBanMessage(array $ban): string
{
    $reason = trim((string)($ban['reason'] ?? ''));
    $message = 'Аккаунт Twitch заблокирован на сервере andergr0ynd.';
    if ($reason !== '') {
        $message .= ' Причина: ' . $reason;
    }
    if (!empty($ban['expires_at'])) {
        $expiresAt = strtotime((string)$ban['expires_at']);
        if ($expiresAt !== false) {
            $message .= ' До: ' . date('d.m.Y H:i', $expiresAt);
        }
    }
    return $message;
}
