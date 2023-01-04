# php-cron-requests-events
php crontab, based on url requests/event-loop, daemon mode, multithreading, second intervals, microseconds queue api, runtime in FastCGI\FPM handler environment.

![php-cron-requests-events](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/cron.png "php-cron-requests-events")
Низкоуровневый код, отсутствие зависимостей от сторонних модулей и библиотек, минимальное потребление ресурсов

## Описание
- Реализация php планировщика с использованием url запросов в качестве триггера событий. 
- Работает в мультипоточном режиме, не создает нагрузки на сервер.
- Работает в контексте окружения веб сервера, не использует системный CRON.
- Позволяет в резидентном режиме выполнять php include "callback.php"; с секундным интервалом.
- Позволяет выполнять задачи расходующие много ресурсов с низким приоритетом. 
- Можно подключать данный CRON к любой CMS, это никак не скажется на производительности.


## Планировщик CRON
- Триггером для запуска задач служит запрос через URI
- Подключается в корневом index.php одной строчкой include('cron.php');
- Альтернативный режим подключения: в файле .htaccess добавить строку: php_value auto_append_file "cron.php"
- Создает в корне сайта подкаталоги cron/dat и cron/log
- При первом запуске создает cron/dat/cron.dat в нем хранит переменные между запусками
- В cron/log/cron.log хранит лог, есть ротация логов
- Работает в отдельном процессе с низким приоритетом 15
- Предотвращает запуск процесса если предыдущий не завершен
- Есть режим ожидания пока предыдущий процесс не закончит работу - очередь
- Минимальные системные требования: PHP 5.3.1

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

Можно запустить задачу на нескольких ядрах, потребуется реализация обработчика очереди. Если ядер на сервере мало, то нагружать можно мелкими задачами в которых большая часть времени уходит например на скачивание - IO bound. Одновременно запущенные CPU bound задачи, желательно ограничить по количеству ядер на сервере.

Включенный OPcache позволяет сэкономить накладные расходы системных ресурсов при запуске потока, и оптимизирует исполнение кода.


### Параметры запуска
- define("CRON_LOG_FILE", CRON_SITE_ROOT . "cron/log/cron.log"); // Путь к файлу журнала, false - отключает журнал
- define("CRON_DAT_FILE", CRON_SITE_ROOT . "cron/dat/cron.dat"); // Путь к системному файлу диспетчера потока
- define("CRON_DELAY", 180); // Тайм аут до следующего запуска в секундах, повышает нагрузку на низких значениях, увеличивает точность для даты и времени 
- define("CRON_LOG_ROTATE_MAX_SIZE", 10 * 1024 * 1024); // Максимальный размер логов в МБ
- define("CRON_LOG_ROTATE_MAX_FILES", 5); // Хранить максимум 5 файлов архивных журналов
- define("CRON_URL_KEY", 'my_secret_key'); // Ключ запуска в URI

При подборе параметра CRON_DELAY можно посмотреть в логи сервера, обычно хост ежеминутно опрашивается массой ботов.


### Пример многопоточной очереди php multicore api
```
function queue_address_manager_extend($mode){ // example: multicore queue
	$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue.dat';
	$index_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_index.dat';
	$frame_size= 95;
		
	if(!file_exists($dat_file)) touch($dat_file);
		
	if($mode){
		// example: multicore queue worker
		// use:
		// queue_address_push($multicore_long_time_micro_job); // add micro job in queue from worker process
			
		$index= []; // index - address array, frame_cursor is key of array
		unlink($dat_file); // reset DB file
		touch($dat_file);
			
			
		// 1 core: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
		// PHP 7.4.3 with Zend OPcache
		// 1 process, no concurency
		// execution time: 0.046951055526733 end - start, 1000 cycles
		for($i= 0; $i < 1000; $i++){
			$frame_cursor= queue_address_push([
				'url'=> "https://multicore_long_time_micro_job?param=" . $i,
				'count'=> $i
			], $frame_size);
				
			if($frame_cursor !== false) $index[$i]= $frame_cursor; 
		}
						
		if(count($index) == 1000){ // SIZE DATA FRAME ERROR if count elements != 1000
			file_put_contents($index_file, serialize($index), LOCK_EX); 
			// 13774 bytes index file size
			// 95000 bytes db file size
		}
			
	} else {
		// example: multicore queue handler
		// use:
		// $multicore_long_time_micro_job= queue_address_pop(); // get micro job from queue in children processess 
		// exec $multicore_long_time_micro_job - in a parallel thread
			
		// use index mode
		// addressed data base, random access
		$index= unserialize(file_get_contents($index_file));
			
		// example 1, get first element
		$multicore_long_time_micro_job= queue_address_pop($frame_size, $index[0]);
			
		// example 2, get last - 10 element, and get first frame in callback function
		function queue_address_pop_callback(& $queue_resource, & $frame_size, & $frame_cursor, & $frame_replace){
			fseek($queue_resource, 0); // get data frame
			$raw_frame= fread($queue_resource, $frame_size);
			$value= unserialize(trim($raw_frame));
					
			if(CRON_LOG_LEVEL > 3){
				if(CRON_LOG_FILE){
					@file_put_contents(
						CRON_LOG_FILE, 
						print_r([$frame_size, $frame_cursor, $value, $raw_frame], true),
						FILE_APPEND | LOCK_EX
					);
				}
			}					
		}
		$multicore_long_time_micro_job= queue_address_pop($frame_size, $index[count($index) - 10], false, "queue_address_pop_callback");
			
		// example 3, linear read
		for($i= 100; $i < 800; $i++){ // execution time:  0.037011861801147, 1000 cycles, address mode
			$multicore_long_time_micro_job= queue_address_pop($frame_size, $index[$i]);
		}
			
		// example 4, replace frames in file
		for($i= 10; $i < 500; $i++){ // execution time:  0.076093912124634, 1000 cycles, address mode, frame_replace
			$multicore_long_time_micro_job= queue_address_pop($frame_size, $index[$i], true);
			unset($index[$i]);
		}
		
		
		// example 5, random access
		shuffle($index);
		for($i= 0; $i < 10; $i++){// execution time: 0.035359859466553, 1000 cycles, address mode, random access
			$multicore_long_time_micro_job= queue_address_pop($frame_size, $index[$i]);
		}

		// example 6, use LIFO mode
		// execution time: 0.051764011383057 end - start, 1000 cycles
		while(true){ // example: loop from the end
			$multicore_long_time_micro_job= queue_address_pop($frame_size);
			
			if($multicore_long_time_micro_job === false) {
				break 1;
			} elseif($multicore_long_time_micro_job !== true) {
				// $content= file_get_contents($multicore_long_time_micro_job['url']);
				// file_put_contents('cron/temp/url-' . $multicore_long_time_micro_job['count'] . '.html', $content);
				
				if(CRON_LOG_LEVEL > 3){
					if(CRON_LOG_FILE){
						@file_put_contents(
							CRON_LOG_FILE, 
								microtime(true) . 
								" INFO: queue_manager " . 
								$multicore_long_time_micro_job['count'] . " \n",
							FILE_APPEND | LOCK_EX
						);
					}
				}					
			}
		}
		
		unlink($dat_file); // reset DB file
		unlink($index_file); // reset index file
	}
}

if(isset($job['queue_address_manager'])){
	// true - call in multithreading context api cron.php, in worker mode
	// false - call in multithreading context api cron.php, in handler mode
	queue_address_manager_extend($job['queue_address_manager']);
}
```
- вызов queue_address_manager_extend(true); // создает список микро задач и помещает их в очередь.
- вызов queue_address_manager_extend(false); // запускает обработчик микро задачи.

