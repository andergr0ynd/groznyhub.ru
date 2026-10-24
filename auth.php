<?php
require_once __DIR__ . '/config.php';

if (isDemoMode()) {
    header('Location: ' . siteUrl('index.php'));
    exit;
}

$port = (int)($_GET['port'] ?? 0);
$state = trim((string)($_GET['state'] ?? ''));

if ($port >= 1024 && $port <= 65535 && preg_match('/^[a-f0-9]{16,64}$/i', $state)) {
    launcherAuthBegin($port, $state);

    $user = getCurrentUser();
    if ($user) {
        $payload = launcherAuthComplete((int)$user['id'], $state);
        if ($payload) {
            launcherAuthRedirectToClient($payload);
        }
    }
}

header('Location: ' . siteUrl('twitchauth/auth.php'));
exit;
