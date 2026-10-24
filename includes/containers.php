<?php

function getContainerCarPool(): array
{
    return [
        ['id' => 'invalidka', 'name' => 'LADA Инвалидка', 'icon' => 'invalidka.png', 'price' => 20000],
        ['id' => 'motoblock', 'name' => 'Мотоблок', 'icon' => 'motoblock.png', 'price' => 30000],
        ['id' => 'izh_jupiter5', 'name' => 'ИЖ Юпитер-5', 'icon' => 'ig5.png', 'price' => 40000],
        ['id' => 'gaz3102', 'name' => 'GAZ 3102 Волга', 'icon' => 'gaz3102.png', 'price' => 120000],
        ['id' => 'gazel31105', 'name' => 'Gazel 31105 «Волга»', 'icon' => 'gazel31105.png', 'price' => 165000],
        ['id' => 'lada2112', 'name' => 'LADA 2112', 'icon' => '2112.png', 'price' => 170000],
        ['id' => 'lada2114', 'name' => 'Lada 2114', 'icon' => '2114.png', 'price' => 215000],
        ['id' => 'matiz', 'name' => 'Daewoo Matiz', 'icon' => 'matiz.png', 'price' => 200000],
        ['id' => 'byhanka', 'name' => 'UAZ 452 Буханка', 'icon' => 'byhanka.png', 'price' => 300000],
        ['id' => 'jeep_gc_xj', 'name' => 'JEEP GC XJ', 'icon' => 'Jeep.png', 'price' => 320000],
        ['id' => 'gaz24volga', 'name' => 'GAZ 24 Волга', 'icon' => 'gaz24volga.png', 'price' => 350000],
        ['id' => 'bmw323e30', 'name' => 'BMW 323i E30', 'icon' => '323ie30.png', 'price' => 350000],
        ['id' => 'saab93', 'name' => 'Saab 9.3 Turbo', 'icon' => 'saab.png', 'price' => 600000],
        ['id' => 'uaz3153', 'name' => 'UAZ 3153', 'icon' => '3153uaz.png', 'price' => 700000],
        ['id' => 'patriot', 'name' => 'UAZ Патриот', 'icon' => 'patriot.png', 'price' => 700000],
        ['id' => 'kiario', 'name' => 'KIA Rio', 'icon' => 'kiario.png', 'price' => 700000],
        ['id' => 'camry_v40', 'name' => 'Toyota Camry V40', 'icon' => 'V40.png', 'price' => 850000],
        ['id' => 'bmw540e34', 'name' => 'BMW 540i E34', 'icon' => '540ie34.png', 'price' => 900000],
        ['id' => 'mbw210', 'name' => 'Mercedes E420 W210', 'icon' => 'mbw210.png', 'price' => 1300000],
        ['id' => 'mark100', 'name' => 'Toyota Mark II 100', 'icon' => 'Mark100.png', 'price' => 1200000],
        ['id' => 'wrx_sti', 'name' => 'Subaru Impreza WRX STI', 'icon' => 'wrxsti.png', 'price' => 1200000],
        ['id' => 'hondas2000', 'name' => 'Honda S2000', 'icon' => 'hondas2000.png', 'price' => 1600000],
        ['id' => 'rx7', 'name' => 'Mazda RX-7 Spirit R', 'icon' => 'RX7.png', 'price' => 1600000],
        ['id' => 'minijcw', 'name' => 'Mini JCW GP', 'icon' => 'minijcw.png', 'price' => 1700000],
        ['id' => 'gaz_business', 'name' => 'GAZ Бизнес', 'icon' => 'gryzgaz.png', 'price' => 1900000],
        ['id' => 'ml63', 'name' => 'Mercedes ML63 AMG W164', 'icon' => 'ml63.png', 'price' => 2000000],
        ['id' => 'sherp', 'name' => 'Sherp Вездеход', 'icon' => 'sherp.png', 'price' => 3000000],
    ];
}

function calculateContainerVehicleValue(int $basePrice, int $wear, ?int $govtCut = null): array
{
    $govtCut = $govtCut ?? CONTAINER_GOVT_CUT_PERCENT;
    $wear = max(0, min(20, $wear));
    $afterGovt = (int)round($basePrice * (100 - $govtCut) / 100);
    $value = (int)round($afterGovt * (100 - $wear) / 100);

    return [
        'base_value' => $basePrice,
        'govt_cut' => $govtCut,
        'wear' => $wear,
        'value' => $value,
    ];
}

