<?php
require_once 'config.php';

$user = getCurrentUser();
$obsMode = isset($_GET['obs']) && $_GET['obs'] !== '0';
$donateUrl = siteDonateUrl();
$qrUrl = siteDonateQrUrl();
$qrExists = is_file(__DIR__ . '/' . SITE_DONATE_QR);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo __('Продление сайта и бота'); ?> - Mark Grozny</title>
<?php renderSiteFaviconTags(); ?>
<?php renderSiteHeadExtras(); ?>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/donate.css?v=<?php echo time(); ?>">
</head>
<body class="donate-page<?php echo $obsMode ? ' donate-page--obs' : ''; ?>">
<?php if (!$obsMode): ?>
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
            </div>
        </div>
    </header>
<?php endif; ?>

    <main class="donate-main">
        <div class="donate-wrap fade-in">
            <div class="donate-disclaimer" role="note">
                <strong><?php echo __('ЭТО НЕ ДОНАТ СТРИМЕРУ'); ?></strong>
                <span><?php echo __('Всё идёт только на продление сайта и Twitch-бота (хостинг, домен, сервер).'); ?></span>
            </div>

            <section class="donate-card">
                <h1><?php echo __('Продление сайта и бота'); ?></h1>
                <p class="donate-lead">
                    <?php echo __('Если хотите помочь оплатить инфраструктуру проекта — можно отправить любую сумму через Boosty. Чтоб не попрошайничать эти деньги у Лысого — andergr0ynd 😂'); ?>
                </p>

                <div class="donate-grid">
                    <?php if ($qrExists): ?>
                    <div class="donate-qr-block">
                        <img src="<?php echo htmlspecialchars($qrUrl); ?>" width="220" height="220" alt="<?php echo htmlspecialchars(__('QR-код для оплаты на Boosty')); ?>" class="donate-qr">
                        <p class="donate-qr-hint"><?php echo __('Отсканируйте камерой или QR-приложением'); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="donate-actions">
                        <a href="<?php echo htmlspecialchars($donateUrl); ?>" class="btn btn-primary donate-boosty-btn" target="_blank" rel="noopener noreferrer">
                            <?php echo __('Поддержать'); ?>
                        </a>
                        <p class="donate-url-label"><?php echo __('Ссылка:'); ?></p>
                        <div class="donate-url-row">
                            <input type="text" class="donate-url-input" id="donateUrl" readonly value="<?php echo htmlspecialchars($donateUrl); ?>">
                            <button type="button" class="btn btn-secondary" id="copyDonateUrl"><?php echo __('Копировать'); ?></button>
                        </div>
                    </div>
                </div>

                <p class="donate-notes"><?php echo __('Здесь — исключительно расходы на сайт и бота.'); ?></p>
                <p class="donate-transparency">
                    <?php echo __('В ленте новостей на Boosty будут публиковаться все траты и, если останется, остаток средств. Спасибо за поддержку!'); ?>
                </p>
            </section>
        </div>
    </main>

<?php if (!$obsMode): ?>
    <footer>
        <div class="container">
            <p id="footerDateTime"></p>
            <?php renderSiteFooterLinks(); ?>
            <p id="footerUsersCount" style="margin-top: 0.5rem;"><?php echo sprintf(__('Пользователей на сайте: %s'), getUsersCount()); ?></p>
        </div>
    </footer>

    <?php renderMobileBottomNav('donate'); ?>
    <script src="js/main.js"></script>
<?php endif; ?>

    <script>
    (function () {
        var btn = document.getElementById('copyDonateUrl');
        var input = document.getElementById('donateUrl');
        if (!btn || !input) return;
        btn.addEventListener('click', function () {
            input.select();
            input.setSelectionRange(0, 99999);
            var text = input.value;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    btn.textContent = '<?php echo addslashes(__('Скопировано')); ?>';
                    setTimeout(function () { btn.textContent = '<?php echo addslashes(__('Копировать')); ?>'; }, 2000);
                });
            } else {
                document.execCommand('copy');
            }
        });
    })();
    </script>
</body>
</html>
