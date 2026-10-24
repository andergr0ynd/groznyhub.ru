<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/kick_oauth.php';

function kickCallbackRedirect(string $error = '', string $description = ''): void
{
    if ($error === '') {
        redirectToIndexWithFlash('success', __('Kick успешно привязан к вашему аккаунту!'));
    }
    $messages = getIndexAuthErrorMessages();
    redirectToIndexWithFlash('error', $messages[$error] ?? $error, $description, $error);
}

if (isDemoMode()) {
    header('Location: ' . siteUrl('index.php'));
    exit;
}

if (!isLoggedIn()) {
    kickCallbackRedirect('kick_auth_required', __('Сначала войдите через Twitch.'));
}

if (isset($_GET['error'])) {
    kickCallbackRedirect((string)$_GET['error'], (string)($_GET['error_description'] ?? ''));
}

if (!isset($_GET['code'], $_SESSION['kick_oauth_state'], $_SESSION['kick_pkce_verifier'])) {
    kickCallbackRedirect('kick_no_code');
}

if (!hash_equals((string)$_SESSION['kick_oauth_state'], (string)($_GET['state'] ?? ''))) {
    kickCallbackRedirect('kick_invalid_state', __('Сессия истекла. Попробуйте привязку снова.'));
}

$tokenData = kickExchangeCode(trim((string)$_GET['code']), (string)$_SESSION['kick_pkce_verifier']);
unset($_SESSION['kick_oauth_state'], $_SESSION['kick_pkce_verifier']);

if (!$tokenData || empty($tokenData['access_token'])) {
    kickCallbackRedirect('kick_token_failed');
}

$kickUser = kickFetchAuthorizedUser((string)$tokenData['access_token']);
if (!$kickUser || empty($kickUser['user_id'])) {
    kickCallbackRedirect('kick_user_failed');
}

$kickId = (string)$kickUser['user_id'];
$kickUsername = kickFetchChannelSlug((string)$tokenData['access_token'], (int)$kickUser['user_id']);
if ($kickUsername === null || $kickUsername === '') {
    $kickUsername = kickSlugFromUserName($kickUser['name'] ?? null);
}
if ($kickUsername === null || $kickUsername === '') {
    kickCallbackRedirect('kick_slug_failed', __('Не удалось определить ник Kick. Разрешите доступ к каналу при авторизации.'));
}

try {
    ensureKickProfileColumns();
    $db = getDB();
    $userId = (int)$_SESSION['user_id'];

    $stmt = $db->prepare('SELECT id, username FROM users WHERE kick_id = ? AND id <> ?');
    $stmt->execute([$kickId, $userId]);
    $other = $stmt->fetch();
    if ($other) {
        kickCallbackRedirect('kick_already_linked', __('Этот Kick уже привязан к другому аккаунту.'));
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE LOWER(kick_username) = ? AND id <> ?');
    $stmt->execute([strtolower($kickUsername), $userId]);
    if ($stmt->fetch()) {
        kickCallbackRedirect('kick_username_taken', __('Этот Kick-ник уже привязан к другому аккаунту.'));
    }

    $stmt = $db->prepare('
        UPDATE users
        SET kick_id = ?, kick_username = ?, kick_linked_at = NOW()
        WHERE id = ?
    ');
    $stmt->execute([$kickId, $kickUsername, $userId]);

    kickCallbackRedirect();
} catch (Throwable $e) {
    error_log('Kick callback error: ' . $e->getMessage());
    kickCallbackRedirect('kick_link_failed');
}