function rollContainerCar(): array
{
    $pool = getContainerCarPool();
    $car = $pool[random_int(0, count($pool) - 1)];
    $wear = random_int(0, 20);
    $pricing = calculateContainerVehicleValue((int)$car['price'], $wear);

    return [
        'id' => $car['id'],
        'name' => $car['name'],
        'icon' => $car['icon'],
        'base_value' => $pricing['base_value'],
        'govt_cut' => $pricing['govt_cut'],
        'wear' => $pricing['wear'],
        'value' => $pricing['value'],
    ];
}

function rollContainerPrizes(int $count = 5): array
{
    $prizes = [];
    for ($i = 0; $i < $count; $i++) {
        $prizes[] = rollContainerCar();
    }
    return $prizes;
}

function sumContainerPrizes(array $prizes): int
{
    $sum = 0;
    foreach ($prizes as $prize) {
        $sum += (int)($prize['value'] ?? 0);
    }
    return $sum;
}

function isContainerPlayAllowed(): bool
{
    $info = getStreamDisplayForHomepage(120);
    return !empty($info['is_live']);
}
function getContainerCooldownRemaining(string $username = ''): int
{
    return getContainerStreamCooldownRemaining();
}

function getContainerCooldownBlockError(string $username = ''): ?array
{
    $remaining = getContainerStreamCooldownRemaining();
    if ($remaining <= 0) {
        return null;
    }

    return [
        'success' => false,
        'error' => sprintf(
            __('Общий КД контейнеров — %s. Следующая дуэль через %s.'),
            formatSecondsShort(getContainersCooldownSeconds()),
            formatSecondsShort($remaining)
        ),
        'cooldown_remaining' => $remaining,
    ];
}

function getContainerStreamCooldownRemaining(): int
{
    if (!ensureContainerTables()) {
        return 0;
    }

    $db = getDB();
    $stmt = $db->query("
        SELECT COALESCE(displayed_at, created_at) AS last_played
        FROM container_duels
        WHERE status NOT IN ('cancelled', 'pending_pvp', 'awaiting_coin')
        ORDER BY COALESCE(displayed_at, created_at) DESC
        LIMIT 1
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['last_played'])) {
        return 0;
    }

    $elapsed = getContainerCooldownElapsedSeconds((string)$row['last_played']);
    $cooldown = getContainersCooldownSeconds();
    $remaining = max(0, $cooldown - $elapsed);
    return min($cooldown, $remaining);
}

function getContainerWidgetIdleState(): array
{
    return [
        'mode' => 'hidden',
        'cooldown_remaining' => getContainerStreamCooldownRemaining(),
        'cooldown_total' => getContainersCooldownSeconds(),
    ];
}

