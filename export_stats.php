<?php
require_once 'config.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: index.php');
    exit;
}

$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'csv';
$rows = buildUserStatsExportRows($user['username'], $user['display_name'] ?? null);
$filenameBase = 'mark-grozny-' . normalizeTwitchUsername($user['username']) . '-' . date('Y-m-d');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
    header('Cache-Control: no-store');

    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

if ($format === 'pdf') {
    $exportedAt = (new DateTime('now', new DateTimeZone(DAILY_BONUS_TIMEZONE)))->format('d.m.Y H:i');
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(__('Статистика')); ?> @<?php echo htmlspecialchars($user['username']); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            color: #111;
            background: #fff;
            padding: 32px;
            line-height: 1.5;
        }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .sub { color: #555; margin-bottom: 24px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #ccc; padding: 10px 12px; text-align: left; font-size: 14px; }
        th { background: #f3f3f3; width: 42%; }
        .toolbar { margin-bottom: 20px; }
        .toolbar button, .toolbar a {
            display: inline-block;
            padding: 10px 16px;
            margin-right: 8px;
            border: 1px solid #333;
            background: #fff;
            color: #111;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }
        .footer-note { font-size: 12px; color: #666; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()"><?php echo __('🖨 Печать / сохранить PDF'); ?></button>
        <a href="export_stats.php?format=csv"><?php echo __('Скачать CSV'); ?></a>
        <a href="index.php"><?php echo __('На сайт'); ?></a>
    </div>

    <h1><?php echo __('Статистика рефки'); ?></h1>
    <p class="sub">@<?php echo htmlspecialchars($user['username']); ?> · <?php echo htmlspecialchars($exportedAt); ?> (MSK)</p>

    <table>
        <tbody>
            <?php foreach (array_slice($rows, 1) as $row): ?>
                <tr>
                    <th><?php echo htmlspecialchars($row[0]); ?></th>
                    <td><?php echo htmlspecialchars($row[1]); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="footer-note">Mark Grozny · <?php echo htmlspecialchars(siteUrl()); ?></p>

    <script>
        if (window.location.search.indexOf('print=1') !== -1) {
            window.addEventListener('load', function () { window.print(); });
        }
    </script>
</body>
</html>
    <?php
    exit;
}

http_response_code(400);
echo __('Неизвестный формат. Используйте format=csv или format=pdf');
