<?php

$cron_settings=[
	'log_file'=> $cron_root . 'cron/log/cron.log', // Path to log file, false - disables logging
	'dat_file'=> $cron_root . 'cron/dat/cron.dat', // Path to the thread manager system file
	'delete_dat_file_on_exit'=> false, // Used in tasks with the specified time and/or date, controlled mode
	'queue_file'=> $cron_root . 'cron/dat/queue.dat', // Path to the multiprocess queue system file
	'site_root'=> '',
	'delay'=> 1, // Timeout until next run in seconds
	'daemon_mode'=> true, // true\false resident mode (background service)
	'log_rotate_max_size'=> 10 * 1024 * 1024, // Maximum log size log 10 in MB
	'log_rotate_max_files'=> 5, // Store max 5 archived log files
	'log_level'=> 5, // Log verbosity: 2 warning, 5 debug info
	'url_key'=> 'my_secret_key', // Launch key in URI
];

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
	'time' => '04:24:00', // "hours:minutes:seconds" execute job on the specified time every day
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




##########
if(
	isset($_REQUEST["cron"]) &&
	$_REQUEST["cron"] === $cron_settings['url_key']
){
	////////////////////////////////////////////////////////////////////////
	// Functions: system api 
	function queue_address_manager($mode) // :void 
	{ // example: multicore queue
		global $cron_settings;
		
		$frame_size= 95;
		$process_id= getmypid();
		$frame_completed= serialize([true]);


		if(!file_exists($cron_settings['queue_file'])) touch($cron_settings['queue_file']);

		if($mode){
			// example: multicore queue worker
			// use:
			// queue_address_push(serialize($value)); // add micro job in queue from worker process

			unlink($cron_settings['queue_file']); // reset DB file
			touch($cron_settings['queue_file']);

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
				global $cron_settings;
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
						$cron_settings['log_file'], 
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
					
					
					if($cron_settings['log_file'] && $cron_settings['log_level'] > 3){
						file_put_contents(
							$cron_settings['log_file'], 
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
