<?php

function ensureJackpotTables() {
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS jackpot_rounds (
                id INT AUTO_INCREMENT PRIMARY KEY,
                started_by VARCHAR(255) NOT NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ends_at DATETIME NOT NULL,
                total_seconds INT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                winner_username VARCHAR(255) DEFAULT NULL,
                winner_seconds INT DEFAULT NULL,
                winning_number TINYINT UNSIGNED DEFAULT NULL,
                winner_displayed TINYINT(1) NOT NULL DEFAULT 0,
                INDEX idx_status_ends (status, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS jackpot_entries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                round_id INT NOT NULL,
                username VARCHAR(255) NOT NULL,
                bet_number TINYINT UNSIGNED NOT NULL DEFAULT 0,
                bet_seconds INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_round_user_number (round_id, username, bet_number),
                INDEX idx_round (round_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        try {
            $db->query('SELECT winner_announced FROM jackpot_rounds LIMIT 1');
        } catch (PDOException $e) {
            $db->exec('ALTER TABLE jackpot_rounds ADD COLUMN winner_announced TINYINT(1) NOT NULL DEFAULT 0');
        }
        try {
            $db->query('SELECT winning_number FROM jackpot_rounds LIMIT 1');
        } catch (PDOException $e) {
            $db->exec('ALTER TABLE jackpot_rounds ADD COLUMN winning_number TINYINT UNSIGNED DEFAULT NULL');
        }
        try {
            $db->query('SELECT bet_number FROM jackpot_entries LIMIT 1');
        } catch (PDOException $e) {
            $db->exec('ALTER TABLE jackpot_entries ADD COLUMN bet_number TINYINT UNSIGNED NOT NULL DEFAULT 0');
            try {
                $db->exec('ALTER TABLE jackpot_entries DROP INDEX unique_round_user');
            } catch (PDOException $e) {
                // already migrated
            }
            try {
                $db->exec('ALTER TABLE jackpot_entries ADD UNIQUE KEY unique_round_user_number (round_id, username, bet_number)');
            } catch (PDOException $e) {
                // already exists
            }
        }
    } catch (PDOException $e) {
        error_log('Ошибка инициализации таблиц джекпота: ' . $e->getMessage());
    }
    ensureSiteSettingsTable();
}

function getJackpotHouseBankSeconds(): int
{
    ensureJackpotTables();
    $raw = getSiteSetting(SITE_SETTING_JACKPOT_HOUSE_BANK);
    if ($raw === null || trim($raw) === '') {
        setJackpotHouseBankSeconds(JACKPOT_HOUSE_BANK_SECONDS);
        return JACKPOT_HOUSE_BANK_SECONDS;
    }
    return max(0, (int)$raw);
}

function setJackpotHouseBankSeconds(int $seconds): bool
{
    return setSiteSetting(SITE_SETTING_JACKPOT_HOUSE_BANK, (string)max(0, $seconds));
}

function jackpotMaxPayoutSeconds(int $betSeconds): int
{
    return max(0, $betSeconds) * ROULETTE_STRAIGHT_MULTIPLIER;
}

function canJackpotAcceptBetSeconds(int $betSeconds): bool
{
    return getJackpotHouseBankSeconds() >= jackpotMaxPayoutSeconds($betSeconds);
}

function canStartJackpotRound(): bool
{
    if (!isJackpotPlayAllowed()) {
        return false;
    }
    return getJackpotHouseBankSeconds() > 0;
}

function isJackpotPlayAllowed(): bool
{
    if (!JACKPOT_REQUIRE_STREAM) {
        return true;
    }
    return isStreamLiveForWidgets();
}

function adjustJackpotHouseBank(PDO $db, int $deltaSeconds): int
{
    ensureSiteSettingsTable();
    $stmt = $db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? FOR UPDATE');
    $stmt->execute([SITE_SETTING_JACKPOT_HOUSE_BANK]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $current = ($row && trim((string)($row['setting_value'] ?? '')) !== '')
        ? max(0, (int)$row['setting_value'])
        : JACKPOT_HOUSE_BANK_SECONDS;
    $next = max(0, $current + $deltaSeconds);
    $upsert = $db->prepare('
        INSERT INTO site_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
    ');
    $upsert->execute([SITE_SETTING_JACKPOT_HOUSE_BANK, (string)$next]);
    return $next;
}

function logBotTimeEvent(string $username, string $actionType, int $deltaSeconds, int $balanceBefore, int $balanceAfter, ?array $meta = null): void
{
    ensureBotTimeLedgerTable();
    try {
        $db = getDB();
        $stmt = $db->prepare('
            INSERT INTO bot_time_ledger (username, action_type, delta_seconds, balance_before, balance_after, meta_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $username,
            $actionType,
            $deltaSeconds,
            $balanceBefore,
            $balanceAfter,
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (PDOException $e) {
        error_log('Ошибка лога времени (' . $actionType . ', ' . $username . '): ' . $e->getMessage());
    }
}

function getJackpotUserBalance(PDO $db, string $username): int
{
    $stmt = $db->prepare('SELECT total_seconds FROM user_time_tracking WHERE LOWER(username) = LOWER(?)');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return (int)($row['total_seconds'] ?? 0);
}

function getJackpotEntriesForRoundRaw(PDO $db, int $roundId): array
{
    $stmt = $db->prepare('SELECT username, bet_number, bet_seconds FROM jackpot_entries WHERE round_id = ? ORDER BY id ASC');
    $stmt->execute([$roundId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function refundJackpotRoundEntries(PDO $db, int $roundId, array $entries, string $reason): void
{
    foreach ($entries as $entry) {
        $refundSeconds = (int)($entry['bet_seconds'] ?? 0);
        $refundUser = (string)$entry['username'];
        if ($refundSeconds <= 0 || $refundUser === '') {
            continue;
        }
        $balanceBefore = getJackpotUserBalance($db, $refundUser);
        $balanceAfter = $balanceBefore + $refundSeconds;

        $stmt = $db->prepare('
            UPDATE user_time_tracking
            SET total_seconds = total_seconds + ?, last_activity = NOW()
            WHERE LOWER(username) = LOWER(?)
        ');
        $stmt->execute([$refundSeconds, $refundUser]);

        logBotTimeEvent($refundUser, 'jackpot_refund', $refundSeconds, $balanceBefore, $balanceAfter, [
            'round_id' => $roundId,
            'reason' => $reason,
            'bet_number' => (int)($entry['bet_number'] ?? 0),
        ]);
    }

    $stmt = $db->prepare("UPDATE jackpot_rounds SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$roundId]);
}

function startJackpotDrawing(PDO $db, int $roundId): void
{
    $stmt = $db->prepare("
        UPDATE jackpot_rounds
        SET status = 'drawing'
        WHERE id = ? AND status = 'active' AND ends_at <= NOW()
    ");
    $stmt->execute([$roundId]);
    if ($stmt->rowCount() === 0) {
        return;
    }

    $entries = getJackpotEntriesForRoundRaw($db, $roundId);
    if (count($entries) === 0) {
        $stmt = $db->prepare("UPDATE jackpot_rounds SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$roundId]);
    }
}

function finishJackpotRound(PDO $db, int $roundId): void
{
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            SELECT id, status, ends_at
            FROM jackpot_rounds
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([$roundId]);
        $round = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$round || ($round['status'] ?? '') !== 'drawing') {
            $db->rollBack();
            return;
        }

        $delay = JACKPOT_DRAW_DELAY_SECONDS;
        $readyStmt = $db->prepare('SELECT DATE_ADD(?, INTERVAL ? SECOND) <= NOW() AS ready');
        $readyStmt->execute([$round['ends_at'], $delay]);
        if (!(int)($readyStmt->fetchColumn() ?: 0)) {
            $db->rollBack();
            return;
        }

        $entries = getJackpotEntriesForRoundRaw($db, $roundId);
        if (count($entries) === 0) {
            $stmt = $db->prepare("UPDATE jackpot_rounds SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$roundId]);
            $db->commit();
            return;
        }

        $winningNumber = rouletteSpinNumber();
        $winners = array_values(array_filter($entries, static function ($entry) use ($winningNumber) {
            return (int)($entry['bet_number'] ?? -1) === $winningNumber;
        }));

        $loserSeconds = 0;
        foreach ($entries as $entry) {
            if ((int)($entry['bet_number'] ?? -1) === $winningNumber) {
                continue;
            }
            $loserSeconds += (int)($entry['bet_seconds'] ?? 0);
        }

        $totalPayout = 0;
        $firstWinner = null;
        foreach ($winners as $winner) {
            $betSeconds = (int)($winner['bet_seconds'] ?? 0);
            $payout = jackpotMaxPayoutSeconds($betSeconds);
            if ($payout <= 0) {
                continue;
            }
            $winnerName = (string)$winner['username'];
            if ($firstWinner === null) {
                $firstWinner = $winnerName;
            }
            $balanceBefore = getJackpotUserBalance($db, $winnerName);
            $balanceAfter = $balanceBefore + $payout;

            $stmt = $db->prepare('
                UPDATE user_time_tracking
                SET total_seconds = total_seconds + ?, last_activity = NOW()
                WHERE LOWER(username) = LOWER(?)
            ');
            $stmt->execute([$payout, $winnerName]);

            $totalPayout += $payout;
            logBotTimeEvent($winnerName, 'jackpot_win', $payout, $balanceBefore, $balanceAfter, [
                'round_id' => $roundId,
                'winning_number' => $winningNumber,
                'bet_number' => $winningNumber,
                'bet_seconds' => $betSeconds,
                'payout_seconds' => $payout,
                'multiplier' => ROULETTE_STRAIGHT_MULTIPLIER,
            ]);
        }

        $stmt = $db->prepare("
            UPDATE jackpot_rounds
            SET status = 'done',
                winner_username = ?,
                winner_seconds = ?,
                winning_number = ?,
                winner_announced = 0
            WHERE id = ? AND status = 'drawing'
        ");
        $stmt->execute([$firstWinner, $totalPayout, $winningNumber, $roundId]);
        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            return;
        }

        adjustJackpotHouseBank($db, $loserSeconds - $totalPayout);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Ошибка завершения рулетки #' . $roundId . ': ' . $e->getMessage());
    }
}

function processJackpotRounds(): void
{
    ensureJackpotTables();
    $db = getDB();

    $stmt = $db->query("
        SELECT id FROM jackpot_rounds
        WHERE status = 'active' AND ends_at <= NOW()
        ORDER BY id ASC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        startJackpotDrawing($db, (int)$row['id']);
    }

    $delay = (int)JACKPOT_DRAW_DELAY_SECONDS;
    $stmt = $db->query("
        SELECT id FROM jackpot_rounds
        WHERE status = 'drawing'
          AND DATE_ADD(ends_at, INTERVAL {$delay} SECOND) <= NOW()
        ORDER BY id ASC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        finishJackpotRound($db, (int)$row['id']);
    }
}

function getActiveJackpotRound() {
    ensureJackpotTables();
    $db = getDB();
    $stmt = $db->query("
        SELECT id, started_by, started_at, ends_at, total_seconds, status, winner_username, winner_seconds, winning_number
        FROM jackpot_rounds
        WHERE status = 'active' AND ends_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    return $stmt->fetch() ?: null;
}

function getJackpotRoundForWidget() {
    expireStaleJackpotWidgetRecords();
    processJackpotRounds();

    $stmt = getDB()->query("
        SELECT id, total_seconds, winner_username, winner_seconds, winning_number, status, ends_at, winner_displayed
        FROM jackpot_rounds
        WHERE status IN ('active', 'drawing')
           OR (status = 'done' AND winner_displayed = 0 AND winning_number IS NOT NULL)
        ORDER BY id DESC
        LIMIT 1
    ");
    $round = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$round) {
        return ['mode' => 'hidden'];
    }

    if (($round['status'] ?? '') === 'done') {
        return ['mode' => 'winner', 'round' => $round];
    }

    if (($round['status'] ?? '') === 'drawing') {
        return ['mode' => 'drawing', 'round' => $round];
    }

    $endsAt = strtotime((string)$round['ends_at']);
    if ($endsAt !== false && $endsAt <= time()) {
        return ['mode' => 'drawing', 'round' => $round];
    }

    $countStmt = getDB()->prepare('SELECT COUNT(DISTINCT username) AS cnt FROM jackpot_entries WHERE round_id = ?');
    $countStmt->execute([(int)$round['id']]);
    $count = (int)($countStmt->fetch()['cnt'] ?? 0);
    return ['mode' => 'active', 'round' => $round, 'participants' => $count];
}

function formatJackpotEntryRow(array $row, int $totalPot): array
{
    $betSeconds = (int)($row['bet_seconds'] ?? 0);
    $betNumber = (int)($row['bet_number'] ?? 0);
    $tier = rouletteChipTier($betSeconds);
    return [
        'username' => $row['username'],
        'bet_number' => $betNumber,
        'bet_seconds' => $betSeconds,
        'bet_text' => formatSecondsShort($betSeconds),
        'chip_tier' => $tier,
        'chip_icon' => rouletteChipIcon($tier),
        'number_color' => rouletteNumberColor($betNumber),
        'chance_percent' => null,
        'avatar_url' => $row['avatar_url'] ?? null,
    ];
}

function getJackpotEntriesForRound($roundId) {
    ensureJackpotTables();
    $db = getDB();
    $stmt = $db->prepare("
        SELECT username, bet_number, bet_seconds
        FROM jackpot_entries
        WHERE round_id = ?
        ORDER BY bet_number ASC, id ASC
    ");
    $stmt->execute([(int)$roundId]);

    $avatarStmt = null;
    try {
        $avatarStmt = $db->prepare("SELECT avatar_url FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
    } catch (PDOException $e) {
        $avatarStmt = null;
    }

    $rows = $stmt->fetchAll();
    $totalPot = 0;
    foreach ($rows as $row) {
        $totalPot += (int)($row['bet_seconds'] ?? 0);
    }

    $entries = [];
    foreach ($rows as $row) {
        if ($avatarStmt) {
            try {
                $avatarStmt->execute([$row['username']]);
                $avatar = $avatarStmt->fetchColumn();
                if ($avatar) {
                    $row['avatar_url'] = $avatar;
                }
            } catch (PDOException $e) {
                // ignore
            }
        }
        $entries[] = formatJackpotEntryRow($row, $totalPot);
    }
    return $entries;
}

function getJackpotWinnersForRound(int $roundId, int $winningNumber): array
{
    $entries = getJackpotEntriesForRound($roundId);
    $winners = [];
    foreach ($entries as $entry) {
        if ((int)($entry['bet_number'] ?? -1) !== $winningNumber) {
            continue;
        }
        $payout = (int)($entry['bet_seconds'] ?? 0) * ROULETTE_STRAIGHT_MULTIPLIER;
        $entry['payout_seconds'] = $payout;
        $entry['payout_text'] = formatSecondsShort($payout);
        $winners[] = $entry;
    }
    return $winners;
}

function startJackpotRound($startedBy, $durationSeconds = null) {
    ensureJackpotTables();
    if (!canStartJackpotRound()) {
        return false;
    }
    $duration = $durationSeconds ?? JACKPOT_DURATION_SECONDS;
    $db = getDB();
    $db->exec("
        UPDATE jackpot_rounds
        SET status = 'cancelled'
        WHERE status IN ('active', 'drawing')
    ");
    $stmt = $db->prepare("
        INSERT INTO jackpot_rounds (started_by, started_at, ends_at, status)
        VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), 'active')
    ");
    $stmt->execute([$startedBy, (int)$duration]);
    return (int)$db->lastInsertId();
}

function ackJackpotWinner($roundId) {
    ensureJackpotTables();
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE jackpot_rounds
        SET winner_displayed = 1
        WHERE id = ? AND status = 'done'
    ");
    $stmt->execute([(int)$roundId]);
    return $stmt->rowCount() > 0;
}

function getRecentJackpotRounds($limit = 10) {
    ensureJackpotTables();
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.id, r.started_by, r.total_seconds, r.status, r.winner_username, r.winner_seconds,
               r.winning_number, r.started_at,
               (SELECT COUNT(*) FROM jackpot_entries e WHERE e.round_id = r.id) AS participants
        FROM jackpot_rounds r
        ORDER BY r.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
