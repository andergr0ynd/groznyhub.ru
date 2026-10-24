<?php
require_once __DIR__ . '/../config.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../index.php');
    exit;
}

if (!canViewBotStats($user)) {
    header('Location: ../index.php?error=access_denied');
    exit;
}

$showTicks = !empty($_GET['show_ticks']);
$filters = [
    'username' => trim((string)($_GET['username'] ?? '')),
    'action' => trim((string)($_GET['action'] ?? '')),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
    'show_ticks' => $showTicks,
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$summary = getBotTimeLedgerSummary($filters);
$result = getBotTimeLedgerEntries($filters, $perPage, $offset);
$rows = $result['rows'];
$total = (int)$result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

$actions = [
    '' => __('Все действия'),
    'refka_stop' => botTimeLedgerActionLabel('refka_stop'),
    'refka_tick' => botTimeLedgerActionLabel('refka_tick'),
    'case_buy' => botTimeLedgerActionLabel('case_buy'),
    'jackpot_bet' => botTimeLedgerActionLabel('jackpot_bet'),
    'jackpot_win' => botTimeLedgerActionLabel('jackpot_win'),
    'jackpot_refund' => botTimeLedgerActionLabel('jackpot_refund'),
    'container_bet' => botTimeLedgerActionLabel('container_bet'),
    'container_win' => botTimeLedgerActionLabel('container_win'),
    'container_refund' => botTimeLedgerActionLabel('container_refund'),
    'daily_bonus' => botTimeLedgerActionLabel('daily_bonus'),
];

function statsBuildQuery(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === false) {
            unset($params[$key]);
        }
    }
    if ($params === []) {
        return '';
    }
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Логи бота'); ?> — andergr0ynd</title>
<?php renderSiteFaviconTags('..'); ?>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
    <style>
        .stats-wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 20px; }
        .stats-header {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .stats-header h1 { margin: 0 0 0.35rem; color: var(--accent); font-size: 1.6rem; }
        .stats-header p { margin: 0; color: var(--text-secondary); line-height: 1.5; }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .stats-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.9rem 1rem;
        }
        .stats-box .label { color: var(--text-secondary); font-size: 0.78rem; }
        .stats-box .value { font-size: 1.15rem; font-weight: 800; margin-top: 0.2rem; }
        .stats-chips { display: flex; flex-wrap: wrap; gap: 0.45rem; margin-bottom: 1rem; }
        .stats-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            font-size: 0.78rem;
            color: var(--text-secondary);
        }
        .stats-chip strong { color: var(--text-primary); font-weight: 700; }
        .stats-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            align-items: end;
        }
        .stats-filters label { display: block; font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 0.3rem; }
        .stats-filters input,
        .stats-filters select {
            width: 100%;
            padding: 0.55rem 0.65rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        .stats-ticks-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .stats-ticks-toggle input { width: auto; }
        .stats-list { display: flex; flex-direction: column; gap: 0.55rem; }
        .log-row {
            display: grid;
            grid-template-columns: 88px 1fr auto;
            gap: 0.75rem 1rem;
            align-items: start;
            padding: 0.85rem 1rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--bg-secondary);
        }
        .log-row.kind-plus { border-left: 3px solid #22c55e; }
        .log-row.kind-minus { border-left: 3px solid #ef4444; }
        .log-row.kind-tick { border-left: 3px solid rgba(148, 163, 184, 0.45); opacity: 0.88; }
        .log-row.kind-neutral { border-left: 3px solid var(--border); }
        .log-time {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-variant-numeric: tabular-nums;
            line-height: 1.35;
        }
        .log-main { min-width: 0; }
        .log-top {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.45rem 0.65rem;
            margin-bottom: 0.25rem;
        }
        .log-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            background: rgba(255,255,255,0.06);
            color: var(--accent);
        }
        .log-user { font-weight: 700; color: var(--text-primary); }
        .log-detail {
            font-size: 0.84rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        .log-balance {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }
        .log-side { text-align: right; white-space: nowrap; }
        .delta-plus { color: #86efac; font-weight: 800; font-size: 1rem; }
        .delta-minus { color: #fca5a5; font-weight: 800; font-size: 1rem; }
        .delta-zero { color: var(--text-secondary); font-weight: 700; font-size: 0.9rem; }
        .stats-empty {
            text-align: center;
            color: var(--text-secondary);
            padding: 2.5rem 1rem;
            border: 1px dashed var(--border);
            border-radius: 12px;
        }
        .stats-pagination { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem; align-items: center; }
        .stats-pagination a,
        .stats-pagination span {
            padding: 0.45rem 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            text-decoration: none;
            color: var(--text-primary);
            background: var(--bg-secondary);
        }
        .stats-pagination .current { border-color: var(--twitch-purple); color: var(--accent); }
        @media (max-width: 720px) {
            .log-row { grid-template-columns: 1fr; }
            .log-side { text-align: left; }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="../index.php" class="logo">
                <?php if (file_exists(__DIR__ . '/../icons/logo.png')): ?>
                    <img src="../icons/logo.png" alt="Logo">
                <?php endif; ?>
                <span>Mark Grozny</span>
            </a>
            <div class="header-right">
                <a href="../index.php" class="btn btn-secondary" style="padding: 0.45rem 0.9rem; font-size: 0.82rem;">
                    <?php echo __('Главная'); ?>
                </a>
            </div>
        </div>
    </header>

    <main class="stats-wrap fade-in">
        <div class="stats-header">
            <h1><?php echo __('Логи времени'); ?></h1>
            <p><?php echo __('Ставки, покупки, бонусы и остановки таймера. Мелкие тики рефки (+15с) скрыты по умолчанию — их слишком много.'); ?></p>
        </div>

        <div class="stats-summary">
            <div class="stats-box">
                <div class="label"><?php echo __('Событий'); ?></div>
                <div class="value"><?php echo number_format((int)$summary['events_count'], 0, ',', ' '); ?></div>
            </div>
            <div class="stats-box">
                <div class="label"><?php echo __('Начислено'); ?></div>
                <div class="value delta-plus">+<?php echo htmlspecialchars(formatSecondsShort((int)$summary['added_seconds'])); ?></div>
            </div>
            <div class="stats-box">
                <div class="label"><?php echo __('Списано'); ?></div>
                <div class="value delta-minus">−<?php echo htmlspecialchars(formatSecondsShort((int)$summary['removed_seconds'])); ?></div>
            </div>
        </div>

        <?php if (!empty($summary['by_action'])): ?>
        <div class="stats-chips">
            <?php foreach ($summary['by_action'] as $item): ?>
                <?php if (!$showTicks && ($item['action_type'] ?? '') === 'refka_tick') continue; ?>
                <span class="stats-chip">
                    <?php echo htmlspecialchars(botTimeLedgerActionLabel((string)$item['action_type'])); ?>
                    <strong><?php echo (int)$item['cnt']; ?></strong>
                    <span><?php echo htmlspecialchars(formatSecondsShort((int)$item['total_delta'])); ?></span>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="get" class="stats-filters">
            <div>
                <label for="username"><?php echo __('Ник'); ?></label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($filters['username']); ?>" placeholder="legenda_bakinskiy">
            </div>
            <div>
                <label for="action"><?php echo __('Тип'); ?></label>
                <select id="action" name="action">
                    <?php foreach ($actions as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $filters['action'] === $value ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="date_from"><?php echo __('С'); ?></label>
                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
            </div>
            <div>
                <label for="date_to"><?php echo __('По'); ?></label>
                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?php echo __('Найти'); ?></button>
            </div>
        </form>

        <form method="get" class="stats-ticks-toggle">
            <?php foreach (['username', 'action', 'date_from', 'date_to'] as $keep): ?>
                <?php if ($filters[$keep] !== ''): ?>
                    <input type="hidden" name="<?php echo $keep; ?>" value="<?php echo htmlspecialchars($filters[$keep]); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <label>
                <input type="checkbox" name="show_ticks" value="1"<?php echo $showTicks ? ' checked' : ''; ?> onchange="this.form.submit()">
                <?php echo __('Показывать тики рефки (+15с каждые ~15с)'); ?>
            </label>
        </form>

        <?php if (empty($rows)): ?>
            <div class="stats-empty">
                <?php echo __('Ничего не найдено. Попробуй другой фильтр или включи тики рефки.'); ?>
            </div>
        <?php else: ?>
            <div class="stats-list">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $event = botTimeLedgerDescribeEvent($row);
                    $delta = (int)$row['delta_seconds'];
                    $deltaClass = $delta > 0 ? 'delta-plus' : ($delta < 0 ? 'delta-minus' : 'delta-zero');
                    $deltaPrefix = $delta > 0 ? '+' : ($delta < 0 ? '−' : '');
                    $kindClass = $event['kind'] === 'tick' ? 'kind-tick' : 'kind-' . $event['kind'];
                    $before = formatSecondsShort((int)($row['balance_before'] ?? 0));
                    $after = formatSecondsShort((int)($row['balance_after'] ?? 0));
                    ?>
                    <article class="log-row <?php echo htmlspecialchars($kindClass); ?>">
                        <div class="log-time"><?php echo htmlspecialchars(botTimeLedgerFormatDateTime($row['created_at'] ?? null)); ?></div>
                        <div class="log-main">
                            <div class="log-top">
                                <span class="log-badge"><?php echo htmlspecialchars($event['label']); ?></span>
                                <span class="log-user">@<?php echo htmlspecialchars((string)$row['username']); ?></span>
                            </div>
                            <?php if ($event['detail'] !== ''): ?>
                                <div class="log-detail"><?php echo htmlspecialchars($event['detail']); ?></div>
                            <?php endif; ?>
                            <div class="log-balance"><?php echo htmlspecialchars($before); ?> → <?php echo htmlspecialchars($after); ?></div>
                        </div>
                        <div class="log-side <?php echo $deltaClass; ?>">
                            <?php echo $deltaPrefix . htmlspecialchars(formatSecondsShort(abs($delta))); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
        <div class="stats-pagination">
            <?php if ($page > 1): ?>
                <a href="stats.php<?php echo htmlspecialchars(statsBuildQuery(['page' => $page - 1])); ?>">← <?php echo __('Назад'); ?></a>
            <?php endif; ?>
            <span class="current"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="stats.php<?php echo htmlspecialchars(statsBuildQuery(['page' => $page + 1])); ?>"><?php echo __('Дальше'); ?> →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <script src="../js/main.js"></script>
</body>
</html>
