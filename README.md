# php-cron-requests-events
php crontab process sheduler, based on url requests/event-loop, daemon mode, multithreading, second intervals, microseconds queue api, runtime in Apache/FastCGI/FPM handler environment.

![php-cron-requests-events](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/cron.png "php-cron-requests-events")
Низкоуровневый код, отсутствие зависимостей от сторонних модулей и библиотек, минимальное потребление ресурсов

## Описание
- Реализация php планировщика с использованием URL запросов в качестве триггера событий. 
- Работает в мультипоточном режиме, не создает нагрузки на сервер.
- Работает в контексте окружения веб сервера, подходит для SHARED хостинга, не использует системный CRON.
- Позволяет в резидентном режиме выполнять PHP include "callback.php"; с секундным интервалом.
- Позволяет выполнять задачи расходующие много ресурсов с низким приоритетом. 
- Можно подключать данный CRON к любой CMS, это никак не скажется на производительности.


## Планировщик CRON
- Триггером для запуска задач служит запрос через URI
- Подключается в корневом index.php одной строчкой include('cron.php');
- Альтернативный режим подключения: в файле .htaccess добавить строку: php_value auto_append_file "cron.php"
- Еще альтернативный режим подключения: в файле php.ini добавить строку: auto_append_file="cron.php"
- Системный режим подключения: добавить в системный cron запуск через wget или curl на закачку url скрипта
- Создает в каталоге запуска подкаталоги cron/dat и cron/log
- При первом запуске создает cron/dat/cron.dat в нем хранит переменные между запусками
- В cron/log/cron.log хранит лог, есть ротация логов
- Работает в отдельном процессе с низким приоритетом 19
- Предотвращает запуск процесса если предыдущий не завершен
- Есть режим ожидания пока предыдущий процесс не закончит работу - очередь
- Работает на всех SAPI (кроме CLI): modApache, [PHP-FPM](https://perfect-inc.com/journal/nginx-php-fpm-i-chto-eto-voobshche/), CGI, FastCGI. Версии PHP от 5.4 до 8.2.0
- Минимальный объем PHP кода: 27.8 Кб (16.6 Кб без пробелов и комментариев)
- Дружественный код под [Jit\OPCache](https://php.watch/articles/jit-in-depth) оптимизацию, [OPCache memory](https://habr.com/ru/company/vk/blog/310054/): 90.58KB (данные, строки, байткод, [DynASM](https://luajit.org/dynasm.html), служебка)


### Пример запуска задачи
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
	'time' => '21:00:00', // "hours:minutes:seconds"execute job on the specified time every day
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_addressed_queue_example.php",
	'queue_address_manager' => true, // use with queue_address_manager(true), in worker mode
	'multithreading' => true
];

for( // CRON job 3, multicore example, four cores, 
	$i= 0;
	$i< 4; // Max processor cores
	$i++	
) {
	$cron_jobs[]= [ // CRON Job 3, multicore example
		'time' => '21:00:10', //  "hours:minutes:seconds" execute job on the specified time every day
		'callback' => CRON_SITE_ROOT . "cron/inc/callback_addressed_queue_example.php",
		'queue_address_manager' => false, // use with queue_address_manager(false), in handler mode
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
- multithreading - Запуск в фоновом режиме true\false

Если указаны параметры date или time то аргумент interval будет проигнорирован, точность регулируется параметром CRON_DELAY, зависит от активности запросов к хосту, если в момент наступления времени события, запросов к серверу не было - запустит задачу при первом запуске.

Можно запустить управляющий процесс в резидентном режиме, установив значение CRON_DELAY = 0, в этом случае возможна проверка заданий непрерывно в цикле, с паузой между итерациями. В данном случае длительная задача будет блокировать основной поток, рекомендую запускать задачи в режиме multithreading true.

Выход из резидентного режима осуществляется через смену параметра CRON_DELAY отличным от 0. Перезагрузка происходит автоматически, при смене параметров.

Можно запустить задачу на нескольких ядрах, потребуется реализация обработчика очереди. Если ядер на сервере мало, то нагружать можно мелкими задачами в которых большая часть времени уходит например на скачивание - IO bound. Одновременно запущенные CPU bound задачи, желательно ограничить по количеству ядер на сервере, в таком случае CPU bound нагрузка будет расходовать оставшееся время ядра.

- По умолчанию Apache\PHP FPM workers сами распределяют CPU bound нагрузку между свободными ядрами.
- За освобождением памяти следит Zend Engine сборщик мусора Garbage Collection.
- Включенный OPcache позволяет сэкономить накладные расходы системных ресурсов при запуске потока, и оптимизирует исполнение кода.


Основное отличие от консольного CLI запуска скриптов по расписанию - это состав PHP модулей. В зависимости от конфигурации сервера или хостинг панели, состав модулей может различаться в CLI и Apache\PHP FPM. В данной реализации планировщика окружение PHP будет содержать все модули и расширения доступные в веб среде. Использование многоядерной очереди, в некоторых случаях подходит для замены микросервисов.


### Параметры запуска
- define("CRON_LOG_FILE", CRON_ROOT . "cron/log/cron.log"); // Путь к файлу журнала, false - отключает журнал
- define("CRON_DAT_FILE", CRON_ROOT . "cron/dat/cron.dat"); // Путь к системному файлу диспетчера потока
- define("CRON_QUEUE_FILE", CRON_ROOT . 'cron/dat/queue.dat'); // Путь к системному файлу многопроцессной очереди
- define("CRON_DELAY", 0); // Тайм аут до следующего запуска в секундах, 0 - резидентный режим (фоновая служба) 
- define("CRON_LOG_ROTATE_MAX_SIZE", 10 * 1024 * 1024); // Максимальный размер логов в МБ
- define("CRON_LOG_ROTATE_MAX_FILES", 5); // Хранить максимум 5 файлов архивных журналов
- define("CRON_URL_KEY", 'my_secret_key'); // Ключ запуска в URI
- $paths= "/usr/bin:/usr/local/bin"; // Если переменная окружения PATH пуста, используем системные пути, каталоги поиска исполняемых файлов: wget, curl
- $profiler['time'] > $time - 15 // 15 сек. интервал проверки времени модификации cron.php, если новее то перезапуск
- $profiler['callback_time'] > $time - 60 // 60 сек. интервал проверки времени модификации include callback файлов, если новее то перезапуск
- $cron_session['log_rotate_last_update'] > time() - 600 // 600 сек. задержка для ротации лог файлов
- ini_set('MAX_EXECUTION_TIME', 600); // Максимальное время выполнения, 0 в резидентном режиме
- 'timeout' => 0.04 // Время блокировки, тайм аут для экстренного запуска через fopen. Время ответа сервера * 10 в зависимости от [Linux Kernel Load Average](https://habr.com/ru/company/odnoklassniki/blog/266005/)\OPcache\FLOPS
- usleep(2000); // В примерах многопроцессной очереди имитирует нагрузку, в данной точке предпологается обработка данных, неограниченно по времени

При подборе параметра CRON_DELAY можно посмотреть в логи сервера, обычно хост ежеминутно опрашивается массой ботов.


### Обработчик событий CRON
#### Пример из файла: cron/inc/callback_cron.php

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


### Пример многопоточной очереди php multicore api
#### Пример из файла: cron/inc/callback_addressed_queue_example.php

- вызов queue_address_manager_extend(true); // создает список микро задач и помещает их в очередь.
- вызов queue_address_manager_extend(false); // запускает обработчик микро задачи.

#### Сценарий выполнения:
1. Добавляем в общий список задач, событие для запуска создания очереди микро задач - workers. Обработчик события поместит микро задачи в очередь, длина которой ограничена только размером дискового пространства на сервере.
2. Добавляем в общий список задач, событие для запуска обработчиков микро задач - handlers. Событие CRON job 3 запускает парелелльно несколько процессов, каждый из которых получает из общей очереди микро задачи, и немедленно их исполняет.

#### IPC передача данных между процессами
[IPC реализован по типу мьютекс](https://habr.com/ru/post/122108/), все участвующие процессы получают доступ к файлу данных в монопольном режиме.

Процесс захватывает файл данных с помощью системного механизма консультативной блокировки файла, все остальные процессы выстраиваются в очередь в ожидании снятия блокировки.

Чтение\запись производится только в начале и в конце файла, нулевой кадр содержит данные для передачи между процессами. Кадры выстраиваются в очередь образуя стек, в режиме LIFO процессы опустошают очередь с конца, усекая файл данных на 1 кадр.


Стек очереди по принципу Last In, First Out последним пришёл — первым ушёл. Линейная структура данных с мгновенным доступом, чтение и запись происходит в монопольном режиме. Процесс потомок ждет пока параллельный потомок освободит файл очереди, чтобы получить свое микро задание.


Во взаимодействии принимает участие [Page Cache Linux Kernel](https://habr.com/ru/company/smart_soft/blog/228937/), чтение\запись первых и последних секторов файла, по умолчанию всегда будет [кэшировано операционной системой](https://drupal-admin.ru/blog/%D0%BE%D0%BF%D1%82%D0%B8%D0%BC%D0%B8%D0%B7%D0%B0%D1%86%D0%B8%D1%8F-linux-%D0%BF%D0%BE%D0%B4-%D0%BD%D0%B0%D0%B3%D1%80%D1%83%D0%B7%D0%BA%D1%83-%D0%BA%D1%8D%D1%88%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5-%D0%BE%D0%BF%D0%B5%D1%80%D0%B0%D1%86%D0%B8%D0%B9-%D0%B7%D0%B0%D0%BF%D0%B8%D1%81%D0%B8-%D0%BD%D0%B0-%D0%B4%D0%B8%D1%81%D0%BA). 
Таким образом передача данных между процессами будет с минимальными задержками, на уровне Shared Memory.


В реверсном варианте, когда при заполнении очереди необходимо затратить больше ресурсов чем при её опустошении, возможен обратный вызов воркеров наполняющих очередь в многопроцессорном режиме.


В приведенном примере есть реализация индексной базы данных: 
- все кадры располагаются в файле со смещеним
- есть возможность хранения индекса в файле данных
- чтение\запись любого кадра по смещению в пределах файла данных
- файл может быть любого размера

#### Функции
```
// value - переменная для помещения в стек
// frame_size - размер кадра, в байтах
// frame_cursor - адрес, смещение в файле
// callback - вызывает функцию во время блокировки файла очереди
// возвращает позицию курсора фрейма
queue_address_push($value, $frame_size= false, $frame_cursor= false, $callback= false); // поместить в очередь
```
1. блокирующая операция
2. ожидает освобождения файла очереди
3. блокирует файл очереди и добавляет в конец файла кадр с данными
4. после чего освобождает файл отдавая его другому процессу
5. позволяет поместить кадр в любую позицию файла очереди

```
// frame_size - размер кадра, в байтах
// frame_cursor - адрес, смещение в файле
// frame_replace - переменная для замены
// callback - вызывает функцию во время блокировки файла очереди
// возвращает значение со стека
$multicore_long_time_micro_job= queue_address_pop($frame_size, $frame_cursor= false, $frame_replace= false, $callback= false); // забрать из очереди
```
1. блокирующая операция
2. ожидает освобождения файла очереди
3. блокирует файл очереди и читает в конце файла кадр с данными
4. усекает файл, уменьшая файл на размер считанных данных
4. после чего освобождает файл отдавая его другому процессу
5. позволяет забрать\заменить кадр в любой позиции файла очереди


В обоих функциях применяются низкоуровневые операции, opcache оптимизатор кода и jit компилятор сокращают издержки. Данные читаются и записываются кадрами, размер кадра подбирается по объему передаваемых данных + служебные поля. 

В LIFO режиме основная работа с файлом происходит в последних секторах, благодаря этому данные легко буферизируются и кэшируются несколькими слоями Zend Engine и ядра операционной системы. При линейном чтении\записи обмен между процессами будет проходить по короткой дистанции.

Время доступа к кадрам от 0.00005 секунды в зависимости от размера кадра, интенсивности параллельных запросов, размера файла, времени загрузки секторов файловой системы и т.д. 



#### Структура загрузочной записи
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



#### Структура IPC кадра
```
// zero data frame from completed job
// 12 thread: AMD Ryzen 5 2600X Six-Core Processor
// PHP 8.2.0 with Zend OPcache, PHP-FPM
// 4 process, concurency
Array
(
    [workers] => Array
        (
            [281372] => Array
                (
                    [process_id] => 281372
                    [last_update] => 1673202601.72
                )

        )

    [handlers] => Array
        (
            [281373] => Array
                (
                    [process_id] => 281373 // system process id, Apache\PHP FPM child process
                    [last_update] => 1673202611.7216 // time start
                    [count_start] => 237 // count processed queue element
                    [last_start] => 1673202612.027 // time last IPC operation
                )

            [281374] => Array
                (
                    [process_id] => 281374
                    [last_update] => 1673202611.7221
                    [count_start] => 274
                    [last_start] => 1673202612.0251
                )

            [281375] => Array
                (
                    [process_id] => 281375
                    [last_update] => 1673202611.7228
                    [count_start] => 249
                    [last_start] => 1673202612.025
                )

            [281370] => Array
                (
                    [process_id] => 281370
                    [last_update] => 1673202611.7239
                    [count_start] => 244
                    [last_start] => 1673202612.0271
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



### Потребление ресурсов
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
- при значении CRON_DELAY = 0 управляющий процесс будет запущен постоянно. 
- при отключенных журналах в основном потоке во время простоя, нагрузкок не будет
- блокирующие управляющий процесс задачи, запускать в режиме multithreading = true


Можно подключать данный CRON к любой CMS, это никак не скажется на производительности. 
##### Тестовый стенд 1:
- Centos 8
- Apache/2.4.6 mpm-itk/2.4.7-04 mod_fcgid/2.3.9
- 1 ядро: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
- PHP 7.4.3 with Zend OPcache
- GNU Wget 1.14

##### Тестовый стенд 2:
- Ubuntu 22
- nginx/1.22.1 php fpm
- 12 thread: AMD Ryzen 5 2600X Six-Core Processor
- PHP 8.2.0 with Zend OPcache, PHP-FPM
- GNU Wget 1.20.3

##### Плоский профиль Xdebug, KCacheGrind
До запуска управляющего процесса
![before_start_main_process](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/before_start_main_process.png "before_start_main_process.png")

После запуска управляющего процесса, CRON_DELAY=10
![start_main_process](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/start_main_process.png "start_main_process.png")

После запуска процесса потомка, include cron/inc/callback_cron.php
![multithreading_start](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/multithreading_start.png "multithreading_start.png")

После запуска процесса потомка, пример наполнения очереди include cron/inc/callback_addressed_queue_example.php
![example_queue_address_manager_extend_push](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/example_queue_address_manager_extend_push.png "example_queue_address_manager_extend_push.png")

После запуска процесса потомка, пример извлечения из очереди include cron/inc/callback_addressed_queue_example.php
![example_queue_address_manager_extend_pop_flock](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/example_queue_address_manager_extend_pop_flock.png "example_queue_address_manager_extend_pop_flock.png")

