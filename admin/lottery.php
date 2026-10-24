<?php
require_once __DIR__ . '/../config.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../index.php');
    exit;
}
if (!isset($user['admin']) || (int)$user['admin'] !== 1) {
    header('Location: ../index.php?error=access_denied');
    exit;
}

ensureLotteryTables();
$stages = getLotteryStageDefinitions();
$activeStage = isset($_GET['stage']) ? (int)$_GET['stage'] : 1;
if (!isset($stages[$activeStage])) {
    $activeStage = 1;
}

$flash = null;
$flashError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'seed') {
        if (seedLotteryTestData(true)) {
            $flash = 'Тестовые призы и бомбы загружены.';
        } else {
            $flashError = 'Не удалось загрузить тестовые данные.';
        }
    } elseif ($action === 'save') {
        $saveStage = (int)($_POST['stage'] ?? 1);
        if (!isset($stages[$saveStage])) {
            $flashError = 'Неверный этап.';
        } else {
            $cells = [];
            $count = (int)$stages[$saveStage]['count'];
            for ($i = 1; $i <= $count; $i++) {
                $type = (string)($_POST['cell_type'][$i] ?? 'empty');
                $cells[$i] = [
                    'cell_type' => $type,
                    'prize_title' => (string)($_POST['prize_title'][$i] ?? ''),
                    'prize_icon' => (string)($_POST['prize_icon'][$i] ?? ''),
                ];
            }
            if (saveLotteryStageCells($saveStage, $cells)) {
                $flash = sprintf('Этап %d сохранён.', $saveStage);
                $activeStage = $saveStage;
            } else {
                $flashError = 'Ошибка сохранения.';
            }
        }
    }
}

$cells = getLotteryStageCells($activeStage);
$meta = $stages[$activeStage];
$cols = (int)$meta['cols'];

