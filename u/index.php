<?php
require_once dirname(__DIR__) . '/config.php';

$nick = isset($_GET['nick']) ? $_GET['nick'] : '';
$profile = getPublicProfile($nick);
$currentUser = getCurrentUser();
$isOwnProfile = $currentUser
    && isset($profile['username'])
    && normalizeTwitchUsername($currentUser['username']) === normalizeTwitchUsername($profile['username']);

$caseCostMinutes = (int)(CASE_COST_SECONDS / 60);
$jackpotMinMinutes = (int)(JACKPOT_MIN_BET_SECONDS / 60);
$jackpotDuration = JACKPOT_DURATION_SECONDS;
$caseDuration = CASE_SALE_DURATION_SECONDS;

$pageTitle = __('Профиль');
$ogDescription = __('Статистика рефки на Mark Grozny');

if ($profile['status'] === 'public') {
    $stats = $profile['stats'];
    $pageTitle = '@' . $profile['username'] . ' — Mark Grozny';
    $ogDescription = sprintf(
        __('⏰ %sч %sм · 💬 %s сообщ. · %s'),
        $stats['hours'],
        $stats['minutes'],
        number_format($stats['messages_count'], 0, ',', ' '),
        $stats['is_active'] ? __('🟢 в рефке') : __('⚪ оффлайн')
    );
    if ($profile['bonus_streak'] > 0) {
        $ogDescription .= ' · 🔥 ' . sprintf(__('серия %s/7'), $profile['bonus_streak']);
    }
} elseif ($profile['status'] === 'private') {
    $pageTitle = '@' . $profile['username'] . ' — ' . __('Профиль скрыт');
    $ogDescription = __('🔒 Пользователь скрыл статистику');
} elseif ($profile['status'] === 'not_found') {
    $pageTitle = __('Пользователь не найден');
    $ogDescription = __('Нет данных в системе рефки');
}

$profileUrl = isset($profile['username']) ? publicProfileUrl($profile['username']) : siteUrl('search.php');
$ogImageUrl = isset($profile['username']) ? publicProfileOgUrl($profile['username']) : siteUrl('u/og.png');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($profileUrl); ?>">
    <meta property="og:type" content="profile">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImageUrl); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImageUrl); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
