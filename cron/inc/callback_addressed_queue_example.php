<?php

function queue_address_manager_extend($mode){ // example: multicore queue
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue.dat';
		$frame_size= 95;
		$process_id= getmypid();
		
		if(!file_exists($dat_file)) touch($dat_file);
		
		if($mode){
			// example: multicore queue worker
			// use:
			// queue_address_push($multicore_long_time_micro_job); // add micro job in queue from worker process
			
			unlink($dat_file); // reset DB file
			touch($dat_file);
			
			// Reserved index struct
			$boot= [ // 0 sector, frame size 4096
				'workers'=> [], // array process_id values
				'handlers'=> [], // array process_id values
				'system_variables'=> [],
				'reserved'=>[],
				'index_offset' => 4097, // data index offset
				'index_frame_size' => 1024 * 16, // data index frame size 16Kb
				'data_offset' => 1024 * 16 + 4098, // data offset
				'data_frame_size' => $frame_size, // data frame size
			];
			
			$boot['workers'][$process_id]=[
				'process_id'=>$process_id,
				'last_update'=> microtime(true)
			];
			
			queue_address_push($boot, 4096, 0);
			$index_data= []; // index - address array, frame_cursor is key of array, 
			// if big data base - save partitions of search index in file, 
			// use fseek\fread and parser on finite state machines for find index key\value
			// alignment data with leading zeros
			
			
			// 1 core: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
			// PHP 7.4.3 with Zend OPcache
			// 1 process, no concurency
			// execution time: 0.046951055526733 end - start, 1000 cycles
			for($i= 0; $i < 1000; $i++){
				$frame_cursor= queue_address_push([
					'url'=> "https://multicore_long_time_micro_job?param=" . $i,
					'count'=> $i
				], $frame_size, $boot['data_offset'] + $i * $boot['data_frame_size']);
				
				if($frame_cursor !== false) $index_data[$i]= $frame_cursor; 
			}
						

			if(count($index_data) == 1000){ // SIZE DATA FRAME ERROR if count elements != 1000
				// 13774 bytes index size
				// 95000 bytes db size
				queue_address_push($index_data, $boot['index_frame_size'], $boot['index_offset']);
			}
		} else {
			// example: multicore queue handler
			// use:
			// $multicore_long_time_micro_job= queue_address_pop(); // get micro job from queue in children processess 
			// exec $multicore_long_time_micro_job - in a parallel thread
			
			// use index mode
			// addressed data base, random access
			
			
			// example INIT
			function init_boot_frame(& $queue_resource){ // Inter-process communication IPC
				// low level, cacheable fast operations, read\write 0-3 sectors of file, 1 memory page
				$process_id= getmypid(); 
				
				fseek($queue_resource, 0); // get 0-3 sectors, boot frame
				$boot= unserialize(trim(fread($queue_resource, 4096)));
				
				if(is_array($boot) && count($boot) > 5){
					$boot['handlers'][$process_id]= [// add active handler
						'process_id'=>$process_id,
						'last_update'=> microtime(true),
						'count_start' => 0,
						'last_start' => 0
					];
					
					fseek($queue_resource, 0); // save 0-3 sectors, boot frame
					fwrite($queue_resource, serialize($boot), 4096);
					fflush($queue_resource);
				} else { // frame error
					if(CRON_LOG_LEVEL > 3){
						if(CRON_LOG_FILE){
							@file_put_contents(
								CRON_LOG_FILE, 
									microtime(true) . " ERROR: init boot frame\n",
								FILE_APPEND | LOCK_EX
							);
						}
					}
					
					_die();				
					
				}
			}
			
			
			$boot= queue_address_pop(4096, 0, false, "init_boot_frame");
			if(!is_array($boot) && count($boot) < 5) return false; // file read error
				
			$index_data= queue_address_pop($boot['index_frame_size'], $boot['index_offset']); 
			if(!is_array($index_data) && count($index_data) < 5) {
				return false; // file read error
			}

			if(count($boot['handlers']) < 1):
				// example 1, get first element
				$multicore_long_time_micro_job= queue_address_pop($frame_size, $index_data[0]);
				
				
				// example 2, get last - 10 element, and get first frame in callback function
				$multicore_long_time_micro_job= queue_address_pop($frame_size, $index_data[count($index_data) - 10]);

				
				// example 3, linear read
				for($i= 100; $i < 800; $i++){ // execution time:  0.037011861801147, 1000 cycles, address mode
					$multicore_long_time_micro_job= queue_address_pop($frame_size, $index_data[$i]);
				}
				
				
				// example 4, replace frames in file
				for($i= 10; $i < 500; $i++){ // execution time:  0.076093912124634, 1000 cycles, address mode, frame_replace
					$multicore_long_time_micro_job= queue_address_pop($frame_size, $index_data[$i], true);
				}
				
				
				// example 5, random access
				shuffle($index_data);
				for($i= 0; $i < 10; $i++){// execution time: 0.035359859466553, 1000 cycles, address mode, random access
					$multicore_long_time_micro_job= queue_address_pop($frame_size, $index_data[$i]);
				}
			endif;

			// example 6, use LIFO mode
			function count_frames(& $queue_resource){ // Inter-process communication IPC
				// low level, cacheable fast operations, read\write 0-3 sectors of file, 1 memory page
				$process_id= getmypid(); 
				
				fseek($queue_resource, 0); // get 0-3 sectors, boot frame
				$boot= unserialize(trim(fread($queue_resource, 4096)));
				
				if(isset($boot['handlers'][$process_id])){
					$boot['handlers'][$process_id]['count_start'] ++;
					$boot['handlers'][$process_id]['last_start']= microtime(true);
						
					fseek($queue_resource, 0); // save 0-3 sectors, boot frame
					fwrite($queue_resource, serialize($boot), 4096);
					fflush($queue_resource);
				}
			}

			// execution time: 0.051764011383057 end - start, 1000 cycles
			while(true){ // example: loop from the end
				$multicore_long_time_micro_job= queue_address_pop($frame_size,  false, false, "count_frames");
			
				if($multicore_long_time_micro_job === false) {
					break 1;
				} elseif($multicore_long_time_micro_job !== true) {
					// $content= file_get_contents($multicore_long_time_micro_job['url']);
					// file_put_contents('cron/temp/url-' . $multicore_long_time_micro_job['count'] . '.html', $content);
					
					usleep(2000); // test load, micro delay 
					
					
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
			
		}
}	

if(isset($job['queue_address_manager'])){
	// true - call in multithreading context api cron.php, in worker mode
	// false - call in multithreading context api cron.php, in handler mode
	queue_address_manager_extend($job['queue_address_manager']);
}

?>
