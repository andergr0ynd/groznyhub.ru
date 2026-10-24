<?php
require_once __DIR__ . '/includes/load_env.php';
loadEnvFile(__DIR__ . '/.env');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'groznyhub');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '');

require_once __DIR__ . '/includes/game_config.php';

// Twitch OAuth — только если DEMO_MODE=0 и ключи заданы в .env
define('TWITCH_CLIENT_ID', getenv('TWITCH_CLIENT_ID') ?: '');
define('TWITCH_CLIENT_SECRET', getenv('TWITCH_CLIENT_SECRET') ?: '');
define('TWITCH_REDIRECT_URI', SITE_URL . '/callback.php');

// Kick OAuth (привязка аккаунта к Twitch-профилю на сайте)
define('KICK_CLIENT_ID', getenv('KICK_CLIENT_ID') ?: '');
define('KICK_CLIENT_SECRET', getenv('KICK_CLIENT_SECRET') ?: '');
define('KICK_REDIRECT_URI', SITE_URL . '/kickauth/callback.php');
define('KICK_DB_HOST', getenv('KICK_DB_HOST') ?: 'localhost');
define('KICK_DB_NAME', getenv('KICK_DB_NAME') ?: 'groznyhub_kick');
define('KICK_DB_USER', getenv('KICK_DB_USER') ?: 'root');
define('KICK_DB_PASS', getenv('KICK_DB_PASS') ?: getenv('KICK_DB_PASSWORD') ?: DB_PASS);

function siteUrl(string $path = ''): string
{
    if ($path === '') {
        return SITE_URL;
    }
    return SITE_URL . '/' . ltrim($path, '/');
}

function renderSiteFaviconTags(string $relativeBase = ''): void
{
    $prefix = $relativeBase !== '' ? rtrim($relativeBase, '/') . '/' : '';
    $iconsDir = __DIR__ . '/icons';
    $version = is_file($iconsDir . '/favicon.ico') ? (int)filemtime($iconsDir . '/favicon.ico') : time();

    echo '    <link rel="icon" href="' . htmlspecialchars($prefix . 'icons/favicon.ico?v=' . $version) . '" sizes="any">' . "\n";
    echo '    <link rel="icon" type="image/png" sizes="32x32" href="' . htmlspecialchars($prefix . 'icons/favicon-32.png?v=' . $version) . '">' . "\n";
    echo '    <link rel="apple-touch-icon" href="' . htmlspecialchars($prefix . 'icons/apple-touch-icon.png?v=' . $version) . '">' . "\n";
}

function renderSiteHeadExtras(): void
{
    echo '    <meta name="theme-color" content="#0e0e10">' . "\n";
    echo '    <meta name="color-scheme" content="dark">' . "\n";
    echo '    <meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
}

function renderMobileBottomNav(string $current = 'home', string $relativeBase = ''): void
{
    $prefix = $relativeBase !== '' ? rtrim($relativeBase, '/') . '/' : '';
    $items = [
        'home' => ['href' => $prefix . 'index.php', 'icon' => '🏠', 'label' => __('Главная')],
        'search' => ['href' => $prefix . 'search.php', 'icon' => '🔍', 'label' => __('Поиск')],
        'faq' => ['href' => $prefix . 'faq.php', 'icon' => '📖', 'label' => __('FAQ')],
        'donate' => ['href' => $prefix . 'donate.php', 'icon' => '🖧', 'label' => __('Поддержать')],
        'exchange' => ['href' => $prefix . 'Obmen/index.html', 'icon' => '💰', 'label' => __('Обмен')],
        'wheel' => ['href' => $prefix . 'koleso/index.html', 'icon' => '🎡', 'label' => __('Колесо')],
    ];

    echo '<nav class="mobile-bottom-nav" aria-label="' . htmlspecialchars(__('Навигация')) . '">' . "\n";
    foreach ($items as $key => $item) {
        $active = $key === $current ? ' is-active' : '';
        echo '    <a href="' . htmlspecialchars($item['href']) . '" class="mobile-nav-link' . $active . '">' . "\n";
        echo '        <span class="mobile-nav-icon" aria-hidden="true">' . $item['icon'] . '</span>' . "\n";
        echo '        <span class="mobile-nav-label">' . htmlspecialchars($item['label']) . '</span>' . "\n";
        echo '    </a>' . "\n";
    }
    echo '</nav>' . "\n";
}

function siteDonateUrl(): string
{
    return SITE_DONATE_URL;
}

function siteDonateQrUrl(string $relativeBase = ''): string
{
    $prefix = $relativeBase !== '' ? rtrim($relativeBase, '/') . '/' : '';
    return $prefix . SITE_DONATE_QR;
}

function renderSiteFooterLinks(string $relativeBase = ''): void
{
    $prefix = $relativeBase !== '' ? rtrim($relativeBase, '/') . '/' : '';
    echo '            <p style="margin-top: 0.5rem;">' . "\n";
    echo '                <a href="' . htmlspecialchars($prefix . 'faq.php') . '" class="footer-link">' . htmlspecialchars(__('FAQ и правила рефки')) . '</a>';
    echo ' · ';
    echo '                <a href="' . htmlspecialchars($prefix . 'donate.php') . '" class="footer-link">' . htmlspecialchars(__('Продление сайта / бота')) . '</a>' . "\n";
    echo '            </p>' . "\n";
}

// Twitch канал для проверки статуса стрима (cron: check_streams.php)
define('TWITCH_CHANNEL_LOGIN', getenv('TWITCH_CHANNEL_LOGIN') ?: 'mark_grozny');

function isSiteAdmin(array|false|null $user = null): bool
{
    if (!is_array($user)) {
        $user = getCurrentUser();
    }
    return is_array($user) && isset($user['admin']) && (int)$user['admin'] === 1;
}

function canViewBotStats(?array $user = null): bool
{
    if ($user === null) {
        $user = getCurrentUser();
    }
    if (!is_array($user)) {
        return false;
    }
    $name = strtolower(trim((string)($user['username'] ?? '')));
    return $name === 'andergr0ynd' || isSiteAdmin($user);
}


require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/bot_ledger.php';
require_once __DIR__ . '/includes/roulette.php';
require_once __DIR__ . '/includes/jackpot.php';
require_once __DIR__ . '/includes/cases.php';
require_once __DIR__ . '/includes/site_settings.php';
require_once __DIR__ . '/includes/containers.php';
require_once __DIR__ . '/includes/raffle.php';
require_once __DIR__ . '/includes/widget_cleanup.php';
require_once __DIR__ . '/includes/client_errors.php';
require_once __DIR__ . '/includes/launcher_auth.php';
require_once __DIR__ . '/includes/launcher_bans.php';




function ensureDailyBonusTables() {
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS daily_bonus_streaks (
                username VARCHAR(255) NOT NULL,
                current_streak INT NOT NULL DEFAULT 0,
                longest_streak INT NOT NULL DEFAULT 0,
                last_claim_date DATE DEFAULT NULL,
                total_claims INT NOT NULL DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        error_log('Ошибка инициализации таблиц ежедневного бонуса: ' . $e->getMessage());
    }
}

function getDailyBonusRewardMinutes($streakDay) {
    $dayIndex = max(1, min((int)$streakDay, 7)) - 1;
    return DAILY_BONUS_REWARDS_MINUTES[$dayIndex];
}

function getUserDailyBonusStats($username) {
    ensureDailyBonusTables();
    $usernameLower = strtolower($username);

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT current_streak, longest_streak, last_claim_date, total_claims
            FROM daily_bonus_streaks
            WHERE LOWER(username) = ?
        ");
        $stmt->execute([$usernameLower]);
        $row = $stmt->fetch();

        $tz = new DateTimeZone(DAILY_BONUS_TIMEZONE);
        $today = (new DateTime('now', $tz))->format('Y-m-d');
        $yesterday = (new DateTime('now', $tz))->modify('-1 day')->format('Y-m-d');

        if (!$row) {
            return [
                'current_streak' => 0,
                'longest_streak' => 0,
                'last_claim_date' => null,
                'total_claims' => 0,
                'claimed_today' => false,
                'next_reward_minutes' => getDailyBonusRewardMinutes(1),
            ];
        }

        $currentStreak = (int)($row['current_streak'] ?? 0);
        $lastClaimDate = $row['last_claim_date'] ?? null;
        $claimedToday = $lastClaimDate === $today;

        if ($claimedToday) {
            $nextStreakDay = $currentStreak;
        } elseif ($lastClaimDate === $yesterday) {
            $nextStreakDay = min($currentStreak + 1, 7);
        } else {
            $nextStreakDay = 1;
        }

        return [
            'current_streak' => $currentStreak,
            'longest_streak' => (int)($row['longest_streak'] ?? 0),
            'last_claim_date' => $lastClaimDate,
            'total_claims' => (int)($row['total_claims'] ?? 0),
            'claimed_today' => $claimedToday,
            'next_reward_minutes' => getDailyBonusRewardMinutes($nextStreakDay),
        ];
    } catch (PDOException $e) {
        error_log('Ошибка получения ежедневного бонуса: ' . $e->getMessage());
        return [
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_claim_date' => null,
            'total_claims' => 0,
            'claimed_today' => false,
            'next_reward_minutes' => getDailyBonusRewardMinutes(1),
        ];
    }
}


// Подключение к базе данных

function demoUser(): array
{
    return [
        'id' => 1,
        'twitch_id' => 'demo',
        'username' => 'demo',
        'display_name' => 'Demo',
        'email' => '',
        'avatar_url' => '',
        'admin' => 1,
        'is_private' => 0,
        'kick_id' => null,
        'kick_username' => null,
        'kick_linked_at' => null,
    ];
}

