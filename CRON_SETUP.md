# Настройка автоматической проверки стримов Twitch

Для отслеживания статуса стримов необходимо настроить периодический запуск скрипта `check_streams.php`.

## Вариант 1: Настройка через cron (Linux/Unix)

1. Откройте crontab для редактирования:
```bash
crontab -e
```

2. Добавьте строку для запуска скрипта каждые 2 минуты:
```bash
*/2 * * * * /usr/bin/php /путь/к/Websait/check_streams.php >> /путь/к/Websait/logs/stream_check.log 2>&1
```

Или каждые 5 минут:
```bash
*/5 * * * * /usr/bin/php /путь/к/Websait/check_streams.php >> /путь/к/Websait/logs/stream_check.log 2>&1
```

**Важно:** Замените `/путь/к/Websait` на реальный путь к папке Websait на вашем сервере.

3. Убедитесь, что путь к PHP правильный. Проверить можно командой:
```bash
which php
```

4. Создайте папку для логов (если её нет):
```bash
mkdir -p /путь/к/Websait/logs
chmod 755 /путь/к/Websait/logs
```

## Вариант 2: Настройка через Windows Task Scheduler (Windows)

1. Откройте "Планировщик заданий" (Task Scheduler)
2. Создайте новое задание (Create Basic Task)
3. Настройте:
   - **Триггер:** Каждые 2-5 минут
   - **Действие:** Запустить программу
   - **Программа:** `C:\путь\к\php.exe` (или полный путь к PHP)
   - **Аргументы:** `E:\Mark-Grozny\Websait\check_streams.php`
   - **Рабочая папка:** `E:\Mark-Grozny\Websait`

## Вариант 3: Использование внешнего сервиса (если нет доступа к cron)

Можно использовать бесплатные сервисы для мониторинга:
- **UptimeRobot** - https://uptimerobot.com/
- **Cronitor** - https://cronitor.io/
- **EasyCron** - https://www.easycron.com/

Настройте HTTP-запрос к:
```
https://mark-grozny.ru/check_streams.php?key=ВАШ_СЕКРЕТНЫЙ_КЛЮЧ
```

И установите переменную окружения `STREAM_CHECK_SECRET` в config.php или на сервере.

## Проверка работы

1. Запустите скрипт вручную для проверки:
```bash
php check_streams.php
```

2. Проверьте логи PHP (обычно в `/var/log/php/` или настройках хостинга)

3. Убедитесь, что:
   - Twitch API токен получается успешно
   - Скрипт выполняется без ошибок

## Отладка

Если уведомления не приходят, проверьте:

1. **Логи PHP:**
```bash
tail -f /var/log/php/error.log
```

2. **Логи скрипта (если настроены):**
```bash
tail -f /путь/к/Websait/logs/stream_check.log
```

3. **Запуск вручную с выводом:**
```bash
php check_streams.php
```

4. **Проверка статуса стрима в базе:**
```sql
SELECT * FROM twitch_stream_status WHERE channel = 'mark_grozny';
```

## Частота проверки

Рекомендуется проверять каждые **2-5 минут**:
- Слишком часто (каждую минуту) - может привести к лимитам API
- Слишком редко (каждые 10+ минут) - статус будет обновляться с задержкой

Оптимально: **каждые 2-3 минуты**.