/** Снимок для инфо-виджета — без захвата дуэли (в отличие от getContainerDuelForWidget). */
function getContainerHubSnapshot(): array
{
    if (!ensureContainerTables()) {
        return getContainerWidgetIdleState();
    }
    $db = getDB();
    $stmt = $db->query("
        SELECT COUNT(*)
        FROM container_duels
        WHERE status IN ('opening', 'displaying')
    ");
    $active = (int)$stmt->fetchColumn() > 0;
    $idle = getContainerWidgetIdleState();
    if ($active) {
        $idle['mode'] = 'active';
    }
    return $idle;
}

function ensureContainerTables(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS container_duels (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                bet_seconds INT NOT NULL,
                player_prizes LONGTEXT NOT NULL,
                bot_prizes LONGTEXT NOT NULL,
                player_total INT NOT NULL,
                bot_total INT NOT NULL,
                winner VARCHAR(16) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'opening',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                displayed_at DATETIME DEFAULT NULL,
                INDEX idx_status (status),
                INDEX idx_username (username),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->query('SELECT 1 FROM container_duels LIMIT 1');
        ensureContainerColumns($db);
        $ready = true;
        return true;
    } catch (PDOException $e) {
        error_log('Ошибка инициализации container_duels: ' . $e->getMessage());
        $ready = false;
        return false;
    }
}

function ensureContainerColumns(PDO $db): void
{
    $columns = [
        'coin_pick' => "VARCHAR(16) DEFAULT NULL",
        'coin_result' => "VARCHAR(16) DEFAULT NULL",
        'first_turn' => "VARCHAR(16) DEFAULT NULL",
        'opponent_username' => "VARCHAR(255) DEFAULT NULL",
        'duel_mode' => "VARCHAR(16) NOT NULL DEFAULT 'bot'",
    ];
    foreach ($columns as $name => $definition) {
        try {
            $db->query("SELECT {$name} FROM container_duels LIMIT 1");
        } catch (PDOException $e) {
            try {
                $db->exec("ALTER TABLE container_duels ADD COLUMN {$name} {$definition}");
            } catch (PDOException $alterError) {
                error_log('container_duels column ' . $name . ': ' . $alterError->getMessage());
            }
        }
    }
}

function getContainerDuelRow(int $duelId, ?string $username = null): ?array
{
    if (!ensureContainerTables()) {
        return null;
    }
    $db = getDB();
    if ($username !== null) {
        $stmt = $db->prepare('
            SELECT * FROM container_duels
            WHERE id = ? AND LOWER(username) = LOWER(?)
            LIMIT 1
        ');
        $stmt->execute([$duelId, $username]);
    } else {
        $stmt = $db->prepare('SELECT * FROM container_duels WHERE id = ? LIMIT 1');
        $stmt->execute([$duelId]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function normalizeContainerPrize(array $prize): array
{
    static $byId = null;
    if ($byId === null) {
        $byId = [];
        foreach (getContainerCarPool() as $car) {
            $byId[$car['id']] = $car;
        }
    }

    $id = (string)($prize['id'] ?? '');
    if ($id !== '' && isset($byId[$id])) {
        $catalog = $byId[$id];
        $wear = (int)($prize['wear'] ?? 0);
        $base = (int)($prize['base_value'] ?? $catalog['price']);
        $govtCut = (int)($prize['govt_cut'] ?? CONTAINER_GOVT_CUT_PERCENT);
        $pricing = calculateContainerVehicleValue($base, $wear, $govtCut);

        return [
            'id' => $catalog['id'],
            'name' => $catalog['name'],
            'icon' => $catalog['icon'],
            'base_value' => $pricing['base_value'],
            'govt_cut' => $pricing['govt_cut'],
            'wear' => $pricing['wear'],
            'value' => $pricing['value'],
        ];
    }

    if (!empty($prize['icon'])) {
        $base = (int)($prize['base_value'] ?? $prize['value'] ?? 0);
        $wear = (int)($prize['wear'] ?? 0);
        $govtCut = (int)($prize['govt_cut'] ?? CONTAINER_GOVT_CUT_PERCENT);
        $pricing = calculateContainerVehicleValue($base, $wear, $govtCut);

        return [
            'id' => $id !== '' ? $id : (string)($prize['name'] ?? 'vehicle'),
            'name' => (string)($prize['name'] ?? 'Транспорт'),
            'icon' => basename((string)$prize['icon']),
            'base_value' => $pricing['base_value'],
            'govt_cut' => $pricing['govt_cut'],
            'wear' => $pricing['wear'],
            'value' => $pricing['value'],
        ];
    }

    $pool = array_values($byId);
    $value = (int)($prize['value'] ?? 0);
    $best = $pool[0];
    $bestDiff = PHP_INT_MAX;
    foreach ($pool as $car) {
        $diff = abs($value - (int)$car['price']);
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $best = $car;
        }
    }

    $wear = (int)($prize['wear'] ?? 0);
    $pricing = calculateContainerVehicleValue((int)$best['price'], $wear);

    return [
        'id' => $best['id'],
        'name' => (string)($prize['name'] ?? $best['name']),
        'icon' => $best['icon'],
        'base_value' => $pricing['base_value'],
        'govt_cut' => $pricing['govt_cut'],
        'wear' => $pricing['wear'],
        'value' => $value > 0 ? $value : $pricing['value'],
    ];
}

function normalizeContainerPrizes(array $prizes): array
{
    $normalized = [];
    foreach ($prizes as $prize) {
        if (!is_array($prize)) {
            continue;
        }
        $normalized[] = normalizeContainerPrize($prize);
    }
    return $normalized;
}

function containerDuelPayload(array $row, bool $includeWinner = false): array
{
    $payload = [
        'id' => (int)$row['id'],
        'duel_id' => (int)$row['id'],
        'username' => $row['username'],
        'bet_seconds' => (int)$row['bet_seconds'],
        'player_prizes' => normalizeContainerPrizes(json_decode((string)$row['player_prizes'], true) ?: []),
        'bot_prizes' => normalizeContainerPrizes(json_decode((string)$row['bot_prizes'], true) ?: []),
        'player_total' => (int)$row['player_total'],
        'bot_total' => (int)$row['bot_total'],
        'coin_pick' => $row['coin_pick'] ?? null,
        'coin_result' => $row['coin_result'] ?? null,
        'first_turn' => $row['first_turn'] ?? null,
        'status' => $row['status'] ?? null,
        'duel_mode' => $row['duel_mode'] ?? 'bot',
        'opponent_username' => $row['opponent_username'] ?? null,
        'win_multiplier' => CONTAINERS_WIN_MULTIPLIER,
    ];
    if ($includeWinner) {
        $payload['winner'] = $row['winner'] ?? null;
    }
    return $payload;
}

function ensureUserTimeTrackingRow(PDO $db, string $username): void
{
    $stmt = $db->prepare('
        INSERT INTO user_time_tracking (username, total_seconds, last_activity)
        VALUES (?, 0, NOW())
        ON DUPLICATE KEY UPDATE last_activity = last_activity
    ');
    $stmt->execute([$username]);
}

function placeContainerDuel(string $username, int $betSeconds): array
{
    return [
        'success' => false,
        'error' => __('Дуэль только против зрителя: !контейнер <мин> @ник в чате.'),
    ];
}

function flipContainerCoin(string $username, int $duelId, string $pick): array
{
    $pick = $pick === 'tails' ? 'tails' : 'heads';
    $row = getContainerDuelRow($duelId);
    if (!$row) {
        return ['success' => false, 'error' => __('Дуэль не найдена')];
    }
    if (($row['status'] ?? '') !== 'awaiting_coin') {
        return ['success' => false, 'error' => __('Монетка уже брошена')];
    }
    $challenger = strtolower((string)($row['username'] ?? ''));
    $opponent = strtolower((string)($row['opponent_username'] ?? ''));
    $actor = strtolower(trim($username));
    if ($actor !== $challenger && ($opponent === '' || $actor !== $opponent)) {
        return ['success' => false, 'error' => __('Ты не участник этой дуэли')];
    }

    $coinResult = random_int(0, 1) === 0 ? 'heads' : 'tails';
    $firstTurn = $pick === $coinResult ? 'player' : 'bot';

    $db = getDB();
    $stmt = $db->prepare("
        UPDATE container_duels
        SET coin_pick = ?, coin_result = ?, first_turn = ?, status = 'opening'
        WHERE id = ? AND status = 'awaiting_coin'
    ");
    $stmt->execute([$pick, $coinResult, $firstTurn, $duelId]);
    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'error' => __('Не удалось бросить монетку')];
    }

    $row['coin_pick'] = $pick;
    $row['coin_result'] = $coinResult;
    $row['first_turn'] = $firstTurn;
    $row['status'] = 'opening';

    return array_merge(
        ['success' => true],
        containerDuelPayload($row, false)
    );
}

function completeContainerDuel(string $username, int $duelId): array
{
    $row = getContainerDuelRow($duelId, $username);
    if (!$row) {
        return ['success' => false, 'error' => __('Дуэль не найдена')];
    }
    $status = $row['status'] ?? '';
    if ($status === 'opening' || $status === 'done') {
        return array_merge(['success' => true], containerDuelPayload($row, true));
    }
    if ($status !== 'playing') {
        return ['success' => false, 'error' => __('Игра ещё не началась')];
    }

    $db = getDB();
    $stmt = $db->prepare("
        UPDATE container_duels
        SET status = 'opening'
        WHERE id = ? AND LOWER(username) = LOWER(?) AND status = 'playing'
    ");
    $stmt->execute([$duelId, $username]);
    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'error' => __('Не удалось завершить дуэль')];
    }

    $row['status'] = 'opening';
    return array_merge(['success' => true], containerDuelPayload($row, true));
}

function getContainerDuelForWidget(int $lastId = 0): array
{
    if (!ensureContainerTables()) {
        return getContainerWidgetIdleState();
    }
    expireStaleContainerWidgetRecords();
    $db = getDB();

    $db->beginTransaction();
    try {
        // Активные дуэли отдаём всегда — last_id только для совместимости, не блокирует повторный показ.
        $stmt = $db->prepare("
            SELECT id, username, bet_seconds, player_prizes, bot_prizes, player_total, bot_total, winner,
                   coin_pick, coin_result, first_turn, duel_mode, opponent_username, status
            FROM container_duels
            WHERE status = 'displaying'
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->commit();
            return array_merge(['mode' => 'duel', 'resume' => true], containerDuelPayload($row, true));
        }

        $stmt = $db->prepare("
            SELECT id, username, bet_seconds, player_prizes, bot_prizes, player_total, bot_total, winner,
                   coin_pick, coin_result, first_turn, duel_mode, opponent_username
            FROM container_duels
            WHERE status = 'opening'
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $db->commit();
            return getContainerWidgetIdleState();
        }

        $claim = $db->prepare("
            UPDATE container_duels
            SET status = 'displaying', displayed_at = NOW()
            WHERE id = ? AND status = 'opening'
        ");
        $claim->execute([(int)$row['id']]);
        if ($claim->rowCount() === 0) {
            $db->rollBack();
            return getContainerWidgetIdleState();
        }

        $db->prepare("
            UPDATE container_duels
            SET status = 'done', displayed_at = NOW()
            WHERE status = 'opening' AND id <> ?
        ")->execute([(int)$row['id']]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('claim container duel for widget: ' . $e->getMessage());
        return getContainerWidgetIdleState();
    }

    return array_merge(['mode' => 'duel'], containerDuelPayload($row, true));
}

function ackContainerDuel(int $duelId): bool
{
    if (!ensureContainerTables()) {
        return false;
    }
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE container_duels
        SET status = 'done', displayed_at = NOW()
        WHERE id = ? AND status IN ('opening', 'displaying')
    ");
    $stmt->execute([$duelId]);
    return $stmt->rowCount() > 0;
}

function getRecentContainerDuels(int $limit = 15): array
{
    if (!ensureContainerTables()) {
        return [];
    }
    $limit = max(1, min(100, $limit));
    $db = getDB();
    $stmt = $db->query("
        SELECT id, username, opponent_username, duel_mode, bet_seconds, player_total, bot_total, winner, status, created_at
        FROM container_duels
        ORDER BY id DESC
        LIMIT {$limit}
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getContainerUserBalance(PDO $db, string $username): int
{
    ensureUserTimeTrackingRow($db, $username);
    $stmt = $db->prepare('SELECT total_seconds FROM user_time_tracking WHERE LOWER(username) = LOWER(?)');
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['total_seconds'] ?? 0);
}

function applyContainerUserBalanceDelta(PDO $db, string $username, int $delta): array
{
    ensureUserTimeTrackingRow($db, $username);
    $before = getContainerUserBalance($db, $username);
    $after = $before + $delta;
    $stmt = $db->prepare('
        UPDATE user_time_tracking
        SET total_seconds = ?, last_activity = NOW()
        WHERE LOWER(username) = LOWER(?)
    ');
    $stmt->execute([$after, $username]);
    return ['before' => $before, 'after' => $after];
}

function refundContainerPendingChallenge(PDO $db, array $duelRow, string $reason): void
{
    $challenger = (string)$duelRow['username'];
    $betSeconds = (int)$duelRow['bet_seconds'];
    $duelId = (int)$duelRow['id'];
    $balance = applyContainerUserBalanceDelta($db, $challenger, $betSeconds);
    logBotTimeEvent(
        $challenger,
        'container_refund',
        $betSeconds,
        $balance['before'],
        $balance['after'],
        ['reason' => $reason, 'duel_id' => $duelId]
    );
}

function reverseContainerPvpSettlement(PDO $db, array $duelRow, string $reason): void
{
    $challenger = (string)$duelRow['username'];
    $opponent = (string)($duelRow['opponent_username'] ?? '');
    if ($opponent === '') {
        return;
    }
    $betSeconds = (int)$duelRow['bet_seconds'];
    $winner = strtolower((string)($duelRow['winner'] ?? ''));
    $winAmount = $betSeconds * CONTAINERS_WIN_MULTIPLIER;
    $duelId = (int)$duelRow['id'];
    $metaBase = ['reason' => $reason, 'duel_id' => $duelId, 'pvp' => true];

    if ($winner === 'player') {
        $balance = applyContainerUserBalanceDelta($db, $challenger, -$winAmount);
        logBotTimeEvent(
            $challenger,
            'container_refund',
            -$winAmount,
            $balance['before'],
            $balance['after'],
            array_merge($metaBase, ['reverse' => 'win'])
        );
        foreach ([$challenger, $opponent] as $user) {
            $balance = applyContainerUserBalanceDelta($db, $user, $betSeconds);
            logBotTimeEvent(
                $user,
                'container_refund',
                $betSeconds,
                $balance['before'],
                $balance['after'],
                array_merge($metaBase, ['refund_bet' => true])
            );
        }
    } elseif ($winner === 'bot') {
        $balance = applyContainerUserBalanceDelta($db, $opponent, -$winAmount);
        logBotTimeEvent(
            $opponent,
            'container_refund',
            -$winAmount,
            $balance['before'],
            $balance['after'],
            array_merge($metaBase, ['reverse' => 'win'])
        );
        foreach ([$challenger, $opponent] as $user) {
            $balance = applyContainerUserBalanceDelta($db, $user, $betSeconds);
            logBotTimeEvent(
                $user,
                'container_refund',
                $betSeconds,
                $balance['before'],
                $balance['after'],
                array_merge($metaBase, ['refund_bet' => true])
            );
        }
    } elseif ($winner === 'draw') {
        foreach ([$challenger, $opponent] as $user) {
            $balance = applyContainerUserBalanceDelta($db, $user, -$betSeconds);
            logBotTimeEvent(
                $user,
                'container_bet',
                -$betSeconds,
                $balance['before'],
                $balance['after'],
                array_merge($metaBase, ['reverse' => 'draw_refund'])
            );
            $balance = applyContainerUserBalanceDelta($db, $user, $betSeconds);
            logBotTimeEvent(
                $user,
                'container_refund',
                $betSeconds,
                $balance['before'],
                $balance['after'],
                array_merge($metaBase, ['refund_bet' => true])
            );
        }
    }
}

function cancelContainerDuel(int $duelId, string $reason = 'admin'): array
{
    if (!ensureContainerTables()) {
        return ['success' => false, 'error' => __('Таблица контейнеров недоступна')];
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM container_duels WHERE id = ? FOR UPDATE');
        $stmt->execute([$duelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $db->rollBack();
            return ['success' => false, 'error' => __('Дуэль не найдена')];
        }

        $status = (string)($row['status'] ?? '');
        $cancellable = ['pending_pvp', 'awaiting_coin', 'opening', 'displaying'];
        if (!in_array($status, $cancellable, true)) {
            $db->rollBack();
            return ['success' => false, 'error' => __('Эту дуэль нельзя отменить')];
        }

        if ($status === 'pending_pvp') {
            refundContainerPendingChallenge($db, $row, $reason);
        } else {
            reverseContainerPvpSettlement($db, $row, $reason);
        }

        $stmt = $db->prepare("UPDATE container_duels SET status = 'cancelled', winner = 'cancelled' WHERE id = ?");
        $stmt->execute([$duelId]);
        $db->commit();

        return [
            'success' => true,
            'duel_id' => $duelId,
            'status' => $status,
            'username' => (string)$row['username'],
            'opponent_username' => (string)($row['opponent_username'] ?? ''),
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('cancelContainerDuel: ' . $e->getMessage());
        return ['success' => false, 'error' => __('Не удалось отменить дуэль')];
    }
}

function getUserContainerDuels(string $username, int $limit = 10): array
{
    if (!ensureContainerTables()) {
        return [];
    }
    $limit = max(1, min(50, $limit));
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, bet_seconds, player_total, bot_total, winner, status, created_at
        FROM container_duels
        WHERE LOWER(username) = LOWER(?)
        ORDER BY id DESC
        LIMIT {$limit}
    ");
    $stmt->execute([$username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