// Функция для проверки авторизации
function isLoggedIn() {
    if (isDemoMode()) {
        return true;
    }
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Функция для получения текущего пользователя
function getCurrentUser() {
    if (isDemoMode()) {
        $fallback = demoUser();
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([(int)$fallback['id']]);
            $row = $stmt->fetch();
            if (is_array($row) && $row) {
                return $row;
            }
        } catch (Throwable $e) {
            error_log('demo user: ' . $e->getMessage());
        }
        return $fallback;
    }

    if (!isLoggedIn()) {
        return null;
    }
    
    ensureKickProfileColumns();
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

// Функция для получения количества пользователей
function getUsersCount() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

// Функция для получения статистики пользователя (только Twitch-БД)
function getUserStats($username) {
    try {
        $db = getDB();
        
        // Приводим username к нижнему регистру для сравнения (Twitch username всегда lowercase)
        $usernameLower = strtolower($username);
        
        // Получаем время из user_time_tracking (сравниваем без учета регистра)
        $stmt = $db->prepare("SELECT total_seconds, last_activity FROM user_time_tracking WHERE LOWER(username) = ?");
        $stmt->execute([$usernameLower]);
        $timeData = $stmt->fetch();
        
        // Получаем количество сообщений
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE LOWER(username) = ?");
        $stmt->execute([$usernameLower]);
        $messagesData = $stmt->fetch();
        
        // Получаем информацию о активной сессии
        $stmt = $db->prepare("SELECT session_start, last_message_time FROM active_sessions WHERE LOWER(username) = ?");
        $stmt->execute([$usernameLower]);
        $sessionData = $stmt->fetch();
        
        $totalSeconds = $timeData['total_seconds'] ?? 0;
        $messagesCount = $messagesData['count'] ?? 0;
        $isActive = $sessionData ? true : false;
        
        // Форматируем время
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;
        
        return [
            'total_seconds' => $totalSeconds,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'messages_count' => $messagesCount,
            'is_active' => $isActive,
            'last_activity' => $timeData['last_activity'] ?? null
        ];
    } catch (PDOException $e) {
        return [
            'total_seconds' => 0,
            'hours' => 0,
            'minutes' => 0,
            'seconds' => 0,
            'messages_count' => 0,
            'is_active' => false,
            'last_activity' => null
        ];
    }
}

function formatStatsFromSeconds(int $totalSeconds): array
{
    return [
        'total_seconds' => $totalSeconds,
        'hours' => (int)floor($totalSeconds / 3600),
        'minutes' => (int)floor(($totalSeconds % 3600) / 60),
        'seconds' => (int)($totalSeconds % 60),
    ];
}

function getKickPlatformStats(?string $kickUsername): array
{
    $empty = [
        'total_seconds' => 0,
        'hours' => 0,
        'minutes' => 0,
        'seconds' => 0,
        'messages_count' => 0,
        'is_active' => false,
        'last_activity' => null,
    ];
    $kickUsername = strtolower(trim((string)$kickUsername));
    if ($kickUsername === '') {
        return $empty;
    }
    try {
        $db = getKickDB();
        $stmt = $db->prepare('SELECT total_seconds, last_activity, is_active FROM user_time_tracking WHERE LOWER(username) = ?');
        $stmt->execute([$kickUsername]);
        $timeData = $stmt->fetch();

        $stmt = $db->prepare('SELECT COUNT(*) AS count FROM chat_messages WHERE LOWER(username) = ?');
        $stmt->execute([$kickUsername]);
        $messagesData = $stmt->fetch();

        $totalSeconds = (int)($timeData['total_seconds'] ?? 0);
        $formatted = formatStatsFromSeconds($totalSeconds);
        return array_merge($formatted, [
            'messages_count' => (int)($messagesData['count'] ?? 0),
            'is_active' => !empty($timeData['is_active']),
            'last_activity' => $timeData['last_activity'] ?? null,
        ]);
    } catch (PDOException $e) {
        error_log('getKickPlatformStats: ' . $e->getMessage());
        return $empty;
    }
}

/** Статистика профиля: Twitch + привязанный Kick */
function getProfileStats(array $user): array
{
    $twitch = getUserStats($user['username'] ?? '');
    $kick = getKickPlatformStats($user['kick_username'] ?? null);

    $totalSeconds = (int)$twitch['total_seconds'] + (int)$kick['total_seconds'];
    $merged = formatStatsFromSeconds($totalSeconds);
    $merged['messages_count'] = (int)$twitch['messages_count'] + (int)$kick['messages_count'];
    $merged['is_active'] = !empty($twitch['is_active']) || !empty($kick['is_active']);
    $merged['last_activity'] = $twitch['last_activity'] ?? $kick['last_activity'];
    if (!empty($twitch['last_activity']) && !empty($kick['last_activity'])) {
        $merged['last_activity'] = max($twitch['last_activity'], $kick['last_activity']);
    }
    $merged['twitch_seconds'] = (int)$twitch['total_seconds'];
    $merged['kick_seconds'] = (int)$kick['total_seconds'];
    $merged['kick_linked'] = !empty($user['kick_username']);
    return $merged;
}

function buildUserStatsListRow(
    string $username,
    array $stats,
    ?string $displayName = null,
    ?string $createdAt = null
): array {
    $totalSeconds = (int)($stats['total_seconds'] ?? 0);

    return [
        'username' => $username,
        'display_name' => $displayName ?? $username,
        'total_seconds' => $totalSeconds,
        'hours' => (int)($stats['hours'] ?? 0),
        'minutes' => (int)($stats['minutes'] ?? 0),
        'seconds' => (int)($stats['seconds'] ?? 0),
        'messages_count' => (int)($stats['messages_count'] ?? 0),
        'is_active' => (bool)($stats['is_active'] ?? false),
        'last_activity' => $stats['last_activity'] ?? null,
        'created_at' => $createdAt,
    ];
}

function sortUsersStatsList(array $users): array {
    usort($users, static function (array $a, array $b): int {
        $cmp = ($b['total_seconds'] ?? 0) <=> ($a['total_seconds'] ?? 0);
        return $cmp !== 0 ? $cmp : strcasecmp($a['username'], $b['username']);
    });

    return $users;
}

// Функция для получения всех пользователей со статистикой
function getAllUsersStats($includePrivate = false) {
    try {
        $db = getDB();
        ensurePrivacyColumn();

        $usersByKey = [];

        if ($includePrivate) {
            // Админка: users (сайт) + user_time_tracking (рефка в боте), без JOIN — без ошибок collation
            $siteStmt = $db->query('
                SELECT username, display_name, created_at
                FROM users
                ORDER BY created_at DESC
            ');
            while ($row = $siteStmt->fetch()) {
                $key = strtolower($row['username']);
                $usersByKey[$key] = buildUserStatsListRow(
                    $row['username'],
                    getUserStats($row['username']),
                    $row['display_name'] ?? $row['username'],
                    $row['created_at'] ?? null
                );
            }

            $trackingStmt = $db->query('
                SELECT username
                FROM user_time_tracking
                ORDER BY total_seconds DESC, username ASC
            ');
            while ($row = $trackingStmt->fetch()) {
                $key = strtolower($row['username']);
                if (isset($usersByKey[$key])) {
                    continue;
                }
                $usersByKey[$key] = buildUserStatsListRow(
                    $row['username'],
                    getUserStats($row['username'])
                );
            }

            return sortUsersStatsList(array_values($usersByKey));
        }

        // Публичный список: трекинг рефки, без приватных профилей на сайте
        $trackingStmt = $db->query('
            SELECT username
            FROM user_time_tracking
            ORDER BY total_seconds DESC, username ASC
        ');
        while ($row = $trackingStmt->fetch()) {
            if (isUserProfilePrivate($row['username'])) {
                continue;
            }
            $key = strtolower($row['username']);
            if (isset($usersByKey[$key])) {
                continue;
            }
            $usersByKey[$key] = buildUserStatsListRow(
                $row['username'],
                getUserStats($row['username'])
            );
        }

        return sortUsersStatsList(array_values($usersByKey));
    } catch (PDOException $e) {
        error_log('Ошибка получения статистики пользователей: ' . $e->getMessage());
        return [];
    }
}

/** Скрыт ли профиль при точном совпадении ника или display name */
function isSearchQueryPrivateProfile($query) {
    $needle = normalizeTwitchUsername($query);
    if ($needle === '') {
        return false;
    }

    if (isUserProfilePrivate($needle)) {
        return true;
    }

    try {
        $db = getDB();
        ensurePrivacyColumn();
        $stmt = $db->prepare('
            SELECT is_private FROM users
            WHERE LOWER(display_name) = ?
            LIMIT 1
        ');
        $stmt->execute([$needle]);
        $row = $stmt->fetch();
        return $row && (int)($row['is_private'] ?? 0) === 1;
    } catch (PDOException $e) {
        return false;
    }
}

// Функция для поиска пользователей (рефка + аккаунты сайта, ник и display name)
function searchUsers($query) {
    $needle = normalizeTwitchUsername($query);
    if ($needle === '') {
        return [];
    }

    try {
        $db = getDB();
        ensurePrivacyColumn();

        $pattern = '%' . $needle . '%';
        $byKey = [];

        $stmt = $db->prepare('
            SELECT username FROM user_time_tracking
            WHERE LOWER(username) LIKE ?
        ');
        $stmt->execute([$pattern]);
        while ($row = $stmt->fetch()) {
            $username = (string)$row['username'];
            $byKey[strtolower($username)] = $username;
        }

        $stmt = $db->prepare('
            SELECT username, display_name FROM users
            WHERE LOWER(username) LIKE ?
               OR LOWER(COALESCE(display_name, \'\')) LIKE ?
        ');
        $stmt->execute([$pattern, $pattern]);
        while ($row = $stmt->fetch()) {
            $username = (string)$row['username'];
            $byKey[strtolower($username)] = $username;
        }

        $users = [];
        foreach ($byKey as $username) {
            if (isUserProfilePrivate($username)) {
                continue;
            }

            $stats = getUserStats($username);
            $bonus = getUserDailyBonusStats($username);

            $users[] = [
                'username' => $username,
                'total_seconds' => (int)$stats['total_seconds'],
                'hours' => (int)$stats['hours'],
                'minutes' => (int)$stats['minutes'],
                'seconds' => (int)$stats['seconds'],
                'messages_count' => (int)$stats['messages_count'],
                'is_active' => !empty($stats['is_active']),
                'last_activity' => $stats['last_activity'],
                'bonus_streak' => (int)($bonus['current_streak'] ?? 0),
            ];
        }

        usort($users, static function (array $a, array $b) use ($needle): int {
            $aExact = strtolower($a['username']) === $needle ? 0 : 1;
            $bExact = strtolower($b['username']) === $needle ? 0 : 1;
            if ($aExact !== $bExact) {
                return $aExact <=> $bExact;
            }
            $cmp = ($b['total_seconds'] ?? 0) <=> ($a['total_seconds'] ?? 0);
            return $cmp !== 0 ? $cmp : strcasecmp($a['username'], $b['username']);
        });

        return array_slice($users, 0, 50);
    } catch (PDOException $e) {
        error_log('Ошибка поиска пользователей: ' . $e->getMessage());
        return [];
    }
}

// Функция для проверки и создания колонки is_private
function ensurePrivacyColumn() {
    try {
        $db = getDB();
        
        // Проверяем, существует ли поле is_private, если нет - добавляем
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'is_private'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN is_private BOOLEAN DEFAULT 0");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Ошибка создания колонки is_private: " . $e->getMessage());
        return false;
    }
}


function ensureKickProfileColumns() {
    try {
        $db = getDB();
        $columns = [
            'kick_id' => "ALTER TABLE users ADD COLUMN kick_id VARCHAR(64) DEFAULT NULL",
            'kick_username' => "ALTER TABLE users ADD COLUMN kick_username VARCHAR(255) DEFAULT NULL",
            'kick_linked_at' => "ALTER TABLE users ADD COLUMN kick_linked_at DATETIME DEFAULT NULL",
        ];
        foreach ($columns as $name => $sql) {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE " . $db->quote($name));
            if ($stmt->rowCount() == 0) {
                $db->exec($sql);
            }
        }
        $stmt = $db->query("SHOW INDEX FROM users WHERE Key_name = 'uniq_users_kick_id'");
        if ($stmt->rowCount() == 0) {
            $db->exec('ALTER TABLE users ADD UNIQUE INDEX uniq_users_kick_id (kick_id)');
        }
        $stmt = $db->query("SHOW INDEX FROM users WHERE Key_name = 'uniq_users_kick_username'");
        if ($stmt->rowCount() == 0) {
            $db->exec('ALTER TABLE users ADD UNIQUE INDEX uniq_users_kick_username (kick_username)');
        }
        return true;
    } catch (PDOException $e) {
        error_log('ensureKickProfileColumns: ' . $e->getMessage());
        return false;
    }
}

// Функция для обновления настройки приватности
function updatePrivacySetting($userId, $isPrivate) {
    try {
        $db = getDB();
        
        // Убеждаемся, что колонка существует
        ensurePrivacyColumn();
        
        $stmt = $db->prepare("UPDATE users SET is_private = ? WHERE id = ?");
        $stmt->execute([$isPrivate ? 1 : 0, $userId]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Ошибка обновления настройки приватности: " . $e->getMessage());
        return false;
    }
}

function normalizeTwitchUsername($username) {
    return strtolower(ltrim(trim((string)$username), '@'));
}

function isValidTwitchUsername($username) {
    return (bool)preg_match('/^[a-z0-9_]{1,25}$/', $username);
}

function publicProfileUrl($username) {
    return siteUrl('u/' . rawurlencode(normalizeTwitchUsername($username)));
}

function publicProfileOgUrl($username) {
    return siteUrl('u/' . rawurlencode(normalizeTwitchUsername($username)) . '/og.png');
}

function isUserProfilePrivate($username) {
    ensurePrivacyColumn();
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT is_private FROM users WHERE LOWER(username) = ?");
        $stmt->execute([normalizeTwitchUsername($username)]);
        $row = $stmt->fetch();
        return $row && (int)($row['is_private'] ?? 0) === 1;
    } catch (PDOException $e) {
        return false;
    }
}

function getPublicProfile($nick) {
    $username = normalizeTwitchUsername($nick);

    if ($username === '' || !isValidTwitchUsername($username)) {
        return ['status' => 'invalid'];
    }

    try {
        $db = getDB();
        ensurePrivacyColumn();

        $stmt = $db->prepare("SELECT username FROM user_time_tracking WHERE LOWER(username) = ?");
        $stmt->execute([$username]);
        $inTracking = (bool)$stmt->fetch();

        $stmt = $db->prepare("SELECT username FROM users WHERE LOWER(username) = ?");
        $stmt->execute([$username]);
        $onSite = (bool)$stmt->fetch();

        if (!$inTracking && !$onSite) {
            return ['status' => 'not_found', 'username' => $username];
        }

        if (isUserProfilePrivate($username)) {
            return ['status' => 'private', 'username' => $username];
        }

        $stmt = $db->prepare("SELECT display_name, avatar_url FROM users WHERE LOWER(username) = ?");
        $stmt->execute([$username]);
        $userRow = $stmt->fetch();

        $stats = getUserStats($username);
        $bonus = getUserDailyBonusStats($username);

        return [
            'status' => 'public',
            'username' => $username,
            'display_name' => $userRow['display_name'] ?? $username,
            'avatar_url' => $userRow['avatar_url'] ?? null,
            'stats' => $stats,
            'bonus_streak' => (int)($bonus['current_streak'] ?? 0),
            'bonus_longest' => (int)($bonus['longest_streak'] ?? 0),
        ];
    } catch (PDOException $e) {
        error_log('Ошибка получения публичного профиля: ' . $e->getMessage());
        return ['status' => 'error', 'username' => $username];
    }
}

function buildUserStatsExportRows($username, $displayName = null) {
    $stats = getUserStats($username);
    $bonus = getUserDailyBonusStats($username);
    $displayName = $displayName ?: $username;

    return [
        ['Поле', 'Значение'],
        ['Никнейм', '@' . $username],
        ['Отображаемое имя', $displayName],
        ['Общее время (сек)', (string)$stats['total_seconds']],
        ['Общее время', $stats['hours'] . 'ч ' . $stats['minutes'] . 'м ' . $stats['seconds'] . 'с'],
        ['Сообщений', (string)$stats['messages_count']],
        ['Статус рефки', $stats['is_active'] ? 'Активен' : 'Неактивен'],
        ['Последняя активность', $stats['last_activity'] ?: '—'],
        ['Серия бонусов', $bonus['current_streak'] . '/7'],
        ['Лучшая серия бонусов', (string)$bonus['longest_streak']],
        ['Бонусов получено всего', (string)$bonus['total_claims']],
        ['Бонус сегодня', $bonus['claimed_today'] ? 'Получен' : 'Доступен (+' . $bonus['next_reward_minutes'] . ' мин)'],
        ['Экспорт создан', (new DateTime('now', new DateTimeZone(DAILY_BONUS_TIMEZONE)))->format('d.m.Y H:i')],
    ];
}


function ensureStreamStatusTable() {
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS twitch_stream_status (
                channel VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY,
                is_live TINYINT(1) DEFAULT 0,
                started_at DATETIME DEFAULT NULL,
                last_notified_at DATETIME DEFAULT NULL,
                last_checked DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $stmt = $db->prepare("INSERT IGNORE INTO twitch_stream_status (channel) VALUES (?)");
        $stmt->execute([TWITCH_CHANNEL_LOGIN]);
    } catch (PDOException $e) {
        error_log("Ошибка проверки таблицы twitch_stream_status: " . $e->getMessage());
    }
}

function getStreamStatus($channel) {
    ensureStreamStatusTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM twitch_stream_status WHERE channel = ? LIMIT 1");
    $stmt->execute([$channel]);
    return $stmt->fetch();
}

function updateStreamStatus($channel, $isLive, $startedAt = null, $notifiedAt = null, $streamTitle = null, $gameName = null) {
    ensureStreamStatusTable();
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE twitch_stream_status 
        SET is_live = ?, started_at = ?, last_notified_at = ?, stream_title = ?, game_name = ?, last_checked = NOW()
        WHERE channel = ?
    ");
    $stmt->execute([
        $isLive ? 1 : 0,
        $startedAt,
        $notifiedAt,
        $streamTitle,
        $gameName,
        $channel
    ]);
}

function formatStreamUptime($seconds) {
    $seconds = max(0, (int)$seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if ($hours > 0) {
        return $hours . 'ч ' . $minutes . 'м';
    }
    if ($minutes > 0) {
        return $minutes . 'м';
    }
    return $seconds . 'с';
}

function getTwitchEmbedParents() {
    $parents = [SITE_HOST];
    if (!empty($_SERVER['HTTP_HOST'])) {
        $host = preg_replace('/:\d+$/', '', (string)$_SERVER['HTTP_HOST']);
        if ($host && !in_array($host, $parents, true)) {
            $parents[] = $host;
        }
    }
    return $parents;
}

function getTwitchPlayerEmbedUrl($channel, array $parents = null) {
    $parents = $parents ?? getTwitchEmbedParents();
    $query = ['channel' => $channel, 'muted' => 'true'];
    foreach ($parents as $parent) {
        $query['parent'][] = $parent;
    }
    return 'https://player.twitch.tv/?' . http_build_query($query);
}

function twitchHelixRequest(string $url, ?string $accessToken, array $query = []): ?array {
    if (!$accessToken) {
        return null;
    }
    if (!empty($query)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Client-Id: ' . TWITCH_CLIENT_ID,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        error_log('Twitch Helix ' . $httpCode . ': ' . $response . ' | ' . $url);
        return null;
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

function getTwitchBroadcasterId(): ?string {
    static $broadcasterId = null;
    static $resolved = false;
    if ($resolved) {
        return $broadcasterId;
    }
    $resolved = true;
    $token = getTwitchAppAccessToken();
    $data = twitchHelixRequest('https://api.twitch.tv/helix/users', $token, ['login' => TWITCH_CHANNEL_LOGIN]);
    $broadcasterId = $data['data'][0]['id'] ?? null;
    return $broadcasterId;
}

function buildStreamDisplayInfo(array $status) {
    $channel = TWITCH_CHANNEL_LOGIN;
    $isLive = !empty($status['is_live']);
    $uptimeText = '';
    if ($isLive && !empty($status['started_at'])) {
        $uptimeText = formatStreamUptime(time() - strtotime($status['started_at']));
    }
    return [
        'is_live' => $isLive,
        'channel' => $channel,
        'channel_url' => 'https://www.twitch.tv/' . rawurlencode($channel),
        'started_at' => $status['started_at'] ?? null,
        'uptime_text' => $uptimeText,
        'title' => trim((string)($status['stream_title'] ?? '')),
        'game_name' => trim((string)($status['game_name'] ?? '')),
        'embed_url' => getTwitchPlayerEmbedUrl($channel),
        'last_checked' => $status['last_checked'] ?? null,
    ];
}

function refreshTwitchStreamStatus($channel = null) {
    $channel = $channel ?: TWITCH_CHANNEL_LOGIN;
    ensureStreamStatusTable();
    $previous = getStreamStatus($channel) ?: [];
    $accessToken = getTwitchAppAccessToken();
    if (!$accessToken) {
        return buildStreamDisplayInfo($previous);
    }
    $broadcasterId = getTwitchBroadcasterId();
    if (!$broadcasterId) {
        return buildStreamDisplayInfo($previous);
    }
    $data = twitchHelixRequest('https://api.twitch.tv/helix/streams', $accessToken, ['user_id' => $broadcasterId]);
    if ($data === null) {
        return buildStreamDisplayInfo($previous);
    }
    $stream = $data['data'][0] ?? null;
    if (!$stream) {
        updateStreamStatus($channel, false, null, $previous['last_notified_at'] ?? null, null, null);
        return buildStreamDisplayInfo(getStreamStatus($channel) ?: ['is_live' => 0]);
    }
    $startedAt = (new DateTime($stream['started_at']))->format('Y-m-d H:i:s');
    updateStreamStatus(
        $channel,
        true,
        $startedAt,
        $previous['last_notified_at'] ?? null,
        $stream['title'] ?? null,
        $stream['game_name'] ?? null
    );
    return buildStreamDisplayInfo(getStreamStatus($channel) ?: []);
}

function getStreamDisplayForHomepage($maxAgeSeconds = 90) {
    ensureStreamStatusTable();
    $db = getDB();
    $titleColumn = $db->query("SHOW COLUMNS FROM twitch_stream_status LIKE 'stream_title'");
    if ($titleColumn && $titleColumn->rowCount() === 0) {
        $db->exec("ALTER TABLE twitch_stream_status ADD COLUMN stream_title VARCHAR(500) DEFAULT NULL AFTER started_at");
        $db->exec("ALTER TABLE twitch_stream_status ADD COLUMN game_name VARCHAR(255) DEFAULT NULL AFTER stream_title");
    }
    $status = getStreamStatus(TWITCH_CHANNEL_LOGIN) ?: ['is_live' => 0];
    $needsRefresh = true;
    if (!empty($status['last_checked'])) {
        $lastChecked = strtotime($status['last_checked']);
        if ($lastChecked && (time() - $lastChecked) < max(30, (int)$maxAgeSeconds)) {
            $needsRefresh = false;
        }
    }
    if ($needsRefresh) {
        return refreshTwitchStreamStatus(TWITCH_CHANNEL_LOGIN);
    }
    return buildStreamDisplayInfo($status);
}

function getTwitchAppAccessToken() {
    static $tokenData = null;

    if ($tokenData && isset($tokenData['access_token'], $tokenData['expires_at']) && $tokenData['expires_at'] > time()) {
        return $tokenData['access_token'];
    }

    $url = 'https://id.twitch.tv/oauth2/token';
    $payload = [
        'client_id' => TWITCH_CLIENT_ID,
        'client_secret' => TWITCH_CLIENT_SECRET,
        'grant_type' => 'client_credentials'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    if ($response === false) {
        error_log('Ошибка получения токена Twitch: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('Ошибка ответа Twitch при получении токена: ' . $response);
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['access_token'], $data['expires_in'])) {
        error_log('Некорректный ответ Twitch при получении токена: ' . $response);
        return null;
    }

    $tokenData = [
        'access_token' => $data['access_token'],
        'expires_at' => time() + (int)$data['expires_in'] - 30
    ];

    return $tokenData['access_token'];
}

// ——— Лотерея-гонки ———

const WIKI_RADMIR_ORIGIN = 'https://wiki.radmir.online';

function getWikiCatalogSourceDefinitions(): array
{
    return [
        'skins' => [
            'group' => 'skins',
            'label' => 'Скины',
            'type' => 'paged',
            'prefix' => 'Скины: Страница_',
            'max_pages' => 250,
        ],
        'economy' => [
            'group' => 'cars',
            'label' => 'Автосалон · эконом',
            'type' => 'page',
            'title' => 'Автосалон_эконом_класса',
        ],
        'middle' => [
            'group' => 'cars',
            'label' => 'Автосалон · средний',
            'type' => 'page',
            'title' => 'Автосалон_среднего_класса',
        ],
        'premium' => [
            'group' => 'cars',
            'label' => 'Автосалон · премиум',
            'type' => 'page',
            'title' => 'Автосалон_премиум_класса',
        ],
        'moto' => [
            'group' => 'cars',
            'label' => 'Мото-вело салон',
            'type' => 'page',
            'title' => 'Мото-вело_салон',
        ],
        'truck' => [
            'group' => 'cars',
            'label' => 'Грузовые авто',
            'type' => 'page',
            'title' => 'Салон_грузовых_автомобилей',
        ],
        'cases' => [
            'group' => 'cases',
            'label' => 'Кейсы',
            'type' => 'cases',
            'title' => 'Кейсы',
        ],
    ];
}

function getWikiCatalogGroups(): array
{
    return [
        'skins' => 'Скины',
        'cars' => 'Машины',
        'cases' => 'Кейсы',
    ];
}

function getWikiCatalogBundleIds(): array
{
    return array_keys(getWikiCatalogGroups());
}

function isWikiCatalogBundle(string $bundle): bool
{
    return in_array($bundle, getWikiCatalogBundleIds(), true);
}

function wikiCatalogGroupForCategory(string $category): string
{
    if ($category === 'cases') {
        return 'cases';
    }
    if (in_array($category, ['economy', 'middle', 'premium', 'moto', 'truck', 'cars'], true)) {
        return 'cars';
    }

    return 'skins';
}

function wikiSkinCategoryLabel(string $category): string
{
    $defs = getWikiCatalogSourceDefinitions();
    if (isset($defs[$category]['label'])) {
        return (string)$defs[$category]['label'];
    }

    return $category !== '' ? $category : 'Прочее';
}

function wikiRadmirNormalizeFilename(string $name): string
{
    return str_replace(' ', '_', trim($name));
}

function wikiRadmirFileRelPath(string $filename): string
{
    $filename = wikiRadmirNormalizeFilename($filename);
    $hash = md5($filename);

    return $hash[0] . '/' . substr($hash, 0, 2) . '/' . $filename;
}

function wikiRadmirExtractRelPath(string $path): ?string
{
    $path = trim($path);
    if ($path === '') {
        return null;
    }

    if (preg_match('~/images/(?:thumb/)?([0-9a-f]/[0-9a-f]{2}/[^/?#]+)~i', $path, $m)) {
        return $m[1];
    }

    if (preg_match('#^[0-9a-f]/[0-9a-f]{2}/.+#i', $path)) {
        return $path;
    }

    return null;
}

function wikiRadmirThumbUrl(string $fileOrPath, int $width = 128): ?string
{
    $fileOrPath = trim($fileOrPath);
    if ($fileOrPath === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $fileOrPath)) {
        return $fileOrPath;
    }

    if (str_starts_with($fileOrPath, '/images/thumb/')) {
        return WIKI_RADMIR_ORIGIN . $fileOrPath;
    }

    if (str_starts_with($fileOrPath, 'wiki:')) {
        $fileOrPath = substr($fileOrPath, 5);
    }

    $rel = wikiRadmirExtractRelPath($fileOrPath);
    if ($rel === null) {
        $rel = wikiRadmirFileRelPath($fileOrPath);
    }

    $filename = basename($rel);

    return sprintf(
        '%s/images/thumb/%s/%dpx-%s',
        WIKI_RADMIR_ORIGIN,
        $rel,
        max(16, $width),
        rawurlencode($filename)
    );
}

function lotteryLocalCaseIconFile(int $index): ?string
{
    if ($index < 0 || $index > 16) {
        return null;
    }

    $file = $index . '.png';
    $path = __DIR__ . '/icons/' . $file;

    return is_readable($path) ? $file : null;
}

function lotteryLocalCaseIconRef(int $index): ?string
{
    $file = lotteryLocalCaseIconFile($index);

    return $file !== null ? 'local:' . $file : null;
}

function lotteryLocalIconFileFromRef(?string $icon): ?string
{
    if ($icon === null || trim($icon) === '') {
        return null;
    }

    $icon = trim($icon);
    if (preg_match('#^(?:local|icon):(.+)$#i', $icon, $m)) {
        $file = basename(str_replace('\\', '/', $m[1]));
    } elseif (preg_match('#^\d+\.(?:png|gif|webp|jpe?g)$#i', $icon)) {
        $file = $icon;
    } else {
        return null;
    }

    $path = __DIR__ . '/icons/' . $file;

    return is_readable($path) ? $file : null;
}

function resolveLotteryLocalIconUrl(?string $icon): ?string
{
    $file = lotteryLocalIconFileFromRef($icon);
    if ($file === null) {
        return null;
    }

    return siteUrl('icons/' . rawurlencode($file));
}

function lotteryCaseIconIndexFromName(string $name): ?int
{
    return caseIconIndexFromLabel($name);
}

function lotteryCaseIconIndexFromStore(string $store): ?int
{
    $store = trim($store);
    if ($store === '') {
        return null;
    }

    if (preg_match('/^case:(\d+)$/', $store, $m)) {
        $n = (int)$m[1];
        if ($n >= 0 && $n <= 16) {
            return $n;
        }
    }

    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (loadWikiCatalogCache()['cases'] ?? [] as $case) {
            $storeKey = (string)($case['store'] ?? '');
            if ($storeKey === '') {
                continue;
            }
            $index = caseIconIndexFromLabel((string)($case['name'] ?? ''));
            if ($index !== null) {
                $map[$storeKey] = max(0, min(16, $index));
            }
        }
    }

    if (isset($map[$store])) {
        return $map[$store];
    }

    if (str_starts_with($store, 'case:')) {
        foreach (loadWikiCatalogCache()['cases'] ?? [] as $case) {
            if ((string)($case['store'] ?? '') === $store) {
                return caseIconIndexFromLabel((string)($case['name'] ?? ''));
            }
        }

        return caseIconIndexFromLabel(wikiCaseCleanDisplayName(substr($store, 5)));
    }

    return null;
}

function lotteryAttachCaseIcons(array &$cases): void
{
    foreach ($cases as $i => &$case) {
        if ($i > 16) {
            break;
        }

        $index = caseIconIndexFromLabel((string)($case['name'] ?? ''));
        if ($index === null) {
            continue;
        }
        $index = max(0, min(16, $index));
        $case['case_index'] = $index;

        $ref = lotteryLocalCaseIconRef($index);
        if ($ref !== null) {
            $case['icon'] = $ref;
            $case['thumb'] = resolveLotteryLocalIconUrl($ref);
        }
    }
    unset($case);
}

function resolveLotteryPrizeIconUrl(?string $icon, int $width = 96): ?string
{
    if ($icon === null || trim($icon) === '') {
        return null;
    }

    $icon = trim($icon);

    $local = resolveLotteryLocalIconUrl($icon);
    if ($local !== null) {
        return $local;
    }

    if (str_starts_with($icon, 'case:')) {
        $index = lotteryCaseIconIndexFromStore($icon);
        if ($index !== null) {
            $local = resolveLotteryLocalIconUrl(lotteryLocalCaseIconRef($index));
            if ($local !== null) {
                return $local;
            }
        }
    }

    if (wikiThumbCacheExists($icon)) {
        return siteUrl('api/lottery_wiki_image.php?icon=' . rawurlencode($icon));
    }

    if (preg_match('#^https?://#i', $icon)) {
        return $icon;
    }
    if (
        str_starts_with($icon, 'wiki:')
        || str_starts_with($icon, '/images/')
        || preg_match('#^[0-9a-f]/[0-9a-f]{2}/#i', $icon)
    ) {
        return wikiRadmirThumbUrl($icon, $width);
    }

    return null;
}

function wikiThumbCacheDir(): string
{
    return __DIR__ . '/data/wiki_thumbs';
}

function wikiIconToFilename(string $icon): string
{
    $icon = trim($icon);
    if (str_starts_with($icon, 'wiki:')) {
        $icon = substr($icon, 5);
    }
    if (preg_match('#/([^/]+\.(?:png|jpe?g|gif|webp))(?:/|$)#i', $icon, $m)) {
        $icon = $m[1];
    }

    return wikiRadmirNormalizeFilename($icon);
}

function wikiThumbCacheKey(string $icon): string
{
    return md5(wikiIconToFilename($icon));
}

function wikiThumbCacheFilePath(string $icon): string
{
    return wikiThumbCacheDir() . '/' . wikiThumbCacheKey($icon) . '.bin';
}

function wikiThumbCacheMetaPath(string $icon): string
{
    return wikiThumbCacheDir() . '/' . wikiThumbCacheKey($icon) . '.json';
}

function wikiThumbCacheExists(string $icon): bool
{
    return is_readable(wikiThumbCacheFilePath($icon));
}

function wikiThumbCacheCount(): int
{
    $dir = wikiThumbCacheDir();
    if (!is_dir($dir)) {
        return 0;
    }

    $count = 0;
    foreach (glob($dir . '/*.bin') ?: [] as $file) {
        if (is_file($file)) {
            $count++;
        }
    }

    return $count;
}

function wikiThumbCacheMeta(string $icon): array
{
    $path = wikiThumbCacheMetaPath($icon);
    if (!is_readable($path)) {
        return ['mime' => 'image/png', 'file' => wikiIconToFilename($icon)];
    }

    $data = json_decode((string)file_get_contents($path), true);

    return is_array($data) ? $data : ['mime' => 'image/png', 'file' => wikiIconToFilename($icon)];
}

function wikiThumbCacheDetectMime(string $binary): string
{
    if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
        return 'image/png';
    }
    if (str_starts_with($binary, "\xFF\xD8\xFF")) {
        return 'image/jpeg';
    }
    if (str_starts_with($binary, 'GIF87a') || str_starts_with($binary, 'GIF89a')) {
        return 'image/gif';
    }
    if (str_starts_with($binary, 'RIFF') && substr($binary, 8, 4) === 'WEBP') {
        return 'image/webp';
    }

    return 'image/png';
}

function wikiThumbCacheWrite(string $icon, string $binary): bool
{
    if ($binary === '') {
        return false;
    }

    $dir = wikiThumbCacheDir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $mime = wikiThumbCacheDetectMime($binary);
    $meta = [
        'icon' => trim($icon),
        'file' => wikiIconToFilename($icon),
        'mime' => $mime,
        'updated_at' => gmdate('c'),
    ];

    if (file_put_contents(wikiThumbCacheFilePath($icon), $binary) === false) {
        return false;
    }

    file_put_contents(
        wikiThumbCacheMetaPath($icon),
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    return true;
}

function wikiThumbCacheSaveFromEntry(array $entry): bool
{
    $payload = (string)($entry['b64'] ?? $entry['data'] ?? '');
    if ($payload === '') {
        return false;
    }

    if (str_contains($payload, ',')) {
        $payload = explode(',', $payload, 2)[1];
    }

    $binary = base64_decode($payload, true);
    if ($binary === false || $binary === '') {
        return false;
    }

    $normalized = normalizeWikiSkinEntry($entry);
    if ($normalized === null) {
        return false;
    }

    return wikiThumbCacheWrite((string)$normalized['store'], $binary);
}

function findWikiSkinStoreByTitle(string $title): ?string
{
    $title = trim($title);
    if ($title === '') {
        return null;
    }

    $lower = mb_strtolower($title);
    foreach (wikiCatalogAllItems(loadWikiCatalogCache()) as $skin) {
        if (mb_strtolower((string)$skin['name']) === $lower) {
            return (string)$skin['store'];
        }
    }

    $guesses = [
        $title . '.png',
        str_replace(' ', '_', $title) . '.png',
        mb_strtolower(str_replace(' ', '_', $title)) . '.png',
    ];
    foreach ($guesses as $guess) {
        $store = 'wiki:' . $guess;
        if (wikiThumbCacheExists($store)) {
            return $store;
        }
    }

    foreach (wikiCatalogAllItems(loadWikiCatalogCache()) as $skin) {
        $name = (string)$skin['name'];
        if ($name === '') {
            continue;
        }
        if (mb_stripos($name, $title) !== false || mb_stripos($title, $name) !== false) {
            return (string)$skin['store'];
        }
    }

    return null;
}

function wikiSkinCleanDisplayName(string $name, string $group = ''): string
{
    $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $isCar = $group === 'cars'
        || preg_match('/<b[^>]*>/i', $name)
        || preg_match('/\bЦена\s*:/iu', $name)
        || preg_match('/\|\s*Привод\s*:/iu', $name);

    if ($isCar) {
        if (preg_match('/<b[^>]*>(.*?)<\/b>/is', $name, $m)) {
            $name = $m[1];
        }

        $name = strip_tags($name);

        if (preg_match('/^(.*?)\s*Цена\s*:/ius', $name, $m)) {
            $name = $m[1];
        } elseif (preg_match('/\|\s*Привод\s*:/iu', $name)) {
            $name = (string)preg_split('/\s*\|\s*/u', $name, 2)[0];
        }
    } else {
        $name = strip_tags($name);
    }

    $name = preg_replace('/^(?:File|Файл):/iu', '', $name);
    $name = preg_replace('/\.[^.]+$/', '', $name);
    $name = str_replace('_', ' ', $name);
    $name = preg_replace('/\s+/u', ' ', $name);

    return trim($name);
}

function wikiCaseCleanDisplayName(string $name): string
{
    $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('/<b[^>]*>(.*?)<\/b>/is', $name, $m)) {
        $name = $m[1];
    }
    $name = strip_tags($name);
    $name = preg_replace('/^\d+\.\s*/u', '', $name);
    $name = preg_replace('/\s+/u', ' ', $name);

    return trim($name);
}

function wikiCaseStoreFromName(string $name): string
{
    $slug = mb_strtolower(trim($name));
    $slug = preg_replace('/\s+/u', '-', $slug);
    $slug = preg_replace('/[^\p{L}\p{N}\-«»"]/u', '', $slug);
    if ($slug === '') {
        $slug = substr(md5($name), 0, 12);
    }

    return 'case:' . $slug;
}

function findWikiSkinNameByStore(string $store): ?string
{
    $store = trim($store);
    if ($store === '') {
        return null;
    }

    foreach (wikiCatalogAllItems(loadWikiCatalogCache()) as $skin) {
        if ((string)$skin['store'] === $store) {
            $name = wikiSkinCleanDisplayName((string)$skin['name'], (string)($skin['group'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }
    }

    if (str_starts_with($store, 'wiki:')) {
        $name = wikiSkinCleanDisplayName(substr($store, 5));
        return $name !== '' ? $name : null;
    }

    return null;
}

function getWikiJsonBundleDir(): string
{
    return __DIR__ . '/json-wiki';
}

function getWikiCatalogCachePath(): string
{
    return __DIR__ . '/data/wiki_catalog.json';
}

function getWikiSkinsCachePath(): string
{
    return getWikiCatalogCachePath();
}

function wikiCatalogEmpty(): array
{
    return [
        'skins_updated_at' => null,
        'cars_updated_at' => null,
        'cases_updated_at' => null,
        'skins' => [],
        'cars' => [],
        'cases' => [],
    ];
}

function wikiCatalogAllItems(array $catalog): array
{
    return array_merge(
        is_array($catalog['skins'] ?? null) ? $catalog['skins'] : [],
        is_array($catalog['cars'] ?? null) ? $catalog['cars'] : [],
        is_array($catalog['cases'] ?? null) ? $catalog['cases'] : []
    );
}

function wikiNormalizeCatalogEntries(array $list, string $defaultGroup): array
{
    $items = [];
    foreach ($list as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $normalized = normalizeWikiSkinEntry($entry);
        if ($normalized === null) {
            continue;
        }
        if (($normalized['group'] ?? '') === '') {
            $group = trim((string)($entry['group'] ?? ''));
            if ($group === '') {
                $group = wikiCatalogGroupForCategory((string)$normalized['category']);
            }
            $normalized['group'] = in_array($group, getWikiCatalogBundleIds(), true) ? $group : $defaultGroup;
        }
        if ($normalized['category_label'] === '' && $normalized['category'] !== '') {
            $normalized['category_label'] = wikiSkinCategoryLabel($normalized['category']);
        }
        $items[] = $normalized;
    }

    usort($items, static function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']);
    });

    return $items;
}

function loadWikiCatalogCache(): array
{
    $path = getWikiCatalogCachePath();
    $legacyPath = __DIR__ . '/data/wiki_skins.json';

    if (!is_readable($path) && is_readable($legacyPath)) {
        $legacyRaw = file_get_contents($legacyPath);
        $legacyData = json_decode($legacyRaw !== false ? $legacyRaw : '', true);
        if (is_array($legacyData)) {
            $legacyList = $legacyData['skins'] ?? $legacyData;
            if (is_array($legacyList)) {
                $migrated = wikiCatalogEmpty();
                $migrated['skins'] = wikiNormalizeCatalogEntries($legacyList, 'skins');
                $migrated['skins_updated_at'] = $legacyData['updated_at'] ?? null;
                saveWikiCatalogCache($migrated);
                return $migrated;
            }
        }
    }

    if (!is_readable($path)) {
        return wikiCatalogEmpty();
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return wikiCatalogEmpty();
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return wikiCatalogEmpty();
    }

    $catalog = wikiCatalogEmpty();
    $catalog['skins_updated_at'] = $data['skins_updated_at'] ?? $data['updated_at'] ?? null;
    $catalog['cars_updated_at'] = $data['cars_updated_at'] ?? null;
    $catalog['cases_updated_at'] = $data['cases_updated_at'] ?? null;
    $catalog['skins'] = wikiNormalizeCatalogEntries(is_array($data['skins'] ?? null) ? $data['skins'] : [], 'skins');
    $catalog['cars'] = wikiNormalizeCatalogEntries(is_array($data['cars'] ?? null) ? $data['cars'] : [], 'cars');
    $catalog['cases'] = wikiNormalizeCatalogEntries(is_array($data['cases'] ?? null) ? $data['cases'] : [], 'cases');

    if (empty($catalog['cars']) && !empty($data['skins']) && is_array($data['skins'])) {
        $splitSkins = [];
        $splitCars = [];
        foreach ($catalog['skins'] as $item) {
            if (($item['group'] ?? 'skins') === 'cars') {
                $splitCars[] = $item;
            } else {
                $splitSkins[] = $item;
            }
        }
        if (!empty($splitCars)) {
            $catalog['skins'] = $splitSkins;
            $catalog['cars'] = $splitCars;
        }
    }

    if (!empty($catalog['cases'])) {
        lotteryAttachCaseIcons($catalog['cases']);
    }

    return $catalog;
}

function saveWikiCatalogCache(array $catalog): bool
{
    $payload = wikiCatalogEmpty();
    $payload['skins_updated_at'] = $catalog['skins_updated_at'] ?? null;
    $payload['cars_updated_at'] = $catalog['cars_updated_at'] ?? null;
    $payload['cases_updated_at'] = $catalog['cases_updated_at'] ?? null;
    $payload['skins'] = is_array($catalog['skins'] ?? null) ? $catalog['skins'] : [];
    $payload['cars'] = is_array($catalog['cars'] ?? null) ? $catalog['cars'] : [];
    $payload['cases'] = is_array($catalog['cases'] ?? null) ? $catalog['cases'] : [];

    $dir = dirname(getWikiCatalogCachePath());
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return $json !== false && file_put_contents(getWikiCatalogCachePath(), $json) !== false;
}

function loadWikiSkinsCache(): array
{
    $catalog = loadWikiCatalogCache();

    return [
        'updated_at' => $catalog['skins_updated_at'] ?? $catalog['cars_updated_at'] ?? $catalog['cases_updated_at'] ?? null,
        'skins' => wikiCatalogAllItems($catalog),
        'catalog' => $catalog,
    ];
}

function parseWikiBundleJsonPayload(array $data): array
{
    if (isset($data['skins']) && is_array($data['skins'])) {
        return $data['skins'];
    }
    if (isset($data['items']) && is_array($data['items'])) {
        return $data['items'];
    }

    return array_is_list($data) ? $data : [];
}

function listWikiJsonBundles(): array
{
    $groups = getWikiCatalogGroups();
    $out = [];
    foreach (array_keys($groups) as $bundle) {
        $path = getWikiJsonBundleDir() . '/' . $bundle . '.json';
        $out[$bundle] = [
            'bundle' => $bundle,
            'label' => $groups[$bundle],
            'filename' => $bundle . '.json',
            'exists' => is_readable($path),
            'size' => is_file($path) ? (int)filesize($path) : 0,
            'modified_at' => is_file($path) ? gmdate('c', (int)filemtime($path)) : null,
        ];
    }

    return $out;
}

function importWikiJsonBundle(string $bundle, bool $replace = true): array
{
    if (!isWikiCatalogBundle($bundle)) {
        return ['success' => false, 'error' => 'Неверный bundle'];
    }

    $path = getWikiJsonBundleDir() . '/' . $bundle . '.json';
    if (!is_readable($path)) {
        return ['success' => false, 'error' => 'Файл json-wiki/' . $bundle . '.json не найден'];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return ['success' => false, 'error' => 'Пустой JSON-файл'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['success' => false, 'error' => 'Неверный JSON'];
    }

    return saveWikiCatalogBundle($bundle, parseWikiBundleJsonPayload($data), !$replace);
}

function saveWikiCatalogBundle(string $bundle, array $items, bool $merge = false): array
{
    if (!isWikiCatalogBundle($bundle)) {
        return ['success' => false, 'error' => 'Неверный bundle'];
    }

    $catalog = loadWikiCatalogCache();
    $normalized = $merge ? ($catalog[$bundle] ?? []) : [];
    $seen = [];
    foreach ($normalized as $entry) {
        $seen[(string)$entry['store']] = true;
    }

    foreach ($items as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        wikiThumbCacheSaveFromEntry($entry);

        $item = normalizeWikiSkinEntry($entry);
        if ($item === null) {
            continue;
        }

        $item['group'] = $bundle;
        if (($item['category'] ?? '') === '' || $item['category'] === 'skins') {
            if ($bundle === 'cars' && !empty($entry['category'])) {
                $item['category'] = (string)$entry['category'];
            } elseif ($bundle === 'skins') {
                $item['category'] = 'skins';
                $item['category_label'] = 'Скины';
            } elseif ($bundle === 'cases') {
                $item['category'] = 'cases';
                $item['category_label'] = 'Кейсы';
            }
        }

        $key = (string)$item['store'];
        if (isset($seen[$key])) {
            foreach ($normalized as &$existing) {
                if ((string)$existing['store'] !== $key) {
                    continue;
                }
                $newName = $bundle === 'cases'
                    ? wikiCaseCleanDisplayName((string)$item['name'])
                    : wikiSkinCleanDisplayName((string)$item['name'], $bundle);
                $oldName = $bundle === 'cases'
                    ? wikiCaseCleanDisplayName((string)$existing['name'])
                    : wikiSkinCleanDisplayName((string)$existing['name'], $bundle);
                if ($newName !== '' && mb_strlen($newName) > mb_strlen($oldName)) {
                    $existing['name'] = $newName;
                }
            }
            unset($existing);
            continue;
        }

        $seen[$key] = true;
        $normalized[] = $item;
    }

    if ($bundle === 'cases') {
        usort($normalized, static function (array $a, array $b): int {
            $na = lotteryCaseIconIndexFromName((string)($a['name'] ?? ''));
            $nb = lotteryCaseIconIndexFromName((string)($b['name'] ?? ''));
            if ($na !== null && $nb !== null) {
                return $na <=> $nb;
            }
            if ($na !== null) {
                return -1;
            }
            if ($nb !== null) {
                return 1;
            }

            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        lotteryAttachCaseIcons($normalized);
    } else {
        usort($normalized, static function (array $a, array $b): int {
            return strcasecmp($a['name'], $b['name']);
        });
    }

    $catalog[$bundle] = $normalized;
    $catalog[$bundle . '_updated_at'] = gmdate('c');

    if (!saveWikiCatalogCache($catalog)) {
        return ['success' => false, 'error' => 'Не удалось записать каталог'];
    }

    return [
        'success' => true,
        'bundle' => $bundle,
        'count' => count($normalized),
        'skins_count' => count($catalog['skins']),
        'cars_count' => count($catalog['cars']),
        'cases_count' => count($catalog['cases']),
        'thumbs_saved' => wikiThumbCacheCount(),
        'updated_at' => $catalog[$bundle . '_updated_at'],
    ];
}

function saveWikiJsonBundleFile(string $bundle, string $rawJson): array
{
    if (!isWikiCatalogBundle($bundle)) {
        return ['success' => false, 'error' => 'Неверный bundle'];
    }

    $data = json_decode($rawJson, true);
    if (!is_array($data)) {
        return ['success' => false, 'error' => 'Неверный JSON'];
    }

    $dir = getWikiJsonBundleDir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['success' => false, 'error' => 'Не удалось создать json-wiki/'];
    }

    $path = $dir . '/' . $bundle . '.json';
    if (file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
        return ['success' => false, 'error' => 'Не удалось сохранить файл'];
    }

    return [
        'success' => true,
        'bundle' => $bundle,
        'path' => 'json-wiki/' . $bundle . '.json',
        'size' => (int)filesize($path),
    ];
}

function saveWikiSkinsCache(array $skins, bool $merge = true, ?string $bundle = null): array
{
    $bundle = $bundle ?? 'skins';
    if (!isWikiCatalogBundle($bundle)) {
        $bundle = 'skins';
    }

    return saveWikiCatalogBundle($bundle, $skins, $merge);
}

function normalizeWikiSkinEntry(array $entry): ?array
{
    $category = trim((string)($entry['category'] ?? ''));
    $group = trim((string)($entry['group'] ?? ''));
    if ($group === '') {
        $group = wikiCatalogGroupForCategory($category);
    }
    if (!in_array($group, getWikiCatalogBundleIds(), true)) {
        $group = 'skins';
    }

    $storeHint = trim((string)($entry['store'] ?? ''));
    if ($group === 'cases' || str_starts_with($storeHint, 'case:')) {
        $name = wikiCaseCleanDisplayName((string)($entry['name'] ?? ''));
        if ($name === '' && str_starts_with($storeHint, 'case:')) {
            $name = wikiCaseCleanDisplayName(substr($storeHint, 5));
        }
        if ($name === '') {
            return null;
        }

        $store = str_starts_with($storeHint, 'case:') ? $storeHint : wikiCaseStoreFromName($name);
        $caseIndex = caseIconIndexFromLabel($name);
        if ($caseIndex !== null) {
            $caseIndex = max(0, min(16, $caseIndex));
        }
        $iconRef = trim((string)($entry['icon'] ?? ''));
        if ($iconRef === '' && $caseIndex !== null) {
            $iconRef = lotteryLocalCaseIconRef($caseIndex) ?? '';
        }
        $thumb = trim((string)($entry['thumb'] ?? ''));
        if ($thumb === '' && $iconRef !== '') {
            $thumb = resolveLotteryLocalIconUrl($iconRef) ?? '';
        }

        return [
            'file' => substr($store, 5),
            'store' => $store,
            'thumb' => $thumb,
            'icon' => $iconRef !== '' ? $iconRef : null,
            'case_index' => $caseIndex,
            'name' => $name,
            'category' => $category !== '' ? $category : 'cases',
            'category_label' => wikiSkinCategoryLabel($category !== '' ? $category : 'cases'),
            'group' => 'cases',
        ];
    }

    $file = trim((string)($entry['file'] ?? ''));
    if ($file === '' && !empty($entry['store'])) {
        $store = (string)$entry['store'];
        if (str_starts_with($store, 'wiki:')) {
            $file = substr($store, 5);
        }
    }
    if ($file === '') {
        $thumb = (string)($entry['thumb'] ?? '');
        if (preg_match('#/([^/]+\.(?:png|jpe?g|gif|webp))(?:/|$)#i', $thumb, $m)) {
            $file = $m[1];
        }
    }
    if ($file === '') {
        return null;
    }

    $name = wikiSkinCleanDisplayName((string)($entry['name'] ?? ''), $group);
    if ($name === '') {
        $name = wikiSkinCleanDisplayName($file, $group);
    }

    $thumb = trim((string)($entry['thumb'] ?? ''));
    if ($thumb === '' || !preg_match('#^https?://#i', $thumb)) {
        $built = wikiRadmirThumbUrl('wiki:' . $file, 52);
        $thumb = $built ?? (WIKI_RADMIR_ORIGIN . '/images/thumb/' . wikiRadmirFileRelPath($file) . '/52px-' . rawurlencode($file));
    }

    $categoryLabel = trim((string)($entry['category_label'] ?? ''));
    if ($categoryLabel === '' && $category !== '') {
        $categoryLabel = wikiSkinCategoryLabel($category);
    }

    return [
        'file' => $file,
        'store' => 'wiki:' . $file,
        'thumb' => $thumb,
        'name' => $name,
        'category' => $category,
        'category_label' => $categoryLabel,
        'group' => $group,
    ];
}

function getLotteryStageDefinitions(): array
{
    return [
        1 => ['cols' => 3, 'rows' => 3, 'count' => 9, 'bombs' => false, 'label' => 'Этап 1 — 3×3'],
        2 => ['cols' => 4, 'rows' => 4, 'count' => 16, 'bombs' => true, 'label' => 'Этап 2 — 4×4'],
        3 => ['cols' => 5, 'rows' => 5, 'count' => 25, 'bombs' => true, 'label' => 'Этап 3 — 5×5'],
    ];
}

function ensureLotteryTables(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS lottery_stage_cells (
                stage TINYINT UNSIGNED NOT NULL,
                cell_index TINYINT UNSIGNED NOT NULL,
                cell_type VARCHAR(16) NOT NULL DEFAULT 'empty',
                prize_title VARCHAR(255) NOT NULL DEFAULT '',
                prize_icon VARCHAR(255) DEFAULT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (stage, cell_index)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS lottery_games (
                id VARCHAR(36) PRIMARY KEY,
                stage TINYINT UNSIGNED NOT NULL,
                opened_cells JSON NOT NULL,
                is_ended TINYINT(1) NOT NULL DEFAULT 0,
                end_reason VARCHAR(32) DEFAULT NULL,
                matched_prize VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_lottery_games_stage (stage),
                INDEX idx_lottery_games_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        ensureLotteryGameColumns($db);
        $ready = true;

        $count = (int)$db->query('SELECT COUNT(*) FROM lottery_stage_cells')->fetchColumn();
        if ($count === 0) {
            seedLotteryTestData(false);
        }

        return true;
    } catch (PDOException $e) {
        error_log('Ошибка инициализации lottery: ' . $e->getMessage());
        $ready = false;
        return false;
    }
}

function ensureLotteryGameColumns(PDO $db): void
{
    $columns = [
        'end_reason' => 'VARCHAR(32) DEFAULT NULL',
        'matched_prize' => 'VARCHAR(255) DEFAULT NULL',
        'board_cells' => 'JSON DEFAULT NULL',
    ];
    foreach ($columns as $name => $definition) {
        try {
            $db->query("SELECT {$name} FROM lottery_games LIMIT 1");
        } catch (PDOException $e) {
            try {
                $db->exec("ALTER TABLE lottery_games ADD COLUMN {$name} {$definition}");
            } catch (PDOException $alterError) {
                error_log('lottery_games column ' . $name . ': ' . $alterError->getMessage());
            }
        }
    }
}

function seedLotteryTestData(bool $replace = true): bool
{
    if (!ensureLotteryTables()) {
        return false;
    }

    $db = getDB();
    if ($replace) {
        $db->exec('DELETE FROM lottery_stage_cells');
    } else {
        $existing = (int)$db->query('SELECT COUNT(*) FROM lottery_stage_cells')->fetchColumn();
        if ($existing > 0) {
            return true;
        }
    }

    $stage1Prizes = [
        'Сникерс', 'Сникерс', 'Пепси 0.5', 'Фундук', 'Сникерс',
        'Ключ от Гаража', 'VIP на 1 день', '100 000 ₽', 'Мотоблок',
    ];

    $stage2Prizes = [
        'Matiz', 'Matiz', 'LADA 2114', 'Matiz', 'UAZ Буханка',
        'Рефка +30 мин', 'Mercedes W210', 'Пустышка', 'Джекпот-удача', '50 000 ₽',
        'BMW E30', 'Ничего себе', 'Sherp', 'Кофе с собой', 'RX-7', 'Mark II',
    ];
    $stage2Bombs = [3, 8, 11, 16];

    $stage3Prizes = [
        'Sherp', 'Sherp', 'Юпитер-5', 'Sherp', 'Gazel', '2112', '2114',
        'Matiz', 'Буханка', 'Jeep XJ', 'Volga 24', 'BMW E30', 'Saab 9.3', 'UAZ 3153',
        'Патриот', 'Rio', 'Camry V40', 'E420', 'Mark 100', 'S2000', 'RX-7', 'Mini JCW',
        'GAZ Бизнес', 'ML63', 'Инвалидка',
    ];
    $stage3Bombs = [4, 9, 14, 19, 22, 25];

    $stmt = $db->prepare("
        INSERT INTO lottery_stage_cells (stage, cell_index, cell_type, prize_title, prize_icon)
        VALUES (?, ?, ?, ?, ?)
    ");

    for ($i = 1; $i <= 9; $i++) {
        $stmt->execute([1, $i, 'prize', $stage1Prizes[$i - 1], null]);
    }

    for ($i = 1; $i <= 16; $i++) {
        if (in_array($i, $stage2Bombs, true)) {
            $stmt->execute([2, $i, 'bomb', 'Бомба', null]);
        } else {
            $title = $stage2Prizes[$i - 1] ?? ('Приз #' . $i);
            $icon = ($title === 'Matiz') ? 'wiki:Matiz.png' : null;
            $stmt->execute([2, $i, 'prize', $title, $icon]);
        }
    }

    for ($i = 1; $i <= 25; $i++) {
        if (in_array($i, $stage3Bombs, true)) {
            $stmt->execute([3, $i, 'bomb', 'Бомба', null]);
        } else {
            $title = $stage3Prizes[$i - 1] ?? ('Приз #' . $i);
            $icon = null;
            if ($title === 'Sherp') {
                $icon = 'wiki:Sherp.png';
            } elseif ($title === 'Matiz') {
                $icon = 'wiki:Matiz.png';
            }
            $stmt->execute([3, $i, 'prize', $title, $icon]);
        }
    }

    return true;
}

function getLotteryStageCells(int $stage): array
{
    if (!ensureLotteryTables()) {
        return [];
    }

    $defs = getLotteryStageDefinitions();
    if (!isset($defs[$stage])) {
        return [];
    }

    $count = (int)$defs[$stage]['count'];
    $db = getDB();
    $stmt = $db->prepare('
        SELECT cell_index, cell_type, prize_title, prize_icon
        FROM lottery_stage_cells
        WHERE stage = ?
        ORDER BY cell_index ASC
    ');
    $stmt->execute([$stage]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[(int)$row['cell_index']] = [
            'cell_index' => (int)$row['cell_index'],
            'cell_type' => (string)$row['cell_type'],
            'prize_title' => (string)$row['prize_title'],
            'prize_icon' => $row['prize_icon'] !== null ? (string)$row['prize_icon'] : null,
        ];
    }

    $cells = [];
    for ($i = 1; $i <= $count; $i++) {
        $cells[] = $rows[$i] ?? [
            'cell_index' => $i,
            'cell_type' => 'empty',
            'prize_title' => '',
            'prize_icon' => null,
        ];
    }

    return $cells;
}

function buildShuffledLotteryBoard(int $stage): array
{
    $defs = getLotteryStageDefinitions();
    if (!isset($defs[$stage])) {
        return [];
    }

    $count = (int)$defs[$stage]['count'];
    $template = getLotteryStageCells($stage);
    $items = [];

    foreach ($template as $cell) {
        $items[] = [
            'cell_type' => (string)($cell['cell_type'] ?? 'empty'),
            'prize_title' => (string)($cell['prize_title'] ?? ''),
            'prize_icon' => $cell['prize_icon'] ?? null,
        ];
    }

    while (count($items) < $count) {
        $items[] = [
            'cell_type' => 'empty',
            'prize_title' => '',
            'prize_icon' => null,
        ];
    }

    $items = array_slice($items, 0, $count);
    shuffle($items);

    $board = [];
    for ($i = 1; $i <= $count; $i++) {
        $item = $items[$i - 1];
        $board[] = [
            'cell_index' => $i,
            'cell_type' => $item['cell_type'],
            'prize_title' => $item['prize_title'],
            'prize_icon' => $item['prize_icon'],
        ];
    }

    return $board;
}

function normalizeLotteryBoardCells(array $cells, int $count): array
{
    $byIndex = [];
    foreach ($cells as $cell) {
        if (!is_array($cell)) {
            continue;
        }
        $idx = (int)($cell['cell_index'] ?? 0);
        if ($idx < 1 || $idx > $count) {
            continue;
        }
        $byIndex[$idx] = [
            'cell_index' => $idx,
            'cell_type' => (string)($cell['cell_type'] ?? 'empty'),
            'prize_title' => (string)($cell['prize_title'] ?? ''),
            'prize_icon' => $cell['prize_icon'] ?? null,
        ];
    }

    $board = [];
    for ($i = 1; $i <= $count; $i++) {
        $board[] = $byIndex[$i] ?? [
            'cell_index' => $i,
            'cell_type' => 'empty',
            'prize_title' => '',
            'prize_icon' => null,
        ];
    }

    return $board;
}

function getLotteryGameBoardCells(array $game): array
{
    $stage = (int)($game['stage'] ?? 0);
    $defs = getLotteryStageDefinitions();
    if (!isset($defs[$stage])) {
        return [];
    }

    $count = (int)$defs[$stage]['count'];
    $board = $game['board_cells'] ?? null;
    if (is_array($board) && !empty($board)) {
        return normalizeLotteryBoardCells($board, $count);
    }

    return getLotteryStageCells($stage);
}

function saveLotteryStageCells(int $stage, array $cells): bool
{
    if (!ensureLotteryTables()) {
        return false;
    }

    $defs = getLotteryStageDefinitions();
    if (!isset($defs[$stage])) {
        return false;
    }

    $bombsAllowed = !empty($defs[$stage]['bombs']);
    $count = (int)$defs[$stage]['count'];
    $db = getDB();

    $delete = $db->prepare('DELETE FROM lottery_stage_cells WHERE stage = ?');
    $delete->execute([$stage]);

    $insert = $db->prepare('
        INSERT INTO lottery_stage_cells (stage, cell_index, cell_type, prize_title, prize_icon)
        VALUES (?, ?, ?, ?, ?)
    ');

    for ($i = 1; $i <= $count; $i++) {
        $cell = $cells[$i] ?? $cells[(string)$i] ?? null;
        if (!is_array($cell)) {
            continue;
        }

        $type = (string)($cell['cell_type'] ?? 'empty');
        if (!in_array($type, ['empty', 'prize', 'bomb'], true)) {
            $type = 'empty';
        }
        if ($type === 'bomb' && !$bombsAllowed) {
            $type = 'prize';
        }
        if ($type === 'empty') {
            $insert->execute([$stage, $i, 'empty', '', null]);
            continue;
        }
        if ($type === 'bomb') {
            $insert->execute([$stage, $i, 'bomb', 'Бомба', null]);
            continue;
        }

        $title = trim((string)($cell['prize_title'] ?? ''));
        if ($title === '') {
            $title = 'Приз';
        }
        $icon = trim((string)($cell['prize_icon'] ?? ''));
        $insert->execute([$stage, $i, 'prize', $title, $icon !== '' ? $icon : null]);
    }

    return true;
}

function startLotteryGame(int $stage): ?array
{
    if (!ensureLotteryTables()) {
        return null;
    }

    $defs = getLotteryStageDefinitions();
    if (!isset($defs[$stage])) {
        return null;
    }

    $gameId = bin2hex(random_bytes(16));
    $board = buildShuffledLotteryBoard($stage);
    $db = getDB();
    $stmt = $db->prepare('
        INSERT INTO lottery_games (id, stage, opened_cells, board_cells, is_ended)
        VALUES (?, ?, ?, ?, 0)
    ');
    $stmt->execute([
        $gameId,
        $stage,
        json_encode([], JSON_UNESCAPED_UNICODE),
        json_encode($board, JSON_UNESCAPED_UNICODE),
    ]);

    return [
        'game_id' => $gameId,
        'stage' => $stage,
        'opened' => [],
        'board_cells' => $board,
        'is_ended' => false,
        'stage_meta' => $defs[$stage],
    ];
}

function getLotteryGame(string $gameId): ?array
{
    if (!ensureLotteryTables()) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT id, stage, opened_cells, board_cells, is_ended, end_reason, matched_prize FROM lottery_games WHERE id = ? LIMIT 1');
    $stmt->execute([$gameId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $opened = json_decode((string)$row['opened_cells'], true);
    if (!is_array($opened)) {
        $opened = [];
    }

    $boardCells = json_decode((string)($row['board_cells'] ?? ''), true);
    if (!is_array($boardCells)) {
        $boardCells = [];
    }

    return [
        'game_id' => (string)$row['id'],
        'stage' => (int)$row['stage'],
        'opened' => array_values(array_map('intval', $opened)),
        'board_cells' => $boardCells,
        'is_ended' => (int)$row['is_ended'] === 1,
        'end_reason' => $row['end_reason'] !== null ? (string)$row['end_reason'] : null,
        'matched_prize' => $row['matched_prize'] !== null ? (string)$row['matched_prize'] : null,
    ];
}

function formatLotteryRevealCell(array $cell): array
{
    $icon = $cell['prize_icon'] ?? null;
    $title = trim((string)($cell['prize_title'] ?? ''));

    if (($cell['cell_type'] ?? '') === 'prize' && $title !== '') {
        $caseIndex = caseIconIndexFromLabel($title);
        if ($caseIndex !== null) {
            $icon = lotteryLocalCaseIconRef($caseIndex);
        }
    }

    if (($icon === null || trim((string)$icon) === '') && (($cell['cell_type'] ?? '') === 'prize')) {
        if ($icon === null || trim((string)$icon) === '') {
            $matched = findWikiSkinStoreByTitle($title);
            if ($matched !== null) {
                $icon = $matched;
            }
        }
    }

    if ($title === '' && $icon !== null && trim((string)$icon) !== '') {
        $title = findWikiSkinNameByStore((string)$icon) ?? 'Приз';
    }

    return [
        'cell_index' => (int)$cell['cell_index'],
        'cell_type' => (string)$cell['cell_type'],
        'prize_title' => $title,
        'prize_icon' => $icon !== null ? (string)$icon : null,
        'prize_icon_url' => resolveLotteryPrizeIconUrl(is_string($icon) ? $icon : null),
    ];
}

function lotteryPrizeMatchKey(array $cell): ?string
{
    if (($cell['cell_type'] ?? '') !== 'prize') {
        return null;
    }

    $title = mb_strtolower(trim((string)($cell['prize_title'] ?? '')));
    if ($title === '') {
        return null;
    }

    $icon = trim((string)($cell['prize_icon'] ?? ''));
    if ($icon !== '') {
        return $title . '|' . mb_strtolower($icon);
    }

    return $title;
}

function findLotteryTripleMatch(array $cells, array $openedIndexes): ?array
{
    $byIndex = [];
    foreach ($cells as $cell) {
        $byIndex[(int)$cell['cell_index']] = $cell;
    }

    $counts = [];
    foreach ($openedIndexes as $idx) {
        $idx = (int)$idx;
        if (!isset($byIndex[$idx])) {
            continue;
        }

        $key = lotteryPrizeMatchKey($byIndex[$idx]);
        if ($key === null) {
            continue;
        }

        if (!isset($counts[$key])) {
            $counts[$key] = [
                'count' => 0,
                'title' => (string)$byIndex[$idx]['prize_title'],
            ];
        }

        $counts[$key]['count']++;
        if ($counts[$key]['count'] >= 3) {
            return $counts[$key];
        }
    }

    return null;
}

function lotteryEndStatusMessage(?string $endReason, ?string $matchedPrize): string
{
    if ($endReason === 'triple_match' && $matchedPrize) {
        return '🎉 3 одинаковых: «' . $matchedPrize . '»! Лотерея остановлена';
    }
    if ($endReason === 'bomb') {
        return '💣 Бомба! Игра окончена';
    }

    return 'Игра окончена';
}

function revealLotteryGameCell(string $gameId, int $cellIndex): array
{
    $game = getLotteryGame($gameId);
    if (!$game) {
        return ['success' => false, 'error' => 'Игра не найдена'];
    }

    if ($game['is_ended']) {
        return ['success' => false, 'error' => 'Игра уже окончена'];
    }

    $stage = (int)$game['stage'];
    $defs = getLotteryStageDefinitions();
    if (!isset($defs[$stage])) {
        return ['success' => false, 'error' => 'Неверный этап'];
    }

    if ($cellIndex < 1 || $cellIndex > (int)$defs[$stage]['count']) {
        return ['success' => false, 'error' => 'Неверный билет'];
    }

    $opened = $game['opened'];
    $cells = getLotteryGameBoardCells($game);
    if (in_array($cellIndex, $opened, true)) {
        $cell = $cells[$cellIndex - 1] ?? null;
        return [
            'success' => true,
            'already_open' => true,
            'game_id' => $gameId,
            'stage' => $stage,
            'is_ended' => $game['is_ended'],
            'opened' => $opened,
            'reveal' => $cell ? formatLotteryRevealCell($cell) : null,
            'reveals' => buildLotteryRevealsMap($cells, $opened),
        ];
    }

    $cell = $cells[$cellIndex - 1] ?? [
        'cell_index' => $cellIndex,
        'cell_type' => 'empty',
        'prize_title' => '',
        'prize_icon' => null,
    ];

    $opened[] = $cellIndex;
    $gameEnded = false;
    $endReason = null;
    $matchedPrize = null;
    $allBombs = [];

    if (($cell['cell_type'] ?? '') === 'bomb') {
        $gameEnded = true;
        $endReason = 'bomb';
        foreach ($cells as $c) {
            if (($c['cell_type'] ?? '') === 'bomb') {
                $allBombs[] = (int)$c['cell_index'];
            }
        }
        $opened = array_values(array_unique(array_merge($opened, $allBombs)));
    } else {
        $triple = findLotteryTripleMatch($cells, $opened);
        if ($triple !== null) {
            $gameEnded = true;
            $endReason = 'triple_match';
            $matchedPrize = (string)$triple['title'];
        }
    }

    $db = getDB();
    $stmt = $db->prepare('
        UPDATE lottery_games
        SET opened_cells = ?, is_ended = ?, end_reason = ?, matched_prize = ?
        WHERE id = ?
    ');
    $stmt->execute([
        json_encode($opened, JSON_UNESCAPED_UNICODE),
        $gameEnded ? 1 : 0,
        $endReason,
        $matchedPrize,
        $gameId,
    ]);

    $result = [
        'success' => true,
        'game_id' => $gameId,
        'stage' => $stage,
        'is_ended' => $gameEnded,
        'end_reason' => $endReason,
        'matched_prize' => $matchedPrize,
        'opened' => $opened,
        'reveal' => formatLotteryRevealCell($cell),
        'reveals' => buildLotteryRevealsMap($cells, $opened),
    ];

    if ($gameEnded) {
        $result['message'] = lotteryEndStatusMessage($endReason, $matchedPrize);
        if ($endReason === 'bomb') {
            $result['all_bombs'] = $allBombs;
        }
    } elseif (($cell['cell_type'] ?? '') === 'prize') {
        $result['message'] = '🎁 ' . ($cell['prize_title'] ?: 'Приз');
    } elseif (($cell['cell_type'] ?? '') === 'empty') {
        $result['message'] = 'Пустой билет';
    }

    return $result;
}

function buildLotteryRevealsMap(array $cells, array $openedIndexes): array
{
    $byIndex = [];
    foreach ($cells as $cell) {
        $byIndex[(int)$cell['cell_index']] = $cell;
    }

    $reveals = [];
    foreach ($openedIndexes as $idx) {
        $idx = (int)$idx;
        if (isset($byIndex[$idx])) {
            $reveals[(string)$idx] = formatLotteryRevealCell($byIndex[$idx]);
        }
    }

    return $reveals;
}

function getLotteryActiveGameIdForStage(int $stage): ?string
{
    if (!ensureLotteryTables()) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare('
        SELECT id
        FROM lottery_games
        WHERE stage = ? AND is_ended = 0
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $stmt->execute([$stage]);
    $id = $stmt->fetchColumn();

    return $id !== false && $id !== '' ? (string)$id : null;
}

function getLotteryAdminLayout(int $stage, ?string $gameId = null, bool $createIfMissing = true): array
{
    $defs = getLotteryStageDefinitions();
    if (!isset($defs[$stage])) {
        return ['success' => false, 'error' => 'Неверный этап'];
    }

    if (!ensureLotteryTables()) {
        return ['success' => false, 'error' => 'Лотерея не настроена'];
    }

    if ($gameId === null || $gameId === '') {
        $gameId = getLotteryActiveGameIdForStage($stage);
    }

    if ($gameId === null && $createIfMissing) {
        $started = startLotteryGame($stage);
        $gameId = is_array($started) ? (string)($started['game_id'] ?? '') : '';
    }

    if ($gameId === null || $gameId === '') {
        return ['success' => false, 'error' => 'Нет активной игры'];
    }

    $game = getLotteryGame($gameId);
    if (!$game || (int)$game['stage'] !== $stage) {
        return ['success' => false, 'error' => 'Игра не найдена'];
    }

    $cells = getLotteryGameBoardCells($game);
    $board = [];
    foreach ($cells as $cell) {
        $board[] = formatLotteryRevealCell($cell);
    }

    usort($board, static function (array $a, array $b): int {
        return (int)$a['cell_index'] <=> (int)$b['cell_index'];
    });

    $meta = $defs[$stage];

    return [
        'success' => true,
        'stage' => $stage,
        'cols' => (int)$meta['cols'],
        'rows' => (int)$meta['rows'],
        'count' => (int)$meta['count'],
        'label' => $meta['label'],
        'game_id' => $game['game_id'],
        'is_ended' => $game['is_ended'],
        'opened' => $game['opened'],
        'board' => $board,
    ];
}

function getLotteryPublicState(int $stage, ?string $gameId = null): array
{
    $defs = getLotteryStageDefinitions();
    if (!isset($defs[$stage])) {
        return ['success' => false, 'error' => 'Неверный этап'];
    }

    if (!ensureLotteryTables()) {
        return ['success' => false, 'error' => 'Лотерея не настроена'];
    }

    $meta = $defs[$stage];
    $payload = [
        'success' => true,
        'stage' => $stage,
        'cols' => (int)$meta['cols'],
        'rows' => (int)$meta['rows'],
        'count' => (int)$meta['count'],
        'bombs' => !empty($meta['bombs']),
        'label' => $meta['label'],
        'game_id' => null,
        'opened' => [],
        'is_ended' => false,
        'end_reason' => null,
        'matched_prize' => null,
        'reveals' => [],
        'status' => '',
        'rule' => 'Собери 3 одинаковых приза — лотерея остановится. На этапах 2–3 есть бомбы.',
    ];

    if ($gameId) {
        $game = getLotteryGame($gameId);
        if ($game && (int)$game['stage'] === $stage) {
            $payload['game_id'] = $game['game_id'];
            $payload['opened'] = $game['opened'];
            $payload['is_ended'] = $game['is_ended'];
            $payload['end_reason'] = $game['end_reason'];
            $payload['matched_prize'] = $game['matched_prize'];
            $payload['reveals'] = buildLotteryRevealsMap(
                getLotteryGameBoardCells($game),
                $game['opened']
            );
            if ($game['is_ended']) {
                $payload['status'] = lotteryEndStatusMessage($game['end_reason'], $game['matched_prize']);
            }
        }
    }

    return $payload;
}


function __(string $text, array $replacements = []): string
{
    if (!empty($replacements)) {
        foreach ($replacements as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
    }

    return $text;
}

