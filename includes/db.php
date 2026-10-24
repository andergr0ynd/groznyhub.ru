<?php

require_once __DIR__ . '/demo_db.php';

function getMysqlTimeZoneOffset(): string
{
    try {
        return (new DateTime('now', new DateTimeZone(DB_TIMEZONE)))->format('P');
    } catch (Throwable $e) {
        return '+03:00';
    }
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        if (isDemoMode()) {
            try {
                $pdo = openDemoDatabase();
            } catch (Throwable $e) {
                die('Ошибка демо-базы: ' . $e->getMessage());
            }
            return $pdo;
        }
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            $pdo->exec('SET time_zone = ' . $pdo->quote(getMysqlTimeZoneOffset()));
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }
    return $pdo;
}

function getKickDB() {
    static $pdo = null;
    if (isDemoMode()) {
        return getDB();
    }
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . KICK_DB_HOST . ';dbname=' . KICK_DB_NAME . ';charset=utf8mb4',
                KICK_DB_USER,
                KICK_DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $pdo->exec('SET time_zone = ' . $pdo->quote(getMysqlTimeZoneOffset()));
        } catch (PDOException $e) {
            error_log('Kick DB connection error: ' . $e->getMessage());
            throw $e;
        }
    }
    return $pdo;
}

// Начало сессии
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
