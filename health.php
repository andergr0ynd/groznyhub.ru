<?php
/**
 * Проверка деплоя после рефакторинга. Удалите файл после проверки.
 * Откройте health.php на том хосте, где запущен сайт.
 */
header('Content-Type: text/plain; charset=utf-8');

$checks = [];

$includesDir = __DIR__ . '/includes';
$requiredFiles = [
    'load_env.php',
    'game_config.php',
    'db.php',
    'helpers.php',
    'bot_ledger.php',
    'jackpot.php',
    'cases.php',
    'site_settings.php',
    'containers.php',
    'raffle.php',
];

foreach ($requiredFiles as $file) {
    $path = $includesDir . '/' . $file;
    $checks[] = [is_file($path), 'Файл includes/' . $file];
}

$config = __DIR__ . '/config.php';
$configText = is_file($config) ? file_get_contents($config) : '';
$checks[] = [strpos($configText, "require_once __DIR__ . '/includes/cases.php'") !== false, 'config.php подключает includes/cases.php'];
$checks[] = [strpos($configText, 'function getCasePurchaseTickerItems') === false, 'config.php без дубля getCasePurchaseTickerItems'];
$checks[] = [strpos($configText, 'inlined includes') === false, 'config.php без inline-блока (только require_once)'];
$checks[] = [is_file($includesDir . '/.htaccess'), 'includes/.htaccess блокирует прямой доступ'];

$casesPath = $includesDir . '/cases.php';
$casesHead = is_file($casesPath) ? file_get_contents($casesPath, false, null, 0, 10) : '';
$checks[] = [str_starts_with($casesHead, '<?php'), 'includes/cases.php начинается с <?php'];

try {
    require_once $config;
    $checks[] = [function_exists('getDB'), 'getDB()'];
    $checks[] = [function_exists('getBotTimeLedgerSummary'), 'getBotTimeLedgerSummary() из bot_ledger.php'];
    $checks[] = [function_exists('getCasePurchaseTickerItems'), 'getCasePurchaseTickerItems() из cases.php'];
    $checks[] = [function_exists('getContainerDuelForWidget'), 'getContainerDuelForWidget() из containers.php'];
    $checks[] = [defined('CASE_COST_SECONDS'), 'CASE_COST_SECONDS = ' . (defined('CASE_COST_SECONDS') ? CASE_COST_SECONDS : '?')];
} catch (Throwable $e) {
    echo "ОШИБКА при загрузке config.php:\n";
    echo $e->getMessage() . "\n\n";
}

$failed = 0;
foreach ($checks as [$ok, $label]) {
    echo ($ok ? '[OK]  ' : '[FAIL]') . ' ' . $label . "\n";
    if (!$ok) {
        $failed++;
    }
}

echo "\n";
if ($failed === 0) {
    echo "Всё в порядке — можно удалить health.php\n";
} else {
    echo "Исправьте отмеченные FAIL и обновите файлы с компьютера (папка Websait целиком).\n";
    echo "НЕ вставляйте код includes в config.php вручную — только отдельные файлы в includes/.\n";
}
