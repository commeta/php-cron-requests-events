<?php

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
		function queue_address_pop_callback(& $queue_resource, $frame_size, $frame_cursor= false, $frame_replace= false){
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
	
queue_address_manager_extend(false);  // call in multithreading context api cron.php, in handler mode
queue_address_manager_extend(true); // call in multithreading context api cron.php, in worker mode

?>
