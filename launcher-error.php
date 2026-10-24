<?php
require_once __DIR__ . '/config.php';

$token = trim((string)($_GET['token'] ?? ''));
$row = $token !== '' ? getClientErrorByToken($token) : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $row ? __('Ошибка лаунчера') : __('Ошибка не найдена'); ?> - GroznyHub</title>
<?php renderSiteFaviconTags(); ?>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .error-card {
            max-width: 900px;
            margin: 3rem auto;
            padding: 2rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .error-meta { color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem; }
        .error-message {
            white-space: pre-wrap;
            word-break: break-word;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .error-log {
            max-height: 420px;
            overflow: auto;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .error-level-error { color: #ff6b6b; }
        .error-level-warning { color: #ffd166; }
        h2 { font-size: 1rem; margin: 1.5rem 0 0.5rem; color: var(--text-secondary); }
    </style>
</head>
<body>
    <div class="error-card">
        <?php if (!$row): ?>
            <h1><?php echo __('Ошибка не найдена'); ?></h1>
            <p class="error-meta"><?php echo __('Ссылка недействительна или устарела.'); ?></p>
        <?php else: ?>
            <h1><?php echo __('Ошибка лаунчера'); ?> #<?php echo (int)$row['id']; ?></h1>
            <p class="error-meta">
                <?php echo htmlspecialchars((string)$row['created_at']); ?>
                · <?php echo htmlspecialchars((string)$row['source']); ?>
                · <span class="error-level-<?php echo htmlspecialchars((string)$row['level']); ?>"><?php echo htmlspecialchars((string)$row['level']); ?></span>
                <?php if (!empty($row['client_version'])): ?>
                    · v<?php echo htmlspecialchars((string)$row['client_version']); ?>
                <?php endif; ?>
                <?php if (!empty($row['username'])): ?>
                    · <?php echo htmlspecialchars((string)$row['username']); ?>
                <?php endif; ?>
            </p>
            <h2><?php echo __('Сообщение'); ?></h2>
            <div class="error-message"><?php echo htmlspecialchars((string)$row['message']); ?></div>
            <?php if (!empty($row['stack_trace'])): ?>
                <h2><?php echo __('Лог игры'); ?></h2>
                <div class="error-message error-log"><?php echo htmlspecialchars((string)$row['stack_trace']); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
