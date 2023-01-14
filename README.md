# php cron requests events

php crontab process sheduler, based on url requests/event-loop, daemon mode, multithreading, second intervals, microseconds queue api, runtime in modApache/CGI/FPM sapi environment.

[README_RU.md на русском](https://github.com/commeta/php-cron-requests-events/blob/main/README_RU.md)

![php-cron-requests-events](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/php_cron_in_php-fpm_htop_process_list.png "php-cron-requests-events")

Procedural code based on built-in functions, no dependencies on third-party modules and libraries, minimal resource consumption

## Description
- Implementation of php scheduler using request URL as event trigger.
- Works in multi-threaded mode, does not create a load on the server.
- Suitable for SHARED hosting, there may be restrictions on the use of CPU resources.
- Useful when there is only FTP access to the server or file upload via CMS.
- It will help save time with frequent site moves, it is not dependent on the system CRON.
- Allows you to schedule PHP functions or include callback scripts.
- Wide range for launching tasks: intervals from a second, there is a launch at a specified time.
- Allows you to perform tasks that consume a lot of resources with low priority.
- You can connect this CRON to any CMS, it will not affect performance in any way.
- Allows to [execute functions in a parallel process](#parallel-function-launch).


## CRON scheduler
- The trigger for running tasks is a request via URI
- Connected in the root `index.php` with one line `include('cron.php');`
- Alternative connection mode: add line in `.htaccess` file: `php_value auto_append_file "cron.php"`
- Another alternative connection mode: add the line in the `php.ini` file: `auto_append_file="cron.php"`
- System connection mode: add `php /path/cron.php` command to system cron
- Creates `cron/dat` and `cron/log` subdirectories in the startup directory
- Creates `cron/dat/cron.dat` on first run and stores variables between runs
- In `cron/log/cron.log` stores the log, there is a log rotation
- Runs in a separate process with low priority 19
- Prevents a process from starting if the previous one has not completed
- There is a waiting mode until the previous process finishes work - queue
- Works on all SAPIs: (CLI startup only), modApache, PHP-FPM, CGI. PHP versions from 5.4 to 8.2.0
- Minimum PHP code size: 27.8 Kb (16.6 Kb without spaces and comments)
- Friendly code for opcache.jit compiler

## Install
Download the script to any site directory:
- CLI: 
```
wget "https://raw.githubusercontent.com/commeta/php-cron-requests-events/main/cron.php"
```

dependency install
- PHP: 
```
 if(!file_exists(dirname(__FILE__).'/cron.php'))  // before include('cron.php') connector
 	file_put_content(
 		dirname(__FILE__).'/cron.php', 
 		file_get_contents(
 			'https://raw.githubusercontent.com/commeta/php-cron-requests-events/main/cron.php'
 		)
 	);
```

## Launch parameters
```
$cron_settings=[
	'log_file'=> $cron_root . 'cron/log/cron.log', // Path to log file, false - disables logging
	'dat_file'=> $cron_root . 'cron/dat/cron.dat', // Path to the thread manager system file
	'queue_file'=> $cron_root . 'cron/dat/queue.dat', // Path to the multiprocess queue system file
	'site_root'=> '',
	'delay'=> 1, // Timeout until next run in seconds
	'daemon_mode'=> true, // true\false resident mode (background service)
	'log_rotate_max_size'=> 10 * 1024 * 1024, // Maximum log size log 10 in MB
	'log_rotate_max_files'=> 5, // Store max 5 archived log files
	'log_level'=> 5, // Log verbosity: 2 warning, 5 debug
	'url_key'=> 'my_secret_key', // Launch key in URI
];

$paths="/usr/bin:/usr/local/bin"; // If the PATH environment variable is empty, use system paths, executable search directories: wget, curl
$profiler['time'] > $time - 15 // 15 sec. cron.php modification time check interval, if newer then restart
$profiler['callback_time'] > $time - 60 // 60 sec. modification time interval check include callback files, if newer then restart
$cron_session['log_rotate_last_update'] > time() - 600 // 600 sec. delay for log file rotation
ini_set('MAX_EXECUTION_TIME', 600); // Maximum execution time, 0 in resident mode
'timeout' => 0.04 // Block time, timeout for emergency start via fopen. Server response time * 10 depending on Linux Kernel Load Average\OPcache\FLOPS
usleep(2000); // In examples of a multi-process queue, it simulates a load, data processing is assumed at this point, unlimited in time
$host="localhost"; // To run through the CLI console, enter your domain name and the path to the root directory of the site $document_root
```

When selecting the `$cron_settings['delay']` parameter, you can look at the server logs, usually the host is polled every minute by a mass of bots.


## Task start example
In the context of the `cron.php` file, the CRON Job section
```
###########################
# EXAMPLES
$cron_jobs= [];

###########################
$cron_jobs[]= [ // CRON Job 1, example
	'interval' => 0, // start interval 1 sec
	'callback' => $cron_root . "cron/inc/callback_cron.php",
	'multithreading' => false
];
##########


###########################
$cron_jobs[]= [ // CRON Job 2, multithreading example
	'interval' => 10, // start interval 10 sec
	'callback' => $cron_root . "cron/inc/callback_cron.php",
	'multithreading' => true
];
##########


###########################
$cron_jobs[]= [ // CRON Job 3, multicore example
	'time' => '04:24:00', // "hours:minutes:seconds"execute job on the specified time every day
	//'callback' => $cron_root . "cron/inc/callback_addressed_queue_example.php",
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
		'time' => '04:24:10', //  "hours:minutes:seconds" execute job on the specified time every day
		//'callback' => $cron_root . "cron/inc/callback_addressed_queue_example.php",
		'function' => "queue_address_manager", // if need file include: comment this, uncomment callback
		'param' => false, // use with queue_address_manager(false), in handler mode
		'multithreading' => true
	];
}

##########


###########################
$cron_jobs[]= [ // CRON Job 4, multithreading example
	'date' => '10-01-2023', // "day-month-year" execute job on the specified date
	'callback' => $cron_root . "cron/inc/callback_cron.php",
	'multithreading' => true
];
##########
```
- `'interval'` - Delay before starting
- `'time'` - Sets the time to start in 24th format '07:20:00', if the date is not specified, it executes every day
- `'date'` - Sets the start date in the format '31-12-2022'
- `'callback'` - PHP script, will be executed after the interval
- `'function'` - Performs the specified function, if param is specified, then passes it in the parameters
- `'multithreading'` - Running in the background true\false

If the date or time parameters are specified, then the interval argument will be ignored, the accuracy is regulated by the `$cron_settings['delay']` parameter, depends on the activity of requests to the host, if there were no requests to the server at the time of the event, it will start the task at the first start.

You can start the control process in resident mode by setting the `'daemon_mode'=> true`, in which case it is possible to check jobs continuously in a loop, with a pause between iterations. In this case, a long-running task will block the main thread, I recommend running tasks in `'multithreading'=> true` mode.

Exiting the resident mode is done by changing the `'daemon_mode'=> false` parameter. Reboot occurs automatically when parameters are changed.

It is possible to run a task on multiple cores, a queue handler implementation would be required. If there are few cores on the server, then you can load with small tasks in which most of the time is spent, for example, on downloading - IO bound. It is desirable to limit the simultaneously running CPU bound tasks by the number of cores on the server, in which case the CPU bound load will consume the remaining core time.

- By default, Apache\PHP FPM workers distribute the CPU bound load among the free cores themselves.
- Memory release is monitored by Zend Engine Garbage Collection garbage collector.
- Enabled OPcache saves system resource overhead when running a thread, and optimizes code execution.
- Using a multi-core queue, in some cases suitable for replacing microservices.


## CRON event handler
### Example from file: `callback_cron.php`

This file will be launched according to the schedule, the path to the file is specified in the field `$cron_jobs[$job_process_id]['callback']`

#### Variables
- `$cron_session`, stores the service fields of the session of each `$cron_jobs` job separately
- `$job_process_id`, contains the job sequence number from `$cron_jobs`
- `$cron_settings`, contains `cron.php` settings

The variables will retain their values between scheduled task runs, but until the fields in `$cron_jobs[$job_process_id]` are changed.
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


## php multicore api multithreaded queue example
### Example from file: `callback_addressed_queue_example.php`

- call `queue_address_manager(true); // creates a list of micro tasks and puts them in the queue.`
- call `queue_address_manager(false); // starts the micro task handler.`

#### Execution script:
1. Add to the general list of tasks, an event to start the creation of a queue of micro tasks - workers. The event handler will place the micro tasks in a queue, the length of which is limited only by the amount of disk space on the server.
2. Add to the general list of tasks, an event for launching micro task handlers - handlers. The CRON job 3 event launches several processes in parallel, each of which receives micro tasks from a common queue, and immediately executes them.

## IPC data transfer between processes
IPC is implemented as a mutex, all participating processes access the data file in exclusive mode.

A process acquires a data file using the system's advisory file lock mechanism, and all other processes queue up to wait for the lock to be released.

Reading\writing is done only at the beginning and at the end of the file, the zero frame contains data for transfer between processes. Frames are queued forming a stack; in LIFO mode, processes empty the queue from the end, truncating the data file by 1 frame.


Queue stack based on the Last In, First Out principle, last in, first out. A linear data structure with instantaneous access, reading and writing occurs in exclusive mode. The child process waits for the parallel child to release the queue file to receive its micro job.


Page Cache Linux Kernel takes part in the interaction, reading\writing the first and last sectors of the file, by default it will always be cached by the operating system.
Thus, data transfer between processes will be with minimal delays, at the Shared Memory level.


In the reverse case, when filling the queue requires more resources than emptying it, it is possible to call back the workers filling the queue in multiprocessor mode.

In the example given, there is an implementation of an index database:
- all frames are located in the file with an offset
- it is possible to store the index in the data frame
- read\write any frame by offset within the data file
- file can be any size

## Functions
```
// frame - variable to put on the stack (string)
// frame_size - frame size in bytes (int)
// frame_cursor - address, file offset, PHP_INT_MAX - LIFO mode (int)
// callback - calls an anonymous function while blocking the queue file (string) :void
// returns the position of the frame cursor (int), 0 on error or null frame
queue_address_push($frame, $frame_size= 0, $frame_cursor= PHP_INT_MAX, $callback= ''); // queue
```
1. blocking operation
2. waiting for the queue file to be released
3. locks the queue file and adds a frame with data to the end of the file
4. then releases the file by giving it to another process
5. Allows you to place a frame at any position in the queue file
6. supports calling IPC procedures in callback functions
```
// frame_size - frame size, in bytes (int)
// frame_cursor - address, file offset, PHP_INT_MAX - LIFO mode (int)
// frame_replace - variable to replace (string)
// callback - calls an anonymous function while blocking the queue file (string) :void
// returns frame from stack (string), empty frame '' in case of error or empty queue
$frame= queue_address_pop($frame_size, $frame_cursor= PHP_INT_MAX, $frame_replace= '', $callback= ''); // pick up from the queue
```
1. blocking operation
2. waiting for the queue file to be released
3. locks the queue file and reads a data frame at the end of the file
4. truncates the file, reducing the file by the size of the read data
4. then releases the file by giving it to another process
5. allows you to pick up / replace a frame at any position in the queue file
6. supports calling IPC procedures in callback functions


Both functions use low-level operations, the opcache code optimizer and the jit compiler reduce overhead. Data is read and written in frames, the frame size is selected according to the amount of transmitted data + service fields.

In LIFO mode, the main work with the file takes place in the last sectors, thanks to which the data is easily buffered and cached by several layers of the Zend Engine and the operating system kernel. With linear reading/writing, the exchange between processes will go through the page cache, in linux, the mechanism for forward caching of files is enabled by default.

Frame access time from 0.00001 second depending on the frame size, intensity of parallel requests, file size, loading time of file system sectors, etc.



## Boot record structure
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



## IPC frame structure
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


## Parallel function launch
#### Execution script:
- `include('cron.php')` in your script
- Add the `'function'` to the task list `$cron_jobs[]` with the given interval `'interval'=> 0`
- Specify a timeout before running `$cron_settings['delay']= -1`
- Disable resident mode `$cron_settings['daemon_mode']= false`
- Specify startup mode `'multithreading'=> false`
- Function must be defined in `cron.php` file
- Parameters are passed through task parameters `$cron_jobs[]['param']`
- It is possible to pass parameters through a session file
- Each subdirectory will run a separate copy of `cron.php`

To transfer mutable data, use the api functions:
- `queue_address_push();`
- `queue_address_pop();`

```
// Example send param, before include('cron.php'):

###########################
if(!function_exists('queue_address_push')){
	// frame - pushed frame (string)
	// frame_size - set frame size (int)
	// frame_cursor - PHP_INT_MAX for LIFO mode, get frame from cursor position (int)
	// return frame cursor offset (int), 0 if error or boot frame
	function queue_address_push($frame, $frame_size= 0, $frame_cursor= PHP_INT_MAX, $callback= '') // :int 
	{ // push data frame in stack
		global $cron_settings;
		
		$queue_resource= fopen($cron_settings['queue_file'], "r+");
		$return_cursor= 0;

		if($frame_size !== 0){
			if($frame_size < mb_strlen($frame)){ // fill
				return $return_cursor;
			}
		} else {
			return $return_cursor;
		}

		if(flock($queue_resource, LOCK_EX)) {
			if($callback !== '') @call_user_func($callback, $queue_resource, $frame_size, $frame_cursor); // callback anonymous
			
			$stat= fstat($queue_resource);
			if($frame_cursor !== PHP_INT_MAX){
				$return_cursor= $frame_cursor;
				$frame_length= mb_strlen($frame);
				for($i= $frame_length; $i <= $frame_size; $i++) $frame.= chr(0);
				
				fseek($queue_resource, $frame_cursor);
				fwrite($queue_resource, $frame, $frame_size);
				fflush($queue_resource);
			} else {
				$return_cursor= $stat['size'];
				fseek($queue_resource, $stat['size']);
				fwrite($queue_resource, $frame, $frame_size);
				fflush($queue_resource);
			}
			
			flock($queue_resource, LOCK_UN);
		}
		
		fclose($queue_resource);
		return $return_cursor;
	}
}
###########################
$frame_size= 4096;
$cron_root= dirname(__FILE__) . DIRECTORY_SEPARATOR;
$cron_settings= ['queue_file'=> $cron_root . 'cron/dat/queue.dat'];
if(!file_exists($cron_settings['queue_file'])) touch($cron_settings['queue_file']);

$params= [];
queue_address_push(serialize($params), $frame_size);
include('cron.php');
```


```
// Example get param, function called in parallel process cron.php
$cron_root= dirname(__FILE__) . DIRECTORY_SEPARATOR;
$process_id= getmypid();

###########################
$cron_settings=[
	'log_file'=> false, // Path to log file, false - disables logging
	'dat_file'=> $cron_root . 'cron/dat/' . $process_id . '.dat', // Path to the thread manager system file
	'queue_file'=> $cron_root . 'cron/dat/queue.dat', // Path to the multiprocess queue system file
	'site_root'=> '',
	'delay'=> -1, // Timeout until next run in seconds
	'daemon_mode'=> false, // true\false resident mode (background service)
	'log_rotate_max_size'=> 10 * 1024 * 1024, // Maximum log size log 10 in MB
	'log_rotate_max_files'=> 5, // Store max 5 archived log files
	'log_level'=> 5, // Log verbosity: 2 warning, 5 debug
	'url_key'=> 'my_secret_key', // Launch key in URI
];

###########################
$cron_jobs= [];

###########################
$cron_jobs[$process_id]= [ // CRON Job
	'interval'=> 0, // start interval 1 sec
	'function'=> 'get_param',
	'param'=> $process_id,
	'multithreading' => false
];

##########
if(isset($_REQUEST["cron"])) { 
	function get_param($process_id){
		global $cron_settings, $cron_resource;
		$frame_completed= serialize([true]);
		$frame_size= 4096;
	
		while(true){ // example: loop from the end
			$frame= queue_address_pop($frame_size);
			$value= unserialize($frame);
				
			if($frame === '') { // end queue
				break 1;
			} elseif($frame !==  $frame_completed) {
					usleep(2000); // test load, micro delay 0.002 sec
			}
		}
		
		if(isset($cron_resource) && is_resource($cron_resource)){// check global resource
			flock($cron_resource, LOCK_UN);
			fclose($cron_resource);
			unset($cron_resource)
		}
		
		unlink($cron_settings['dat_file']);
		_die();
	}
}
```


## Resource consumption
The task manager runs in the background using the network request mechanism.

cron.php works in 4 modes:
1. Schedule timeout check mode (before the interval expires)
- 1.7 KB of RAM 0.0005 seconds.
2. Schedule timeout check mode (starting a separate process)
- run via shell_exec wget: 2.8 KB RAM, 0.0056 seconds (non-blocking).
- fallback launch via stream_context: 2 KB RAM, 0.0418 seconds (blocking).
4. Separate process
- 382KB RAM, priority 39 (nice 19), `MAX_EXECUTION_TIME` 600 seconds by default.
5. Separate process using multithreading
- just like the previous point, it is necessary to take into account the server parameters: the number of processor cores, limiting the number of simultaneous requests to the server, Apache\PHP FPM configuration parameters.
6. Resident mode: 380Kb of RAM, priority 39 (nice 19)
- if `'daemon_mode'=> true`, the control process will be constantly running.
- with disabled logs in the main thread during idle time, there will be no load
- tasks blocking the control process, run in `'multithreading' => true` mode

OPCache memory consumption: 69.48KB (PHP 8.2 data, strings, bytecode, DynASM, service). Jit buffer: ~100.0KB (+ ~100KB queued process)


You can connect this CRON to any CMS, it will not affect performance in any way.
### Test stand 1:
- Centos 8
- Apache/2.4.6 mpm-itk/2.4.7-04 mod_fcgid/2.3.9
- 1 core: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
- PHP 7.4.3 with Zend OPcache
Sapi: mod Apache2
- GNU wget 1.14

### Test bench 2:
- Ubuntu 22
- nginx/1.22.1 php fpm
- 12 threads: AMD Ryzen 5 2600X Six-Core Processor
- PHP 8.2.0 with Zend OPcache Jit enable
Sapi: PHP-FPM
- GNU wget 1.20.3

## Xdebug flat profile, KCacheGrind
Before starting the control process
![before_start_main_process](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/before_start_main_process.png "before_start_main_process.png")

After starting the control process, `'daemon_mode'=> false`, log on
![start_main_process](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/start_main_process.png "start_main_process.png")

After starting the child process, `include callback_cron.php`, log on
![multithreading_start](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/multithreading_include_callback_cron.png "multithreading_include_callback_cron.png")

After starting the child process, an example `queue_address_manager(true)`, log on
![example_queue_address_manager_push](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/example_queue_address_manager_push.png "example_queue_address_manager_push.png")

After starting the child process, an example `queue_address_manager(false)`, log on
![example_queue_address_manager_pop](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/example_queue_address_manager_pop.png "example_queue_address_manager_pop.png")


## Vulnerability check snyk.io
[php-cron-requests-events-snyk.pdf](https://raw.githubusercontent.com/commeta/php-cron-requests-events/master/docs/php-cron-requests-events-snyk.pdf)
Security code, warnings handled.

## Links:
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
