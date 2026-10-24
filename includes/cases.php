<?php

function getCaseCollectionNames() {
    return [
        'Коллекция «Форсаж»',
        'Коллекция «Темные дела»',
        'Коллекция «Велесова ночь»',
        'Коллекция «Оперской»',
        'Коллекция «Зимняя сказка»',
        'Коллекция «Чёрное золото»',
        'Коллекция «Охотничий»',
        'Коллекция «Египетская сила»',
        'Коллекция «Велесова Ночь 2»',
        'Коллекция «Рождественский экспресс»',
        'Коллекция «Путь воина»',
        'Коллекция «Пиратский»',
        'Коллекция «Проклятие»',
        'Коллекция «Новогодний»',
        'Коллекция «Бандитский»',
        'Коллекция «Клоунский»',
        'Коллекция «Мандариновый»',
    ];
}

function getCaseCollectionIconMap(): array
{
    return [
        'форсаж' => 0,
        'темные дела' => 1,
        'велесова ночь 2' => 8,
        'велесова ночь' => 2,
        'оперской' => 3,
        'зимняя сказка' => 4,
        'черное золото' => 5,
        'чёрное золото' => 5,
        'охотничий' => 6,
        'египетская сила' => 7,
        'рождественский экспресс' => 9,
        'путь воина' => 10,
        'путь война' => 10,
        'пиратский' => 11,
        'проклятие' => 12,
        'новогодний' => 13,
        'бандитский' => 14,
        'клоунский' => 15,
        'мандариновый' => 16,
    ];
}

function normalizeCaseIconLookupKey(string $name): string
{
    $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = preg_replace('/^коллекция\s+/ui', '', $name);
    $name = trim($name, " \t\n\r\0\x0B«»\"'");
    if (preg_match('/^[«"](.+)[»"]$/u', $name, $m)) {
        $name = $m[1];
    }
    $name = preg_replace('/^\d+\s*[\.)]\s*/u', '', $name);
    $name = mb_strtolower(str_replace('ё', 'е', preg_replace('/\s+/u', ' ', $name)));

    return trim($name);
}

function caseIconIndexFromLabel(string $name): ?int
{
    $key = normalizeCaseIconLookupKey($name);
    if ($key === '') {
        return null;
    }

    $map = getCaseCollectionIconMap();
    if (isset($map[$key])) {
        return $map[$key];
    }

    $bestLen = 0;
    $bestIndex = null;
    foreach ($map as $needle => $index) {
        if (!str_contains($key, $needle)) {
            continue;
        }
        $len = mb_strlen($needle);
        if ($len > $bestLen) {
            $bestLen = $len;
            $bestIndex = $index;
        }
    }

    return $bestIndex;
}

