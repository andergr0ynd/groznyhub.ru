<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/kick_oauth.php';

if (isDemoMode()) {
    header('Location: ' . siteUrl('index.php'));
    exit;
}

if (!isLoggedIn()) {
    header('Location: ' . siteUrl('index.php'));
    exit;
}

if (empty(KICK_CLIENT_ID) || empty(KICK_CLIENT_SECRET)) {
    redirectToIndexWithFlash('error', getIndexAuthErrorMessages()['kick_not_configured'], '', 'kick_not_configured');
}

$state = bin2hex(random_bytes(16));
[$verifier, $challenge] = kickPkcePair();

$_SESSION['kick_oauth_state'] = $state;
$_SESSION['kick_pkce_verifier'] = $verifier;

$authUrl = KICK_AUTHORIZE_URL . '?' . http_build_query([
    'client_id' => KICK_CLIENT_ID,
    'redirect_uri' => kickOAuthRedirectUri(),
    'response_type' => 'code',
    'scope' => 'user:read channel:read',
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
]);

header('Location: ' . $authUrl);
exit;
