<?php
##########
// this code replace examples $cron_requests_events_settings and $cron_requests_events_jobs variables

$process_id= getmypid();

// System dirs
$cron_requests_events_root= dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR;
$cron_requests_events_dat= dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'dat' . DIRECTORY_SEPARATOR;
$cron_requests_events_inc= dirname(__FILE__) . DIRECTORY_SEPARATOR;
$cron_requests_events_log= dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR;

###########################
$cron_requests_events_settings=[
	'log_file'=> false, // Path to log file, false - disables logging
	'dat_file'=> $cron_requests_events_dat . (string) $process_id . '.dat', // Path to the thread manager system file
	'delete_dat_file_on_exit'=> true,
	'queue_file'=> $cron_requests_events_dat . 'queue.dat', // Path to the multiprocess queue system file
	'site_root'=> '',
	'delay'=> -1, // Timeout until next run in seconds
	'daemon_mode'=> false, // true\false resident mode (background service)
	'log_rotate_max_size'=> 10 * 1024 * 1024, // Maximum log size log 10 in MB
	'log_rotate_max_files'=> 5, // Store max 5 archived log files
	'log_level'=> 5, // Log verbosity: 2 warning, 5 debug
	'url_key'=> 'my_secret_key', // Launch key in URI
];

###########################
$cron_requests_events_jobs= [
	$process_id=>  [ // CRON Job
		'interval'=> 0,
		'function'=> 'get_param',
		'param'=> $process_id,
		'multithreading' => false
	]
];


###########################
if(!function_exists('send_param_and_parallel_launch')) { 
	function send_param_and_parallel_launch($params, $frame_size){ 
		global $cron_requests_events_settings, $cron_requests_events_root;
		
		if(!is_dir(dirname($cron_requests_events_settings['queue_file']))) mkdir(dirname($cron_requests_events_settings['queue_file']), 0755, true);
		if(!file_exists($cron_requests_events_settings['queue_file'])) touch($cron_requests_events_settings['queue_file']);
		
		queue_address_push($params, $frame_size);
		
		if(function_exists('open_cron_socket')) {
			open_cron_socket($cron_requests_events_settings['url_key']);
		} else {
			include($cron_requests_events_root . 'cron.php');
		}
	}
}


if(isset($_REQUEST["cron"])):
	//if(!function_exists('launch')) touch($cron_requests_events_settings['dat_file'], time() - $cron_requests_events_settings['delay']);
	if(!isset($cron_settings_conf)) touch($cron_requests_events_settings['dat_file'], time() - $cron_requests_events_settings['delay']);

	function get_param($process_id){ // Example get param, function called in parallel process cron.php
		global $cron_requests_events_settings, $cron_requests_events_resource, $cron_requests_events_log;
		$frame_size= 64;
	
		while(true){ // example: loop from the end
			$frame= queue_address_pop($frame_size);
			$value= unserialize($frame);
				
			if($frame === '') { // end queue
				break 1;
			} else { // Example handler
				if(!is_dir($cron_requests_events_log)) mkdir($cron_requests_events_log, 0755, true);

				file_put_contents(
					$cron_requests_events_log . 'cron.log', 
					sprintf(
						"%f Info: get_param while %s\n", 
						microtime(true), 
						print_r($value, true)),
					FILE_APPEND | LOCK_EX
				);
				
			}
		}
		
		if(isset($cron_requests_events_resource) && is_resource($cron_requests_events_resource)){// check global resource
			flock($cron_requests_events_resource, LOCK_UN);
			fclose($cron_requests_events_resource);
		}
		
		_die();
	}
endif;


###########################
if(!function_exists('queue_address_push')) { 
	// frame - pushed frame (string)
	// frame_size - set frame size (int)
	// frame_cursor - PHP_INT_MAX for LIFO mode, get frame from cursor position (int)
	// return frame cursor offset (int), 0 if error or boot frame
	function queue_address_push($frame, $frame_size= 0, $frame_cursor= PHP_INT_MAX, $callback= '') // :int 
	{ // push data frame in stack
		global $cron_requests_events_settings;
		
		$queue_resource= fopen($cron_requests_events_settings['queue_file'], "r+");
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
			$frame_length= mb_strlen($frame);
			
			if($frame_length < $frame_size){
				for($i= $frame_length; $i <= $frame_size; $i++) $frame.= chr(0);
			}

			if($frame_cursor !== PHP_INT_MAX){
				$return_cursor= $frame_cursor;				
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

if(!function_exists('queue_address_pop')) { 
	// frame_size - set frame size (int)
	// frame_cursor - PHP_INT_MAX for LIFO mode, get frame from cursor position (int)
	// frame_replace - empty('') is off, replace frame (string)
	// return value from stack frame, empty string '' if error or lifo queue end (string)
	function queue_address_pop($frame_size= 0, $frame_cursor= PHP_INT_MAX, $frame_replace= '', $callback= '') // :string 
	{ // pop data frame from stack
		global $cron_requests_events_settings;
		
		$queue_resource= fopen($cron_requests_events_settings['queue_file'], "r+");
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
}


##########
if(
	isset($_REQUEST["cron"]) &&
	$_REQUEST["cron"] === $cron_requests_events_settings['url_key']
){
	////////////////////////////////////////////////////////////////////////
	// Functions: system api
	
	function queue_address_manager($mode) // :void 
	{ // example: multicore queue
		global $cron_requests_events_settings;
		
		// Init
		$frame_size= 95;
		$process_id= getmypid();
		$frame_completed= serialize([true]);

		if(!file_exists($cron_requests_events_settings['queue_file'])) touch($cron_requests_events_settings['queue_file']);

		if($mode){
			// example: multicore queue worker
			// use:
			// queue_address_push(serialize($value)); // add micro job in queue from worker process

			unlink($cron_requests_events_settings['queue_file']); // reset DB file
			touch($cron_requests_events_settings['queue_file']);

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
				global $cron_requests_events_settings;
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
					file_put_contents(
						$cron_requests_events_settings['log_file'], 
						sprintf("%f ERROR: init boot frame\n", microtime(true)),
						FILE_APPEND | LOCK_EX
					);
					
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

			// execution time: 0.051764011383057 end - start, 1000 cycles (without usleep)
			while(true){ // example: loop from the end
				$frame= queue_address_pop($frame_size,  PHP_INT_MAX, '', "count_frames");
				$value= unserialize($frame);
				
				if($frame === '') { // end queue
					break 1;
				} elseif($frame !==  $frame_completed) {
					// $content= file_get_contents($value['url']);
					// file_put_contents('cron/temp/url-' . $value['count'] . '.html', $content);
					
					usleep(2000); // test load, micro delay 0.002 sec
					
					
					if($cron_requests_events_settings['log_file'] && $cron_requests_events_settings['log_level'] > 3){
						file_put_contents(
							$cron_requests_events_settings['log_file'], 
							sprintf("%f INFO: queue_manager %d\n", microtime(true), $value['count']),
							FILE_APPEND | LOCK_EX
						);
					}
					
				}
			}
			
		}
	}
		
}



?>
