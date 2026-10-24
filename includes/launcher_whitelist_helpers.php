<?php

require_once __DIR__ . '/launcher_bans.php';

function launcherWhitelistMinecraftName($value) {
    $name = strtolower(trim((string)$value));
    if ($name === '' || !preg_match('/^[a-z0-9_]{3,16}$/', $name)) {
        return null;
    }
    return $name;
}

function launcherWhitelistNamesForUserRow(array $row) {
    $names = [];

    $twitchName = launcherWhitelistMinecraftName(normalizeTwitchUsername($row['username'] ?? ''));
    if ($twitchName !== null) {
        $names[$twitchName] = true;
    }

    $radmirNick = launcherWhitelistMinecraftName($row['radmir_nickname'] ?? '');
    if ($radmirNick !== null) {
        $names[$radmirNick] = true;
    }

    return array_keys($names);
}

function launcherWhitelistIsUserRowBanned(array $row, array $bannedTwitchIds) {
    $twitchId = trim((string)($row['twitch_id'] ?? ''));
    return $twitchId !== '' && isset($bannedTwitchIds[$twitchId]);
}

function launcherWhitelistCollectAllNames(PDO $db) {
    $bannedTwitchIds = launcherGetBannedTwitchIds($db);
    $stmt = $db->query('SELECT twitch_id, username, radmir_nickname FROM users');
    $names = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (launcherWhitelistIsUserRowBanned($row, $bannedTwitchIds)) {
            continue;
        }
        foreach (launcherWhitelistNamesForUserRow($row) as $name) {
            $names[$name] = true;
        }
    }

    $list = array_keys($names);
    sort($list, SORT_STRING);
    return $list;
}

function launcherWhitelistFindUserByTwitchId(PDO $db, $twitchId) {
    $twitchId = trim((string)$twitchId);
    if ($twitchId === '') {
        return null;
    }

    $stmt = $db->prepare('SELECT id, twitch_id, username, radmir_nickname FROM users WHERE twitch_id = ? LIMIT 1');
    $stmt->execute([$twitchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function launcherWhitelistCheckAccess(PDO $db, $twitchId, $minecraftUsername) {
    $minecraftUsername = launcherWhitelistMinecraftName($minecraftUsername);
    if ($minecraftUsername === null) {
        return [
            'allowed' => false,
            'reason' => 'invalid_username',
            'message' => 'Ник Minecraft не подходит (нужно 3–16 символов: a-z, 0-9, _)',
        ];
    }

    $ban = launcherGetActiveBan($db, $twitchId);
    if ($ban) {
        return [
            'allowed' => false,
            'reason' => 'banned',
            'message' => launcherBanMessage($ban),
        ];
    }

    $user = launcherWhitelistFindUserByTwitchId($db, $twitchId);
    if (!$user) {
        return [
            'allowed' => false,
            'reason' => 'not_registered',
            'message' => 'Аккаунт не найден. Войди через Twitch в лаунчере.',
        ];
    }

    $ownedNames = launcherWhitelistNamesForUserRow($user);
    if (!in_array($minecraftUsername, $ownedNames, true)) {
        return [
            'allowed' => false,
            'reason' => 'nick_mismatch',
            'message' => 'Ник Minecraft не совпадает с профилем Twitch на GroznyHub.',
        ];
    }

    $allNames = array_fill_keys(launcherWhitelistCollectAllNames($db), true);
    if (!isset($allNames[$minecraftUsername])) {
        return [
            'allowed' => false,
            'reason' => 'not_whitelisted',
            'message' => 'Твой ник не в белом списке сервера andergr0ynd.',
        ];
    }

    return [
        'allowed' => true,
        'reason' => 'ok',
        'message' => 'Доступ к серверу разрешён',
        'username' => $minecraftUsername,
    ];
}
