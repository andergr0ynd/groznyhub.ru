<?php
require_once 'config.php';

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$searchResults = [];
$isPrivateProfile = false;

if (!empty($searchQuery)) {
    $searchQueryLower = normalizeTwitchUsername($searchQuery);
    $isPrivateProfile = isSearchQueryPrivateProfile($searchQueryLower);
    $searchResults = searchUsers($searchQueryLower);
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo __('Поиск пользователей'); ?> - Mark Grozny</title>
<?php renderSiteFaviconTags(); ?>
<?php renderSiteHeadExtras(); ?>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        .search-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 20px;
        }

        .search-box {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-panel);
            position: relative;
            overflow: hidden;
        }

        .search-box::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-brand);
        }

        .search-form {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .search-input {
            flex: 1;
        }

        .search-results {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-panel);
        }

        .result-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.2s ease;
        }

        .result-item:last-child {
            border-bottom: none;
        }

        .result-item:hover {
            background-color: var(--bg-elevated);
        }

        .result-username {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .result-info {
            flex: 1;
        }

        .result-stats {
            display: flex;
            gap: 2rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .result-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .search-hint {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .search-container {
                padding: 1rem 12px 1.5rem;
            }

            .search-box,
            .search-results {
                padding: 1.25rem;
                border-radius: var(--radius-lg);
            }

            .search-form {
                flex-direction: column;
            }

            .search-form .btn {
                width: 100%;
            }

            .result-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
                padding: 1rem;
            }

            .result-stats {
                flex-wrap: wrap;
                gap: 0.75rem 1.25rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php" class="logo">
                <?php if (file_exists('icons/logo.png')): ?>
                    <img src="icons/logo.png" alt="Logo">
                <?php endif; ?>
                <span>Mark Grozny</span>
            </a>
            <div class="header-right">
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <?php echo __('Главная'); ?>
                    </a>
                    <?php if ($user): ?>
                        <a href="logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                            <?php echo __('Выйти'); ?>
                        </a>
                    <?php else: ?>
                        <a href="twitchauth/auth.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                            <?php echo __('Войти'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="search-container fade-in">
            <div class="search-box">
                <h1 style="margin-bottom: 1.5rem; color: var(--text-primary);"><?php echo __('🔍 Поиск пользователей'); ?></h1>
                <form method="GET" action="search.php" class="search-form">
                    <input type="text" 
                           name="q" 
                           class="search-input" 
                           placeholder="<?php echo __('Введите никнейм Twitch (без @)'); ?>" 
                           value="<?php echo htmlspecialchars(isset($_GET['q']) ? $_GET['q'] : ''); ?>"
                           required>
                    <button type="submit" class="btn btn-primary"><?php echo __('Поиск'); ?></button>
                </form>
                <p class="search-hint"><?php echo __('Введите ник Twitch или отображаемое имя'); ?> · <a href="faq.php" style="color: var(--twitch-purple);"><?php echo __('Правила рефки'); ?></a></p>
            </div>

            <?php if (!empty($searchQuery)): ?>
                <div class="search-results">
                    <h2 style="margin-bottom: 1.5rem; color: var(--text-primary);">
                        <?php echo sprintf(__('Результаты поиска для "%s"'), htmlspecialchars($searchQuery)); ?>
                    </h2>
                    
                    <?php if (empty($searchResults)): ?>
                        <div class="no-results">
                            <?php if ($isPrivateProfile): ?>
                                <p style="font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--text-primary);"><?php echo __('🔒 Профиль скрыт'); ?></p>
                                <p><?php echo __('У этого пользователя включен "Приватный профиль". Статистика недоступна для просмотра.'); ?></p>
                            <?php else: ?>
                                <p style="font-size: 1.1rem; margin-bottom: 0.5rem;"><?php echo __('Пользователь не найден'); ?></p>
                                <p><?php echo __('Проверьте правильность написания никнейма или попробуйте другой поисковый запрос'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($searchResults as $result): ?>
                            <div class="result-item">
                                <div class="result-info">
                                    <div class="result-username">
                                        <a href="<?php echo htmlspecialchars(publicProfileUrl($result['username'])); ?>">
                                            @<?php echo htmlspecialchars($result['username']); ?>
                                        </a>
                                    </div>
                                    <div class="result-stats">
                                        <div class="result-stat">
                                            <span>⏰</span>
                                            <span>
                                                <?php 
                                                echo $result['hours'] . __('ч ') . 
                                                     $result['minutes'] . __('м ') . 
                                                     $result['seconds'] . __('с');
                                                ?>
                                            </span>
                                        </div>
                                        <div class="result-stat">
                                            <span>💬</span>
                                            <span><?php echo number_format($result['messages_count'], 0, ',', ' '); ?> <?php echo __('сообщений'); ?></span>
                                        </div>
                                        <div class="result-stat">
                                            <span><?php echo $result['is_active'] ? '🟢' : '⚪'; ?></span>
                                            <span><?php echo $result['is_active'] ? __('Активен') : __('Неактивен'); ?></span>
                                        </div>
                                        <?php if (!empty($result['bonus_streak'])): ?>
                                        <div class="result-stat">
                                            <span>🔥</span>
                                            <span><?php echo sprintf(__('серия %s/7'), (int)$result['bonus_streak']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p id="footerDateTime"></p>
            <?php renderSiteFooterLinks(); ?>
            <p id="footerUsersCount" style="margin-top: 0.5rem;"><?php echo sprintf(__('Пользователей на сайте: %s'), getUsersCount()); ?></p>
        </div>
    </footer>

    <?php renderMobileBottomNav('search'); ?>

    <script src="js/main.js"></script>
</body>
</html>

