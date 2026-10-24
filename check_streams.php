<?php
require_once __DIR__ . '/config.php';

// Определяем, запущен ли скрипт через HTTP или CLI
$isCli = php_sapi_name() === 'cli';

// Если запущен через HTTP, проверяем секретный ключ (опционально)
if (!$isCli) {
    // Устанавливаем заголовки для HTTP-ответа
    header('Content-Type: text/plain; charset=utf-8');
    
    // Опциональная защита через секретный ключ (можно задать через переменную окружения)
    $secretKey = getenv('STREAM_CHECK_SECRET') ?: '';
    if (!empty($secretKey) && (!isset($_GET['key']) || $_GET['key'] !== $secretKey)) {
        http_response_code(403);
        die("Access denied. Invalid or missing key.\n");
    }
}

ensureStreamStatusTable();

$status = getStreamStatus(TWITCH_CHANNEL_LOGIN);
if (!$status) {
    $status = [
        'is_live' => 0,
        'started_at' => null,
        'last_notified_at' => null,
    ];
}

$accessToken = getTwitchAppAccessToken();
if (!$accessToken) {
    error_log("check_streams.php: Failed to obtain Twitch access token");
    echo "Failed to obtain Twitch access token.\n";
    exit;
}

// Сначала получаем user_id по user_login
$userUrl = 'https://api.twitch.tv/helix/users?login=' . urlencode(TWITCH_CHANNEL_LOGIN);
$ch = curl_init($userUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Client-Id: ' . TWITCH_CLIENT_ID,
    'Authorization: Bearer ' . $accessToken,
]);

$userResponse = curl_exec($ch);
$userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($userHttpCode !== 200) {
    error_log("check_streams.php: Failed to get user info. HTTP $userHttpCode: $userResponse");
    echo "Twitch API error getting user info: " . $userResponse . "\n";
    exit;
}

$userData = json_decode($userResponse, true);
$userId = $userData['data'][0]['id'] ?? null;

if (!$userId) {
    error_log("check_streams.php: User ID not found for login: " . TWITCH_CHANNEL_LOGIN);
    echo "User ID not found for channel: " . TWITCH_CHANNEL_LOGIN . "\n";
    exit;
}

// Теперь получаем информацию о стриме по user_id
$url = 'https://api.twitch.tv/helix/streams?user_id=' . urlencode($userId);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Client-Id: ' . TWITCH_CLIENT_ID,
    'Authorization: Bearer ' . $accessToken,
]);

$response = curl_exec($ch);
if ($response === false) {
    $curlError = curl_error($ch);
    error_log("check_streams.php: Curl error: $curlError");
    echo "Curl error: " . $curlError . "\n";
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log("check_streams.php: Twitch API error. HTTP $httpCode: $response");
    echo "Twitch API error: " . $response . "\n";
    exit;
}

$data = json_decode($response, true);
$stream = $data['data'][0] ?? null;

if (!$stream) {
    if (!empty($status['is_live'])) {
        updateStreamStatus(TWITCH_CHANNEL_LOGIN, 0, null, $status['last_notified_at'], null, null);
    }
    echo "Stream offline.\n";
    exit;
}

$startedAt = (new DateTime($stream['started_at']))->format('Y-m-d H:i:s');
$wasLive = !empty($status['is_live']);
$previousStarted = $status['started_at'] ?? null;

updateStreamStatus(
    TWITCH_CHANNEL_LOGIN,
    1,
    $startedAt,
    $status['last_notified_at'] ?? null,
    $stream['title'] ?? null,
    $stream['game_name'] ?? null
);

if (!$wasLive) {
    echo "Stream is live (started).\n";
} elseif ($previousStarted && $previousStarted !== $startedAt) {
    echo "Stream restarted (new session, started_at changed).\n";
} else {
    echo "Stream is live.\n";
}

