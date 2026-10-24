<?php

const KICK_TOKEN_URL = 'https://id.kick.com/oauth/token';
const KICK_AUTHORIZE_URL = 'https://id.kick.com/oauth/authorize';
const KICK_API_URL = 'https://api.kick.com/public/v1';

function kickOAuthRedirectUri(): string
{
    return SITE_URL . '/kickauth/callback.php';
}

function kickPkcePair(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    return [$verifier, $challenge];
}

function kickHttpPost(string $url, array $fields, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => (string)$body, 'error' => $err];
}

function kickHttpGet(string $url, string $accessToken): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => (string)$body, 'error' => $err];
}

function kickExchangeCode(string $code, string $codeVerifier): ?array
{
    $resp = kickHttpPost(KICK_TOKEN_URL, [
        'grant_type' => 'authorization_code',
        'client_id' => KICK_CLIENT_ID,
        'client_secret' => KICK_CLIENT_SECRET,
        'code' => $code,
        'redirect_uri' => kickOAuthRedirectUri(),
        'code_verifier' => $codeVerifier,
    ]);
    if ($resp['code'] !== 200) {
        error_log('Kick token exchange failed: ' . $resp['body']);
        return null;
    }
    $data = json_decode($resp['body'], true);
    return is_array($data) ? $data : null;
}

function kickFetchAuthorizedUser(string $accessToken): ?array
{
    $resp = kickHttpGet(KICK_API_URL . '/users', $accessToken);
    if ($resp['code'] !== 200) {
        error_log('Kick /users failed: ' . $resp['body']);
        return null;
    }
    $data = json_decode($resp['body'], true);
    $entries = $data['data'] ?? [];
    return is_array($entries) && isset($entries[0]) ? $entries[0] : null;
}

function kickExtractChannelSlug(?array $data): ?string
{
    $entries = $data['data'] ?? [];
    if (!is_array($entries) || !isset($entries[0]['slug'])) {
        return null;
    }
    $slug = strtolower(trim((string)$entries[0]['slug']));
    return $slug !== '' ? $slug : null;
}

function kickFetchChannelSlug(string $accessToken, int $userId): ?string
{
    $attempts = [
        KICK_API_URL . '/channels',
        KICK_API_URL . '/channels?' . http_build_query(['broadcaster_user_id' => $userId]),
    ];
    foreach ($attempts as $url) {
        $resp = kickHttpGet($url, $accessToken);
        if ($resp['code'] !== 200) {
            error_log('Kick /channels failed (' . $resp['code'] . '): ' . $resp['body']);
            continue;
        }
        $slug = kickExtractChannelSlug(json_decode($resp['body'], true));
        if ($slug !== null) {
            return $slug;
        }
    }
    return null;
}

function kickSlugFromUserName(?string $name): ?string
{
    $name = strtolower(trim((string)$name));
    if ($name === '') {
        return null;
    }
    $slug = preg_replace('/[^a-z0-9_-]+/', '-', $name);
    $slug = trim((string)$slug, '-');
    return $slug !== '' ? $slug : null;
}
