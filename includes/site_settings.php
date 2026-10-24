<?php

function ensureSiteSettingsTable(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS site_settings (
                setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
                setting_value TEXT NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $ready = true;
    } catch (Throwable $e) {
        error_log('ensureSiteSettingsTable: ' . $e->getMessage());
        $ready = false;
    }

    return $ready;
}

function getSiteSetting(string $key, ?string $default = null): ?string
{
    $key = trim($key);
    if ($key === '' || !ensureSiteSettingsTable()) {
        return $default;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $default;
        }
        return (string)($row['setting_value'] ?? $default);
    } catch (Throwable $e) {
        error_log('getSiteSetting(' . $key . '): ' . $e->getMessage());
        return $default;
    }
}

function setSiteSetting(string $key, string $value): bool
{
    $key = trim($key);
    if ($key === '' || !ensureSiteSettingsTable()) {
        return false;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare('
            INSERT INTO site_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([$key, $value]);
        return true;
    } catch (Throwable $e) {
        error_log('setSiteSetting(' . $key . '): ' . $e->getMessage());
        return false;
    }
}

function getContainersCooldownSeconds(): int
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $fallback = CONTAINERS_COOLDOWN_ENV_SECONDS;
    $raw = getSiteSetting(SITE_SETTING_CONTAINERS_COOLDOWN);
    if ($raw === null || trim($raw) === '') {
        $cached = $fallback;
        return $cached;
    }

    $seconds = (int)$raw;
    $cached = max(
        CONTAINERS_COOLDOWN_MIN_SECONDS,
        min(CONTAINERS_COOLDOWN_MAX_SECONDS, $seconds)
    );
    return $cached;
}

function setContainersCooldownMinutes(int $minutes): array
{
    $maxMinutes = (int)(CONTAINERS_COOLDOWN_MAX_SECONDS / 60);
    $minutes = max(1, min($maxMinutes, $minutes));
    $seconds = $minutes * 60;

    if (!setSiteSetting(SITE_SETTING_CONTAINERS_COOLDOWN, (string)$seconds)) {
        return [
            'success' => false,
            'error' => __('Не удалось сохранить настройку.'),
        ];
    }

    return [
        'success' => true,
        'cooldown_seconds' => $seconds,
        'cooldown_minutes' => $minutes,
    ];
}

function parseDbDateTime(?string $value): ?DateTimeImmutable
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    try {
        return new DateTimeImmutable(trim($value), new DateTimeZone(DB_TIMEZONE));
    } catch (Throwable $e) {
        return null;
    }
}

function getContainerCooldownElapsedSeconds(?string $lastPlayedAt): int
{
    $lastPlayed = parseDbDateTime($lastPlayedAt);
    if ($lastPlayed === null) {
        return PHP_INT_MAX;
    }
    $now = new DateTimeImmutable('now', new DateTimeZone(DB_TIMEZONE));
    return max(0, $now->getTimestamp() - $lastPlayed->getTimestamp());
}
