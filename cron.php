<?php
/*
 * PHP CRON use request events
 * Copyright 2023 commeta <dcs-spb@ya.ru>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 * 
 */
 
// declare(strict_types = 1); // strict typing PHP > 7.0

 
 ////////////////////////////////////////////////////////////////////////
// CRON Jobs
define("CRON_ROOT", dirname(__FILE__) . DIRECTORY_SEPARATOR);

$cron_jobs= [];

###########################
# EXAMPLES


###########################
$cron_jobs[]= [ // CRON Job 1, example
	'interval' => 0, // start interval 1 sec
	'callback' => CRON_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => false
];
##########


###########################
$cron_jobs[]= [ // CRON Job 2, multithreading example
	'interval' => 10, // start interval 10 sec
	'callback' => CRON_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => true
];
##########


###########################
$cron_jobs[]= [ // CRON Job 3, multicore example
	'time' => '07:20:00', // "hours:minutes:seconds"execute job on the specified time every day
	'callback' => CRON_ROOT . "cron/inc/callback_addressed_queue_example.php",
	'queue_address_manager' => true, // use with queue_address_manager(true), in worker mode
	'multithreading' => true
];


for( // CRON job 3, multicore example, four cores, 
	$i= 0;
	$i< 4; // Max processor cores
	$i++	
) {
	$cron_jobs[]= [ // CRON Job 3, multicore example
		'time' => '07:20:10', //  "hours:minutes:seconds" execute job on the specified time every day
		'callback' => CRON_ROOT . "cron/inc/callback_addressed_queue_example.php",
		'queue_address_manager' => false, // use with queue_address_manager(false), in handler mode
		'multithreading' => true
	];
}

##########


###########################
$cron_jobs[]= [ // CRON Job 4, multithreading example
	'date' => '10-01-2023', // "day-month-year" execute job on the specified date
	'callback' => CRON_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => true
];
##########

////////////////////////////////////////////////////////////////////////
// Variables
define("CRON_LOG_FILE", CRON_ROOT . 'cron/log/cron.log'); // false switched off
define("CRON_DAT_FILE", CRON_ROOT . 'cron/dat/cron.dat');

define("CRON_DELAY", 1);  // interval between requests in seconds, 0 to max int, increases the accuracy of the job timer hit
define("CRON_DAEMON_MODE", true);  // true\false - enable\disable daemon mode

define("CRON_LOG_ROTATE_MAX_SIZE", 10 * 1024 * 1024); // 10 in MB
define("CRON_LOG_ROTATE_MAX_FILES", 5);
define("CRON_LOG_LEVEL", 2);

define("CRON_URL_KEY", 'my_secret_key'); // change this!
define("CRON_QUEUE_FILE", CRON_ROOT . 'cron/dat/queue.dat');