function adminLotteryTicketFaceHtml(array $cell): string
{
    $type = (string)($cell['cell_type'] ?? 'empty');
    if ($type === 'bomb') {
        return '<div class="ticket-face bomb admin-face"><span class="bomb-icon">💣</span></div>';
    }
    if ($type === 'prize') {
        $title = trim((string)($cell['prize_title'] ?? ''));
        $icon = trim((string)($cell['prize_icon'] ?? ''));
        $url = $icon !== '' ? resolveLotteryPrizeIconUrl($icon) : null;
        $img = $url ? '<img class="prize-img" src="' . htmlspecialchars($url) . '" alt="">' : '';
        return '<div class="ticket-face prize admin-face">'
            . $img
            . '<div class="prize-title">' . htmlspecialchars($title !== '' ? $title : 'Приз') . '</div></div>';
    }
    if ($type === 'empty') {
        return '<div class="ticket-face empty admin-face"><span class="empty-icon">Пусто</span></div>';
    }

    return '<div class="ticket-closed"><span class="q">?</span></div>';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Лотерея-гонки — админка'); ?></title>
<?php renderSiteFaviconTags('..'); ?>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../lotereya/lottery-play.css">
    <link rel="stylesheet" href="lottery-board.css">
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
            margin-bottom: 1.5rem;
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
            max-width: 640px;
            margin: 0 auto;
        }

        .admin-content {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .admin-content h2 {
            margin: 0 0 0.35rem;
            font-size: 1.15rem;
            color: var(--text-primary);
        }

        .admin-content--board {
            padding: 1.25rem 1rem 1.5rem;
        }

        .admin-content--board h2 {
            text-align: center;
        }

        .admin-actions-row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            margin-top: 0.5rem;
        }

        .stage-setup-lead {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.82rem;
            margin-bottom: 0.75rem;
            line-height: 1.45;
        }

        .flash {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }

        .flash.ok {
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #86efac;
        }

        .flash.err {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
        }

        .admin-collapsible {
            margin-top: 0;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background-color: var(--bg-secondary);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .admin-collapsible summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            cursor: pointer;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1rem;
            list-style: none;
        }

        .admin-collapsible summary::-webkit-details-marker { display: none; }

        .admin-collapsible summary::after {
            content: '▾';
            color: var(--text-secondary);
            font-size: 0.85rem;
            transition: transform 0.2s ease;
        }

        .admin-collapsible[open] summary::after { transform: rotate(180deg); }

        .admin-collapsible-body {
            padding: 0 1.25rem 1.25rem;
            border-top: 1px solid var(--border);
        }

        .wiki-cache-status {
            font-size: 0.88rem;
            color: #86efac;
            margin: 1rem 0 0.75rem;
            padding: 0.65rem 0.85rem;
            background: var(--bg-primary);
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .wiki-cache-status.is-empty { color: #fca5a5; }

        .wiki-bundle-status {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.75rem;
        }
        .wiki-bundle-status.is-warn { color: #fcd34d; }

        .wiki-bundle-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 0.75rem 0;
        }

        .wiki-streamer-panel {
            padding-top: 0.25rem;
        }

        .wiki-advanced-panel {
            margin-top: 1rem;
            border: 1px dashed var(--border);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.15);
        }

        .wiki-advanced-panel summary {
            cursor: pointer;
            padding: 0.65rem 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            list-style: none;
            user-select: none;
        }

        .wiki-advanced-panel summary::-webkit-details-marker { display: none; }

        .wiki-advanced-panel summary::after {
            content: ' ▾';
            float: right;
            opacity: 0.6;
        }

        .wiki-advanced-panel[open] summary::after {
            content: ' ▴';
        }

        .wiki-advanced-body {
            padding: 0 1rem 1rem;
            border-top: 1px solid var(--border);
        }

        .wiki-import-steps {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.55;
            margin: 0 0 1rem;
        }

        .wiki-import-steps ol { margin: 0.5rem 0 0 1.1rem; padding: 0; }
        .wiki-import-steps li { margin-bottom: 0.4rem; }

        .wiki-import-box textarea {
            width: 100%;
            min-height: 140px;
            margin: 0.75rem 0;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: ui-monospace, monospace;
            font-size: 0.78rem;
            resize: vertical;
        }

        .wiki-import-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: stretch;
        }

        @media (min-width: 560px) {
            .wiki-import-actions.is-row {
                flex-direction: row;
                flex-wrap: wrap;
                align-items: center;
            }
        }

        .wiki-import-result {
            font-size: 0.85rem;
            margin-top: 0.75rem;
            color: var(--text-secondary);
        }

        .wiki-import-result.is-ok { color: #86efac; }
        .wiki-import-result.is-error { color: #fca5a5; }

        .wiki-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.72);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .wiki-modal[hidden] { display: none; }
        body.wiki-modal-open { overflow: hidden; }

        .wiki-modal-panel {
            width: min(920px, 100%);
            max-height: min(85vh, 760px);
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.45);
        }

        .wiki-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
        }

        .wiki-modal-head h3 {
            margin: 0;
            font-size: 1.05rem;
            color: var(--accent);
        }

        .wiki-modal-tools {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--border);
        }

        .wiki-modal-tools input {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .wiki-modal-body {
            padding: 1rem 1.25rem;
            overflow: auto;
            flex: 1;
        }

        .wiki-skin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
        }

        .wiki-skin-item {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-primary);
            padding: 8px;
            cursor: pointer;
            color: var(--text-secondary);
            text-align: center;
        }

        .wiki-skin-item:hover { border-color: var(--accent); }

        .wiki-skin-item img {
            width: 100%;
            height: 56px;
            object-fit: contain;
            display: block;
            margin-bottom: 6px;
        }

        .wiki-skin-item .wiki-skin-case {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 56px;
            font-size: 1.75rem;
            margin-bottom: 6px;
        }

        .wiki-skin-item span {
            display: block;
            font-size: 0.7rem;
            line-height: 1.25;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .wiki-skin-item .wiki-skin-cat {
            font-size: 0.58rem;
            color: var(--accent);
            opacity: 0.85;
            margin-bottom: 2px;
        }

        .wiki-modal-tools select {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.85rem;
        }

        .wiki-picker-status {
            font-size: 0.82rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .wiki-picker-empty {
            color: var(--text-secondary);
            font-size: 0.85rem;
            grid-column: 1 / -1;
        }

        @media (max-width: 640px) {
            .admin-header h1 { font-size: 1.5rem; }
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
            <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                <a href="index.php" class="btn btn-secondary" style="padding:0.5rem 1rem;font-size:0.875rem;"><?php echo __('Админка'); ?></a>
                <a href="../lotereya/index.html" class="btn btn-secondary" style="padding:0.5rem 1rem;font-size:0.875rem;" target="_blank" rel="noopener"><?php echo __('Открыть лотерею'); ?></a>
                <a href="../logout.php" class="btn btn-secondary" style="padding:0.5rem 1rem;font-size:0.875rem;"><?php echo __('Выйти'); ?></a>
            </div>
        </div>
    </div>
</header>

<main>
    <div class="admin-container fade-in">
        <div class="admin-header">
            <h1><?php echo __('🎟 Лотерея-гонки'); ?></h1>
            <p><?php echo __('Расставь призы и бомбы по билетам. Игра останавливается при 3 одинаковых призах или при бомбе на этапах 2–3.'); ?></p>
        </div>

        <?php if ($flash): ?>
            <div class="flash ok"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="flash err"><?php echo htmlspecialchars($flashError); ?></div>
        <?php endif; ?>

        <div class="admin-content admin-content--board">
            <h2><?php echo __('Настройка этапа'); ?></h2>
            <p class="stage-setup-lead">
                <?php echo __('Клик по билету на поле — редактирование. Вид как в игре.'); ?>
            </p>

            <form method="post" id="lotteryStageForm" data-stage="<?php echo (int)$activeStage; ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="stage" value="<?php echo (int)$activeStage; ?>">

                <div class="lottery-board-wrap">
                    <div class="lottery-board-scale stage-<?php echo (int)$activeStage; ?>">
                        <section class="lottery stage-<?php echo (int)$activeStage; ?>" aria-label="<?php echo __('Поле лотереи'); ?>">
                            <div class="lottery-bg" aria-hidden="true"></div>
                            <div class="lottery-inner">
                                <div class="brand">
                                    <?php if (file_exists(__DIR__ . '/../icons/logo.png')): ?>
                                        <img src="../icons/logo.png" alt="">
                                    <?php endif; ?>
                                    Mark Grozny
                                </div>
                                <h3 class="title">ЛОТЕРЕЯ-ГОНКИ</h3>
                                <div class="badge-wrap"><span class="badge">Бесплатно</span></div>
                                <nav class="stage-nav" aria-label="<?php echo __('Этапы'); ?>">
                                    <?php foreach ($stages as $id => $st):
                                        $stageNavLabel = (int)$id === 1 ? 'Этап 1 · 3×3' : ((int)$id === 2 ? 'Этап 2 · 4×4' : ((int)$id === 3 ? 'Этап 3 · 5×5' : $st['label']));
                                    ?>
                                        <a href="?stage=<?php echo (int)$id; ?>"
                                           class="stage-btn <?php echo $id === $activeStage ? 'active' : ''; ?>">
                                            <?php echo htmlspecialchars($stageNavLabel); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </nav>
                                <p class="stage-title"><?php echo htmlspecialchars($meta['label']); ?></p>
                                <div class="grid-wrap">
                                    <div class="grid cols-<?php echo $cols; ?>">
                                        <?php foreach ($cells as $cell):
                                            $idx = (int)$cell['cell_index'];
                                            $type = (string)$cell['cell_type'];
                                        ?>
                                        <div class="cell lottery-cell" data-index="<?php echo $idx; ?>">
                                            <span class="label"><?php echo $idx; ?>-БИЛЕТ</span>
                                            <button type="button" class="ticket admin-ticket" aria-label="<?php echo sprintf(__('Билет %d'), $idx); ?>">
                                                <?php echo adminLotteryTicketFaceHtml($cell); ?>
                                            </button>
                                            <div class="cell-fields">
                                                <div class="field-group">
                                                    <label for="cell_type_<?php echo $idx; ?>"><?php echo __('Тип'); ?></label>
                                                    <select name="cell_type[<?php echo $idx; ?>]" id="cell_type_<?php echo $idx; ?>" class="cell-type-select">
                                                        <option value="empty" <?php echo $type === 'empty' ? 'selected' : ''; ?>><?php echo __('Пусто'); ?></option>
                                                        <option value="prize" <?php echo $type === 'prize' ? 'selected' : ''; ?>><?php echo __('Приз'); ?></option>
                                                        <?php if (!empty($meta['bombs'])): ?>
                                                        <option value="bomb" <?php echo $type === 'bomb' ? 'selected' : ''; ?>><?php echo __('Бомба'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="field-group">
                                                    <label for="prize_title_<?php echo $idx; ?>"><?php echo __('Название'); ?></label>
                                                    <input type="text" name="prize_title[<?php echo $idx; ?>]" id="prize_title_<?php echo $idx; ?>"
                                                           value="<?php echo htmlspecialchars($cell['prize_title']); ?>"
                                                           placeholder="Matiz">
                                                </div>
                                                <div class="field-group icon-block">
                                                    <label for="prize_icon_<?php echo $idx; ?>"><?php echo __('Иконка'); ?></label>
                                                    <div class="icon-preview-wrap">
                                                        <img class="icon-preview" alt="" hidden referrerpolicy="no-referrer">
                                                    </div>
                                                    <input type="text" name="prize_icon[<?php echo $idx; ?>]" id="prize_icon_<?php echo $idx; ?>"
                                                           value="<?php echo htmlspecialchars((string)($cell['prize_icon'] ?? '')); ?>"
                                                           placeholder="wiki:Matiz.png">
                                                    <button type="button" class="wiki-pick-btn" data-cell="<?php echo $idx; ?>"><?php echo __('Выбрать приз'); ?></button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <p class="board-hint"><?php echo __('Нажми на билет для настройки'); ?></p>
                            </div>
                        </section>
                    </div>

                    <div class="ticket-editor" id="ticketEditor" hidden>
                        <div class="ticket-editor-head">
                            <strong id="ticketEditorLabel">1-билет</strong>
                        </div>
                        <div class="ticket-editor-fields" id="ticketEditorFields"></div>
                    </div>

                    <div class="board-save-row">
                        <button type="submit" class="btn btn-primary"><?php echo __('Сохранить этап'); ?></button>
                    </div>
                </div>
            </form>
        </div>

        <div class="admin-content admin-content--layout">
            <h2><?php echo __('Раскладка для стримера'); ?></h2>
            <p class="section-lead layout-lead">
                <?php echo __('Куда перемешаны призы и бомбы в текущей игре. Зрители видят только «?» — стирают билеты сами.'); ?>
            </p>
            <div class="layout-toolbar">
                <span id="lotteryLayoutMeta" class="layout-meta"></span>
                <button type="button" id="lotteryLayoutRefresh" class="btn btn-secondary"><?php echo __('Обновить'); ?></button>
                <button type="button" id="lotteryLayoutNewGame" class="btn btn-primary"><?php echo __('Новая игра'); ?></button>
            </div>
            <div id="lotteryLayoutGrid" class="lottery-layout-grid cols-<?php echo (int)$cols; ?>">
                <p class="layout-empty"><?php echo __('Загрузка…'); ?></p>
            </div>
        </div>

        <details class="admin-collapsible" id="wikiCatalogPanel">
            <summary>
                <span><?php echo __('Каталог wiki'); ?></span>
            </summary>
            <div class="admin-collapsible-body wiki-streamer-panel">
                <div id="wikiCacheStatus" class="wiki-cache-status is-empty"><?php echo __('Загрузка…'); ?></div>
                <div class="wiki-bundle-actions">
                    <button type="button" id="wikiLoadSkinsBtn" class="btn btn-primary"><?php echo __('Подгрузить скины'); ?></button>
                    <button type="button" id="wikiLoadCarsBtn" class="btn btn-primary"><?php echo __('Подгрузить машины'); ?></button>
                    <button type="button" id="wikiLoadCasesBtn" class="btn btn-primary"><?php echo __('Подгрузить кейсы'); ?></button>
                </div>
                <div id="wikiImportResult" class="wiki-import-result"></div>

                <details class="wiki-advanced-panel">
                    <summary><?php echo __('Черновые работы — экспорт, ручной импорт'); ?></summary>
                    <div class="wiki-advanced-body">
                        <p class="section-lead" style="margin:0.75rem 0;">
                            <?php echo __('Загрузите '); ?><code>skins.json</code><?php echo __(', '); ?><code>cars.json</code><?php echo __(' и '); ?><code>cases.json</code><?php echo __(' в '); ?><code>json-wiki/</code><?php echo __(' на сервере. Превью — в '); ?><code>data/wiki_thumbs/</code>.
                        </p>
                        <div id="wikiBundleStatus" class="wiki-bundle-status"></div>
                        <div class="wiki-import-box">
                            <p class="wiki-import-steps">
                                <?php echo __('Экспорт с wiki:'); ?>
                                <ol>
                                    <li><?php echo __('Скины → '); ?><code>json-wiki/skins.json</code></li>
                                    <li><?php echo __('Салоны → '); ?><code>json-wiki/cars.json</code></li>
                                    <li><a href="https://wiki.radmir.online/index.php?title=%D0%9A%D0%B5%D0%B9%D1%81%D1%8B" target="_blank" rel="noopener"><?php echo __('Кейсы'); ?></a><?php echo __(' → '); ?><code>json-wiki/cases.json</code></li>
                                </ol>
                                <?php echo __('Откройте '); ?><a href="https://wiki.radmir.online/" target="_blank" rel="noopener">wiki</a><?php echo __(' → F12 → Console → вставьте скрипт.'); ?>
                            </p>
                            <div class="wiki-import-actions is-row">
                                <button type="button" id="wikiCopyExportScript" class="btn btn-secondary"><?php echo __('Скопировать скрипт'); ?></button>
                                <a href="https://wiki.radmir.online/index.php?title=%D0%A1%D0%BA%D0%B8%D0%BD%D1%8B:_%D0%A1%D1%82%D1%80%D0%B0%D0%BD%D0%B8%D1%86%D0%B0_1" class="btn btn-secondary" target="_blank" rel="noopener" style="text-decoration:none;"><?php echo __('Открыть wiki'); ?></a>
                                <label class="btn btn-secondary" style="cursor:pointer;margin:0;text-align:center;">
                                    <?php echo __('Загрузить .json'); ?>
                                    <input type="file" id="wikiImportFile" accept=".json,application/json" hidden>
                                </label>
                                <select id="wikiImportBundle" aria-label="<?php echo __('Группа импорта'); ?>" style="padding:0.45rem 0.65rem;border-radius:6px;border:1px solid var(--border);background:var(--surface);color:inherit;">
                                    <option value="skins"><?php echo __('Скины'); ?></option>
                                    <option value="cars"><?php echo __('Машины'); ?></option>
                                    <option value="cases"><?php echo __('Кейсы'); ?></option>
                                </select>
                            </div>
                            <textarea id="wikiImportJson" placeholder='{"bundle":"skins","skins":[{"file":"1.png","name":"Название","b64":"…"}]}'></textarea>
                            <div class="wiki-import-actions is-row">
                                <button type="button" id="wikiImportBtn" class="btn btn-secondary"><?php echo __('Импорт вручную (добавить)'); ?></button>
                                <button type="button" id="wikiImportReplaceBtn" class="btn btn-secondary"><?php echo __('Заменить группу'); ?></button>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        </details>

        <div class="admin-content">
            <h2><?php echo __('Тестовые данные'); ?></h2>
            <p class="section-lead">
                <?php echo __('Заполнит все этапы тестовыми призами (с тройками одинаковых) и бомбами на 2–3 этапе.'); ?>
            </p>
            <form method="post" onsubmit="return confirm('Заменить все ячейки тестовыми данными?');">
                <input type="hidden" name="action" value="seed">
                <button type="submit" class="btn btn-secondary"><?php echo __('Загрузить тестовые призы'); ?></button>
            </form>
        </div>
    </div>
</main>

<div id="wikiSkinModal" class="wiki-modal" hidden>
    <div class="wiki-modal-panel" role="dialog" aria-labelledby="wikiPickerTitle">
        <div class="wiki-modal-head">
            <h3 id="wikiPickerTitle"><?php echo __('Каталог wiki'); ?></h3>
            <button type="button" id="wikiPickerClose" class="btn btn-secondary" style="padding:0.4rem 0.75rem;font-size:0.8rem;"><?php echo __('Закрыть'); ?></button>
        </div>
        <div class="wiki-modal-tools">
            <input type="search" id="wikiSkinSearch" placeholder="<?php echo __('Поиск по названию…'); ?>">
            <select id="wikiGroupFilter" aria-label="<?php echo __('Группа'); ?>">
                <option value=""><?php echo __('Все'); ?></option>
                <option value="skins"><?php echo __('Скины'); ?></option>
                <option value="cars"><?php echo __('Машины'); ?></option>
                <option value="cases"><?php echo __('Кейсы'); ?></option>
            </select>
            <select id="wikiCategoryFilter" aria-label="<?php echo __('Категория'); ?>">
                <option value=""><?php echo __('Все категории'); ?></option>
            </select>
            <button type="button" id="wikiCacheRefresh" class="btn btn-secondary" style="padding:0.45rem 0.85rem;font-size:0.8rem;"><?php echo __('Обновить'); ?></button>
        </div>
        <div class="wiki-modal-body">
            <div id="wikiPickerStatus" class="wiki-picker-status"><?php echo __('Загрузка…'); ?></div>
            <div id="wikiSkinGrid" class="wiki-skin-grid"></div>
        </div>
    </div>
</div>

<script src="lottery-board.js"></script>
<script src="lottery-wiki.js"></script>
<script src="lottery-layout.js"></script>
</body>
</html>
