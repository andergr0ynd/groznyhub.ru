<?php

class DemoStatement extends PDOStatement
{
    public int $forcedRowCount = -1;

    public function rowCount(): int
    {
        if ($this->forcedRowCount >= 0) {
            return $this->forcedRowCount;
        }

        return parent::rowCount();
    }
}

class DemoPDO extends PDO
{
    public function __construct(string $dsn)
    {
        parent::__construct($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DemoStatement::class]);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(demoAdaptSql($statement));
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $show = demoShowStatement($this, $query);
        if ($show instanceof PDOStatement) {
            return $show;
        }

        $sql = demoAdaptSql($query);
        if ($fetchMode === null) {
            return parent::query($sql);
        }

        return parent::query($sql, $fetchMode, ...$fetchModeArgs);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(demoAdaptSql($query), $options);
    }

    public function queryRaw(string $sql): PDOStatement|false
    {
        return parent::query($sql);
    }
}

function demoAdaptSql(string $sql): string
{
    $sql = trim($sql);
    if ($sql === '') {
        return $sql;
    }

    if (preg_match(
        '/^INSERT\s+INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*(\([^;]*\))\s*ON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.+)$/is',
        $sql,
        $upsert
    )) {
        $updates = preg_replace('/\bVALUES\s*\(\s*(\w+)\s*\)/i', 'excluded.$1', $upsert[4]);
        $updates = preg_replace('/\bCURRENT_TIMESTAMP\b/i', "datetime('now')", (string)$updates);
        $firstCol = trim(explode(',', $upsert[2])[0], " `\t\n\r");

        return 'INSERT INTO ' . $upsert[1] . ' (' . $upsert[2] . ') VALUES ' . $upsert[3]
            . ' ON CONFLICT(' . $firstCol . ') DO UPDATE SET ' . $updates;
    }

    $sql = preg_replace('/\s+ENGINE\s*=\s*\w+/i', '', $sql);
    $sql = preg_replace('/\s+DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
    $sql = preg_replace('/\s+COLLATE\s+[A-Za-z0-9_]+/i', '', $sql);
    $sql = preg_replace("/\s+COMMENT\s+'(?:\\\\'|[^'])*'/i", '', $sql);
    $sql = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql);
    $sql = preg_replace('/\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\b[\s\S]*$/i', '', $sql);
    $sql = preg_replace('/\bINSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $sql);
    $sql = preg_replace('/^TRUNCATE\s+TABLE\s+/i', 'DELETE FROM ', $sql);
    $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);

    $sql = preg_replace_callback(
        '/DATE_SUB\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+(SECOND|MINUTE|HOUR|DAY)\s*\)/i',
        static function (array $m): string {
            return "datetime('now', '-{$m[1]} " . strtolower($m[2]) . "s')";
        },
        $sql
    );
    $sql = preg_replace_callback(
        '/DATE_ADD\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+(SECOND|MINUTE|HOUR|DAY)\s*\)/i',
        static function (array $m): string {
            return "datetime('now', '+{$m[1]} " . strtolower($m[2]) . "s')";
        },
        $sql
    );
    $sql = preg_replace(
        '/DATE_ADD\(\s*NOW\(\)\s*,\s*INTERVAL\s+\?\s+SECOND\s*\)/i',
        "datetime('now', '+' || ? || ' seconds')",
        $sql
    );
    $sql = preg_replace(
        '/DATE_ADD\(\s*([A-Za-z0-9_.]+)\s*,\s*INTERVAL\s+(\d+)\s+SECOND\s*\)/i',
        "datetime($1, '+$2 seconds')",
        $sql
    );
    $sql = preg_replace(
        '/DATE_ADD\(\s*\?\s*,\s*INTERVAL\s+\?\s+SECOND\s*\)/i',
        "datetime(?, '+' || ? || ' seconds')",
        $sql
    );
    $sql = preg_replace('/\bNOW\(\)/i', "datetime('now')", $sql);

    if (preg_match('/^ALTER\s+TABLE\s+/i', $sql)) {
        $sql = preg_replace('/\s+AFTER\s+[`"]?\w+[`"]?/i', '', $sql);
        if (preg_match(
            '/^ALTER\s+TABLE\s+[`"]?(\w+)[`"]?\s+ADD\s+(UNIQUE\s+)?(?:INDEX|KEY)\s+[`"]?(\w+)[`"]?\s*\((.+)\)\s*$/is',
            $sql,
            $index
        )) {
            $unique = trim($index[2]) !== '' ? 'UNIQUE ' : '';
            $cols = rtrim($index[4], " \t\n\r;");

            return 'CREATE ' . $unique . 'INDEX IF NOT EXISTS ' . $index[3] . ' ON ' . $index[1] . ' (' . $cols . ')';
        }
        if (preg_match('/^ALTER\s+TABLE\s+[`"]?(\w+)[`"]?\s+DROP\s+INDEX\s+[`"]?(\w+)[`"]?/i', $sql, $drop)) {
            return 'DROP INDEX IF EXISTS ' . $drop[2];
        }
    }

    if (preg_match('/^CREATE\s+TABLE/i', $sql)) {
        $sql = demoAdaptCreateTable($sql);
    }

    return $sql;
}

