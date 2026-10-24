<?php

function ensureLauncherAuthCodesTable(): void
{
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS launcher_auth_codes (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(64) NOT NULL,
                user_id INT NOT NULL,
                state VARCHAR(64) NOT NULL,
                port INT NOT NULL,
                used TINYINT(1) NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_launcher_auth_code (code),
                INDEX idx_launcher_auth_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        error_log('ensureLauncherAuthCodesTable: ' . $e->getMessage());
    }
}

function ensureLauncherAuthPendingTable(): void
{
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS launcher_auth_pending (
                state VARCHAR(64) NOT NULL,
                port INT NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (state),
                INDEX idx_launcher_auth_pending_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        error_log('ensureLauncherAuthPendingTable: ' . $e->getMessage());
    }
}

function launcherAuthCleanupExpired(): void
{
    ensureLauncherAuthCodesTable();
    ensureLauncherAuthPendingTable();
    try {
        $db = getDB();
        $db->exec('DELETE FROM launcher_auth_codes WHERE expires_at < NOW() OR (used = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))');
        $db->exec('DELETE FROM launcher_auth_pending WHERE expires_at < NOW()');
    } catch (PDOException $e) {
        error_log('launcherAuthCleanupExpired: ' . $e->getMessage());
    }
}

function launcherAuthSavePending(string $state, int $port): void
{
    ensureLauncherAuthPendingTable();
    try {
        $db = getDB();
        $stmt = $db->prepare('
            INSERT INTO launcher_auth_pending (state, port, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
            ON DUPLICATE KEY UPDATE port = VALUES(port), expires_at = VALUES(expires_at)
        ');
        $stmt->execute([$state, max(1024, min(65535, $port))]);
    } catch (PDOException $e) {
        error_log('launcherAuthSavePending: ' . $e->getMessage());
    }
}

function launcherAuthLoadPending(string $state): ?array
{
    $state = trim($state);
    if ($state === '') {
        return null;
    }

    ensureLauncherAuthPendingTable();
    try {
        $db = getDB();
        $stmt = $db->prepare('
            SELECT state, port
            FROM launcher_auth_pending
            WHERE state = ? AND expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([$state]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'state' => (string)$row['state'],
            'port' => (int)$row['port'],
        ];
    } catch (PDOException $e) {
        error_log('launcherAuthLoadPending: ' . $e->getMessage());
        return null;
    }
}

function launcherAuthDeletePending(string $state): void
{
    $state = trim($state);
    if ($state === '') {
        return;
    }

    ensureLauncherAuthPendingTable();
    try {
        $db = getDB();
        $stmt = $db->prepare('DELETE FROM launcher_auth_pending WHERE state = ?');
        $stmt->execute([$state]);
    } catch (PDOException $e) {
        error_log('launcherAuthDeletePending: ' . $e->getMessage());
    }
}

function launcherAuthBegin(int $port, string $state): void
{
    launcherAuthCleanupExpired();
    $port = max(1024, min(65535, $port));
    $_SESSION['launcher_auth'] = [
        'port' => $port,
        'state' => $state,
    ];
    launcherAuthSavePending($state, $port);
}

function launcherAuthResolvePending(?string $oauthState = null): ?array
{
    if (!empty($_SESSION['launcher_auth']) && is_array($_SESSION['launcher_auth'])) {
        $pending = $_SESSION['launcher_auth'];
        unset($_SESSION['launcher_auth']);

        $port = (int)($pending['port'] ?? 0);
        $state = trim((string)($pending['state'] ?? ''));
        if ($port > 0 && $state !== '') {
            launcherAuthDeletePending($state);
            return [
                'port' => $port,
                'state' => $state,
            ];
        }
    }

    $oauthState = trim((string)$oauthState);
    if ($oauthState === '') {
        return null;
    }

    $pending = launcherAuthLoadPending($oauthState);
    if (!$pending) {
        return null;
    }

    launcherAuthDeletePending($oauthState);
    return $pending;
}

function launcherAuthComplete(int $userId, ?string $oauthState = null): ?array
{
    $pending = launcherAuthResolvePending($oauthState);
    if (!$pending) {
        return null;
    }

    $port = (int)($pending['port'] ?? 0);
    $state = trim((string)($pending['state'] ?? ''));
    if ($port <= 0 || $state === '') {
        return null;
    }

    ensureLauncherAuthCodesTable();
    $code = bin2hex(random_bytes(24));

    try {
        $db = getDB();
        $stmt = $db->prepare('
            INSERT INTO launcher_auth_codes (code, user_id, state, port, expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))
        ');
        $stmt->execute([$code, $userId, $state, $port]);
    } catch (PDOException $e) {
        error_log('launcherAuthComplete: ' . $e->getMessage());
        launcherAuthSavePending($state, $port);
        return null;
    }

    return [
        'code' => $code,
        'state' => $state,
        'port' => $port,
    ];
}

function launcherAuthConsumeCode(string $code): ?array
{
    launcherAuthCleanupExpired();
    $code = trim($code);
    if ($code === '') {
        return null;
    }

    ensureLauncherAuthCodesTable();
    $db = getDB();
    $stmt = $db->prepare('
        SELECT lac.*, u.twitch_id, u.username, u.display_name, u.avatar_url, u.radmir_nickname
        FROM launcher_auth_codes lac
        INNER JOIN users u ON u.id = lac.user_id
        WHERE lac.code = ? AND lac.used = 0 AND lac.expires_at > NOW()
        LIMIT 1
    ');
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $update = $db->prepare('UPDATE launcher_auth_codes SET used = 1 WHERE id = ?');
    $update->execute([(int)$row['id']]);

    return [
        'twitch_id' => (string)$row['twitch_id'],
        'username' => (string)$row['username'],
        'display_name' => (string)$row['display_name'],
        'avatar_url' => (string)($row['avatar_url'] ?? ''),
        'minecraft_username' => (string)($row['radmir_nickname'] ?? $row['username']),
    ];
}

function launcherAuthRedirectToClient(array $payload): void
{
    $port = (int)($payload['port'] ?? 0);
    $code = rawurlencode((string)($payload['code'] ?? ''));
    $state = rawurlencode((string)($payload['state'] ?? ''));
    if ($port <= 0 || $code === '' || $state === '') {
        return;
    }

    $target = 'http://127.0.0.1:' . $port . '/callback?code=' . $code . '&state=' . $state;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">';
    echo '<title>GroznyHub Launcher</title>';
    echo '<style>body{font-family:sans-serif;background:#0a0e14;color:#fff;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:2rem;text-align:center}';
    echo 'a{color:#9146ff}</style></head><body>';
    echo '<div><h1>Вход выполнен</h1>';
    echo '<p>Возвращаем в лаунчер...</p>';
    echo '<p><a id="launcher-link" href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">Открыть лаунчер</a></p>';
    echo '<script>location.replace(' . json_encode($target, JSON_UNESCAPED_SLASHES) . ');</script>';
    echo '</body></html>';
    exit;
}

function launcherAuthHasPending(?string $oauthState = null): bool
{
    if (!empty($_SESSION['launcher_auth']) && is_array($_SESSION['launcher_auth'])) {
        return true;
    }

    $oauthState = trim((string)$oauthState);
    return $oauthState !== '' && launcherAuthLoadPending($oauthState) !== null;
}
