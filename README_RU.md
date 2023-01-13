# php-cron-requests-events
php crontab process sheduler, based on url requests/event-loop, daemon mode, multithreading, second intervals, microseconds queue api, runtime in modApache/CGI/FPM sapi environment.


[User manual en](https://github.com/commeta/php-cron-requests-events/wiki/User-manual)


![php-cron-requests-events](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/php_cron_in_php-fpm_htop_process_list.png "php-cron-requests-events")
Процедурный код на встроенных функциях, отсутствие зависимостей от сторонних модулей и библиотек, минимальное потребление ресурсов

## Описание
- Реализация php планировщика с использованием URL запросов в качестве триггера событий. 
- Работает в мультипоточном режиме, не создает нагрузки на сервер.
- Подходит для SHARED хостинга, возможны ограничения на использование ресурсов CPU.
- Пригодится когда есть только FTP доступ к серверу или загрузка файлов через CMS.
- Поможет экономить время при частых переездах сайта, не зависим от системного CRON.
- Позволяет выполнять по расписанию PHP функции или include callback скрипты.
- Широкий диапазон для запуска задач: интервалы от секунды, есть запуск в указанное время.
- Позволяет выполнять задачи расходующие много ресурсов с низким приоритетом. 
- Можно подключать данный CRON к любой CMS, это никак не скажется на производительности.


## Планировщик CRON
- Триггером для запуска задач служит запрос через URI
- Подключается в корневом index.php одной строчкой include('cron.php');
- Альтернативный режим подключения: в файле .htaccess добавить строку: php_value auto_append_file "cron.php"
- Еще альтернативный режим подключения: в файле php.ini добавить строку: auto_append_file="cron.php"
- Системный режим подключения: добавить в системный cron запуск команды php /path/cron.php
- Создает в каталоге запуска подкаталоги cron/dat и cron/log
- При первом запуске создает cron/dat/cron.dat в нем хранит переменные между запусками
- В cron/log/cron.log хранит лог, есть ротация логов
- Работает в отдельном процессе с низким приоритетом 19
- Предотвращает запуск процесса если предыдущий не завершен
- Есть режим ожидания пока предыдущий процесс не закончит работу - очередь
- Работает на всех SAPI: (CLI только запуск), modApache, PHP-FPM, CGI. Версии PHP от 5.4 до 8.2.0
- Минимальный объем PHP кода: 27.8 Кб (16.6 Кб без пробелов и комментариев)
- Дружественный код для opcache.jit компилятора


## Установка
Скачайте скрипт в любой каталог сайта:
- PHP: 
```
file_put_content('__FILE__', file_get_contents('https://raw.githubusercontent.com/commeta/php-cron-requests-events/main/cron.php'));
```
- CLI: 
```
wget "https://raw.githubusercontent.com/commeta/php-cron-requests-events/main/cron.php"
```

## Пример запуска задачи
В контексте файла cron.php раздел CRON Job
```
###########################
$cron_jobs[]= [ // CRON Job 1, example
	'interval' => 0, // start interval 1 sec
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => false
];
##########


###########################
$cron_jobs[]= [ // CRON Job 2, multithreading example
	'interval' => 10, // start interval 10 sec
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => true
];
##########
 
 
###########################
$cron_jobs[]= [ // CRON Job 3, multicore example
	'time' => '09:05:00', // "hours:minutes:seconds"execute job on the specified time every day
	//'callback' => CRON_ROOT . "cron/inc/callback_addressed_queue_example.php",
	'function' => "queue_address_manager", // if need file include: comment this, uncomment callback
	'param' => true, // use with queue_address_manager(true), in worker mode
	'multithreading' => true
];


for( // CRON job 3, multicore example, four cores, 
	$i= 0;
	$i< 4; // Max processor cores
	$i++	
) {
	$cron_jobs[]= [ // CRON Job 3, multicore example
		'time' => '09:05:10', //  "hours:minutes:seconds" execute job on the specified time every day
		//'callback' => CRON_ROOT . "cron/inc/callback_addressed_queue_example.php",
		'function' => "queue_address_manager", // if need file include: comment this, uncomment callback
		'param' => false, // use with queue_address_manager(false), in handler mode
		'multithreading' => true
	];
}

##########


###########################
$cron_jobs[]= [ // CRON Job 4, multithreading example
	'date' => '01-01-2023', // "day-month-year" execute job on the specified date
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => true
];
##########
```
- interval - Задержка перед запуском
- time - Устанавливает время для старта в 24-ом формате 07:20:00, если дата не указана то выполняет каждый день
- date - Устанавливает дату для старта в формате 31-12-2022
- callback - PHP скрипт, будет выполнен по истечении интервала
- function - Выполняет указанную функцию, если указан param то передает его в параметрах
- multithreading - Запуск в фоновом режиме true\false

Если указаны параметры date или time то аргумент interval будет проигнорирован, точность регулируется параметром CRON_DELAY, зависит от активности запросов к хосту, если в момент наступления времени события, запросов к серверу не было - запустит задачу при первом запуске.

Можно запустить управляющий процесс в резидентном режиме, установив значение CRON_DAEMON_MODE true, в этом случае возможна проверка заданий непрерывно в цикле, с паузой между итерациями. В данном случае длительная задача будет блокировать основной поток, рекомендую запускать задачи в режиме multithreading true.

Выход из резидентного режима осуществляется через смену параметра CRON_DAEMON_MODE false. Перезагрузка происходит автоматически, при смене параметров.

Можно запустить задачу на нескольких ядрах, потребуется реализация обработчика очереди. Если ядер на сервере мало, то нагружать можно мелкими задачами в которых большая часть времени уходит например на скачивание - IO bound. Одновременно запущенные CPU bound задачи, желательно ограничить по количеству ядер на сервере, в таком случае CPU bound нагрузка будет расходовать оставшееся время ядра.

- По умолчанию Apache\PHP FPM workers сами распределяют CPU bound нагрузку между свободными ядрами.
- За освобождением памяти следит Zend Engine сборщик мусора Garbage Collection.
- Включенный OPcache позволяет сэкономить накладные расходы системных ресурсов при запуске потока, и оптимизирует исполнение кода.
- Использование многоядерной очереди, в некоторых случаях подходит для замены микросервисов.


## Параметры запуска
- define("CRON_LOG_FILE", CRON_ROOT . "cron/log/cron.log"); // Путь к файлу журнала, false - отключает журнал
- define("CRON_DAT_FILE", CRON_ROOT . "cron/dat/cron.dat"); // Путь к системному файлу диспетчера потока
- define("CRON_LOG_LEVEL", 5); // Детализация журнала 2 warning, 3 debug
- define("CRON_QUEUE_FILE", CRON_ROOT . 'cron/dat/queue.dat'); // Путь к системному файлу многопроцессной очереди
- define("CRON_DELAY", 1); // Тайм аут до следующего запуска в секундах
- define("CRON_DAEMON_MODE", true); // true\false резидентный режим (фоновая служба)
- define("CRON_LOG_ROTATE_MAX_SIZE", 10 * 1024 * 1024); // Максимальный размер логов в МБ
- define("CRON_LOG_ROTATE_MAX_FILES", 5); // Хранить максимум 5 файлов архивных журналов
- define("CRON_URL_KEY", 'my_secret_key'); // Ключ запуска в URI
- $paths= "/usr/bin:/usr/local/bin"; // Если переменная окружения PATH пуста, используем системные пути, каталоги поиска исполняемых файлов: wget, curl
- $profiler['time'] > $time - 15 // 15 сек. интервал проверки времени модификации cron.php, если новее то перезапуск
- $profiler['callback_time'] > $time - 60 // 60 сек. интервал проверки времени модификации include callback файлов, если новее то перезапуск
- $cron_session['log_rotate_last_update'] > time() - 600 // 600 сек. задержка для ротации лог файлов
- ini_set('MAX_EXECUTION_TIME', 600); // Максимальное время выполнения, 0 в резидентном режиме
- 'timeout' => 0.04 // Время блокировки, тайм аут для экстренного запуска через fopen. Время ответа сервера * 10 в зависимости от Linux Kernel Load Average\OPcache\FLOPS
- usleep(2000); // В примерах многопроцессной очереди имитирует нагрузку, в данной точке предполагается обработка данных, неограниченно по времени
- $host= "localhost"; // Для запуска через консоль CLI, впишите имя вашего домена и путь к корневой директории сайта $document_root

При подборе параметра CRON_DELAY можно посмотреть в логи сервера, обычно хост ежеминутно опрашивается массой ботов.


## Обработчик событий CRON
### Пример из файла: cron/inc/callback_cron.php

Данный файл будет запущен по расписанию, путь к файлу указан в поле $cron_jobs[$job_process_id]['callback']

#### Переменные
- $cron_session, хранит служебные поля сессии каждого задания $cron_jobs в отдельности
- $job_process_id, содержит порядковый номер задания из $cron_jobs

Переменные сохраниют свои значения между запусками задач по расписанию, но до тех пор пока поля в $cron_jobs[$job_process_id] не будут изменены.
```
Array // $cron_session
(
    [1] => Array
        (
            [md5] => 6e2ab79f4e8f5056a4f6a59475ffc31a
            [last_update] => 1673054971
            [complete] => 1
        )

    [start_counter] => 37
)
```


## Пример многопоточной очереди php multicore api
### Пример из файла: cron/inc/callback_addressed_queue_example.php

- вызов queue_address_manager(true); // создает список микро задач и помещает их в очередь.
- вызов queue_address_manager(false); // запускает обработчик микро задачи.

#### Сценарий выполнения:
1. Добавляем в общий список задач, событие для запуска создания очереди микро задач - workers. Обработчик события поместит микро задачи в очередь, длина которой ограничена только размером дискового пространства на сервере.
2. Добавляем в общий список задач, событие для запуска обработчиков микро задач - handlers. Событие CRON job 3 запускает парелелльно несколько процессов, каждый из которых получает из общей очереди микро задачи, и немедленно их исполняет.

## IPC передача данных между процессами
IPC реализован по типу мьютекс, все участвующие процессы получают доступ к файлу данных в монопольном режиме.

Процесс захватывает файл данных с помощью системного механизма консультативной блокировки файла, все остальные процессы выстраиваются в очередь в ожидании снятия блокировки.

Чтение\запись производится только в начале и в конце файла, нулевой кадр содержит данные для передачи между процессами. Кадры выстраиваются в очередь образуя стек, в режиме LIFO процессы опустошают очередь с конца, усекая файл данных на 1 кадр.


Стек очереди по принципу Last In, First Out последним пришёл — первым ушёл. Линейная структура данных с мгновенным доступом, чтение и запись происходит в монопольном режиме. Процесс потомок ждет пока параллельный потомок освободит файл очереди, чтобы получить свое микро задание.


Во взаимодействии принимает участие Page Cache Linux Kernel, чтение\запись первых и последних секторов файла, по умолчанию всегда будет кэшировано операционной системой. 
Таким образом передача данных между процессами будет с минимальными задержками, на уровне Shared Memory.


В реверсном варианте, когда при заполнении очереди необходимо затратить больше ресурсов чем при её опустошении, возможен обратный вызов воркеров наполняющих очередь в многопроцессорном режиме.


В приведенном примере есть реализация индексной базы данных: 
- все кадры располагаются в файле со смещеним
- есть возможность хранения индекса в кадре данных
- чтение\запись любого кадра по смещению в пределах файла данных
- файл может быть любого размера

## Функции
```
// frame - переменная для помещения в стек (string)
// frame_size - размер кадра в байтах (int)
// frame_cursor - адрес, смещение в файле, PHP_INT_MAX - LIFO режим (int)
// callback - вызывает анонимную функцию во время блокировки файла очереди (string) :void
// возвращает позицию курсора фрейма (int), 0 в случае ошибки или нулевого фрейма
queue_address_push($frame, $frame_size= 0, $frame_cursor= PHP_INT_MAX, $callback= ''); // поместить в очередь
```
1. блокирующая операция
2. ожидает освобождения файла очереди
3. блокирует файл очереди и добавляет в конец файла кадр с данными
4. после чего освобождает файл отдавая его другому процессу
5. позволяет поместить кадр в любую позицию файла очереди
6. поддерживает вызов IPC процедур в callback функции
```
// frame_size - размер кадра, в байтах (int)
// frame_cursor - адрес, смещение в файле, PHP_INT_MAX - LIFO режим (int)
// frame_replace - переменная для замены (string)
// callback - вызывает анонимную функцию во время блокировки файла очереди (string) :void
// возвращает значение со стека (string), пустой фрейм '' в случае ошибки или пустой очереди
$frame= queue_address_pop($frame_size, $frame_cursor= PHP_INT_MAX, $frame_replace= '', $callback= ''); // забрать из очереди
```
1. блокирующая операция
2. ожидает освобождения файла очереди
3. блокирует файл очереди и читает в конце файла кадр с данными
4. усекает файл, уменьшая файл на размер считанных данных
4. после чего освобождает файл отдавая его другому процессу
5. позволяет забрать\заменить кадр в любой позиции файла очереди
6. поддерживает вызов IPC процедур в callback функции


В обоих функциях применяются низкоуровневые операции, opcache оптимизатор кода и jit компилятор сокращают издержки. Данные читаются и записываются кадрами, размер кадра подбирается по объему передаваемых данных + служебные поля. 

В LIFO режиме основная работа с файлом происходит в последних секторах, благодаря этому данные легко буферизируются и кэшируются несколькими слоями Zend Engine и ядра операционной системы. При линейном чтении\записи обмен между процессами будет проходить через page cache, в linux по умолчанию включен механизм упреждающего кэширования файлов.

Время доступа к кадрам от 0.00001 секунды в зависимости от размера кадра, интенсивности параллельных запросов, размера файла, времени загрузки секторов файловой системы и т.д. 



## Структура загрузочной записи
```
// Reserved index struct
$boot= [ // 0-3 sector, frame size 4096
	'workers'=> [], // array process_id values
	'handlers'=> [], // array process_id values
	'system_variables'=> [],
	'reserved'=>[],
	'index_offset' => 4097, // data index offset
	'index_frame_size' => 1024 * 16, // data index frame size 16Kb
	'data_offset' => 1024 * 16 + 4098 + $frame_size, // data offset
	'data_frame_size' => $frame_size, // data frame size
];
```



## Структура IPC кадра
```
// zero data frame from completed job
// 12 thread: AMD Ryzen 5 2600X Six-Core Processor
// PHP 8.2.0 with Zend OPcache Jit enable, PHP-FPM
// 4 process, concurency
Array
(
    [workers] => Array
        (
            [678399] => Array
                (
                    [process_id] => 678399
                    [last_update] => 1673479681.9307
                )

        )

    [handlers] => Array
        (
            [678401] => Array
                (
                    [process_id] => 678401 // system process id, Apache\PHP FPM child process
                    [last_update] => 1673479691.9354 // time start
                    [count_start] => 250 // count processed queue element
                    [last_start] => 1673479692.2561 // time last IPC operation
                )

            [678399] => Array
                (
                    [process_id] => 678399
                    [last_update] => 1673479691.9372
                    [count_start] => 253
                    [last_start] => 1673479692.2541
                )

            [678400] => Array
                (
                    [process_id] => 678400
                    [last_update] => 1673479691.9374
                    [count_start] => 244
                    [last_start] => 1673479692.2542
                )

            [678402] => Array
                (
                    [process_id] => 678402
                    [last_update] => 1673479691.9399
                    [count_start] => 257
                    [last_start] => 1673479692.2562
                )

        )

    [system_variables] => Array
        (
        )

    [reserved] => Array
        (
        )

    [index_offset] => 4097
    [index_frame_size] => 16384
    [data_offset] => 20577
    [data_frame_size] => 95
)
```



## Потребление ресурсов
Управляющий задачами процесс запускается в фоновом режиме с использованием механизма сетевых запросов. 

cron.php работает в 4-х режимах:
1. Режим проверки тайм-аута расписания (до истечения интервала) 
- 1.7 Кб оперативной памяти 0.0005 секунды.
2. Режим проверки тайм-аута расписания (запуск отдельного процесса) 
- запуск через shell_exec wget: 2.8 Кб оперативной памяти, 0.0056 секунд (не блокирующий).
- резервный запуск через stream_context: 2 Кб оперативной памяти, 0.0418 секунд (блокирующий).
4. Отдельный процесс 
- 382Кб оперативной памяти, приоритет 39 (nice 19), MAX_EXECUTION_TIME 600 секунд по умолчанию.
5. Отдельный процесс c использованием многопоточности 
- так же как и предыдущий пункт, необходимо учитывать параметры сервера: количество ядер процессора, ограничение количества одновременных запросов к серверу, параметры конфигурации Apache\PHP FPM.
6. Резидентный режим: 380Кб оперативной памяти, приоритет 39 (nice 19)
- при значении CRON_DAEMON_MODE true управляющий процесс будет запущен постоянно. 
- при отключенных журналах в основном потоке во время простоя, нагрузкок не будет
- блокирующие управляющий процесс задачи, запускать в режиме multithreading = true

OPCache memory consumption: 69.48KB (PHP 8.2 данные, строки, байткод, DynASM, служебка). Jit buffer: ~100.0KB (+ ~100KB процесс с очередью)


Можно подключать данный CRON к любой CMS, это никак не скажется на производительности. 
### Тестовый стенд 1:
- Centos 8
- Apache/2.4.6 mpm-itk/2.4.7-04 mod_fcgid/2.3.9
- 1 ядро: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
- PHP 7.4.3 with Zend OPcache
- Sapi: mod Apache2
- GNU Wget 1.14

### Тестовый стенд 2:
- Ubuntu 22
- nginx/1.22.1 php fpm
- 12 thread: AMD Ryzen 5 2600X Six-Core Processor
- PHP 8.2.0 with Zend OPcache Jit enable
- Sapi: PHP-FPM
- GNU Wget 1.20.3

## Плоский профиль Xdebug, KCacheGrind
До запуска управляющего процесса
![before_start_main_process](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/before_start_main_process.png "before_start_main_process.png")

После запуска управляющего процесса, CRON_DAEMON_MODE false, log on
![start_main_process](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/start_main_process.png "start_main_process.png")

После запуска процесса потомка, пример include callback_cron.php, log on
![multithreading_start](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/multithreading_include_callback_cron.png "multithreading_include_callback_cron.png")

После запуска процесса потомка, пример queue_address_manager(true), log on
![example_queue_address_manager_push](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/example_queue_address_manager_push.png "example_queue_address_manager_push.png")

После запуска процесса потомка, пример queue_address_manager(false), log on
![example_queue_address_manager_pop](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/example_queue_address_manager_pop.png "example_queue_address_manager_pop.png")


## Проверка уязвимостей snyk.io
[php-cron-requests-events-snyk.pdf](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/php-cron-requests-events-snyk.pdf)
Секьюрный код, предупреждения обработаны.

## Ссылки:
- [Nginx, Php-Fpm и что это вообще?](https://perfect-inc.com/journal/nginx-php-fpm-i-chto-eto-voobshche/)
- [fastcgi_ignore_client_abort](https://nginx.org/ru/docs/http/ngx_http_fastcgi_module.html#fastcgi_ignore_client_abort)
- [PHP jit in depth](https://php.watch/articles/jit-in-depth)
- [Обзор расширения OPCache для PHP](https://habr.com/ru/company/vk/blog/310054/)
- [luajit Dynamic Assembler](https://luajit.org/dynasm.html)
- [Unofficial DynASM Documentation](https://corsix.github.io/dynasm-doc/index.html)
- [Nginx: как узнать время обработки запроса](https://codedepth.wordpress.com/2017/05/04/nginx-request-time/)
- [Тюним память и сетевой стек в Linux](https://habr.com/ru/company/odnoklassniki/blog/266005/)
- [PHP 8: Как включить JIT](https://sergeymukhin.com/blog/php-8-kak-vklyucit-jit) 
- [Опции настройки OPcache](https://www.php.net/manual/ru/opcache.configuration.php)
- [Знакомство с межпроцессным взаимодействием на Linux](https://habr.com/ru/post/122108/)
- [Semaphore, Shared Memory and IPC Functions](http://www.nusphere.com/kb/phpmanual/ref.sem.htm)
- [Использование разделяемой памяти в PHP](http://codenet.ru/webmast/php/Shared-Memory.php)
- [Page-кэш, или как связаны между собой оперативная память и файлы](https://habr.com/ru/company/smart_soft/blog/228937/)
- [Оптимизация Linux под нагрузку. Кэширование операций записи на диск](https://drupal-admin.ru/blog/%D0%BE%D0%BF%D1%82%D0%B8%D0%BC%D0%B8%D0%B7%D0%B0%D1%86%D0%B8%D1%8F-linux-%D0%BF%D0%BE%D0%B4-%D0%BD%D0%B0%D0%B3%D1%80%D1%83%D0%B7%D0%BA%D1%83-%D0%BA%D1%8D%D1%88%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5-%D0%BE%D0%BF%D0%B5%D1%80%D0%B0%D1%86%D0%B8%D0%B9-%D0%B7%D0%B0%D0%BF%D0%B8%D1%81%D0%B8-%D0%BD%D0%B0-%D0%B4%D0%B8%D1%81%D0%BA)
- [манипуляции с дисковым кэшем Linux](https://habr.com/ru/company/otus/blog/706702/)
- [Xdebug](https://xdebug.org/docs/)
- [KCacheGrind](https://kcachegrind.github.io/html/Home.html)
- [https://github.com/php/php-src](https://github.com/php/php-src)
- [php-src/ext/opcache/jit/zend_jit.h](https://github.com/php/php-src/blob/master/ext/opcache/jit/zend_jit.h)

