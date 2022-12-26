# php-cron-requests-events
php crontab, based on url requests/event-loop, work in background, ready multithreading.

![php-cron-requests-events](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/cron.png "php-cron-requests-events")

## Описание
- Реализация php планировщика с использованием url запросов в качестве триггера событий. 
- Работает в мультипоточном режиме, не создает нагрузки на сервер.
- Можно подключать данный CRON к любой CMS, это никак не скажется на производительности.
- Позволяет выполнять задачи расходующие много ресурсов с низким приоритетом. 
- Работает в контексте окружения веб сервера, не использует системный CRON.

## Планировщик CRON
- Триггером для запуска задач служит запрос через URI
- Подключается в корневом index.php одной строчкой include('cron.php');
- Альтернативный режим подключения: в файле .htaccess добавить строку: php_value auto_append_file "cron.php"
- Создает в корне сайта подкаталоги cron/dat и cron/log
- При первом запуске создает cron/dat/cron.dat в нем хранит переменные между запусками
- В cron/log/cron.log хранит лог, есть ротация логов
- Работает в отдельном процессе с низким приоритетом 15
- Предотвращает запуск процесса если предыдущий не завершен
- Минимальные системные требования: PHP 5.2.1

### Пример запуска задачи
В контексте файла cron.php раздел CRON Job
```
$GLOBALS['cron_jobs'][]= [ // CRON Job 1, example
	'name' => 'job1',
	'interval' => 60 * 60 * 24, // 1 start in 24 hours
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => false
];

$GLOBALS['cron_jobs'][]= [ // CRON Job 2, multithreading example
	'name' => 'job2multithreading',
	'interval' => 60 * 60 * 24, // 1 start in 24 hours
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_multithreading_cron.php",
	'multithreading' => true
];

```
- name Имя задачи
- interval Задержка перед запуском
- callback PHP скрипт, будет выполнен по истечении интервала
- multithreading true\false Запуск в фоновом режиме

### Параметры запуска
- define("CRON_LOG_FILE", CRON_SITE_ROOT . "cron/log/cron.log"); // Путь к файлу журнала
- define("CRON_DAT_FILE", CRON_SITE_ROOT . "cron/dat/cron.dat"); // Путь к системному файлу диспетчера потока
- $cron_delay= 60; // Тайм аут до следующего запуска в секундах
- $cron_log_rotate_max_size= 10 * 1024 * 1024; // Максимальный размер логов в МБ
- $cron_log_rotate_max_files= 5; // Хранить максимум 5 файлов архивных журналов
- $cron_url_key= 'my_secret_key'; // Ключ запуска в URI

### Потребление ресурсов
Управляющий задачами процесс запускается в фоновом режиме с использованием механизма сетевых запросов. 

cron.php работает в 4-х режимах:
1. Режим проверки тайм-аута расписания (до истечения интервала) 
- 328 байт оперативной памяти 0.0004 секунды.
2. Режим проверки тайм-аута расписания (запуск отдельного процесса) 
- запуск через shell_exec wget: 3 Кб оперативной памяти, 0.0088 секунд (не блокирующий).
- резервный запуск через stream_context: 2 Кб оперативной памяти, 0.0418 секунд (блокирующий).
4. Отдельный процесс 
- ресурсов от скрипта обработчика запроса не потребляет, приоритет 15, MAX_EXECUTION_TIME 600 секунд по умолчанию.
5. Отдельный процесс c использованием многопоточности 
- так же как и предыдущий пункт, необходимо учитывать параметры сервера: количество ядер процессора и ограничение количества одновременных запросов к серверу

Можно подключать данный CRON к любой CMS, это никак не скажется на производительности. 
##### Тестовый стенд:
- Centos 8
- Apache/2.4.6 mpm-itk/2.4.7-04 mod_fcgid/2.3.9
- 1 ядро: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
- PHP 7.4.3 with Zend OPcache
- GNU Wget 1.14
