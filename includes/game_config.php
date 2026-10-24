<?php

// Общие игровые константы (см. shared/game.env.example — значения должны совпадать с TwitchBot/.env)

function isDemoMode(): bool
{
    $value = getenv('DEMO_MODE');
    if ($value === false || trim((string)$value) === '') {
        return true;
    }

    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function requestIsHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($proto === 'https') {
        return true;
    }

    return isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on';
}

function requestSiteBasePath(): string
{
    $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($docRoot === '') {
        return '';
    }

    $docReal = realpath($docRoot);
    $rootReal = realpath(dirname(__DIR__));
    if ($docReal === false || $rootReal === false) {
        return '';
    }

    $docReal = rtrim(str_replace('\\', '/', $docReal), '/');
    $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');
    if (!str_starts_with(strtolower($rootReal), strtolower($docReal))) {
        return '';
    }

    return rtrim(substr($rootReal, strlen($docReal)), '/');
}

function requestSiteHost(): string
{
    $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));

    return $host !== '' ? $host : 'localhost';
}

function requestSiteUrl(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if ($host === '') {
        $host = 'localhost';
    }
    $scheme = requestIsHttps() ? 'https' : 'http';

    return $scheme . '://' . $host . requestSiteBasePath();
}

define('SITE_HOST', requestSiteHost());
define('SITE_URL', requestSiteUrl());

define('CASE_COST_SECONDS', (int)(getenv('CASE_COST_SECONDS') ?: 5400));
define('CASE_SALE_DURATION_SECONDS', (int)(getenv('CASE_SALE_DURATION_SECONDS') ?: 30));

define('JACKPOT_DURATION_SECONDS', (int)(getenv('JACKPOT_DURATION_SECONDS') ?: 120));
define('JACKPOT_DRAW_DELAY_SECONDS', (int)(getenv('JACKPOT_DRAW_DELAY_SECONDS') ?: 10));
define('JACKPOT_MIN_BET_SECONDS', (int)(getenv('JACKPOT_MIN_BET_SECONDS') ?: 600));
define('JACKPOT_DEFAULT_BET_SECONDS', (int)(getenv('JACKPOT_DEFAULT_BET_SECONDS') ?: 1800));
define('JACKPOT_HOUSE_BANK_SECONDS', (int)(getenv('JACKPOT_HOUSE_BANK_SECONDS') ?: 86400));
define('JACKPOT_REQUIRE_STREAM', in_array(strtolower((string)(getenv('JACKPOT_REQUIRE_STREAM') ?: '0')), ['1', 'true', 'yes', 'on'], true));
define('SITE_SETTING_JACKPOT_HOUSE_BANK', 'jackpot_house_bank_seconds');

define('CONTAINERS_MIN_BET_SECONDS', (int)(getenv('CONTAINERS_MIN_BET_SECONDS') ?: 1800));
define('CONTAINERS_DEFAULT_BET_SECONDS', (int)(getenv('CONTAINERS_DEFAULT_BET_SECONDS') ?: 1800));
define('CONTAINERS_COOLDOWN_ENV_SECONDS', max(60, (int)(getenv('CONTAINERS_COOLDOWN_SECONDS') ?: 600)));
define('CONTAINERS_COOLDOWN_MIN_SECONDS', 60);
define('CONTAINERS_COOLDOWN_MAX_SECONDS', 7200);
define('SITE_SETTING_CONTAINERS_COOLDOWN', 'containers_cooldown_seconds');
define('CONTAINERS_WIN_MULTIPLIER', max(2, (int)(getenv('CONTAINERS_WIN_MULTIPLIER') ?: 2)));
define('CONTAINER_GOVT_CUT_PERCENT', max(0, min(50, (int)(getenv('CONTAINER_GOVT_CUT_PERCENT') ?: 20))));

define('RAFFLE_RESPONSE_SECONDS', max(15, (int)(getenv('RAFFLE_RESPONSE_SECONDS') ?: 60)));
define('RAFFLE_MATCH_MODE', getenv('RAFFLE_MATCH_MODE') ?: 'contains');

define('DAILY_BONUS_REWARDS_MINUTES', [5, 8, 11, 14, 17, 20, 23]);
define('DAILY_BONUS_TIMEZONE', getenv('DAILY_BONUS_TIMEZONE') ?: 'Europe/Moscow');
define('DB_TIMEZONE', getenv('DB_TIMEZONE') ?: DAILY_BONUS_TIMEZONE);

define('SITE_DONATE_URL', getenv('SITE_DONATE_URL') ?: 'https://boosty.to/helperforradmir/single-payment/donation/809580/target?share=target_link');
define('SITE_DONATE_QR', 'icons/icondonate.png');
