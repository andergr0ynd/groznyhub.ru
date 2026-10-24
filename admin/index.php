<?php
require_once '../config.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    header('Location: ../index.php');
    exit;
}

// Проверка прав администратора
if (!isset($user['admin']) || $user['admin'] != 1) {
    header('Location: ../index.php?error=access_denied');
    exit;
}

$containerCooldownFlash = null;
$containerCooldownError = null;
$containerCancelFlash = null;
$containerCancelError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'containers_cooldown') {
    $minutes = (int)($_POST['cooldown_minutes'] ?? 0);
    $result = setContainersCooldownMinutes($minutes);
    if (!empty($result['success'])) {
        $containerCooldownFlash = sprintf(__('КД контейнеров сохранён: %d мин.'), (int)$result['cooldown_minutes']);
    } else {
        $containerCooldownError = (string)($result['error'] ?? __('Не удалось сохранить настройку.'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'containers_cancel') {
    $duelId = (int)($_POST['duel_id'] ?? 0);
    $result = cancelContainerDuel($duelId, 'admin');
    if (!empty($result['success'])) {
        $containerCancelFlash = sprintf(
            __('Дуэль #%d отменена, ставки возвращены.'),
            (int)$result['duel_id']
        );
    } else {
        $containerCancelError = (string)($result['error'] ?? __('Не удалось отменить дуэль.'));
    }
}

$containersCooldownMinutes = (int)ceil(getContainersCooldownSeconds() / 60);
$containersCooldownMaxMinutes = (int)(CONTAINERS_COOLDOWN_MAX_SECONDS / 60);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Админ-Панель'); ?> - Mark Grozny</title>
<?php renderSiteFaviconTags('..'); ?>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 20px;
        }

        .admin-header {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .admin-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--accent);
        }

        .admin-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .admin-content {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
        }

        .users-table thead {
            background-color: var(--bg-primary);
        }

        .users-table th {
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid var(--border);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .users-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .users-table tbody tr:hover {
            background-color: var(--bg-primary);
        }

        .users-table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-active {
            color: var(--success);
            font-weight: 600;
        }

        .status-inactive {
            color: var(--text-secondary);
        }

        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .admin-stat-box {
            background-color: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }

        .admin-stat-box h3 {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .admin-stat-box .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .admin-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .admin-collapsible {
            margin-top: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background-color: var(--bg-primary);
        }

        .admin-collapsible summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
            list-style: none;
        }

        .admin-collapsible summary::-webkit-details-marker {
            display: none;
        }

        .admin-collapsible summary::after {
            content: '▾';
            color: var(--text-secondary);
            font-size: 0.85rem;
            transition: transform 0.2s ease;
        }

        .admin-collapsible[open] summary::after {
            transform: rotate(180deg);
        }

        .admin-collapsible-badge {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--accent);
            background: rgba(56, 189, 248, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.25);
            border-radius: 999px;
            padding: 0.15rem 0.55rem;
        }

        .container-duel-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 260px;
            overflow-y: auto;
            border-top: 1px solid var(--border);
        }

        .container-duel-list li {
            padding: 0.55rem 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 0.82rem;
            line-height: 1.45;
        }

        .container-duel-list li:last-child {
            border-bottom: none;
        }

        .container-duel-list strong {
            color: var(--text-primary);
        }

        .container-duel-list .duel-meta {
            color: var(--text-secondary);
            opacity: 0.85;
        }

        .container-duel-cancel {
            margin-left: 0.5rem;
        }

        .container-duel-cancel button {
            padding: 0.2rem 0.55rem;
            font-size: 0.75rem;
            border-radius: 6px;
            border: 1px solid #f87171;
            background: transparent;
            color: #f87171;
            cursor: pointer;
        }

        .container-duel-cancel button:hover {
            background: rgba(248, 113, 113, 0.12);
        }

        .admin-section-collapsible {
            margin-bottom: 2rem;
        }

        .admin-section-collapsible summary {
            font-size: 1.05rem;
            padding: 1rem 1.25rem;
        }

        .admin-collapsible-body {
            padding: 0 1rem 1rem;
        }

        .admin-collapsible-body > p:first-child,
        .admin-collapsible-body > .admin-actions:first-child {
            margin-top: 0.25rem;
        }

        .users-table th:last-child,
        .users-table td:last-child {
            text-align: center;
            width: 80px;
        }

        .case-issue-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        .case-issue-label input {
            width: 16px;
            height: 16px;
            accent-color: var(--success);
            cursor: pointer;
        }
        .case-issue-text { font-weight: 600; font-size: 0.875rem; }
        .case-issue-label.is-pending .case-issue-text { color: #ffb347; }
        .case-issue-label.is-issued .case-issue-text { color: var(--success); }
        .case-issue-label.is-loading { opacity: 0.6; pointer-events: none; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="../index.php" class="logo">
                <?php if (file_exists('../icons/logo.png')): ?>
                    <img src="../icons/logo.png" alt="Logo">
                <?php endif; ?>
                <span>Mark Grozny</span>
            </a>
            <div class="header-right">
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <a href="../index.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <?php echo __('На главную'); ?>
                    </a>
                    <a href="lottery.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <?php echo __('Лотерея'); ?>
                    </a>
                    <a href="launcher_errors.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <?php echo __('Ошибки лаунчера'); ?>
                    </a>
                    <a href="launcher_bans.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <?php echo __('Баны лаунчера'); ?>
                    </a>
                    <a href="../logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <?php echo __('Выйти'); ?>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="admin-container fade-in">
            <div class="admin-header">
                <h1><?php echo __('⚙️ Админ-Панель'); ?></h1>
                <p><?php echo sprintf(__('Добро пожаловать, %s!'), htmlspecialchars($user['display_name'])); ?></p>
            </div>

            <div class="admin-content">
                <?php
                // В админ-панели показываем всех пользователей, включая приватных
                $allUsers = getAllUsersStats(true);
                $totalUsers = count($allUsers);
                $totalTime = array_sum(array_column($allUsers, 'total_seconds'));
                $totalMessages = array_sum(array_column($allUsers, 'messages_count'));
                $activeUsers = count(array_filter($allUsers, function($u) { return $u['is_active']; }));
                $recentCasePurchases = getRecentCasePurchases(15);
                $recentJackpotRounds = getRecentJackpotRounds(10);
                $recentContainerDuels = getRecentContainerDuels(15);
                ?>
                
                <div class="admin-stats">
                    <div class="admin-stat-box">
                        <h3><?php echo __('Всего пользователей'); ?></h3>
                        <div class="stat-value"><?php echo number_format($totalUsers, 0, ',', ' '); ?></div>
                    </div>
                    <div class="admin-stat-box">
                        <h3><?php echo __('Активных сейчас'); ?></h3>
                        <div class="stat-value"><?php echo number_format($activeUsers, 0, ',', ' '); ?></div>
                    </div>
                    <div class="admin-stat-box">
                        <h3><?php echo __('Всего сообщений'); ?></h3>
                        <div class="stat-value"><?php echo number_format($totalMessages, 0, ',', ' '); ?></div>
                    </div>
                    <div class="admin-stat-box">
                        <h3><?php echo __('Общее время'); ?></h3>
                        <div class="stat-value" style="font-size: 1.5rem;">
                            <?php 
                            $totalHours = floor($totalTime / 3600);
                            echo number_format($totalHours, 0, ',', ' ') . __('ч');
                            ?>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 2rem; padding: 1.25rem 1.5rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px;">
                    <h2 style="margin: 0 0 0.5rem; color: var(--text-primary); font-size: 1.15rem;"><?php echo __('📺 Инфо-виджет OBS (все чат-игры)'); ?></h2>
                    <p style="color: var(--text-secondary); margin: 0 0 1rem; font-size: 0.9rem; line-height: 1.5;">
                        <?php echo __('Чередует мегафон и летающего пингвина раз в 15 мин. OBS: весь холст 1920×1080. Тест: ?cycle=60&debug=1 · только мегафон: ?mode=megaphone · только самолёт: ?mode=fly'); ?>
                    </p>
                    <a class="btn btn-primary" href="../widget/hub.html" target="_blank" rel="noopener">
                        <?php echo __('Открыть widget/hub.html'); ?>
                    </a>
                    <span style="color: var(--text-secondary); font-size: 0.85rem; margin-left: 0.75rem;">
                        <?php echo __('OBS: 1920×1080'); ?>
                    </span>
                </div>

                <details class="admin-collapsible admin-section-collapsible">
                    <summary>
                        <span><?php echo __('📦 Последние покупки кейсов'); ?></span>
                        <?php if (!empty($recentCasePurchases)): ?>
                            <span class="admin-collapsible-badge"><?php echo count($recentCasePurchases); ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="admin-collapsible-body">
                    <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">
                        <?php echo __('Виджет OBS:'); ?>
                        <a href="../widget/case.html" target="_blank" rel="noopener" style="color: var(--accent);">
                            widget/case.html
                        </a>
                        · <?php echo sprintf(__('Стоимость: %s мин рефки'), (int)(CASE_COST_SECONDS / 60)); ?>
                        · <?php echo sprintf(__('Баннер: %s сек'), CASE_SALE_DURATION_SECONDS); ?>
                    </p>
                    <div class="admin-actions" style="margin-bottom: 1rem;">
                        <button class="btn btn-primary" onclick="startCaseSale()">
                            <?php echo __('🎁 Запустить продажу кейса'); ?>
                        </button>
                    </div>
                    <?php if (empty($recentCasePurchases)): ?>
                        <p style="color: var(--text-secondary); margin: 0;"><?php echo __('Покупок пока нет'); ?></p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo __('Twitch'); ?></th>
                                        <th><?php echo __('Коллекция'); ?></th>
                                        <th><?php echo __('Статус'); ?></th>
                                        <th><?php echo __('Время'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentCasePurchases as $purchase): ?>
                                        <tr>
                                            <td><?php echo (int)$purchase['id']; ?></td>
                                            <td><strong style="color: var(--text-primary);"><?php echo htmlspecialchars($purchase['username']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($purchase['collection_name']); ?></td>
                                            <td>
                                                <?php if ($purchase['status'] === 'opening'): ?>
                                                    <span class="status-active"><?php echo __('На виджете'); ?></span>
                                                <?php else:
                                                    $isIssued = !empty($purchase['issued_in_game']);
                                                ?>
                                                    <label class="case-issue-label <?php echo $isIssued ? 'is-issued' : 'is-pending'; ?>">
                                                        <input type="checkbox" class="case-issue-checkbox" data-id="<?php echo (int)$purchase['id']; ?>" <?php echo $isIssued ? 'checked' : ''; ?> onchange="toggleCaseIssued(this)">
                                                        <span class="case-issue-text"><?php echo $isIssued ? __('Выдано') : __('Выдать в игре'); ?></span>
                                                    </label>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 0.875rem;">
                                                <?php
                                                $createdAt = new DateTime($purchase['created_at']);
                                                echo $createdAt->format('d.m.Y H:i');
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    </div>
                </details>

                <div style="margin-bottom: 2rem;">
                    <h2 style="margin: 0 0 1rem; color: var(--text-primary);"><?php echo __('🎡 Колесо'); ?></h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">
                        <?php echo __('Панель стримера:'); ?>
                        <a href="../koleso/index.html" target="_blank" rel="noopener" style="color: var(--accent);">
                            koleso/index.html
                        </a>
                        · <?php echo __('Импорт .txt, взвешенные шансы, режим выбывания'); ?>
                    </p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h2 style="margin: 0 0 1rem; color: var(--text-primary);"><?php echo __('📦 Дуэль контейнеров'); ?></h2>
                    <?php if ($containerCancelFlash): ?>
                        <p style="color: #34d399; margin: 0 0 1rem; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($containerCancelFlash); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($containerCancelError): ?>
                        <p style="color: #f87171; margin: 0 0 1rem; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($containerCancelError); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($containerCooldownFlash): ?>
                        <p style="color: #34d399; margin: 0 0 1rem; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($containerCooldownFlash); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($containerCooldownError): ?>
                        <p style="color: #f87171; margin: 0 0 1rem; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($containerCooldownError); ?>
                        </p>
                    <?php endif; ?>
                    <form method="post" style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.75rem; margin-bottom: 1rem;">
                        <input type="hidden" name="action" value="containers_cooldown">
                        <label style="display: flex; flex-direction: column; gap: 0.35rem; color: var(--text-secondary); font-size: 0.875rem;">
                            <?php echo __('Общий КД между дуэлями (мин)'); ?>
                            <input
                                type="number"
                                name="cooldown_minutes"
                                min="1"
                                max="<?php echo (int)$containersCooldownMaxMinutes; ?>"
                                value="<?php echo (int)$containersCooldownMinutes; ?>"
                                required
                                style="width: 7rem; padding: 0.5rem 0.65rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-primary); color: var(--text-primary);"
                            >
                        </label>
                        <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1rem; font-size: 0.875rem;">
                            <?php echo __('Сохранить КД'); ?>
                        </button>
                    </form>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">
                        <?php echo __('Команда в чате:'); ?> <code>!контейнер &lt;мин&gt; @ник</code> (вызов зрителю),
                        <code>!контейнер принять</code> / <code>отказать</code> / <code>отозвать</code>,
                        <code>!контейнер орел</code> / <code>решка</code>
                        · <?php echo __('Виджет OBS:'); ?>
                        <a href="../widget/containers.html?obs=1" target="_blank" rel="noopener" style="color: var(--accent);">
                            widget/containers.html?obs=1
                        </a>
                        <div style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                            <?php echo __('В OBS: Browser Source 1920×1080, URL с ?obs=1, «Обновить кэш браузера» при смене виджета.'); ?>
                        </div>
                        · <?php echo sprintf(
                            __('Мин. ставка: %s мин · общий КД %s мин · только в эфире · выигрыш x%s'),
                            (int)(CONTAINERS_MIN_BET_SECONDS / 60),
                            (int)$containersCooldownMinutes,
                            CONTAINERS_WIN_MULTIPLIER
                        ); ?>
                    </p>
                    <?php if (empty($recentContainerDuels)): ?>
                        <p style="color: var(--text-secondary); margin: 0;"><?php echo __('Дуэлей пока нет'); ?></p>
                    <?php else: ?>
                        <details class="admin-collapsible">
                            <summary>
                                <span><?php echo __('Последние дуэли'); ?></span>
                                <span class="admin-collapsible-badge"><?php echo count($recentContainerDuels); ?></span>
                            </summary>
                            <ul class="container-duel-list">
                                <?php foreach ($recentContainerDuels as $duel): ?>
                                    <?php
                                    $duelMode = (string)($duel['duel_mode'] ?? 'bot');
                                    $opponentName = (string)($duel['opponent_username'] ?? '');
                                    $winnerLabel = match ($duel['winner'] ?? '') {
                                        'player' => (string)$duel['username'],
                                        'bot' => $duelMode === 'pvp' && $opponentName !== ''
                                            ? $opponentName
                                            : 'БОТ',
                                        'draw' => __('Ничья'),
                                        'cancelled' => __('Отмена'),
                                        default => '—',
                                    };
                                    $createdLabel = !empty($duel['created_at'])
                                        ? date('d.m H:i', strtotime((string)$duel['created_at']))
                                        : '';
                                    $canCancel = in_array(
                                        (string)($duel['status'] ?? ''),
                                        ['pending_pvp', 'awaiting_coin', 'opening', 'displaying'],
                                        true
                                    );
                                    ?>
                                    <li>
                                        <strong>#<?php echo (int)$duel['id']; ?></strong>
                                        <?php echo htmlspecialchars((string)$duel['username']); ?>
                                        <?php if ($duelMode === 'pvp' && $opponentName !== ''): ?>
                                            vs <?php echo htmlspecialchars($opponentName); ?>
                                        <?php endif; ?>
                                        · <?php echo formatSecondsShort((int)$duel['bet_seconds']); ?>
                                        · <?php echo htmlspecialchars(formatContainerRubles((int)$duel['player_total'])); ?>
                                        / <?php echo htmlspecialchars(formatContainerRubles((int)$duel['bot_total'])); ?>
                                        · <?php echo __('победил'); ?> <strong><?php echo htmlspecialchars($winnerLabel); ?></strong>
                                        <span class="duel-meta">
                                            · <?php echo htmlspecialchars((string)$duel['status']); ?>
                                            <?php if ($createdLabel !== ''): ?>
                                                · <?php echo htmlspecialchars($createdLabel); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($canCancel): ?>
                                            <span class="container-duel-cancel">
                                                <form method="post" style="display: inline;" onsubmit="return confirm('<?php echo htmlspecialchars(__('Отменить дуэль и вернуть ставки?')); ?>');">
                                                    <input type="hidden" name="action" value="containers_cancel">
                                                    <input type="hidden" name="duel_id" value="<?php echo (int)$duel['id']; ?>">
                                                    <button type="submit"><?php echo __('Отменить'); ?></button>
                                                </form>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>

                <details class="admin-collapsible admin-section-collapsible">
                    <summary>
                        <span><?php echo __('🎰 Рулетка 0–36'); ?></span>
                        <?php if (!empty($recentJackpotRounds)): ?>
                            <span class="admin-collapsible-badge"><?php echo count($recentJackpotRounds); ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="admin-collapsible-body">
                    <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">
                        <?php echo __('Виджет OBS:'); ?>
                        <a href="../widget/jackpot.html" target="_blank" rel="noopener" style="color: var(--accent);">
                            widget/jackpot.html
                        </a>
                        · <?php echo sprintf(__('Окно: %s сек'), JACKPOT_DURATION_SECONDS); ?>
                        · <?php echo sprintf(__('Мин. ставка: %s мин · x36'), (int)(JACKPOT_MIN_BET_SECONDS / 60)); ?>
                    </p>
                    <div class="admin-actions" style="margin-bottom: 1rem;">
                        <button class="btn btn-primary" onclick="startJackpot()">
                            <?php echo __('🎰 Запустить рулетку'); ?>
                        </button>
                    </div>
                    <?php if (empty($recentJackpotRounds)): ?>
                        <p style="color: var(--text-secondary); margin: 0;"><?php echo __('Раундов джекпота пока нет'); ?></p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo __('Банк'); ?></th>
                                        <th><?php echo __('Игроков'); ?></th>
                                        <th><?php echo __('Число'); ?></th>
                                        <th><?php echo __('Выплата'); ?></th>
                                        <th><?php echo __('Статус'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentJackpotRounds as $round): ?>
                                        <tr>
                                            <td><?php echo (int)$round['id']; ?></td>
                                            <td><?php echo formatSecondsShort((int)$round['total_seconds']); ?></td>
                                            <td><?php echo (int)$round['participants']; ?></td>
                                            <td>
                                                <?php echo isset($round['winning_number'])
                                                    ? (int)$round['winning_number']
                                                    : '—'; ?>
                                            </td>
                                            <td>
                                                <?php echo (int)$round['winner_seconds'] > 0
                                                    ? formatSecondsShort((int)$round['winner_seconds'])
                                                    : '—'; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($round['status']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    </div>
                </details>

                <details class="admin-collapsible admin-section-collapsible">
                    <summary>
                        <span><?php echo __('🎁 Розыгрыш (кодовое слово)'); ?></span>
                    </summary>
                    <div class="admin-collapsible-body">
                    <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">
                        <?php echo __('Панель управления:'); ?>
                        <a href="../widget/raffle.html?control=1" target="_blank" rel="noopener" style="color: var(--accent);">
                            widget/raffle.html?control=1
                        </a>
                        · <?php echo __('OBS:'); ?>
                        <a href="../widget/raffle.html" target="_blank" rel="noopener" style="color: var(--accent);">
                            widget/raffle.html
                        </a>
                        · <?php echo __('Лимит на ответ задаёт стример — в панели видно, сколько победитель уже ждёт / за сколько ответил.'); ?>
                    </p>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">
                        <?php echo __('Чат:'); ?> <code>!розыгрыш &lt;слово&gt;</code>,
                        <code>!розыгрыш крутить</code>, <code>!розыгрыш перевыбрать</code>,
                        <code>!розыгрыш стоп</code>
                    </p>
                    <div class="admin-actions" style="margin-bottom: 1rem;">
                        <a class="btn btn-primary" href="../widget/raffle.html?control=1" target="_blank" rel="noopener">
                            <?php echo __('🎁 Открыть панель розыгрыша'); ?>
                        </a>
                    </div>
                    </div>
                </details>

                <details class="admin-collapsible admin-section-collapsible">
                    <summary>
                        <span><?php echo __('📊 Список пользователей'); ?></span>
                        <?php if (!empty($allUsers)): ?>
                            <span class="admin-collapsible-badge"><?php echo count($allUsers); ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="admin-collapsible-body">
                    <div class="admin-actions" style="margin-bottom: 1rem;">
                        <button class="btn-danger" onclick="resetAllData()">
                            <?php echo __('🗑️ Обнулить всю таблицу'); ?>
                        </button>
                    </div>
                
                <?php if (empty($allUsers)): ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 1rem 0; margin: 0;">
                        <?php echo __('Пользователей пока нет'); ?>
                    </p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo __('Twitch'); ?></th>
                                    <th><?php echo __('Время'); ?></th>
                                    <th><?php echo __('Сообщений'); ?></th>
                                    <th><?php echo __('Статус'); ?></th>
                                    <th><?php echo __('Последняя активность'); ?></th>
                                    <th><?php echo __('Действия'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $index => $trackedUser): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <strong style="color: var(--text-primary);">
                                                <?php echo htmlspecialchars($trackedUser['username']); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php 
                                            echo $trackedUser['hours'] . __('ч ') . 
                                                 $trackedUser['minutes'] . __('м ') . 
                                                 $trackedUser['seconds'] . __('с');
                                            ?>
                                        </td>
                                        <td><?php echo number_format($trackedUser['messages_count'], 0, ',', ' '); ?></td>
                                        <td>
                                            <span class="<?php echo $trackedUser['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $trackedUser['is_active'] ? __('🟢 Активен') : __('⚪ Неактивен'); ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.875rem;">
                                            <?php 
                                            if ($trackedUser['last_activity']) {
                                                $lastActivity = new DateTime($trackedUser['last_activity']);
                                                echo $lastActivity->format('d.m.Y H:i');
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn-delete" 
                                                    onclick="deleteUser('<?php echo htmlspecialchars($trackedUser['username'], ENT_QUOTES); ?>')"
                                                    title="<?php echo __('Удалить пользователя'); ?>">
                                                🗑️
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                    </div>
                </details>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p id="footerDateTime"></p>
            <p id="footerUsersCount" style="margin-top: 0.5rem;"><?php echo sprintf(__('Пользователей на сайте: %s'), getUsersCount()); ?></p>
        </div>
    </footer>

    <script src="../js/main.js"></script>
    <script>
        function toggleCaseIssued(checkbox) {
            const label = checkbox.closest('.case-issue-label');
            const textEl = label.querySelector('.case-issue-text');
            const purchaseId = checkbox.dataset.id;
            const issued = checkbox.checked ? 1 : 0;
            const prevChecked = !checkbox.checked;
            label.classList.add('is-loading');
            const formData = new FormData();
            formData.append('id', purchaseId);
            formData.append('issued', String(issued));
            fetch('../api/case_issue.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                label.classList.remove('is-loading');
                if (!data.success) {
                    checkbox.checked = prevChecked;
                    alert(<?php echo json_encode(__('Ошибка')); ?> + ': ' + (data.error || ''));
                    return;
                }
                if (checkbox.checked) {
                    label.classList.remove('is-pending');
                    label.classList.add('is-issued');
                    textEl.textContent = <?php echo json_encode(__('Выдано')); ?>;
                } else {
                    label.classList.remove('is-issued');
                    label.classList.add('is-pending');
                    textEl.textContent = <?php echo json_encode(__('Выдать в игре')); ?>;
                }
            })
            .catch(() => {
                label.classList.remove('is-loading');
                checkbox.checked = prevChecked;
            });
        }

        function startJackpot() {
            fetch('../api/jackpot_start.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(<?php echo json_encode(sprintf(__('Джекпот запущен на %s секунд.'), JACKPOT_DURATION_SECONDS)); ?>);
                } else {
                    alert(<?php echo json_encode(__('Ошибка')); ?> + ': ' + (data.error || <?php echo json_encode(__('Неизвестная ошибка')); ?>));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(<?php echo json_encode(__('Ошибка запуска джекпота')); ?>);
            });
        }

        function startCaseSale() {
            fetch('../api/case_start.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(<?php echo json_encode(sprintf(__('Продажа запущена на %s секунд. Доступен 1 кейс. Баннер появится в OBS.'), CASE_SALE_DURATION_SECONDS)); ?>);
                } else {
                    alert(<?php echo json_encode(__('Ошибка')); ?> + ': ' + (data.error || <?php echo json_encode(__('Неизвестная ошибка')); ?>));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(<?php echo json_encode(__('Ошибка запуска продажи')); ?>);
            });
        }

        function deleteUser(username) {
            const deleteConfirm = <?php echo json_encode(__('Вы уверены, что хотите удалить пользователя "{username}"?\n\nЭто действие удалит все данные пользователя: время, сообщения, активные сессии.')); ?>;
            if (!confirm(deleteConfirm.replace('{username}', username))) {
                return;
            }
            
            const formData = new FormData();
            formData.append('username', username);
            
            fetch('delete_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const successMessage = <?php echo json_encode(__('Пользователь "{username}" успешно удален')); ?>;
                    alert(successMessage.replace('{username}', username));
                    location.reload();
                } else {
                    alert(<?php echo json_encode(__('Ошибка')); ?> + ': ' + (data.error || <?php echo json_encode(__('Неизвестная ошибка')); ?>));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(<?php echo json_encode(__('Ошибка при удалении пользователя')); ?>);
            });
        }
        
        function resetAllData() {
            if (!confirm(<?php echo json_encode(__('⚠️ ВНИМАНИЕ! ⚠️\n\nВы уверены, что хотите обнулить ВСЮ таблицу?\n\nЭто действие удалит:\n- Всех пользователей\n- Все время\n- Все сообщения\n- Все активные сессии\n\nЭто действие НЕОБРАТИМО!')); ?>)) {
                return;
            }
            
            // Двойное подтверждение
            if (!confirm(<?php echo json_encode(__('Последний шанс! Вы действительно хотите удалить ВСЕ данные?\n\nВведите "yes" в следующем окне для подтверждения.')); ?>)) {
                return;
            }
            
            const confirmText = prompt(<?php echo json_encode(__('Для подтверждения введите "yes":')); ?>);
            if (confirmText !== 'yes') {
                alert(<?php echo json_encode(__('Отменено. Данные не были удалены.')); ?>);
                return;
            }
            
            const formData = new FormData();
            formData.append('confirm', 'yes');
            
            fetch('reset_all.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(<?php echo json_encode(__('Все данные успешно обнулены')); ?>);
                    location.reload();
                } else {
                    alert(<?php echo json_encode(__('Ошибка')); ?> + ': ' + (data.error || <?php echo json_encode(__('Неизвестная ошибка')); ?>));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(<?php echo json_encode(__('Ошибка при обнулении данных')); ?>);
            });
        }
    </script>
</body>
</html>

