<?php
require_once __DIR__ . '/config.php';

function oauthCallbackRedirectUri(): string
{
    return TWITCH_REDIRECT_URI;
}

function oauthRedirectToIndex(string $error = '', string $description = ''): void
{
    if ($error !== '') {
        $messages = getIndexAuthErrorMessages();
        redirectToIndexWithFlash('error', $messages[$error] ?? $error, $description, $error);
    }
    header('Location: ' . siteUrl('index.php'));
    exit;
}

try {
    if (isDemoMode()) {
        oauthRedirectToIndex();
    }

    if (isset($_GET['error'])) {
        $error = (string)$_GET['error'];
        $errorDescription = isset($_GET['error_description']) ? urldecode((string)$_GET['error_description']) : '';

        if ($error === 'redirect_mismatch') {
            oauthRedirectToIndex('redirect_mismatch', $errorDescription);
        }

        oauthRedirectToIndex($error, $errorDescription);
    }

    if (!isset($_GET['code']) || trim((string)$_GET['code']) === '') {
        oauthRedirectToIndex('no_code');
    }

    if (isset($_SESSION['oauth_state'])) {
        $returnedState = trim((string)($_GET['state'] ?? ''));
        $stateOk = $returnedState !== ''
            && hash_equals((string)$_SESSION['oauth_state'], $returnedState);
        if (!$stateOk && !launcherAuthHasPending($returnedState)) {
            oauthRedirectToIndex('invalid_state', 'Сессия истекла. Нажмите «Войти через Twitch» ещё раз.');
        }
    }

    $code = trim((string)$_GET['code']);
    $redirectUri = oauthCallbackRedirectUri();

    $ch = curl_init('https://id.twitch.tv/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => TWITCH_CLIENT_ID,
            'client_secret' => TWITCH_CLIENT_SECRET,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        $errorData = json_decode((string)$response, true);
        $errorMessage = 'Ошибка получения токена';

        if ($curlError) {
            $errorMessage .= ': ' . $curlError;
        } elseif (isset($errorData['message'])) {
            $errorMessage .= ': ' . $errorData['message'];
        } elseif (isset($errorData['error'])) {
            $errorMessage .= ': ' . $errorData['error'];
            if (isset($errorData['error_description'])) {
                $errorMessage .= ' - ' . $errorData['error_description'];
            }
        } else {
            $errorMessage .= ' (HTTP ' . $httpCode . ')';
        }

        oauthRedirectToIndex('token_failed', $errorMessage);
    }

    $tokenInfo = json_decode((string)$response, true);
    if (!is_array($tokenInfo) || empty($tokenInfo['access_token'])) {
        $errorMessage = 'Токен доступа не получен в ответе от Twitch';
        if (!empty($tokenInfo['message'])) {
            $errorMessage .= ': ' . $tokenInfo['message'];
        }
        oauthRedirectToIndex('token_failed', $errorMessage);
    }

    $accessToken = $tokenInfo['access_token'];

    $ch = curl_init('https://api.twitch.tv/helix/users');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Client-Id: ' . TWITCH_CLIENT_ID,
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $userResponse = curl_exec($ch);
    $userHttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($userHttpCode !== 200) {
        oauthRedirectToIndex('user_info_failed');
    }

    $userData = json_decode((string)$userResponse, true);
    $user = $userData['data'][0] ?? null;

    if (!$user) {
        oauthRedirectToIndex('no_user_data');
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE twitch_id = ?');
    $stmt->execute([$user['id']]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        $stmt = $db->prepare('
            UPDATE users
            SET username = ?, display_name = ?, email = ?, avatar_url = ?, access_token = ?, refresh_token = ?, updated_at = NOW()
            WHERE twitch_id = ?
        ');
        $stmt->execute([
            $user['login'],
            $user['display_name'],
            $user['email'] ?? null,
            $user['profile_image_url'] ?? null,
            $accessToken,
            $tokenInfo['refresh_token'] ?? null,
            $user['id'],
        ]);
        $userId = (int)$existingUser['id'];
    } else {
        $stmt = $db->prepare('
            INSERT INTO users (twitch_id, username, display_name, email, avatar_url, access_token, refresh_token, admin)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ');
        $stmt->execute([
            $user['id'],
            $user['login'],
            $user['display_name'],
            $user['email'] ?? null,
            $user['profile_image_url'] ?? null,
            $accessToken,
            $tokenInfo['refresh_token'] ?? null,
        ]);
        $userId = (int)$db->lastInsertId();
    }

    $_SESSION['user_id'] = $userId;
    unset($_SESSION['oauth_state']);

    $oauthState = trim((string)($_GET['state'] ?? ''));
    $launcherPayload = launcherAuthComplete($userId, $oauthState);
    if ($launcherPayload) {
        launcherAuthRedirectToClient($launcherPayload);
    }

    oauthRedirectToIndex();
} catch (Throwable $e) {
    error_log('OAuth callback error: ' . $e->getMessage());
    oauthRedirectToIndex('token_failed', 'Ошибка авторизации. Попробуйте войти снова.');
}