<?php renderSiteFaviconTags('..'); ?>
<?php renderSiteHeadExtras(); ?>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <header>
        <div class="container">
            <a href="../index.php" class="logo">
                <?php if (file_exists(dirname(__DIR__) . '/icons/logo.png')): ?>
                    <img src="../icons/logo.png" alt="Logo">
                <?php endif; ?>
                <span>Mark Grozny</span>
            </a>
            <div class="header-right">
                <a href="../search.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                    <?php echo __('Поиск'); ?>
                </a>
                <?php if ($currentUser): ?>
                    <a href="../index.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <?php echo __('Мой профиль'); ?>
                    </a>
                <?php else: ?>
                    <a href="../twitchauth/auth.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <?php echo __('Войти'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <div class="public-profile-page fade-in">
            <?php if ($profile['status'] === 'invalid'): ?>
                <div class="public-profile-card public-profile-card--empty">
                    <div class="public-profile-empty-icon">⚠️</div>
                    <h1><?php echo __('Некорректный ник'); ?></h1>
                    <p><?php echo __('Используйте ник Twitch: латиница, цифры и _, до 25 символов.'); ?></p>
                    <a href="../search.php" class="btn btn-primary"><?php echo __('Поиск пользователей'); ?></a>
                </div>
            <?php elseif ($profile['status'] === 'not_found'): ?>
                <div class="public-profile-card public-profile-card--empty">
                    <div class="public-profile-empty-icon">🔍</div>
                    <h1><?php echo __('Пользователь не найден'); ?></h1>
                    <p><?php echo sprintf(__('@%s ещё не участвовал в рефке на стриме.'), htmlspecialchars($profile['username'])); ?></p>
                    <a href="../search.php?q=<?php echo urlencode($profile['username']); ?>" class="btn btn-secondary"><?php echo __('Попробовать поиск'); ?></a>
                </div>
            <?php elseif ($profile['status'] === 'private'): ?>
                <div class="public-profile-card public-profile-card--empty">
                    <div class="public-profile-empty-icon">🔒</div>
                    <h1>@<?php echo htmlspecialchars($profile['username']); ?></h1>
                    <p><?php echo __('Профиль скрыт. Пользователь включил приватность в настройках сайта.'); ?></p>
                    <?php if ($isOwnProfile): ?>
                        <a href="../index.php" class="btn btn-primary"><?php echo __('Открыть мой профиль'); ?></a>
                    <?php endif; ?>
                </div>
            <?php elseif ($profile['status'] === 'error'): ?>
                <div class="public-profile-card public-profile-card--empty">
                    <div class="public-profile-empty-icon">⚠️</div>
                    <h1><?php echo __('Ошибка загрузки'); ?></h1>
                    <p><?php echo __('Не удалось загрузить профиль. Попробуйте позже.'); ?></p>
                </div>
            <?php else: ?>
                <?php $stats = $profile['stats']; ?>
                <div class="public-profile-card">
                    <div class="public-profile-top">
                        <div class="public-profile-avatar">
                            <?php if (!empty($profile['avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars($profile['avatar_url']); ?>"
                                     alt="<?php echo htmlspecialchars($profile['display_name']); ?>">
                            <?php else: ?>
                                <span><?php echo strtoupper(substr($profile['display_name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="public-profile-meta">
                            <h1><?php echo htmlspecialchars($profile['display_name']); ?></h1>
                            <p class="public-profile-nick">@<?php echo htmlspecialchars($profile['username']); ?></p>
                            <p class="public-profile-status <?php echo $stats['is_active'] ? 'is-active' : ''; ?>">
                                <?php echo $stats['is_active'] ? __('🟢 Сейчас в рефке') : __('⚪ Не в рефке'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="public-profile-stats">
                        <div class="public-stat">
                            <span class="public-stat-value">
                                <?php echo $stats['hours'] . __('ч ') . $stats['minutes'] . __('м'); ?>
                            </span>
                            <span class="public-stat-label"><?php echo __('Время рефки'); ?></span>
                        </div>
                        <div class="public-stat">
                            <span class="public-stat-value"><?php echo number_format($stats['messages_count'], 0, ',', ' '); ?></span>
                            <span class="public-stat-label"><?php echo __('Сообщений'); ?></span>
                        </div>
                        <?php if ($profile['bonus_streak'] > 0): ?>
                        <div class="public-stat">
                            <span class="public-stat-value"><?php echo $profile['bonus_streak']; ?>/7</span>
                            <span class="public-stat-label"><?php echo __('Серия бонусов'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($stats['last_activity']): ?>
                        <p class="public-profile-last">
                            <?php echo __('Последняя активность:'); ?>
                            <?php
                            $lastActivity = new DateTime($stats['last_activity']);
                            echo $lastActivity->format('d.m.Y H:i');
                            ?>
                        </p>
                    <?php endif; ?>

                    <div class="public-profile-actions">
                        <button type="button" class="btn btn-secondary" id="copyProfileLink" data-url="<?php echo htmlspecialchars($profileUrl); ?>">
                            <?php echo __('📋 Скопировать ссылку'); ?>
                        </button>
                        <a href="https://twitch.tv/<?php echo htmlspecialchars($profile['username']); ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">
                            <?php echo __('Twitch'); ?>
                        </a>
                        <?php if ($isOwnProfile): ?>
                            <a href="../index.php" class="btn btn-primary"><?php echo __('Мой профиль'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="public-profile-hint">
                    <?php echo __('Поделитесь ссылкой в Discord — карточка покажет основную статистику.'); ?>
                    <a href="../faq.php"><?php echo __('Правила рефки →'); ?></a>
                </p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p id="footerDateTime"></p>
            <p style="margin-top: 0.5rem;">
                <a href="../faq.php" class="footer-link"><?php echo __('FAQ и правила рефки'); ?></a>
            </p>
            <p id="footerUsersCount" style="margin-top: 0.5rem;"><?php echo sprintf(__('Пользователей на сайте: %s'), getUsersCount()); ?></p>
        </div>
    </footer>

    <?php renderMobileBottomNav('home', '..'); ?>

    <script src="../js/main.js"></script>
    <script>
        (function () {
            var btn = document.getElementById('copyProfileLink');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-url');
                if (!url) return;

                function onCopied() {
                    var original = btn.textContent;
                    btn.textContent = <?php echo json_encode(__('✓ Скопировано')); ?>;
                    setTimeout(function () { btn.textContent = original; }, 2000);
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(onCopied).catch(function () {
                        window.prompt(<?php echo json_encode(__('Скопируйте ссылку:')); ?>, url);
                    });
                } else {
                    window.prompt(<?php echo json_encode(__('Скопируйте ссылку:')); ?>, url);
                }
            });
        })();
    </script>
</body>
</html>