function demoAdaptCreateTable(string $sql): string
{
    $sql = str_replace('`', '', $sql);
    $sql = preg_replace(
        '/\bINT(?:\(\d+\))?\s+(?:NOT\s+NULL\s+)?AUTO_INCREMENT\s+PRIMARY\s+KEY/i',
        'INTEGER PRIMARY KEY AUTOINCREMENT',
        $sql
    );
    $sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql);
    $sql = preg_replace('/\bTINYINT(?:\(\d+\))?(?![A-Za-z])/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bBIGINT(?:\(\d+\))?(?![A-Za-z])/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bINT(?:\(\d+\))?(?![A-Za-z])/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bDOUBLE\b/i', 'REAL', $sql);
    $sql = preg_replace('/\bENUM\s*\([^)]*\)/i', 'TEXT', $sql);
    $sql = preg_replace('/,?\s*UNIQUE\s+(?:KEY|INDEX)\s+\w+\s*\(([^)]+)\)/i', ', UNIQUE ($1)', $sql);
    $sql = preg_replace('/,?\s*(?:KEY|INDEX)\s+\w+\s*\([^)]*\)/i', '', $sql);
    $sql = preg_replace('/,\s*\)/', ')', $sql);

    return $sql;
}

function demoShowStatement(DemoPDO $pdo, string $sql): ?PDOStatement
{
    if (preg_match('/^SHOW\s+COLUMNS\s+FROM\s+[`"]?(\w+)[`"]?(?:\s+LIKE\s+(.+))?\s*$/i', trim($sql), $m)) {
        $table = $m[1];
        $like = isset($m[2]) ? trim($m[2], " \t'\"") : '';
        $info = $pdo->queryRaw('PRAGMA table_info(' . $table . ')');
        $names = [];
        if ($info instanceof PDOStatement) {
            foreach ($info->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = (string)($row['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                if ($like === '' || strcasecmp($name, $like) === 0) {
                    $names[] = $name;
                }
            }
        }

        $stmt = $names
            ? $pdo->queryRaw('SELECT ' . $pdo->quote($names[0]) . ' AS Field')
            : $pdo->queryRaw('SELECT NULL AS Field WHERE 0');
        if ($stmt instanceof DemoStatement) {
            $stmt->forcedRowCount = count($names);
        }

        return $stmt instanceof PDOStatement ? $stmt : null;
    }

    if (preg_match('/^SHOW\s+INDEX\s+FROM\s+[`"]?(\w+)[`"]?(?:\s+WHERE\s+Key_name\s*=\s*([\'"])(.*?)\2)?\s*$/i', trim($sql), $m)) {
        $table = $m[1];
        $keyName = $m[3] ?? '';
        $info = $pdo->queryRaw('PRAGMA index_list(' . $table . ')');
        $count = 0;
        if ($info instanceof PDOStatement) {
            foreach ($info->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = (string)($row['name'] ?? '');
                if ($keyName === '' || strcasecmp($name, $keyName) === 0) {
                    $count++;
                }
            }
        }

        $stmt = $count > 0
            ? $pdo->queryRaw('SELECT ' . $pdo->quote($keyName !== '' ? $keyName : 'index') . ' AS Key_name')
            : $pdo->queryRaw('SELECT NULL AS Key_name WHERE 0');
        if ($stmt instanceof DemoStatement) {
            $stmt->forcedRowCount = $count;
        }

        return $stmt instanceof PDOStatement ? $stmt : null;
    }

    return null;
}

function openDemoDatabase(): DemoPDO
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось создать data/ для демо-базы');
    }

    $path = $dir . DIRECTORY_SEPARATOR . 'demo.sqlite';
    $pdo = new DemoPDO('sqlite:' . str_replace('\\', '/', $path));
    $pdo->exec('PRAGMA foreign_keys = ON');
    demoSeed($pdo);

    return $pdo;
}

