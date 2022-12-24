# php-cron-requests-events
php crontab based on url requests/event-loop 

## Планировщик CRON
- Триггером для запуска задач служит запрос через URI
- Подключается в корневом index.php одной строчкой include('cron.php');
- Создает в корне сайта подкаталоги cron и cron/log
- При первом запуске создает cron/cron.dat в нем хранит переменные между запусками
- В cron/log/cron.log хранит лог, есть ротация логов
- Работает в отдельном процессе с низким приоритетом 15
