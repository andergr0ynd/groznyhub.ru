<?php

function ensureRaffleTables() {
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS raffle_rounds (
                id INT AUTO_INCREMENT PRIMARY KEY,
                keyword VARCHAR(255) NOT NULL,
                started_by VARCHAR(255) NOT NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ends_at DATETIME DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                winner_username VARCHAR(255) DEFAULT NULL,
                winner_picked_at DATETIME DEFAULT NULL,
                response_deadline DATETIME DEFAULT NULL,
                response_seconds INT NOT NULL DEFAULT 60,
                responded_at DATETIME DEFAULT NULL,
                winner_announced TINYINT(1) NOT NULL DEFAULT 0,
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS raffle_entries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                round_id INT NOT NULL,
                username VARCHAR(255) NOT NULL,
                matched_message TEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_round_user (round_id, username),
                INDEX idx_round (round_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS raffle_excluded (
                id INT AUTO_INCREMENT PRIMARY KEY,
                round_id INT NOT NULL,
                username VARCHAR(255) NOT NULL,
                reason VARCHAR(32) DEFAULT 'no_response',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_round_excluded (round_id, username),
                INDEX idx_round (round_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        error_log('Ошибка инициализации таблиц розыгрыша: ' . $e->getMessage());
    }
}

function getActiveRaffleRound(PDO $db = null): ?array
{
    ensureRaffleTables();
    $db = $db ?: getDB();
    $stmt = $db->query("
        SELECT *
        FROM raffle_rounds
        WHERE status IN ('active', 'awaiting_response', 'responded')
        ORDER BY id DESC
        LIMIT 1
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function getRaffleEntryCount(int $roundId): int
{
    ensureRaffleTables();
    $db = getDB();
    $stmt = $db->prepare('SELECT COUNT(*) FROM raffle_entries WHERE round_id = ?');
    $stmt->execute([$roundId]);
    return (int)$stmt->fetchColumn();
}

function getRaffleEntries(int $roundId, int $limit = 200): array
{
    ensureRaffleTables();
    $db = getDB();
    $stmt = $db->prepare('
        SELECT username, matched_message, created_at
        FROM raffle_entries
        WHERE round_id = ?
        ORDER BY id ASC
        LIMIT ?
    ');
    $stmt->bindValue(1, $roundId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getWinnerChatMessages(string $username, ?string $since, int $limit = 40): array
{
    $db = getDB();
    if ($since) {
        $stmt = $db->prepare('
            SELECT message, created_at
            FROM chat_messages
            WHERE LOWER(username) = LOWER(?)
              AND created_at >= ?
            ORDER BY id DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $username, PDO::PARAM_STR);
        $stmt->bindValue(2, $since, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    } else {
        $stmt = $db->prepare('
            SELECT message, created_at
            FROM chat_messages
            WHERE LOWER(username) = LOWER(?)
            ORDER BY id DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $username, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    return array_map(static function ($row) {
        return [
            'message' => (string)$row['message'],
            'created_at' => (string)$row['created_at'],
        ];
    }, $rows);
}

function startRaffleRound(string $startedBy, string $keyword, ?int $durationSeconds = null): array
{
    ensureRaffleTables();
    $keyword = trim($keyword);
    if ($keyword === '') {
        return ['success' => false, 'error' => 'Укажи кодовое слово'];
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        $db->exec("
            UPDATE raffle_rounds
            SET status = 'cancelled'
            WHERE status IN ('active', 'awaiting_response', 'responded')
        ");

        $responseSeconds = RAFFLE_RESPONSE_SECONDS;
        if ($durationSeconds !== null && $durationSeconds > 0) {
            $stmt = $db->prepare('
                INSERT INTO raffle_rounds (keyword, started_by, started_at, ends_at, status, response_seconds)
                VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), \'active\', ?)
            ');
            $stmt->execute([$keyword, $startedBy, $durationSeconds, $responseSeconds]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO raffle_rounds (keyword, started_by, started_at, status, response_seconds)
                VALUES (?, ?, NOW(), \'active\', ?)
            ');
            $stmt->execute([$keyword, $startedBy, $responseSeconds]);
        }
        $roundId = (int)$db->lastInsertId();
        $db->commit();
        return [
            'success' => true,
            'round_id' => $roundId,
            'keyword' => $keyword,
            'response_seconds' => $responseSeconds,
        ];
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('startRaffleRound: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Не удалось запустить розыгрыш'];
    }
}

function stopRaffleRound(): array
{
    ensureRaffleTables();
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE raffle_rounds
        SET status = 'cancelled'
        WHERE status IN ('active', 'awaiting_response', 'responded')
    ");
    $stmt->execute();
    return ['success' => $stmt->rowCount() > 0];
}

function pickRaffleWinner(bool $excludeCurrent = false, ?int $roundId = null): array
{
    ensureRaffleTables();
    $db = getDB();
    $db->beginTransaction();
    try {
        if ($roundId) {
            $stmt = $db->prepare('SELECT * FROM raffle_rounds WHERE id = ? FOR UPDATE');
            $stmt->execute([$roundId]);
        } else {
            $stmt = $db->query("
                SELECT * FROM raffle_rounds
                WHERE status IN ('active', 'awaiting_response')
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ");
        }
        $round = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$round) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Нет активного розыгрыша'];
        }

        $roundId = (int)$round['id'];
        if ($excludeCurrent && !empty($round['winner_username'])) {
            $stmt = $db->prepare('
                INSERT IGNORE INTO raffle_excluded (round_id, username, reason)
                VALUES (?, ?, \'no_response\')
            ');
            $stmt->execute([$roundId, strtolower((string)$round['winner_username'])]);
        }

        $entriesStmt = $db->prepare('SELECT username FROM raffle_entries WHERE round_id = ?');
        $entriesStmt->execute([$roundId]);
        $entries = array_map(static fn($r) => strtolower((string)$r['username']), $entriesStmt->fetchAll(PDO::FETCH_ASSOC));

        $excludedStmt = $db->prepare('SELECT username FROM raffle_excluded WHERE round_id = ?');
        $excludedStmt->execute([$roundId]);
        $excluded = array_map(static fn($r) => strtolower((string)$r['username']), $excludedStmt->fetchAll(PDO::FETCH_ASSOC));
        $excludedMap = array_fill_keys($excluded, true);

        $candidates = array_values(array_filter($entries, static fn($u) => !isset($excludedMap[$u])));
        if (count($candidates) === 0) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Нет участников для выбора'];
        }

        $winner = $candidates[random_int(0, count($candidates) - 1)];

        $stmt = $db->prepare('
            UPDATE raffle_rounds
            SET status = \'awaiting_response\',
                winner_username = ?,
                winner_picked_at = NOW(),
                response_deadline = NULL,
                responded_at = NULL,
                winner_announced = 0
            WHERE id = ?
        ');
        $stmt->execute([$winner, $roundId]);
        $db->commit();

        return [
            'success' => true,
            'round_id' => $roundId,
            'winner_username' => $winner,
            'participants' => count($entries),
            'candidates_left' => count($candidates),
        ];
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('pickRaffleWinner: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Не удалось выбрать победителя'];
    }
}

function confirmRaffleWinner(?int $roundId = null): array
{
    ensureRaffleTables();
    $db = getDB();
    if (!$roundId) {
        $round = getActiveRaffleRound($db);
        if (!$round) {
            return ['success' => false, 'error' => 'Нет активного розыгрыша'];
        }
        $roundId = (int)$round['id'];
    }
    $stmt = $db->prepare("
        UPDATE raffle_rounds
        SET status = 'done'
        WHERE id = ? AND status IN ('awaiting_response', 'responded')
    ");
    $stmt->execute([$roundId]);
    return [
        'success' => $stmt->rowCount() > 0,
        'error' => $stmt->rowCount() > 0 ? null : 'Не удалось завершить розыгрыш',
    ];
}

function raffleResponseElapsedSeconds(?string $pickedAt, ?string $respondedAt = null): int
{
    if (!$pickedAt) {
        return 0;
    }
    $pickedTs = strtotime($pickedAt);
    if ($pickedTs === false) {
        return 0;
    }
    $endTs = $respondedAt ? strtotime($respondedAt) : time();
    if ($endTs === false) {
        $endTs = time();
    }
    return max(0, $endTs - $pickedTs);
}

function buildRaffleStatePayload(?array $round): array
{
    if (!$round) {
        return ['mode' => 'hidden'];
    }

    $roundId = (int)$round['id'];
    $status = (string)$round['status'];
    $participants = getRaffleEntryCount($roundId);
    $entries = getRaffleEntries($roundId);
    $responded = $status === 'responded' || !empty($round['responded_at']);

    $base = [
        'mode' => $status === 'active' ? 'collecting' : $status,
        'round_id' => $roundId,
        'keyword' => (string)$round['keyword'],
        'status' => $status,
        'participants' => $participants,
        'entries' => $entries,
        'started_by' => (string)$round['started_by'],
        'responded' => $responded,
    ];

    if (!empty($round['ends_at'])) {
        $endsAt = strtotime((string)$round['ends_at']);
        $base['ends_at'] = $endsAt;
        $base['entry_seconds_left'] = max(0, $endsAt - time());
    }

    $winner = (string)($round['winner_username'] ?? '');
    if ($winner !== '') {
        $base['winner_username'] = $winner;
        $pickedAt = (string)($round['winner_picked_at'] ?? '');
        if ($pickedAt !== '') {
            $pickedTs = strtotime($pickedAt);
            if ($pickedTs !== false) {
                $base['winner_picked_at'] = $pickedTs;
            }
            if ($responded && !empty($round['responded_at'])) {
                $elapsed = raffleResponseElapsedSeconds($pickedAt, (string)$round['responded_at']);
                $base['response_elapsed_seconds'] = $elapsed;
                $base['response_elapsed_text'] = formatSecondsShort($elapsed);
            } elseif ($status === 'awaiting_response') {
                $base['response_elapsed_seconds'] = raffleResponseElapsedSeconds($pickedAt);
            }
        }
        $base['responded_at'] = $round['responded_at'] ?? null;
        $base['winner_messages'] = getWinnerChatMessages($winner, $pickedAt !== '' ? $pickedAt : null);
    } else {
        $base['winner_messages'] = [];
    }

    return $base;
}

function getRaffleWinnerHistory(int $limit = 25): array
{
    ensureRaffleTables();
    $limit = max(1, min(100, $limit));
    $db = getDB();
    $stmt = $db->prepare("
        SELECT
            r.id,
            r.keyword,
            r.winner_username,
            r.started_by,
            r.started_at,
            r.winner_picked_at,
            r.responded_at,
            (SELECT COUNT(*) FROM raffle_entries e WHERE e.round_id = r.id) AS participants
        FROM raffle_rounds r
        WHERE r.status = 'done'
          AND r.winner_username IS NOT NULL
          AND r.winner_username != ''
        ORDER BY r.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static function (array $row) {
        $pickedAt = (string)($row['winner_picked_at'] ?? '');
        $respondedAt = (string)($row['responded_at'] ?? '');
        $responded = $respondedAt !== '';
        $elapsed = $responded
            ? raffleResponseElapsedSeconds($pickedAt, $respondedAt)
            : ($pickedAt !== '' ? raffleResponseElapsedSeconds($pickedAt) : 0);

        $finishedRaw = $respondedAt !== '' ? $respondedAt : ($pickedAt !== '' ? $pickedAt : (string)($row['started_at'] ?? ''));
        $finishedTs = $finishedRaw !== '' ? strtotime($finishedRaw) : false;
        $finishedLabel = $finishedTs ? date('d.m.Y H:i', $finishedTs) : '—';

        return [
            'round_id' => (int)$row['id'],
            'winner_username' => (string)$row['winner_username'],
            'keyword' => (string)$row['keyword'],
            'participants' => (int)($row['participants'] ?? 0),
            'started_by' => (string)($row['started_by'] ?? ''),
            'responded' => $responded,
            'response_elapsed_seconds' => $elapsed,
            'response_elapsed_text' => $elapsed > 0 ? formatSecondsShort($elapsed) : null,
            'finished_at' => $finishedRaw,
            'finished_label' => $finishedLabel,
        ];
    }, $rows);
}

function getRaffleStateForWidget(): array
{
    expireStaleRaffleWidgetRecords();
    $round = getActiveRaffleRound();
    $state = $round ? buildRaffleStatePayload($round) : ['mode' => 'hidden'];
    $state['winner_log'] = getRaffleWinnerHistory(25);
    return $state;
}

ensureRaffleTables();
