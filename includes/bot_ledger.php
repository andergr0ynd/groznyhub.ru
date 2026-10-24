<?php

function ensureBotTimeLedgerTable(): void
{
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS bot_time_ledger (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                action_type VARCHAR(64) NOT NULL,
                delta_seconds INT NOT NULL,
                balance_before INT DEFAULT NULL,
                balance_after INT DEFAULT NULL,
                meta_json TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ledger_username (username),
                INDEX idx_ledger_created (created_at),
                INDEX idx_ledger_action (action_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        error_log('Ошибка создания bot_time_ledger: ' . $e->getMessage());
    }
}

function botTimeLedgerActionLabel(string $actionType): string
{
    return match ($actionType) {
        'refka_tick' => 'Рефка',
        'refka_stop' => 'Стоп рефки',
        'case_buy' => 'Кейс',
        'jackpot_bet' => 'Ставка',
        'jackpot_win' => 'Выигрыш',
        'jackpot_refund' => 'Возврат',
        'container_bet' => 'Контейнеры',
        'container_win' => 'Победа в контейнерах',
        'container_refund' => 'Возврат (контейнеры)',
        'time_gift_in' => 'Подарок времени',
        'time_gift_out' => 'Передал время',
        'daily_bonus' => 'Бонус',
        default => $actionType,
    };
}

function botTimeLedgerActionKind(string $actionType): string
{
    return match ($actionType) {
        'refka_tick', 'refka_stop', 'jackpot_win', 'jackpot_refund', 'container_win', 'container_refund', 'daily_bonus', 'time_gift_in' => 'plus',
        'case_buy', 'jackpot_bet', 'container_bet', 'time_gift_out' => 'minus',
        default => 'neutral',
    };
}

function botTimeLedgerParseMeta(?string $metaJson): array
{
    if ($metaJson === null || $metaJson === '') {
        return [];
    }
    $meta = json_decode($metaJson, true);
    return is_array($meta) ? $meta : [];
}

function botTimeLedgerFormatMeta(?string $metaJson, string $actionType = ''): string
{
    $event = botTimeLedgerDescribeEvent([
        'action_type' => $actionType,
        'meta_json' => $metaJson,
    ]);
    return $event['detail'] !== '' ? $event['detail'] : '—';
}

function botTimeLedgerDescribeEvent(array $row): array
{
    $action = (string)($row['action_type'] ?? '');
    $meta = botTimeLedgerParseMeta($row['meta_json'] ?? null);
    $detail = '';

    switch ($action) {
        case 'refka_tick':
            $detail = 'Сообщения в чате во время стрима';
            if (!empty($meta['multiplier']) && (float)$meta['multiplier'] > 1) {
                $detail .= ' · множитель ×' . rtrim(rtrim((string)$meta['multiplier'], '0'), '.');
            }
            break;

        case 'refka_stop':
            $reason = (string)($meta['reason'] ?? '');
            $detail = match ($reason) {
                'inactivity' => 'Нет активности 7 мин — таймер остановлен',
                'bot_restart' => !empty($meta['capped'])
                    ? 'Перезапуск бота — время простоя не начислено'
                    : 'Перезапуск бота — сохранён хвост сессии',
                'spam' => 'Антиспам — таймер остановлен',
                'session_stop' => 'Таймер остановлен вручную',
                default => $reason !== ''
                    ? 'Таймер остановлен (' . $reason . ')'
                    : 'Таймер остановлен',
            };
            break;

        case 'case_buy':
            $detail = 'Покупка кейса';
            if (!empty($meta['collection_name'])) {
                $detail = (string)$meta['collection_name'];
            }
            break;

        case 'jackpot_bet':
            $bet = (int)($meta['bet_seconds'] ?? 0);
            $total = (int)($meta['total_bet_seconds'] ?? 0);
            if ($total > 0 && $total !== $bet) {
                $detail = 'Добавлено ' . formatSecondsShort($bet) . ', всего ' . formatSecondsShort($total);
            } elseif ($bet > 0) {
                $detail = 'Ставка ' . formatSecondsShort($bet);
            } else {
                $detail = 'Ставка в джекпот';
            }
            if (!empty($meta['round_id'])) {
                $detail .= ' · раунд #' . (int)$meta['round_id'];
            }
            break;

        case 'jackpot_win':
            $pot = (int)($meta['pot_seconds'] ?? 0);
            $players = (int)($meta['players'] ?? 0);
            $detail = $pot > 0 ? 'Забрал банк ' . formatSecondsShort($pot) : 'Победа в джекпоте';
            if ($players > 0) {
                $detail .= ' · ' . $players . ' игр.';
            }
            break;

        case 'jackpot_refund':
            $detail = match ((string)($meta['reason'] ?? '')) {
                'single_player' => 'Один игрок — раунд отменён, ставка возвращена',
                default => 'Возврат ставки в джекпоте',
            };
            break;

        case 'container_bet':
            $bet = (int)($meta['bet_seconds'] ?? 0);
            $detail = $bet > 0 ? 'Ставка ' . formatSecondsShort($bet) : 'Дуэль контейнеров';
            break;

        case 'container_win':
            $win = (int)($meta['win_seconds'] ?? 0);
            $detail = $win > 0 ? 'Выигрыш ' . formatSecondsShort($win) : 'Победа в дуэли контейнеров';
            if (!empty($meta['player_total']) && isset($meta['bot_total'])) {
                $detail .= ' · ' . formatContainerRubles((int)$meta['player_total'])
                    . ' vs ' . formatContainerRubles((int)$meta['bot_total']);
            }
            break;

        case 'container_refund':
            $detail = ((string)($meta['reason'] ?? '')) === 'draw'
                ? 'Ничья — ставка возвращена'
                : 'Возврат ставки (контейнеры)';
            break;

        case 'daily_bonus':
            $streak = (int)($meta['streak'] ?? 0);
            $detail = $streak > 0
                ? 'Ежедневный бонус · серия ' . $streak . ' дн.'
                : 'Ежедневный бонус';
            break;

        case 'time_gift_in':
            $seconds = (int)($meta['seconds'] ?? 0);
            $from = (string)($meta['from_username'] ?? '');
            $detail = $seconds > 0 ? 'Получено ' . formatSecondsShort($seconds) : 'Подарок времени';
            if ($from !== '') {
                $detail .= ' от @' . $from;
            }
            break;

        case 'time_gift_out':
            $seconds = (int)($meta['seconds'] ?? 0);
            $to = (string)($meta['to_username'] ?? '');
            $detail = $seconds > 0 ? 'Передано ' . formatSecondsShort($seconds) : 'Передал время';
            if ($to !== '') {
                $detail .= ' → @' . $to;
            }
            break;

        default:
            if ($meta !== []) {
                $parts = [];
                foreach ($meta as $key => $value) {
                    if (is_bool($value)) {
                        $value = $value ? 'да' : 'нет';
                    } elseif (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    $parts[] = $key . ': ' . $value;
                }
                $detail = implode(' · ', $parts);
            }
            break;
    }

    return [
        'label' => botTimeLedgerActionLabel($action),
        'detail' => $detail,
        'kind' => botTimeLedgerActionKind($action),
    ];
}

function botTimeLedgerFormatDateTime(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    return date('d.m H:i:s', $ts);
}

function getBotTimeLedgerEntries(array $filters = [], int $limit = 100, int $offset = 0): array
{
    ensureBotTimeLedgerTable();
    $db = getDB();

    $where = [];
    $params = [];

    if (!empty($filters['username'])) {
        $where[] = 'LOWER(username) = LOWER(?)';
        $params[] = trim((string)$filters['username']);
    }
    if (!empty($filters['action'])) {
        $where[] = 'action_type = ?';
        $params[] = trim((string)$filters['action']);
    }
    if (empty($filters['show_ticks'])) {
        $where[] = "action_type != 'refka_tick'";
    }
    if (!empty($filters['date_from'])) {
        $where[] = 'created_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $where[] = 'created_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }

    $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) FROM bot_time_ledger {$sqlWhere}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $limit = max(1, min(500, $limit));
    $offset = max(0, $offset);

    $stmt = $db->prepare("
        SELECT id, username, action_type, delta_seconds, balance_before, balance_after, meta_json, created_at
        FROM bot_time_ledger
        {$sqlWhere}
        ORDER BY id DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => $total,
        'rows' => $rows,
    ];
}

function getBotTimeLedgerSummary(array $filters = []): array
{
    ensureBotTimeLedgerTable();
    $db = getDB();

    $where = [];
    $params = [];
    if (!empty($filters['username'])) {
        $where[] = 'LOWER(username) = LOWER(?)';
        $params[] = trim((string)$filters['username']);
    }
    if (!empty($filters['action'])) {
        $where[] = 'action_type = ?';
        $params[] = trim((string)$filters['action']);
    }
    if (empty($filters['show_ticks'])) {
        $where[] = "action_type != 'refka_tick'";
    }
    if (!empty($filters['date_from'])) {
        $where[] = 'created_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $where[] = 'created_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS events_count,
            COALESCE(SUM(CASE WHEN delta_seconds > 0 THEN delta_seconds ELSE 0 END), 0) AS added_seconds,
            COALESCE(SUM(CASE WHEN delta_seconds < 0 THEN ABS(delta_seconds) ELSE 0 END), 0) AS removed_seconds
        FROM bot_time_ledger
        {$sqlWhere}
    ");
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'events_count' => 0,
        'added_seconds' => 0,
        'removed_seconds' => 0,
    ];

    $byActionStmt = $db->prepare("
        SELECT action_type, COUNT(*) AS cnt, COALESCE(SUM(delta_seconds), 0) AS total_delta
        FROM bot_time_ledger
        {$sqlWhere}
        GROUP BY action_type
        ORDER BY cnt DESC
    ");
    $byActionStmt->execute($params);
    $summary['by_action'] = $byActionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return $summary;
}
