<?php

function ensureClientErrorLogsTable(): void
{
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS client_error_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                public_token VARCHAR(64) NOT NULL,
                source VARCHAR(64) NOT NULL,
                level VARCHAR(16) NOT NULL DEFAULT 'error',
                message TEXT NOT NULL,
                stack_trace MEDIUMTEXT,
                meta_json MEDIUMTEXT,
                user_agent VARCHAR(512) DEFAULT NULL,
                client_version VARCHAR(64) DEFAULT NULL,
                username VARCHAR(255) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_client_error_token (public_token),
                INDEX idx_client_error_created (created_at),
                INDEX idx_client_error_source (source),
                INDEX idx_client_error_level (level)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        error_log('ensureClientErrorLogsTable: ' . $e->getMessage());
    }
}

function clientErrorPublicUrl(string $token): string
{
    return siteUrl('launcher-error.php?token=' . urlencode($token));
}

function clientErrorAdminUrl(int $id): string
{
    return siteUrl('admin/launcher_errors.php?id=' . $id);
}

function logClientError(array $input): array
{
    ensureClientErrorLogsTable();

    $source = substr(trim((string)($input['source'] ?? 'launcher')), 0, 64);
    if ($source === '') {
        $source = 'launcher';
    }

    $level = strtolower(substr(trim((string)($input['level'] ?? 'error')), 0, 16));
    if (!in_array($level, ['error', 'warning', 'info'], true)) {
        $level = 'error';
    }

    $message = trim((string)($input['message'] ?? ''));
    if ($message === '') {
        throw new InvalidArgumentException('Пустое сообщение об ошибке');
    }

    $meta = $input['meta'] ?? [];
    if (!is_array($meta)) {
        $meta = [];
    }

    $token = bin2hex(random_bytes(16));
    $username = substr(trim((string)($meta['username'] ?? $meta['twitchUsername'] ?? '')), 0, 255);
    $clientVersion = substr(trim((string)($meta['launcherVersion'] ?? '')), 0, 64);
    $stackTrace = (string)($input['stack_trace'] ?? $input['stackTrace'] ?? '');
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    $db = getDB();
    $stmt = $db->prepare('
        INSERT INTO client_error_logs
            (public_token, source, level, message, stack_trace, meta_json, user_agent, client_version, username, ip_address)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $token,
        $source,
        $level,
        mb_substr($message, 0, 8000),
        mb_substr($stackTrace, 0, 32000),
        json_encode($meta, JSON_UNESCAPED_UNICODE),
        $userAgent !== '' ? $userAgent : null,
        $clientVersion !== '' ? $clientVersion : null,
        $username !== '' ? $username : null,
        $ip !== '' ? $ip : null,
    ]);

    $id = (int)$db->lastInsertId();

    return [
        'id' => $id,
        'token' => $token,
        'url' => clientErrorPublicUrl($token),
    ];
}

function getClientErrorByToken(string $token): ?array
{
    ensureClientErrorLogsTable();
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM client_error_logs WHERE public_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function getClientErrorLogs(array $filters = [], int $limit = 50, int $offset = 0): array
{
    ensureClientErrorLogsTable();
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);

    $where = [];
    $params = [];

    if (!empty($filters['source'])) {
        $where[] = 'source = ?';
        $params[] = (string)$filters['source'];
    }
    if (!empty($filters['level'])) {
        $where[] = 'level = ?';
        $params[] = (string)$filters['level'];
    }
    if (!empty($filters['username'])) {
        $where[] = 'username LIKE ?';
        $params[] = '%' . (string)$filters['username'] . '%';
    }

    $sql = 'SELECT id, public_token, source, level, message, client_version, username, created_at
            FROM client_error_logs';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getClientErrorById(int $id): ?array
{
    ensureClientErrorLogsTable();
    if ($id <= 0) {
        return null;
    }
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM client_error_logs WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function clientErrorFormatMeta(?string $metaJson): array
{
    if ($metaJson === null || $metaJson === '') {
        return [];
    }
    $meta = json_decode($metaJson, true);
    return is_array($meta) ? $meta : [];
}