function caseCollectionIconIndex(?string $collectionName = null, ?int $collectionId = null): ?int
{
    if ($collectionName !== null && trim($collectionName) !== '') {
        $index = caseIconIndexFromLabel($collectionName);
        if ($index !== null) {
            return $index;
        }
    }

    if ($collectionId !== null && $collectionId > 0) {
        ensureCaseTables();
        $db = getDB();
        $stmt = $db->prepare('SELECT name FROM case_collections WHERE id = ? LIMIT 1');
        $stmt->execute([$collectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return caseIconIndexFromLabel((string)($row['name'] ?? ''));
        }
    }

    return null;
}

function caseCollectionIconUrl(?string $collectionName = null, ?int $collectionId = null): ?string
{
    $index = caseCollectionIconIndex($collectionName, $collectionId);
    if ($index === null) {
        return null;
    }

    return resolveLotteryLocalIconUrl(lotteryLocalCaseIconRef($index));
}

function ensureCaseTables() {
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS case_collections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                sort_order INT DEFAULT 0,
                UNIQUE KEY unique_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS case_purchases (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_id INT DEFAULT NULL,
                username VARCHAR(255) NOT NULL,
                collection_id INT NOT NULL,
                collection_name VARCHAR(255) NOT NULL,
                cost_seconds INT NOT NULL DEFAULT 1800,
                status VARCHAR(20) NOT NULL DEFAULT 'opening',
                issued_in_game TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                displayed_at DATETIME DEFAULT NULL,
                INDEX idx_status (status),
                INDEX idx_issued (issued_in_game),
                INDEX idx_username (username),
                INDEX idx_sale (sale_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS case_sale_windows (
                id INT AUTO_INCREMENT PRIMARY KEY,
                started_by VARCHAR(255) NOT NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ends_at DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                INDEX idx_status_ends (status, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $columnStmt = $db->query("SHOW COLUMNS FROM case_purchases LIKE 'sale_id'");
        if ($columnStmt->rowCount() === 0) {
            $db->exec("ALTER TABLE case_purchases ADD COLUMN sale_id INT DEFAULT NULL AFTER id");
            $db->exec("ALTER TABLE case_purchases ADD INDEX idx_sale (sale_id)");
        }

        $indexStmt = $db->query("SHOW INDEX FROM case_purchases WHERE Key_name = 'unique_sale_purchase'");
        if ($indexStmt->rowCount() === 0) {
            $db->exec("ALTER TABLE case_purchases ADD UNIQUE KEY unique_sale_purchase (sale_id)");
        }

        $issuedColumnStmt = $db->query("SHOW COLUMNS FROM case_purchases LIKE 'issued_in_game'");
        if ($issuedColumnStmt->rowCount() === 0) {
            $db->exec("ALTER TABLE case_purchases ADD COLUMN issued_in_game TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
            $db->exec("ALTER TABLE case_purchases ADD INDEX idx_issued (issued_in_game)");
        }

        seedCaseCollections();
    } catch (PDOException $e) {
        error_log('Ошибка инициализации таблиц кейсов: ' . $e->getMessage());
    }
}

function seedCaseCollections() {
    $db = getDB();
    $names = getCaseCollectionNames();
    $stmt = $db->prepare("
        INSERT INTO case_collections (name, slug, sort_order)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order)
    ");
    foreach ($names as $index => $name) {
        $slug = 'case-' . ($index + 1);
        $stmt->execute([$name, $slug, $index + 1]);
    }
}

function getCaseCollections() {
    ensureCaseTables();
    $db = getDB();
    $stmt = $db->query("
        SELECT id, name, slug
        FROM case_collections
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    return $stmt->fetchAll();
}

function expireCaseSales() {
    ensureCaseTables();
    $db = getDB();
    $db->exec("
        UPDATE case_sale_windows
        SET status = 'expired'
        WHERE status = 'active' AND ends_at <= NOW()
    ");
}

function getActiveCaseSale() {
    expireCaseSales();
    $db = getDB();
    $stmt = $db->query("
        SELECT s.id, s.started_by, s.started_at, s.ends_at, s.status
        FROM case_sale_windows s
        WHERE s.status = 'active'
          AND s.ends_at > NOW()
          AND NOT EXISTS (
              SELECT 1 FROM case_purchases p WHERE p.sale_id = s.id
          )
        ORDER BY s.id DESC
        LIMIT 1
    ");
    return $stmt->fetch() ?: null;
}

function markCaseSaleSold($saleId) {
    ensureCaseTables();
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE case_sale_windows
        SET status = 'sold'
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([(int)$saleId]);
}

function startCaseSale($startedBy, $durationSeconds = null) {
    ensureCaseTables();
    expireCaseSales();

    $duration = $durationSeconds ?? CASE_SALE_DURATION_SECONDS;
    $db = getDB();
    $db->exec("
        UPDATE case_sale_windows
        SET status = 'expired'
        WHERE status = 'active'
    ");

    $stmt = $db->prepare("
        INSERT INTO case_sale_windows (started_by, started_at, ends_at, status)
        VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), 'active')
    ");
    $stmt->execute([$startedBy, (int)$duration]);

    return (int)$db->lastInsertId();
}

function getRecentCasePurchases($limit = 20) {
    ensureCaseTables();
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, username, collection_name, cost_seconds, status, issued_in_game, created_at, displayed_at
            FROM case_purchases
            ORDER BY id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return $rows;
    } catch (PDOException $e) {
        error_log('Ошибка получения покупок кейсов: ' . $e->getMessage());
        return [];
    }
}

function getJackpotWinnerTickerItems($limit = 24) {
    ensureJackpotTables();
    $limit = max(1, min(100, (int)$limit));
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT winner_username AS username, winner_seconds, ends_at AS won_at
            FROM jackpot_rounds
            WHERE status = 'done'
              AND winner_username IS NOT NULL
              AND winner_username != ''
            ORDER BY id DESC
            LIMIT {$limit}
        ");
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }
        $avatarStmt = null;
        try {
            $avatarStmt = $db->prepare("SELECT avatar_url FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        } catch (PDOException $e) {
            $avatarStmt = null;
        }
        foreach ($rows as &$row) {
            $row['avatar_url'] = null;
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
        }
        unset($row);
        return $rows;
    } catch (PDOException $e) {
        error_log('Ошибка ленты победителей джекпота: ' . $e->getMessage());
        return [];
    }
}

function getCasePurchaseTickerItems($limit = 24) {
    ensureCaseTables();
    $limit = max(1, min(100, (int)$limit));
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT username, collection_name, created_at
            FROM case_purchases
            ORDER BY id DESC
            LIMIT {$limit}
        ");
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }
        $avatarStmt = null;
        try {
            $avatarStmt = $db->prepare("SELECT avatar_url FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        } catch (PDOException $e) {
            $avatarStmt = null;
        }
        foreach ($rows as &$row) {
            $row['avatar_url'] = null;
            $row['case_icon_url'] = caseCollectionIconUrl($row['collection_name'] ?? null);
            $row['case_icon_index'] = caseCollectionIconIndex($row['collection_name'] ?? null);
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
        }
        unset($row);
        return $rows;
    } catch (PDOException $e) {
        error_log('Ошибка ленты покупок кейсов: ' . $e->getMessage());
        return [];
    }
}

function ackCasePurchase($purchaseId) {
    ensureCaseTables();
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE case_purchases
        SET status = 'done', displayed_at = NOW()
        WHERE id = ? AND status = 'opening'
    ");
    $stmt->execute([(int)$purchaseId]);
    return $stmt->rowCount() > 0;
}

function setCasePurchaseIssued($purchaseId, $issued) {
    ensureCaseTables();
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE case_purchases
        SET issued_in_game = ?
        WHERE id = ? AND status = 'done'
    ");
    $stmt->execute([(int)(bool)$issued, (int)$purchaseId]);
    return $stmt->rowCount() > 0;
}