#### Сценарий выполнения:
1. Добавляем в общий список задач, событие для запуска создания очереди микро задач - workers. Обработчик события поместит микро задачи в очередь, длина которой ограничена только размером дискового пространства на сервере.
2. Добавляем в общий список задач, событие для запуска обработчиков микро задач - handlers. Событие CRON job 3 запускает парелелльно несколько процессов, каждый из которых получает из общей очереди микро задачи, и немедленно их исполняет.

#### IPC передача данных между процессами
Стек очереди организован по принципу Last In, First Out последним пришёл — первым ушёл. Линейная структура данных с мгновенным доступом, чтение и запись происходит в монопольном режиме. Процесс потомок ждет пока параллельный потомок освободит файл очереди, чтобы получить свое микро задание.

В реверсном варианте, когда при заполнении очереди необходимо затратить больше ресурсов чем при её опустошении, возможен обратный вызов воркеров наполняющих очередь в многопроцессорном режиме.

Есть адресация стека, любой кадр можно забрать или забрать с заменой, либо поместить в стек в позицию курсора.

Нулевой кадр можно зарезервировать под системные переменные и\или индекс для кадров, в таком случае вам потребуется дописать обертку к низкоуровневым функциям под ваши данные.

#### Функции
```
// value - переменная для помещения в стек
// frame_size - размер кадра, в байтах
// frame_cursor - адрес, смещение в файле
// callback - вызывает функцию во время блокировки файла очереди
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

### Потребление ресурсов
Управляющий задачами процесс запускается в фоновом режиме с использованием механизма сетевых запросов. 

cron.php работает в 4-х режимах:
1. Режим проверки тайм-аута расписания (до истечения интервала) 
- 1.7 Кб оперативной памяти 0.0005 секунды.
2. Режим проверки тайм-аута расписания (запуск отдельного процесса) 
- запуск через shell_exec wget: 2.8 Кб оперативной памяти, 0.0056 секунд (не блокирующий).
- резервный запуск через stream_context: 2 Кб оперативной памяти, 0.0418 секунд (блокирующий).
4. Отдельный процесс 
- ресурсов от скрипта обработчика запроса не потребляет, приоритет 15, MAX_EXECUTION_TIME 600 секунд по умолчанию.
5. Отдельный процесс c использованием многопоточности 
- так же как и предыдущий пункт, необходимо учитывать параметры сервера: количество ядер процессора и ограничение количества одновременных запросов к серверу.
6. Резидентный режим: 380Кб оперативной памяти, низкий приоритет 15
- при значении CRON_DELAY = 0 управляющий процесс будет запущен постоянно.
- блокирующие управляющий процесс задачи, запускать в режиме multithreading = true

Можно подключать данный CRON к любой CMS, это никак не скажется на производительности. 
##### Тестовый стенд:
- Centos 8
- Apache/2.4.6 mpm-itk/2.4.7-04 mod_fcgid/2.3.9
- 1 ядро: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
- PHP 7.4.3 with Zend OPcache
- GNU Wget 1.14
