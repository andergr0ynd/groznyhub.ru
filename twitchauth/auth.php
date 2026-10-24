<?php
require_once __DIR__ . '/../config.php';

if (isDemoMode()) {
    header('Location: ' . siteUrl('index.php'));
    exit;
}

$hasLauncherAuth = !empty($_SESSION['launcher_auth']) && is_array($_SESSION['launcher_auth']);

if (!$hasLauncherAuth && session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

if ($hasLauncherAuth) {
    $state = trim((string)($_SESSION['launcher_auth']['state'] ?? ''));
    if ($state === '' || !preg_match('/^[a-f0-9]{16,64}$/i', $state)) {
        $state = bin2hex(random_bytes(16));
    }
} else {
    $state = bin2hex(random_bytes(16));
}

$_SESSION['oauth_state'] = $state;

$authUrl = 'https://id.twitch.tv/oauth2/authorize?' . http_build_query([
    'client_id' => TWITCH_CLIENT_ID,
    'redirect_uri' => TWITCH_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'user:read:email user:read:subscriptions',
    'state' => $state,
]);

header('Location: ' . $authUrl);
exit;
