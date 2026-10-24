# GroznyHub

Демо без привязки к домену и без входа: [andergr0ynd.github.io/groznyhub.ru](https://andergr0ynd.github.io/groznyhub.ru/).

GitHub Pages отдаёт только статику. Главная, поиск, FAQ, обмен, колесо и лотерея открываются в браузере. Рулетка и контейнеры — по ссылкам с `?demo=1`.

PHP-часть (админка, чат-бот, Twitch) на Pages не выполняется. Локально, с PHP 8.1 и `pdo_sqlite`:

```bash
php -S localhost:8080 router.php
```

По умолчанию `DEMO_MODE=1`: локальный файл `data/demo.sqlite`, без OAuth. Свой MySQL включается в `.env` строкой `DEMO_MODE=0`.
