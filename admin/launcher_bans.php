<?php
require_once '../config.php';
require_once '../includes/launcher_bans.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../index.php');
    exit;
}

if (!isSiteAdmin($user)) {
    header('Location: ../index.php?error=access_denied');
    exit;
}

$db = getDB();
$bans = launcherListActiveBans($db);
$flash = null;
$error = null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Баны лаунчера'); ?> - Admin</title>
<?php renderSiteFaviconTags('..'); ?>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-container { max-width: 1100px; margin: 0 auto; padding: 2rem 20px; }
        .panel {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-grid .full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 0.35rem; color: var(--text-secondary); font-size: 0.875rem; }
        input, textarea {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        textarea { min-height: 80px; resize: vertical; }
        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th, .users-table td {
            padding: 0.75rem 0.85rem;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }
        .users-table th { color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase; }
        .badge-banned {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: rgba(255, 107, 107, 0.15);
            color: #ff8a8a;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .hint { color: var(--text-secondary); font-size: 0.875rem; line-height: 1.5; }
        @media (max-width: 760px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="admin-container">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap;">
            <h1 style="color:var(--accent);margin:0;"><?php echo __('Баны лаунчера / сервера'); ?></h1>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <a href="index.php" class="btn btn-secondary"><?php echo __('Админ-панель'); ?></a>
                <a href="launcher_errors.php" class="btn btn-secondary"><?php echo __('Ошибки лаунчера'); ?></a>
                <a href="../index.php" class="btn btn-secondary"><?php echo __('На сайт'); ?></a>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;"><?php echo __('Заблокировать игрока'); ?></h2>
            <p class="hint" style="margin-top:0;">
                Блокировка идёт по <strong>Twitch ID</strong>. Игрок не сможет запустить игру через лаунчер и будет удалён из whitelist Paper-сервера после синхронизации.
            </p>
            <form id="ban-form" class="form-grid">
                <div>
                    <label for="ban-username">Twitch-ник на GroznyHub</label>
                    <input id="ban-username" name="username" placeholder="andergr0ynd121" required>
                </div>
                <div>
                    <label for="ban-expires">Разбан (необязательно)</label>
                    <input id="ban-expires" name="expires_at" type="datetime-local">
                </div>
                <div class="full">
                    <label for="ban-reason">Причина</label>
                    <textarea id="ban-reason" name="reason" placeholder="Griefing, читы, токсичность..."></textarea>
                </div>
                <div class="full">
                    <button type="submit" class="btn btn-danger"><?php echo __('Заблокировать'); ?></button>
                </div>
            </form>
            <p id="ban-feedback" class="hint" style="margin-bottom:0;"></p>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;"><?php echo __('Активные баны'); ?> (<?php echo count($bans); ?>)</h2>
            <?php if (!$bans): ?>
                <p class="hint" style="margin:0;"><?php echo __('Активных банов нет.'); ?></p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Twitch</th>
                                <th>Twitch ID</th>
                                <th>Причина</th>
                                <th>Когда</th>
                                <th>До</th>
                                <th>Кем</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bans as $ban): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars((string)$ban['username']); ?></strong>
                                        <div><span class="badge-banned">BANNED</span></div>
                                    </td>
                                    <td style="font-size:0.8rem;"><?php echo htmlspecialchars((string)$ban['twitch_id']); ?></td>
                                    <td><?php echo htmlspecialchars((string)($ban['reason'] ?: '—')); ?></td>
                                    <td><?php echo htmlspecialchars((string)$ban['banned_at']); ?></td>
                                    <td><?php echo htmlspecialchars((string)($ban['expires_at'] ?: '∞')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($ban['banned_by'] ?: '—')); ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-unban"
                                            data-twitch-id="<?php echo htmlspecialchars((string)$ban['twitch_id'], ENT_QUOTES); ?>"
                                            data-username="<?php echo htmlspecialchars((string)$ban['username'], ENT_QUOTES); ?>"
                                        >Разбанить</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const banForm = document.getElementById('ban-form');
        const banFeedback = document.getElementById('ban-feedback');

        banForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            banFeedback.textContent = 'Сохраняю...';

            const formData = new FormData(banForm);
            formData.append('action', 'ban');

            try {
                const response = await fetch('launcher_ban_action.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Ошибка');
                }
                banFeedback.textContent = `Заблокирован: ${data.username}`;
                setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                banFeedback.textContent = error.message || 'Ошибка';
            }
        });

        document.querySelectorAll('.btn-unban').forEach((button) => {
            button.addEventListener('click', async () => {
                const username = button.dataset.username;
                if (!confirm(`Разбанить ${username}?`)) {
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'unban');
                formData.append('twitch_id', button.dataset.twitchId || '');
                formData.append('username', username);

                try {
                    const response = await fetch('launcher_ban_action.php', {
                        method: 'POST',
                        body: formData,
                    });
                    const data = await response.json();
                    if (!data.success) {
                        throw new Error(data.error || 'Ошибка');
                    }
                    window.location.reload();
                } catch (error) {
                    alert(error.message || 'Ошибка');
                }
            });
        });
    </script>
</body>
</html>