////////////////////////////////////////////////////////////////////////
// Functions
if(!function_exists('open_cron_socket')) { 
	function open_cron_socket($cron_url_key, $job_process_id= '') // :void 
	{ // Start job in parallel process
		static $wget= '';
		static $curl= '';
		
		if(
			isset($_SERVER['HTTPS']) &&
			($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1) ||
			isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
			$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
		) {
			$protocol= 'https';
		} else {
			$protocol= 'http';
		}

		$document_root= preg_match('/\/$/',$_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR;

		if(isset($_SERVER["HTTP_HOST"])) {
			$host= strtolower($_SERVER["HTTP_HOST"]);
						
			if($job_process_id !== '') $cron_url_key.= '&job_process_id=' . $job_process_id;
		} elseif( // sapi: CLI
			defined('STDIN') ||
			php_sapi_name() === 'cli' ||
			array_key_exists('SHELL', $_ENV) ||
			!array_key_exists('REQUEST_METHOD', $_SERVER)
		) {
			$protocol= 'https';
			$host= "localhost";
			$document_root= dirname(__FILE__) . DIRECTORY_SEPARATOR; // site root path
			
			echo "Request: " . $protocol . '://' . $host . "/" . basename(__FILE__) . "?cron=" . $cron_url_key . "\n";
			echo 'or change $host= "localhost" your domain' . "\n";
		}
		
		$cron_url= $protocol . '://' . $host . '/' . 
			str_replace($document_root , '', dirname(__FILE__) . DIRECTORY_SEPARATOR) . 
			basename(__FILE__) ."?cron=" . $cron_url_key;
			
		if(
			is_callable("shell_exec") &&
			strtolower(PHP_OS) === 'linux' && 
			$wget === '' && 
			$curl === ''
		){
			if(!getenv('PATH')) $paths= "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/snap/bin";
			else $paths= getenv('PATH');
			
			foreach(explode(':', $paths) as $path){
				if(is_executable($path.'/wget')) $wget= $path.'/wget';
				if(is_executable($path.'/curl')) $curl= $path.'/curl';
			}
		}
		
		if(
			is_callable("shell_exec") &&
			$wget !== ''
		){
			if($protocol ===  'https') shell_exec($wget . ' -T 1 --no-check-certificate --delete-after -q "' . $cron_url . '" > /dev/null &');
			else shell_exec($wget . ' -T 1 --delete-after -q "' . $cron_url . '" > /dev/null &');
		} elseif(
			is_callable("shell_exec") &&
			$curl !== ''
		){
			if($protocol ===  'https') shell_exec($curl . ' -I -k --connect-timeout 1 "' . $cron_url . '" > /dev/null &');
			else shell_exec($curl . ' -I --connect-timeout 1 "' . $cron_url . '" > /dev/null &');
		} else {
			@fclose( 
				@fopen(
					$cron_url, 
					'r', 
					false, 
					stream_context_create([ // block mode
						'http'=>[ // script responce time 0.004 * 10 (Linux Kernel Load Average\OPcache\FLOPS) = 0.04 timeout start process
							'timeout' => 0.04 // it will be necessary to increase for high loaded systems
						]
					])
				)
			);
		}
	}
}



////////////////////////////////////////////////////////////////////////
// main
if(
	isset($_REQUEST["cron"]) &&
	$_REQUEST["cron"] === CRON_URL_KEY
){
	////////////////////////////////////////////////////////////////////////
	// Functions: system api 
	function queue_address_manager($mode) // :void 
	{ // example: multicore queue
		$frame_size= 95;
		$process_id= getmypid();
		$frame_completed= serialize([true]);


		if(!file_exists(CRON_QUEUE_FILE)) touch(CRON_QUEUE_FILE);

		if($mode){
			// example: multicore queue worker
			// use:
			// queue_address_push(serialize($value)); // add micro job in queue from worker process

			unlink(CRON_QUEUE_FILE); // reset DB file
			touch(CRON_QUEUE_FILE);

			// Reserved index struct
			$boot= [ // 0 sector, frame size 4096
				'workers'=> [], // array process_id values
				'handlers'=> [], // array process_id values
				'system_variables'=> [],
				'reserved'=>[],
				'index_offset' => 4097, // data index offset
				'index_frame_size' => 1024 * 16, // data index frame size 16Kb
				'data_offset' => 1024 * 16 + 4098 + $frame_size, // data offset
				'data_frame_size' => $frame_size, // data frame size
			];
			
			$boot['workers'][$process_id]=[
				'process_id'=>$process_id,
				'last_update'=> microtime(true)
			];
			
			queue_address_push(serialize($boot), 4096, 0);
			$index_data= []; // example index - address array, frame_cursor is key of array, 
			// if big data base - save partitions of search index in file, 
			// use fseek\fread and parser on finite state machines for find index key\value
			// alignment data with leading zeros


			// 1 core: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
			// PHP 7.4.3 with Zend OPcache
			// 1 process, no concurency
			// execution time: 0.046951055526733 end - start, 1000 cycles
			for($i= 0; $i < 1000; $i++){ // exanple add data in queue, any array serialized size < $frame_size
				$frame_cursor= queue_address_push(
					serialize([
						'url'=> sprintf("https://multicore_long_time_micro_job?param=&d", $i),
						'count'=> $i]), 
					$frame_size, $boot['data_offset'] + $i * $boot['data_frame_size']);
				
				if($frame_cursor !== 0) $index_data[$i]= $frame_cursor;  // example add cursor to index
			}


			// Example save index
			if(count($index_data) === 1000){ // SIZE DATA FRAME ERROR if count elements != 1000
				// 13774 bytes index size
				// 95000 bytes db size
				queue_address_push(serialize($index_data), $boot['index_frame_size'], $boot['index_offset']);
			}
		} else {
			// example: multicore queue handler
			// use:
			// $value= unserialize(queue_address_pop()); // get micro job from queue in children processess 
			// exec $value - in a parallel thread
			
			// use index mode
			// addressed data base, random access
			
			
			// example INIT
			function init_boot_frame(& $queue_resource) // :void 
			{ // Inter-process communication IPC
				// low level, cacheable fast operations, read\write 0-3 sectors of file, 1 cache page
				$process_id= getmypid(); 
				
				fseek($queue_resource, 0); // get 0-3 sectors, boot frame
				$boot= unserialize(trim(fread($queue_resource, 4096)));
				
				if(is_array($boot)){
					$boot['handlers'][$process_id]= [// add active handler
						'process_id'=> $process_id,
						'last_update'=> microtime(true),
						'count_start' => 0,
						'last_start' => 0
					];
					
					$frame= serialize($boot);
					$frame_length= mb_strlen($frame);
					for($i= $frame_length; $i <= $frame_size; $i++) $frame.= chr(0);
					
					fseek($queue_resource, 0); // save 0-3 sectors, boot frame
					fwrite($queue_resource,$frame, 4096);
					fflush($queue_resource);
				} else { // frame error
					if(CRON_LOG_LEVEL > 3){
						if(CRON_LOG_FILE){
							@file_put_contents(
								CRON_LOG_FILE, 
								sprintf("%f ERROR: init boot frame\n", microtime(true)),
								FILE_APPEND | LOCK_EX
							);
						}
					}
					
					_die();				
					
				}
			}
			
			$boot= unserialize(queue_address_pop(4096, 0, '', "init_boot_frame"));
			if(!is_array($boot)) return; // file read error
				
			$index_data= unserialize(queue_address_pop($boot['index_frame_size'], $boot['index_offset'])); 
			if(!is_array($index_data)) {
				return; // file read error
			}


			// examples use adressed mode
			if(is_array($boot) && count($boot['handlers']) === 1): // first handler process or use $job_process_id
				// example 1, get first element
				$value= unserialize(queue_address_pop($frame_size, $index_data[0]));
				// task handler
				//usleep(2000); // test load, micro delay 

				
				// example 2, get last - 10 element, and get first frame in callback function
				$value= unserialize(queue_address_pop($frame_size, $index_data[count($index_data) - 10]));
				// task handler
				//usleep(2000); // test load, micro delay 

				
				// example 3, linear read
				for($i= 100; $i < 800; $i++){ // execution time:  0.037011861801147, 1000 cycles, address mode
					$value= unserialize(queue_address_pop($frame_size, $index_data[$i]));
					// task handler
					//usleep(2000); // test load, micro delay 
				}
				
				
				// example 4, replace frames in file
				for($i= 10; $i < 500; $i++){ // execution time:  0.076093912124634, 1000 cycles, address mode, frame_replace
					$value= unserialize(queue_address_pop($frame_size, $index_data[$i], $frame_completed));
					// task handler
					//usleep(2000); // test load, micro delay 
				}
				
				// example 5, random access
				shuffle($index_data);
				for($i= 0; $i < 10; $i++){// execution time: 0.035359859466553, 1000 cycles, address mode, random access
					$value= unserialize(queue_address_pop($frame_size, $index_data[$i]));
					// task handler
					//usleep(2000); // test load, micro delay 
				}
			endif;

			// example 6, use LIFO mode
			function count_frames(& $queue_resource) // :void 
			{ // Inter-process communication IPC
				// low level, cacheable fast operations, read\write 0-3 sectors of file, 1 cache page
				$process_id= getmypid(); 
				
				fseek($queue_resource, 0); // get 0-3 sectors, boot frame
				$boot= unserialize(trim(fread($queue_resource, 4096)));
				
				if(isset($boot['handlers'][$process_id])){
					$boot['handlers'][$process_id]['count_start']++;
					$boot['handlers'][$process_id]['last_start']= microtime(true);
					
					$frame= serialize($boot);
					$frame_length= mb_strlen($frame);
					for($i= $frame_length; $i <= $frame_size; $i++) $frame.= chr(0);
					
					fseek($queue_resource, 0); // save 0-3 sectors, boot frame
					fwrite($queue_resource, $frame, 4096);
					fflush($queue_resource);
				}
			}

			// execution time: 0.051764011383057 end - start, 1000 cycles
			while(true){ // example: loop from the end
				$frame= queue_address_pop($frame_size,  PHP_INT_MAX, '', "count_frames");
				$value= unserialize($frame);
				
				if($frame === '') {
					break 1;
				} elseif($frame !==  $frame_completed) {
					// $content= file_get_contents($value['url']);
					// file_put_contents('cron/temp/url-' . $value['count'] . '.html', $content);
					
					usleep(2000); // test load, micro delay 0.002 sec
					
					
					if(CRON_LOG_LEVEL > 3){
						if(CRON_LOG_FILE){
							@file_put_contents(
								CRON_LOG_FILE, 
								sprintf("%f INFO: queue_manager %d\n", microtime(true), $value['count']),
								FILE_APPEND | LOCK_EX
							);
						}
					}
					
				}
			}
			
		}
	}


	// frame - pushed frame (string)
	// frame_size - set frame size (int)
	// frame_cursor - PHP_INT_MAX for LIFO mode, get frame from cursor position (int)
	// return frame cursor offset (int), 0 if error or boot frame
	function queue_address_push($frame, $frame_size= 0, $frame_cursor= PHP_INT_MAX, $callback= '') // :int 
	{ // push data frame in stack
		$queue_resource= fopen(CRON_QUEUE_FILE, "r+");
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


	// frame_size - set frame size (int)
	// frame_cursor - PHP_INT_MAX for LIFO mode, get frame from cursor position (int)
	// frame_replace - empty('') is off, replace frame (string)
	// return value from stack frame, empty string '' if error or lifo queue end (string)
	function queue_address_pop($frame_size= 0, $frame_cursor= PHP_INT_MAX, $frame_replace= '', $callback= '') // :string 
	{ // pop data frame from stack
		$queue_resource= fopen(CRON_QUEUE_FILE, "r+");
		$frame= '';
		
		if(flock($queue_resource, LOCK_EX)) {
			if($callback !== '') @call_user_func($callback, $queue_resource, $frame_size, $frame_cursor, $frame_replace); // callback anonymous
			
			$stat= fstat($queue_resource);

			if($stat['size'] < 1){ // queue file is empty
				flock($queue_resource, LOCK_UN);
				fclose($queue_resource);
				return $frame;
			}

			if($frame_cursor !== PHP_INT_MAX){
				$cursor= $frame_cursor;
			} else {
				if($stat['size'] - $frame_size > 0) $cursor= $stat['size'] - $frame_size;
				else $cursor= 0;
			}

			fseek($queue_resource, $cursor); // get data frame
			$v= trim(fread($queue_resource, $frame_size));
			if(mb_strlen($v) > 0) $frame= $v;
			
			if($frame_cursor !== PHP_INT_MAX){
				if($frame_replace !== ''){ // replace frame
					$length_frame_replace= mb_strlen($frame_replace);
					
					if($length_frame_replace <= $frame_size){
						if($length_frame_replace < $frame_size){
							for($i= $length_frame_replace; $i <= $frame_size; $i++) $frame_replace.= chr(0);
						}
						
						fseek($queue_resource, $cursor); 
						fwrite($queue_resource, $frame_replace, $frame_size);
						fflush($queue_resource);
					} else {
						return '';
					}
				}
				
			} elseif(mb_strlen($v) > 0) { // LIFO mode	
				if($stat['size'] - $frame_size >= 0) $trunc= $stat['size'] - $frame_size;
				else $trunc= 0; // truncate file

				ftruncate($queue_resource, $trunc);
				fflush($queue_resource);
			}
			
			flock($queue_resource, LOCK_UN);
		}
		
		fclose($queue_resource);
		return $frame;
	}
	
	
	function write_cron_session() // :void 
	{
		global  $cron_resource, $cron_session;
		
		$serialized= serialize($cron_session);
		rewind($cron_resource);
		fwrite($cron_resource, $serialized);
		ftruncate($cron_resource, mb_strlen($serialized));
	}

	function _die($return= '') // :void 
	{
		global $cron_resource, $cron_dat_file;
		
		if(isset($cron_resource) && is_resource($cron_resource)){// check global resource
			write_cron_session();
			flock($cron_resource, LOCK_UN);
			fclose($cron_resource);
			unset($cron_resource);
		}
		
		if($return === 'restart'){ // restart cron
			if(isset($cron_dat_file)) touch($cron_dat_file, time() - CRON_DELAY);
			open_cron_socket(CRON_URL_KEY);
		}
		
		exit();
	}
	

	function fcgi_finish_request() // :void 
	{
		header($_SERVER['SERVER_PROTOCOL'] . " 200 OK");
		header('Content-Encoding: none');
		header('Content-Length: ' . (string) ob_get_length());
		header('Connection: close');
		http_response_code(200);
		
		// check if fastcgi_finish_request is callable
		if(is_callable('fastcgi_finish_request')) {
			session_write_close();
			fastcgi_finish_request();
		}

		while(ob_get_level()) ob_end_clean();
		ob_start();
		@ob_end_flush();
		@ob_flush();
		@flush();
	}


	function init_background_cron() // :void 
	{
		ignore_user_abort(true);
		fcgi_finish_request();

		if (is_callable('proc_nice')) {
			proc_nice(19);
		}

		if(CRON_DAEMON_MODE){
			set_time_limit(0);
			ini_set('MAX_EXECUTION_TIME', "0");
		} else {
			set_time_limit(600);
			ini_set('MAX_EXECUTION_TIME', "600");
		}
		
		ini_set('error_reporting', "E_ALL");
		ini_set('display_errors', "1"); // 1 to debug
		ini_set('display_startup_errors', "1");
		
		register_shutdown_function('_die');
	}

	function cron_log_rotate() // :void 
	{ // LOG Rotate
		global $cron_session;

		if(!isset($cron_session['log_rotate_last_update'])) {
			$cron_session['log_rotate_last_update']= 0;
		}

		if(CRON_DAEMON_MODE && $cron_session['log_rotate_last_update'] > time() - 600){
			return;
		}
		
		$cron_session['log_rotate_last_update']= time();

		if(CRON_LOG_FILE && @filesize(CRON_LOG_FILE) > CRON_LOG_ROTATE_MAX_SIZE / CRON_LOG_ROTATE_MAX_FILES) {
			@rename(CRON_LOG_FILE, CRON_LOG_FILE . "." . (string) time());
			
			file_put_contents(
				CRON_LOG_FILE, 
				date('m/d/Y H:i:s',time()) . " INFO: log rotate\n", 
				FILE_APPEND | LOCK_EX
			);
				
			$the_oldest = time();
			$log_old_file = '';
			$log_files_size = 0;
						
			foreach(glob(CRON_LOG_FILE . '*') as $file_log_rotate){
				$log_files_size+= @filesize($file_log_rotate);
				if ($file_log_rotate === CRON_LOG_FILE) {
					continue;
				}
					
				$log_mtime = @filectime($file_log_rotate);
				if ($log_mtime < $the_oldest) {
					$log_old_file = $file_log_rotate;
					$the_oldest = $log_mtime;
				}
			}

			if ($log_files_size >  CRON_LOG_ROTATE_MAX_SIZE) {
				if (file_exists($log_old_file)) {
					unlink($log_old_file);
					file_put_contents(
						CRON_LOG_FILE, 
						date('m/d/Y H:i:s', time()) . " INFO: log removal\n",
						FILE_APPEND | LOCK_EX
					);
				}
			}
		}
	}

	
	function callback_connector($job, $job_process_id, $mode) // :void 
	{
		global $cron_session;
		
		if($job['multithreading'] && $mode){ // multithreading\singlethreading
			open_cron_socket(CRON_URL_KEY, (string) $job_process_id); 
		} else {
			
			
			if(isset($job['function'])){ // use call function mode
				if(isset($job['queue_address_manager'])) call_user_func($job['function'], $job['queue_address_manager']);
				else call_user_func($job['function']);
				
			} else { // include callback mode
				if(file_exists($job['callback'])) {
					include $job['callback'];
					
				} else {
					if(CRON_LOG_FILE){
						file_put_contents(
							CRON_LOG_FILE,
							implode(' ', [
								'date'=> date('m/d/Y H:i:s', time()),
								'message'=> 'ERROR:',
								'job_process_id' => (string) $job_process_id,
								'callback' => $job['callback'],
								'mode' => $job['multithreading'] ? 'multithreading' : 'singlethreading',
							]) . "\n",
							FILE_APPEND | LOCK_EX
						);
					}
				}
			}
		}
	}
	
		
	function cron_session_init($job, $job_process_id) // :void 
	{
		global $cron_session;
		static $init= [];
		
		if(isset($init[$job_process_id])) return;
		$init[$job_process_id]= true;
		
		if(isset($cron_session[$job_process_id]['md5'])) {
			if($cron_session[$job_process_id]['md5'] !== md5(serialize($job))){
				$cron_session[$job_process_id]['md5']= md5(serialize($job));
				$cron_session[$job_process_id]['last_update']= 0;
				$cron_session[$job_process_id]['complete']= false;
			}
		} else {
			$cron_session[$job_process_id]['md5']= md5(serialize($job));
		}
		
		if(!isset($cron_session[$job_process_id]['last_update'])) {
			$cron_session[$job_process_id]['last_update']= 0;
		}
						
		if(!isset($cron_session[$job_process_id]['complete'])){
			$cron_session[$job_process_id]['complete']= false;
		}
	}
	
	function cron_check_job($job, $job_process_id, $mode) // :void 
	{
		global $cron_session;
		$time= time();
		
		if(isset($job['date']) || isset($job['time'])){
			$time_stamp= 0;
			$unlocked= false;
			
			if( // unlock job
				isset($job['time']) &&
				!isset($job['date']) &&
				$cron_session[$job_process_id]['last_update'] !== 0 &&
				$cron_session[$job_process_id]['complete']  &&
				date('d-m-Y', $time) !== date('d-m-Y', $cron_session[$job_process_id]['last_update'])
			){
				$cron_session[$job_process_id]['complete']= false;
			}
			
			if($cron_session[$job_process_id]['complete']) return;
			
			if(isset($job['date'])) $d= explode('-', $job['date']);		
			if(isset($job['time'])) $t= explode(':', $job['time']);
			
			if(isset($job['date']) && isset($job['time'])){ // check date time, one - time
				$time_stamp= mktime(intval($t[0]), intval($t[1]), intval($t[2]), intval($d[1]), intval($d[0]), intval($d[2]));
				
				if($time_stamp < $time) $unlocked= true;
			} else {
				if(isset($job['date'])){ // check date, one - time
					$time_stamp= mktime(0, 0, 0, intval($d[1]), intval($d[0]), intval($d[2]));
					
					if($time_stamp < $time) $unlocked= true;
				}
				if(isset($job['time'])){ // check time, every day
					$time_stamp= mktime(intval($t[0]), intval($t[1]), intval($t[2]));
					
					if($time_stamp < $time) $unlocked= true;
				}
			}
			
			if($unlocked){
				callback_connector($job, $job_process_id, $mode);
				$cron_session[$job_process_id]['complete']= true;
				$cron_session[$job_process_id]['last_update']= time();
			}
		} else {
			if(
				$cron_session[$job_process_id]['last_update'] + $job['interval'] < $time
			){
				callback_connector($job, $job_process_id, $mode);
				$cron_session[$job_process_id]['complete']= true;
				$cron_session[$job_process_id]['last_update']= time();
			}
		}
	}
	
 
	function singlethreading_dispatcher() // :void 
	{ // main loop job list
		global $cron_jobs;
		
		foreach($cron_jobs as $job_process_id=> $job){
			cron_session_init($job, $job_process_id);
			cron_check_job($job, $job_process_id, true);
		}
	}
	
	

	function memory_profiler() // :void 
	{
		global $cron_jobs;
		static  $profiler= [];
		$time= time();
		
		if(!isset($profiler['time'])) $profiler['time']= $time;
		if($profiler['time'] > $time - 15){ // delayed start
			return;
		}
		$profiler['time']= $time;
		
		if(!isset($profiler['memory_get_usage'])){
			$profiler['memory_get_usage']= 0;
		}
		
		if(!isset($profiler['filemtime'])){
			$profiler['filemtime']= filemtime(__FILE__);
		}
		
		if($profiler['filemtime'] !== filemtime(__FILE__) || !file_exists(__FILE__)){ // write in main file event, restart
			_die('restart');
		}
		
		if($profiler['memory_get_usage'] < memory_get_usage()){
			$profiler['memory_get_usage']= memory_get_usage();
			
			if(CRON_LOG_FILE){
				file_put_contents(
					CRON_LOG_FILE,
					implode(' ', [
						'date'=> date('m/d/Y H:i:s', $time),
						'message'=> 'INFO:',
						'name' => 'memory_get_usage',
						'value' => (string) $profiler['memory_get_usage'],
					]) . "\n",
					FILE_APPEND | LOCK_EX
				);
			}
		} 
		
		if(!isset($profiler['callback_time'])) $profiler['callback_time']= $time;
		if($profiler['callback_time'] > $time - 60){ // delayed start
			return;
		}
		$profiler['callback_time']= $time;
		
		foreach($cron_jobs as $job){
			if(is_file($job['callback'])){
				$filemtime_callback= filemtime($job['callback']);
				
				if(!isset($profiler['filemtime_' . $job['callback']])){
					$profiler['filemtime_' . $job['callback']]= $filemtime_callback;
					continue;
				}
				
				if($profiler['filemtime_' . $job['callback']] !== $filemtime_callback){ // write in callback file event, restart
					_die('restart');
				}
			}
		}
	}


	////////////////////////////////////////////////////////////////////////
	// start in background
	init_background_cron();
	$cron_session= [];
	
	////////////////////////////////////////////////////////////////////////
	// multithreading Dispatcher
	if( // job in parallel process. For long tasks, a separate dispatcher is needed
		isset($_GET["job_process_id"])
	){
		$job_process_id= intval($_GET["job_process_id"]);
		if(isset($cron_jobs[$job_process_id])) {
			$job= $cron_jobs[$job_process_id];

			if($job['multithreading']){
				// Dispatcher init
				$job_process_id= intval($_GET["job_process_id"]);
				$cron_dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . (string) $job_process_id . '.dat';
				if(!file_exists($cron_dat_file)) touch($cron_dat_file);
				
				$cron_resource= fopen($cron_dat_file, "r+");
				if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
					$stat= fstat($cron_resource);
					$cs= unserialize(fread($cron_resource, $stat['size']));
					if(is_array($cs)) $cron_session= $cs;
					
					cron_session_init($job, $job_process_id);
					cron_check_job($job, $job_process_id, false);
					
					write_cron_session();
					flock($cron_resource, LOCK_UN);
				}
				
				fclose($cron_resource);
			}
		}
		
		_die();
	}

	
	////////////////////////////////////////////////////////////////////////
	// Dispatcher init
	$cron_dat_file= CRON_DAT_FILE;
	
	if(filemtime(CRON_DAT_FILE) + CRON_DELAY > time()) _die();
	
	$cron_resource= fopen(CRON_DAT_FILE, "r+");
	if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
		$stat= fstat($cron_resource);
		$cs= unserialize(@fread($cron_resource, $stat['size']));
		if(is_array($cs)) $cron_session= $cs;
		
		if(CRON_LOG_FILE && !is_dir(dirname(CRON_LOG_FILE))) {
			mkdir(dirname(CRON_LOG_FILE), 0755, true);
		}
		
		//###########################################
		// check jobs
		singlethreading_dispatcher();

		while(CRON_DAEMON_MODE){
			singlethreading_dispatcher();
			memory_profiler();
				
			if(CRON_LOG_FILE){
				cron_log_rotate();
			}
				
			sleep(CRON_DELAY); // delay in infinite loop
		}
		
		//###########################################
		if(CRON_LOG_FILE) cron_log_rotate();
		write_cron_session();
		flock($cron_resource, LOCK_UN);
	}

	fclose($cron_resource);
	_die();
} else {
	////////////////////////////////////////////////////////////////////////
	// check time out to start in background 
	if(file_exists(CRON_DAT_FILE)){
		if(CRON_DAEMON_MODE){
			$cron_resource= fopen(CRON_DAT_FILE, "r");
			$cron_started= true;
			
			if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
				$cron_started= false;
				flock($cron_resource, LOCK_UN);
			}
			
			fclose($cron_resource);
			
			if(!$cron_started) open_cron_socket(CRON_URL_KEY);
		} else {
			if(filemtime(CRON_DAT_FILE) + CRON_DELAY < time()){
				open_cron_socket(CRON_URL_KEY);
			}
		}
	} else {
		@mkdir(dirname(CRON_DAT_FILE), 0755, true);
		file_put_contents(CRON_DAT_FILE, serialize([]));
		touch(CRON_DAT_FILE, time() - CRON_DELAY);
		
		if(CRON_LOG_FILE) {
			mkdir(dirname(CRON_LOG_FILE), 0755, true);
			touch(CRON_LOG_FILE);
		}
		
		open_cron_socket(CRON_URL_KEY);
	}
}

unset($cron_jobs);
?>
