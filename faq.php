<?php
require_once 'config.php';

$user = getCurrentUser();
$caseCostMinutes = (int)(CASE_COST_SECONDS / 60);
$jackpotMinMinutes = (int)(JACKPOT_MIN_BET_SECONDS / 60);
$containersMinMinutes = (int)(CONTAINERS_MIN_BET_SECONDS / 60);
$containersCooldownMinutes = (int)ceil(getContainersCooldownSeconds() / 60);
$containersWinMultiplier = (int)CONTAINERS_WIN_MULTIPLIER;
$bonusRewards = implode(' → ', array_map(function ($m) { return '+' . $m . ' мин'; }, DAILY_BONUS_REWARDS_MINUTES));

// ⚠️ Честная игра в чате — заполните тексты ниже
$faqFairPlay = [
    'intro' => '',
    'rules' => [
        '',
        '',
        '',
    ],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo __('FAQ и правила рефки'); ?> - Mark Grozny</title>
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
                <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                    <?php echo __('Главная'); ?>
                </a>
                <a href="search.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                    <?php echo __('Поиск'); ?>
                </a>
            </div>
        </div>
    </header>

    <main>
        <div class="faq-page fade-in">
            <div class="faq-hero">
                <h1><?php echo __('📖 FAQ и правила рефки'); ?></h1>
                <p><?php echo __('Как работает система времени в чате, бонусы, кейсы, дуэль контейнеров и джекпот на стриме Mark Grozny.'); ?></p>
            </div>

            <section class="faq-section">
                <h2><?php echo __('⏰ Рефка — что это?'); ?></h2>
                <p><?php echo __('Рефка — это учёт времени, которое вы проводите в активном чате во время стрима. Накопленное время можно тратить на кейсы, дуэль контейнеров и джекпот.'); ?></p>
                <ul class="faq-list">
                    <li><?php echo __('Запуск: команда <code>!startrefka</code> в чате Twitch или Kick.'); ?></li>
                    <li><?php echo __('После команды таймер запускается сразу. Пишите в чат, чтобы оставаться активным.'); ?></li>
                    <li><?php echo __('Если долгое время нет активности — таймер останавливается (нужно снова <code>!startrefka</code>).'); ?></li>
                    <li><?php echo __('Рефка работает только когда стрим онлайн.'); ?></li>
                    <li><?php echo __('Статистика доступна на сайте в профиле и через поиск.'); ?></li>
                </ul>
            </section>

            <section class="faq-section">
                <h2><?php echo __('🎁 Ежедневный бонус (!бонус)'); ?></h2>
                <p><?php echo __('Каждый зритель может раз в сутки забрать бесплатные минуты рефки командой <code>!бонус</code>.'); ?></p>
                <ul class="faq-list">
                    <li><?php echo sprintf(__('Награды по серии (дни 1–7): %s.'), $bonusRewards); ?></li>
                    <li><?php echo __('Серия сохраняется, если забираете бонус каждый день подряд (по Москве).'); ?></li>
                    <li><?php echo __('Пропустили день — серия сбрасывается на 1-й день.'); ?></li>
                </ul>
            </section>

            <section class="faq-section">
                <h2><?php echo __('🎁 Кейсы (!купить кейс)'); ?></h2>
                <ul class="faq-list">
                    <li><?php echo sprintf(__('Покупка: <code>!купить кейс</code> — стоимость %s мин накопленной рефки.'), $caseCostMinutes); ?></li>
                    <li><?php echo __('Нужен запущенный таймер рефки (<code>!startrefka</code>).'); ?></li>
                    <li><?php echo __('Кейсы доступны только во время стрима.'); ?></li>
                    <li><?php echo __('Первый успевший покупатель получает случайный кейс на RADMIR RP.'); ?></li>
                </ul>
            </section>

            <section class="faq-section">
                <h2><?php echo __('📦 Дуэль контейнеров (!контейнер)'); ?></h2>
                <p><?php echo __('Мини-игра в чате: вы и другой зритель открываете по 5 контейнеров с машинами. Побеждает тот, у кого больше суммарная стоимость в ₽.'); ?></p>
                <ul class="faq-list">
                    <li><?php echo sprintf(__('Вызов: <code>!контейнер %s @ник</code>, соперник отвечает <code>!контейнер принять</code> или <code>!контейнер отказать</code>. Отозвать свой вызов — <code>!контейнер отозвать</code>.'), $containersMinMinutes); ?></li>
                    <li><?php echo __('Нельзя вызвать самого себя или кинуть повторный вызов тому же человеку, пока дуэль активна.'); ?></li>
                    <li><?php echo __('Перед игрой выберите монетку: <code>!контейнер орел</code> или <code>!контейнер решка</code> — от этого зависит, кто ходит первым.'); ?></li>
                    <li><?php echo sprintf(__('Победитель забирает x%s ставки в рефке; проигравший теряет свою ставку.'), $containersWinMultiplier); ?></li>
                    <li><?php echo sprintf(__('Общий кулдаун — %s мин между дуэлями в чате (для всех).'), $containersCooldownMinutes); ?></li>
                    <li><?php echo __('Контейнеры доступны только во время стрима. Дуэль показывается на виджете в OBS.'); ?></li>
                </ul>
            </section>

            <section class="faq-section">
                <h2><?php echo __('🎰 Рулетка 0–36 (!ставка)'); ?></h2>
                <ul class="faq-list">
                    <li><?php echo sprintf(__('Банк игры — %s. Проигрыши пополняют банк, выигрыши платятся из него (x%s). Если банк на нуле — игра недоступна.'), formatSecondsShort(JACKPOT_HOUSE_BANK_SECONDS), ROULETTE_STRAIGHT_MULTIPLIER); ?></li>
                    <li><?php echo sprintf(__('Ставка: <code>!ставка 17</code> или <code>!ставка 30 1,27,17</code> — до 3 разных чисел за раунд, по умолчанию %s мин на число.'), (int)(JACKPOT_DEFAULT_BET_SECONDS / 60)); ?></li>
                    <li><?php echo sprintf(__('Минимум — %s мин на число, максимум не ограничен. Повторно на то же число поставить нельзя.'), $jackpotMinMinutes); ?></li>
                    <li><?php echo sprintf(__('Угадал число — выплата x%s. Не угадал — ставка уходит в банк.'), ROULETTE_STRAIGHT_MULTIPLIER); ?></li>
                    <li><?php echo __('Модератор запускает раунд командой <code>!джекпот</code>.'); ?></li>
                </ul>
            </section>

            <section class="faq-section">
                <h2><?php echo __('🔒 Приватность и публичный профиль'); ?></h2>
                <ul class="faq-list">
                    <li><?php echo __('В настройках профиля можно включить «Приватный профиль» — статистика скроется из поиска и по ссылке <code>/u/ник</code>.'); ?></li>
                    <li><?php echo __('Публичная карточка <code>/u/ник</code> — короткая ссылка для Discord: время, сообщения, серия бонусов.'); ?></li>
                    <li><?php echo __('Экспорт PDF доступен только в вашем личном профиле после входа через Twitch.'); ?></li>
                </ul>
            </section>

            <section class="faq-section">
                <h2><?php echo __('💰 Обмен и связь'); ?></h2>
                <ul class="faq-list">
                    <li><?php echo __('Пакеты «Богатей со скидкой» — на странице <a href="Obmen/index.html">Обмен через стримера</a>.'); ?></li>
                    <li><?php echo __('Покупка через VK или Telegram — текст сообщения подставляется автоматически.'); ?></li>
                    <li><?php echo __('Акции на сайте действуют только когда такая же акция активна на игровом сервере.'); ?></li>
                </ul>
            </section>

            <section class="faq-section faq-section--note">
                <h2><?php echo __('⚠️ Честная игра в чате'); ?></h2>
                <?php if ($faqFairPlay['intro'] !== ''): ?>
                    <p><?php echo __($faqFairPlay['intro']); ?></p>
                <?php endif; ?>
                <?php
                $fairPlayRules = array_filter($faqFairPlay['rules'], function ($rule) {
                    return trim($rule) !== '';
                });
                if (!empty($fairPlayRules)):
                ?>
                <ul class="faq-list">
                    <?php foreach ($fairPlayRules as $rule): ?>
                        <li><?php echo __($rule); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>

            <div class="faq-actions">
                <a href="search.php" class="btn btn-secondary"><?php echo __('🔍 Поиск пользователей'); ?></a>
                <a href="Obmen/index.html" class="btn btn-secondary"><?php echo __('💰 Обмен'); ?></a>
                <a href="koleso/index.html" class="btn btn-secondary"><?php echo __('🎡 Колесо'); ?></a>
                <?php if ($user): ?>
                    <a href="index.php" class="btn btn-primary"><?php echo __('Мой профиль'); ?></a>
                <?php else: ?>
                    <a href="twitchauth/auth.php" class="btn btn-primary"><?php echo __('Войти через Twitch'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p id="footerDateTime"></p>
            <?php renderSiteFooterLinks(); ?>
            <p id="footerUsersCount" style="margin-top: 0.5rem;"><?php echo sprintf(__('Пользователей на сайте: %s'), getUsersCount()); ?></p>
        </div>
    </footer>

    <?php renderMobileBottomNav('faq'); ?>

    <script src="js/main.js"></script>
</body>
</html>
