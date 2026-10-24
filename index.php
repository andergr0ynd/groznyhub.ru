<?php
require_once 'config.php';

$user = getCurrentUser();
$flashMessages = consumeIndexUrlNotifications();
$caseTickerItems = getCasePurchaseTickerItems(24);
$jackpotTickerItems = getJackpotWinnerTickerItems(24);
$streamInfo = getStreamDisplayForHomepage();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php
        if ($user) {
            echo htmlspecialchars(__('Профиль') . ' - ' . ($user['display_name'] ?: $user['username']));
        } else {
            echo htmlspecialchars(__('Авторизация') . ' - Mark Grozny');
        }
    ?></title>
<?php renderSiteFaviconTags(); ?>
<?php renderSiteHeadExtras(); ?>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
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
                <a class="stream-status-pill <?php echo $streamInfo['is_live'] ? 'is-live' : 'is-offline'; ?>"
                   href="<?php echo htmlspecialchars($streamInfo['channel_url']); ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   title="<?php echo $streamInfo['is_live'] ? htmlspecialchars($streamInfo['title'] ?: __('В эфире')) : __('Канал на Twitch'); ?>">
                    <?php if ($streamInfo['is_live']): ?>
                        <span class="stream-status-dot" aria-hidden="true"></span>
                        <?php echo __('В эфире'); ?>
                        <?php if ($streamInfo['uptime_text']): ?>
                            <span class="stream-status-meta"><?php echo htmlspecialchars($streamInfo['uptime_text']); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php echo __('Оффлайн'); ?>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </header>

    <div class="case-ticker" aria-label="<?php echo htmlspecialchars(__('Последние покупки кейсов')); ?>">
        <div class="case-ticker-label">🎁 <?php echo __('Кейсы'); ?></div>
        <div class="case-ticker-viewport">
            <?php if (!empty($caseTickerItems)): ?>
            <div class="case-ticker-marquee">
                <?php for ($copy = 0; $copy < 2; $copy++): ?>
                <div class="case-ticker-group"<?php echo $copy > 0 ? ' aria-hidden="true"' : ''; ?>>
                    <?php foreach ($caseTickerItems as $item):
                        $profileUrl = publicProfileUrl($item['username']);
                        $when = !empty($item['created_at']) ? (new DateTime($item['created_at']))->format('d.m H:i') : '';
                    ?>
                    <span class="case-ticker-item">
                        <?php if (!empty($item['avatar_url'])): ?>
                            <img class="case-ticker-avatar" src="<?php echo htmlspecialchars($item['avatar_url']); ?>" alt="">
                        <?php else: ?>
                            <span class="case-ticker-avatar case-ticker-avatar-fallback"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($item['username'], 0, 1))); ?></span>
                        <?php endif; ?>
                        <a class="case-ticker-user" href="<?php echo htmlspecialchars($profileUrl); ?>">@<?php echo htmlspecialchars($item['username']); ?></a>
                        <span class="case-ticker-action"><?php echo __('забрал'); ?></span>
                        <?php if (!empty($item['case_icon_url'])): ?>
                            <img class="case-ticker-case-icon" src="<?php echo htmlspecialchars($item['case_icon_url']); ?>" alt="">
                        <?php endif; ?>
                        <span class="case-ticker-case"><?php echo htmlspecialchars($item['collection_name']); ?></span>
                        <?php if ($when): ?><span class="case-ticker-time"><?php echo htmlspecialchars($when); ?></span><?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endfor; ?>
            </div>
            <?php else: ?>
            <div class="case-ticker-empty"><?php echo __('Пока никто не забирал кейсы — жди !кейс на стриме'); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="case-ticker jackpot-ticker" aria-label="<?php echo htmlspecialchars(__('Последние победители джекпота')); ?>">
        <div class="case-ticker-label">🎰 <?php echo __('Рулетка'); ?></div>
        <div class="case-ticker-viewport">
            <?php if (!empty($jackpotTickerItems)): ?>
            <div class="case-ticker-marquee">
                <?php for ($copy = 0; $copy < 2; $copy++): ?>
                <div class="case-ticker-group"<?php echo $copy > 0 ? ' aria-hidden="true"' : ''; ?>>
                    <?php foreach ($jackpotTickerItems as $item):
                        $profileUrl = publicProfileUrl($item['username']);
                        $when = !empty($item['won_at']) ? (new DateTime($item['won_at']))->format('d.m H:i') : '';
                        $prize = !empty($item['winner_seconds']) ? formatSecondsShort((int)$item['winner_seconds']) : '';
                    ?>
                    <span class="case-ticker-item">
                        <?php if (!empty($item['avatar_url'])): ?>
                            <img class="case-ticker-avatar" src="<?php echo htmlspecialchars($item['avatar_url']); ?>" alt="">
                        <?php else: ?>
                            <span class="case-ticker-avatar case-ticker-avatar-fallback"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($item['username'], 0, 1))); ?></span>
                        <?php endif; ?>
                        <a class="case-ticker-user" href="<?php echo htmlspecialchars($profileUrl); ?>">@<?php echo htmlspecialchars($item['username']); ?></a>
                        <span class="case-ticker-action"><?php echo __('выиграл'); ?></span>
                        <?php if ($prize): ?><span class="case-ticker-case"><?php echo htmlspecialchars($prize); ?></span><?php endif; ?>
                        <?php if ($when): ?><span class="case-ticker-time"><?php echo htmlspecialchars($when); ?></span><?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endfor; ?>
            </div>
            <?php else: ?>
            <div class="case-ticker-empty"><?php echo __('Пока никто не выигрывал рулетку — жди !джекпот на стриме'); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <main>
        <?php if (!$user): ?>
        <div class="container single-layout">
            <div class="main-content">
                <?php renderIndexFlashMessages($flashMessages); ?>
                <div class="auth-container fade-in">
                    <h1><?php echo __('Добро пожаловать'); ?></h1>
                    <p><?php echo __('Авторизуйтесь через Twitch, чтобы продолжить'); ?></p>
                    <a href="twitchauth/auth.php" class="btn btn-primary">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                            <path d="M10 2C5.58 2 2 5.58 2 10s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6z"/>
                            <path d="M8 7h4v2H8V7zm0 4h4v2H8v-2z"/>
                        </svg>
                        <?php echo __('Войти через Twitch'); ?>
                    </a>
                    <div class="guest-actions">
                        <a href="Obmen/index.html" class="btn btn-secondary"><?php echo __('💰 Обмен через стримера'); ?></a>
                        <a href="koleso/index.html" class="btn btn-secondary"><?php echo __('🎡 Колесо'); ?></a>
                        <a href="search.php" class="btn btn-secondary"><?php echo __('🔍 Поиск пользователей'); ?></a>
                        <a href="faq.php" class="btn btn-secondary"><?php echo __('📖 FAQ и правила рефки'); ?></a>
                        <a href="donate.php" class="btn btn-secondary"><?php echo __('Поддержать'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php else:
            $userStats = getProfileStats($user);
            $dailyBonusStats = getUserDailyBonusStats($user['username']);
        ?>
        <div class="container profile-layout">
            <div class="profile-dashboard fade-in">
                <?php renderIndexFlashMessages($flashMessages); ?>
                <?php if (isDemoMode()): ?>
                <p class="demo-banner"><?php echo __('Демо-режим: сайт открыт без входа и без привязки к домену. Данные лежат в локальной базе.'); ?></p>
                <?php endif; ?>

                <section class="profile-card profile-settings fade-in">
                    <div class="profile-section-header">
                        <span class="section-icon">🔒</span>
                        <div>
                            <h3><?php echo __('Настройки профиля'); ?></h3>
                            <p><?php echo isDemoMode() ? __('Приватность профиля.') : __('Приватность и Kick.'); ?></p>
                        </div>
                    </div>

                    <label class="profile-toggle">
                        <input type="checkbox" 
                               id="privacyToggle" 
                               <?php echo (isset($user['is_private']) && $user['is_private'] == 1) ? 'checked' : ''; ?>
                               onchange="togglePrivacy(this)">
                        <div class="toggle-text">
                            <strong><?php echo __('Приватный профиль'); ?></strong>
                            <small><?php echo __('Скрыть мою статистику из поиска'); ?></small>
                        </div>
                    </label>

                    <?php if (!isDemoMode()): ?>
                    <div class="profile-radmir-box" style="margin-bottom: 1.25rem;">
                        <label>Kick</label>
                        <?php if (!empty($user['kick_username']) || !empty($user['kick_id'])): ?>
                            <p style="margin: 0.5rem 0;">
                                <?php echo __('Привязан:'); ?>
                                <strong>@<?php echo htmlspecialchars($user['kick_username'] ?: ('id:' . $user['kick_id'])); ?></strong>
                            </p>
                            <?php if (!empty($user['kick_linked_at'])): ?>
                                <small class="profile-radmir-hint">
                                    <?php echo __('С '); echo (new DateTime($user['kick_linked_at']))->format('d.m.Y H:i'); ?>
                                </small>
                            <?php endif; ?>
                            <div class="profile-share-row" style="margin-top: 0.75rem;">
                                <button type="button" class="btn btn-secondary" id="unlinkKickBtn" style="padding: 0.6rem 1rem; font-size: 0.875rem;">
                                    <?php echo __('Отвязать Kick'); ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <p style="margin: 0.5rem 0; color: var(--text-secondary);">
                                <?php echo __('Привяжи Kick, чтобы время из чата Kick суммировалось с Twitch.'); ?>
                            </p>
                            <div class="profile-share-row" style="margin-top: 0.75rem;">
                                <a href="kickauth/auth.php" class="btn btn-kick" style="padding: 0.6rem 1rem; font-size: 0.875rem;">
                                    <?php echo __('Привязать Kick'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <section class="profile-card profile-summary fade-in">
                    <div class="profile-avatar">
                        <?php if ($user['avatar_url']): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="<?php echo htmlspecialchars($user['display_name']); ?>">
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($user['display_name'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-summary-info">
                        <h1><?php echo htmlspecialchars($user['display_name']); ?></h1>
                        <p class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></p>
                        <?php if ($user['email']): ?>
                            <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
                        <?php endif; ?>
                        <div class="profile-share-box">
                            <label><?php echo __('Публичная ссылка для Discord:'); ?></label>
                            <div class="profile-share-row">
                                <input type="text" id="publicProfileUrl" readonly value="<?php echo htmlspecialchars(publicProfileUrl($user['username'])); ?>">
                                <button type="button" class="btn btn-secondary" id="copyPublicProfileUrl" style="padding: 0.6rem 1rem; font-size: 0.875rem;">
                                    <?php echo __('Копировать'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="user-actions">
                        <a href="Obmen/index.html" class="btn btn-secondary"><?php echo __('💰 Обмен через стримера'); ?></a>
                        <a href="koleso/index.html" class="btn btn-secondary"><?php echo __('🎡 Колесо'); ?></a>
                        <a href="search.php" class="btn btn-secondary"><?php echo __('🔍 Поиск пользователей'); ?></a>
                        <a href="faq.php" class="btn btn-secondary"><?php echo __('📖 FAQ'); ?></a>
                        <a href="donate.php" class="btn btn-secondary"><?php echo __('Поддержать'); ?></a>
                        <?php if (isset($user['admin']) && $user['admin'] == 1): ?>
                            <a href="admin/" class="btn btn-secondary highlight"><?php echo __('Админ-Панель'); ?></a>
                        <?php endif; ?>
                        <?php if (!isDemoMode()): ?>
                        <a href="logout.php" class="btn btn-secondary"><?php echo __('Выйти'); ?></a>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="profile-card profile-stats fade-in">
                    <div class="profile-section-header" style="margin-bottom: 1rem;">
                        <span class="section-icon">📊</span>
                        <div>
                            <h3><?php echo __('Общая статистика'); ?></h3>
                            <p><?php echo __('Ваша активность в Twitch и Kick.'); ?></p>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stats-item">
                            <span class="label"><?php echo __('⏰ Общее время:'); ?></span>
                            <span class="value">
                                <?php 
                                echo $userStats['hours'] . __('ч ') . 
                                     $userStats['minutes'] . __('м ') . 
                                     $userStats['seconds'] . __('с');
                                ?>
                            </span>
                        </div>
                        <?php if (!empty($userStats['kick_linked'])): ?>
                        <div class="stats-item">
                            <span class="label"><?php echo __('🟢 Kick:'); ?></span>
                            <span class="value">
                                <?php
                                $ks = formatStatsFromSeconds((int)$userStats['kick_seconds']);
                                echo $ks['hours'] . __('ч ') . $ks['minutes'] . __('м ');
                                ?>
                            </span>
                        </div>
                        <div class="stats-item">
                            <span class="label"><?php echo __('🟣 Twitch:'); ?></span>
                            <span class="value">
                                <?php
                                $ts = formatStatsFromSeconds((int)$userStats['twitch_seconds']);
                                echo $ts['hours'] . __('ч ') . $ts['minutes'] . __('м ');
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="stats-item">
                            <span class="label"><?php echo __('💬 Сообщений:'); ?></span>
                            <span class="value"><?php echo number_format($userStats['messages_count'], 0, ',', ' '); ?></span>
                        </div>
                        <?php if ($userStats['last_activity']): ?>
                        <div class="stats-item">
                            <span class="label"><?php echo __('🕐 Последняя активность:'); ?></span>
                            <span class="value">
                                <?php 
                                $lastActivity = new DateTime($userStats['last_activity']);
                                echo $lastActivity->format('d.m.Y H:i');
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="stats-item">
                            <span class="label"><?php echo __('🔥 Серия бонусов:'); ?></span>
                            <span class="value">
                                <?php
                                if ($dailyBonusStats['current_streak'] > 0) {
                                    echo $dailyBonusStats['current_streak'] . '/7';
                                } else {
                                    echo __('ещё нет');
                                }
                                ?>
                            </span>
                        </div>
                        <div class="stats-item">
                            <span class="label"><?php echo __('🎁 Ежедневный бонус:'); ?></span>
                            <span class="value">
                                <?php
                                if ($dailyBonusStats['claimed_today']) {
                                    echo __('получен сегодня');
                                } else {
                                    echo sprintf(
                                        __('доступен: +%s мин (!бонус)'),
                                        $dailyBonusStats['next_reward_minutes']
                                    );
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="profile-export-actions">
                        <a href="export_stats.php?format=pdf" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">
                            <?php echo __('🖨 PDF / печать'); ?>
                        </a>
                    </div>
                </section>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p id="footerDateTime"></p>
            <?php renderSiteFooterLinks(); ?>
            <p id="footerUsersCount" style="margin-top: 0.5rem;"><?php echo sprintf(__('Пользователей на сайте: %s'), getUsersCount()); ?></p>
        </div>
    </footer>

    <?php renderMobileBottomNav('home'); ?>

    <script src="js/main.js"></script>
    <?php if ($user): ?>
    <script>
        function togglePrivacy(checkbox) {
            const isPrivate = checkbox.checked ? 1 : 0;
            
            const formData = new FormData();
            formData.append('is_private', isPrivate);
            
            fetch('update_privacy.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Можно показать уведомление
                    console.log(data.message);
                } else {
                    alert(<?php echo json_encode(__('Ошибка')); ?> + ': ' + (data.error || <?php echo json_encode(__('Неизвестная ошибка')); ?>));
                    // Возвращаем чекбокс в исходное состояние
                    checkbox.checked = !checkbox.checked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(<?php echo json_encode(__('Ошибка при обновлении настройки')); ?>);
                checkbox.checked = !checkbox.checked;
            });
        }

        (function () {
            var btn = document.getElementById('copyPublicProfileUrl');
            var input = document.getElementById('publicProfileUrl');
            if (!btn || !input) return;

            btn.addEventListener('click', function () {
                input.select();
                input.setSelectionRange(0, input.value.length);

                function onCopied() {
                    var original = btn.textContent;
                    btn.textContent = <?php echo json_encode(__('✓ Скопировано')); ?>;
                    setTimeout(function () { btn.textContent = original; }, 2000);
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value).then(onCopied).catch(function () {
                        document.execCommand('copy');
                        onCopied();
                    });
                } else {
                    document.execCommand('copy');
                    onCopied();
                }
            });
        })();

        (function () {
            var btn = document.getElementById('unlinkKickBtn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                if (!confirm(<?php echo json_encode(__('Отвязать Kick от этого аккаунта?')); ?>)) {
                    return;
                }
                btn.disabled = true;
                fetch('unlink_kick.php', { method: 'POST' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.error || <?php echo json_encode(__('Ошибка')); ?>);
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        alert(<?php echo json_encode(__('Ошибка сети')); ?>);
                        btn.disabled = false;
                    });
            });
        })();

    </script>
    <?php endif; ?>
</body>
</html>