function demoSeed(DemoPDO $db): void
{
    $db->queryRaw("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            twitch_id TEXT,
            username TEXT NOT NULL UNIQUE,
            display_name TEXT,
            email TEXT,
            avatar_url TEXT,
            access_token TEXT,
            refresh_token TEXT,
            admin INTEGER NOT NULL DEFAULT 1,
            is_private INTEGER DEFAULT 0,
            kick_id TEXT,
            kick_username TEXT,
            kick_linked_at TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->queryRaw("
        INSERT OR IGNORE INTO users (id, twitch_id, username, display_name, email, admin, is_private)
        VALUES (1, 'demo', 'demo', 'Demo', '', 1, 0)
    ");

    $db->queryRaw("
        CREATE TABLE IF NOT EXISTS user_time_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            twitch_id TEXT,
            total_seconds INTEGER NOT NULL DEFAULT 0,
            session_start TEXT,
            last_activity TEXT,
            is_active INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->queryRaw("
        INSERT OR IGNORE INTO user_time_tracking (username, total_seconds, last_activity, is_active)
        VALUES
            ('demo', 18300, datetime('now'), 1),
            ('viewer_one', 7200, datetime('now', '-1 hour'), 0),
            ('viewer_two', 3600, datetime('now', '-2 days'), 0)
    ");

    $db->queryRaw("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->queryRaw("
        INSERT INTO chat_messages (username, message)
        SELECT 'demo', 'привет, это демо'
        WHERE NOT EXISTS (SELECT 1 FROM chat_messages WHERE username = 'demo')
    ");

    $db->queryRaw("
        CREATE TABLE IF NOT EXISTS active_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            session_start TEXT,
            last_message_time TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->queryRaw("
        INSERT OR IGNORE INTO active_sessions (username, session_start, last_message_time)
        VALUES ('demo', datetime('now', '-20 minutes'), datetime('now'))
    ");

    $channel = defined('TWITCH_CHANNEL_LOGIN') ? TWITCH_CHANNEL_LOGIN : 'mark_grozny';
    $db->queryRaw("
        CREATE TABLE IF NOT EXISTS twitch_stream_status (
            channel TEXT PRIMARY KEY,
            is_live INTEGER DEFAULT 0,
            started_at TEXT,
            stream_title TEXT,
            game_name TEXT,
            last_notified_at TEXT,
            last_checked TEXT
        )
    ");
    $stmt = $db->prepare('INSERT OR IGNORE INTO twitch_stream_status (channel, is_live, last_checked) VALUES (?, 0, datetime(\'now\'))');
    $stmt->execute([$channel]);

    $db->queryRaw("
        CREATE TABLE IF NOT EXISTS case_purchases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id INTEGER,
            username TEXT NOT NULL,
            collection_id INTEGER NOT NULL,
            collection_name TEXT NOT NULL,
            cost_seconds INTEGER NOT NULL DEFAULT 1800,
            status TEXT NOT NULL DEFAULT 'done',
            issued_in_game INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            displayed_at TEXT
        )
    ");
    $db->queryRaw("
        INSERT INTO case_purchases (username, collection_id, collection_name, cost_seconds, status, created_at)
        SELECT 'viewer_one', 1, 'Коллекция «Форсаж»', 5400, 'done', datetime('now', '-2 hours')
        WHERE NOT EXISTS (SELECT 1 FROM case_purchases)
    ");

    $db->queryRaw("
        CREATE TABLE IF NOT EXISTS jackpot_rounds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            started_by TEXT NOT NULL,
            started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ends_at TEXT NOT NULL,
            total_seconds INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'done',
            winner_username TEXT,
            winner_seconds INTEGER,
            winning_number INTEGER,
            winner_displayed INTEGER NOT NULL DEFAULT 1,
            winner_announced INTEGER NOT NULL DEFAULT 1
        )
    ");
    $db->queryRaw("
        INSERT INTO jackpot_rounds (
            started_by, started_at, ends_at, total_seconds, status, winner_username, winner_seconds, winning_number
        )
        SELECT 'demo', datetime('now', '-1 day'), datetime('now', '-1 day', '+2 minutes'), 3600, 'done', 'viewer_two', 3600, 17
        WHERE NOT EXISTS (SELECT 1 FROM jackpot_rounds)
    ");
}
