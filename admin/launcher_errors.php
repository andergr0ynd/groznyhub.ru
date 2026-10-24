<?php
require_once '../config.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../index.php');
    exit;
}

if (!isSiteAdmin($user)) {
    header('Location: ../index.php?error=access_denied');
    exit;
}

$selectedId = (int)($_GET['id'] ?? 0);
$selected = $selectedId > 0 ? getClientErrorById($selectedId) : null;
$items = getClientErrorLogs(['source' => 'launcher'], 100, 0);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Ошибки лаунчера'); ?> - Admin</title>
<?php renderSiteFaviconTags('..'); ?>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 2rem 20px; }
        .errors-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 1.5rem; }
        .panel { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; }
        .error-item { display: block; padding: 0.85rem 1rem; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 0.75rem; color: inherit; text-decoration: none; }
        .error-item.active, .error-item:hover { border-color: var(--accent); background: rgba(145, 70, 255, 0.08); }
        .error-item small { display: block; color: var(--text-secondary); margin-top: 0.35rem; }
        pre { white-space: pre-wrap; word-break: break-word; background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 10px; overflow: auto; }
        @media (max-width: 900px) { .errors-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="admin-container">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap;">
            <h1 style="color:var(--accent);margin:0;"><?php echo __('Ошибки лаунчера'); ?></h1>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <a href="index.php" class="btn btn-secondary"><?php echo __('Админ-панель'); ?></a>
                <a href="../index.php" class="btn btn-secondary"><?php echo __('На сайт'); ?></a>
            </div>
        </div>

        <div class="errors-grid">
            <div class="panel">
                <h2 style="margin-top:0;"><?php echo __('Последние'); ?></h2>
                <?php if (!$items): ?>
                    <p style="color:var(--text-secondary);"><?php echo __('Пока нет ошибок.'); ?></p>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <a class="error-item<?php echo $selectedId === (int)$item['id'] ? ' active' : ''; ?>" href="launcher_errors.php?id=<?php echo (int)$item['id']; ?>">
                            #<?php echo (int)$item['id']; ?> · <?php echo htmlspecialchars((string)$item['level']); ?>
                            <div><?php echo htmlspecialchars(mb_substr((string)$item['message'], 0, 120)); ?></div>
                            <small><?php echo htmlspecialchars((string)$item['created_at']); ?><?php if (!empty($item['username'])): ?> · <?php echo htmlspecialchars((string)$item['username']); ?><?php endif; ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="panel">
                <?php if (!$selected): ?>
                    <p style="color:var(--text-secondary);"><?php echo __('Выбери ошибку слева, чтобы посмотреть детали.'); ?></p>
                <?php else: ?>
                    <h2 style="margin-top:0;">#<?php echo (int)$selected['id']; ?></h2>
                    <p style="color:var(--text-secondary);">
                        <?php echo htmlspecialchars((string)$selected['created_at']); ?>
                        · <?php echo htmlspecialchars((string)$selected['level']); ?>
                        · v<?php echo htmlspecialchars((string)($selected['client_version'] ?? '—')); ?>
                    </p>
                    <p><strong><?php echo __('Пользователь:'); ?></strong> <?php echo htmlspecialchars((string)($selected['username'] ?: '—')); ?></p>
                    <p><strong><?php echo __('Публичная ссылка:'); ?></strong>
                        <a href="<?php echo htmlspecialchars(clientErrorPublicUrl((string)$selected['public_token'])); ?>" target="_blank" rel="noopener">
                            <?php echo htmlspecialchars(clientErrorPublicUrl((string)$selected['public_token'])); ?>
                        </a>
                    </p>
                    <h3><?php echo __('Сообщение'); ?></h3>
                    <pre><?php echo htmlspecialchars((string)$selected['message']); ?></pre>
                    <?php if (!empty($selected['stack_trace'])): ?>
                        <h3><?php echo __('Stack trace'); ?></h3>
                        <pre><?php echo htmlspecialchars((string)$selected['stack_trace']); ?></pre>
                    <?php endif; ?>
                    <?php $meta = clientErrorFormatMeta($selected['meta_json'] ?? null); if ($meta): ?>
                        <h3><?php echo __('Meta'); ?></h3>
                        <pre><?php echo htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
