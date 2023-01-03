<?php
define("CRON_SITE_ROOT", preg_match('/\/$/',$_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR);
define("CRON_DAT_FILE", CRON_SITE_ROOT . 'cron/dat/cron_test.dat');

	function queue_address_manager($mode){ // example: multicore queue
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_test.dat';
		$index_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_index_test.dat';
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
				]);
				
				if($frame_cursor !== false) $index[$i]= $frame_cursor; 
			}
						
			if(count($index) == 1000){ // SIZE DATA FRAME ERROR if count elements != 1000
				file_put_contents($index_file, serialize($index), LOCK_EX); 
				// 13774 bytes index file size
				// 89780 bytes db file size
			}
			
		} else {
			// example: multicore queue handler
			// use:
			// $multicore_long_time_micro_job= queue_address_pop(); // get micro job from queue in children processess 
			// exec $multicore_long_time_micro_job - in a parallel thread
			
			
			// use index mode
			// addressed data base, random access
			$index= unserialize(file_get_contents($index_file));
			
			// execution time: 0.047177791595459 end - start, 1000 cycles, address mode
			// execution time: 0.082197904586792 end - start, 1000 cycles, address mode + $frame_replace= true
			// execution time: 0.079766988754272 end - start, 1000 cycles, address mode + $frame_replace= true, shuffle($index)
			// execution time: 0.15690398216248 end - start, 1000 cycles, address mode + $frame_replace= true, shuffle($index)
			// execution time: 0.077039957046509 end - start, 1000 cycles, noaddress + file truncate
			//$start= microtime(true);
			//print_r(['36 ', microtime(true) - $start]);
			
			// example 1, get first element
			$multicore_long_time_micro_job= queue_address_pop(false, $index[0]);
			
			// example 2, linear read
			for($i= 100; $i < 800; $i++){
				$multicore_long_time_micro_job= queue_address_pop(false, $index[$i]);
			}
			
			// example 3, replace frames in file
			for($i= 10; $i < 500; $i++){
				$multicore_long_time_micro_job= queue_address_pop(false, $index[$i], true);
				unset($index[$i]);
			}
			
			
			// example 4, random access
			shuffle($index);
			for($i= 0; $i < 10; $i++){
				$multicore_long_time_micro_job= queue_address_pop(false, $index[$i]);
			}
			
			// example 5, use LIFO mode
			// execution time: 0.070611000061035 end - start, 1000 cycles
			while(true){ // example: loop from the end
				$multicore_long_time_micro_job= queue_address_pop();
				
				if($multicore_long_time_micro_job === false) {
					break 1;
				} elseif($multicore_long_time_micro_job !== true) {
					// $content= file_get_contents($multicore_long_time_micro_job['url']);
					// file_put_contents('cron/temp/url-' . $multicore_long_time_micro_job['count'] . '.html', $content);
					
					//print_r($multicore_long_time_micro_job);
				}
			}
			
			unlink($dat_file); // reset DB file
		}
	}


	// value - pushed value
	// frame_size - false for auto, set frame size
	// frame_cursor - false for LIFO mode, get frame from cursor position
	function queue_address_push($value, $frame_size= false, $frame_cursor= false){ // push data frame in stack
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_test.dat';
		$queue_resource= fopen($dat_file, "r+");
		$return_cursor= false;

		if($frame_size !== false){
			$frame= serialize($value);
			$value_size= mb_strlen($frame);
			$value_size++; // reserved byte
			
			if($frame_size > $value_size){ // fill
				for($i= $value_size; $i< $frame_size; $i++) $frame.= ' ';
			} else {
				return false;
			}
			
			$frame.= "\n";
		} else {
			$frame= serialize($value) . "\n";
			$frame_size= mb_strlen($frame);
		}

		if(flock($queue_resource, LOCK_EX)) {
			$stat= fstat($queue_resource);
			
			if($frame_cursor !== false){
				$return_cursor= $frame_cursor;
				
				fseek($queue_resource, $frame_cursor);
				fwrite($queue_resource, $frame, $frame_size);
				fflush($queue_resource);
			} else {
				$return_cursor= $stat['size'];
				
				fseek($queue_resource, $stat['size']);
				fwrite($queue_resource, $frame, $frame_size);
				ftruncate($queue_resource, $stat['size'] + $frame_size);
				fflush($queue_resource);
			}

			flock($queue_resource, LOCK_UN);
		}
		
		fclose($queue_resource);
		return $return_cursor;
	}


	// frame_size - false for auto, set frame size
	// frame_cursor - false for LIFO mode, get frame from cursor position
	// frame_replace - false is off, delete frame
	function queue_address_pop($frame_size= false, $frame_cursor= false, $frame_replace= false){ // pop data frame from stack
		static $frames_size_average= 0;
		
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_test.dat';
		$queue_resource= fopen($dat_file, "r+");
		$value= false;
		
		if(flock($queue_resource, LOCK_EX)) {
			$stat= fstat($queue_resource);
			
			if($stat['size'] < 1){ // queue file is empty
				flock($queue_resource, LOCK_UN);
				fclose($queue_resource);
				return false;
			}

			if($stat['size'] < 4096) $length= $stat['size']; // set buffer size  equal max size queue value
			else $length= 4096;
			
			if($frames_size_average != 0) $length= $frames_size_average;
			
			if($frame_size !== false){
				$length= $frame_size;
			}
			
			if($stat['size'] - $length > 0) $cursor= $stat['size'] - $length;
			else $cursor= 0;

			if($frame_cursor !== false){
				$cursor= $frame_cursor;
			}

			fseek($queue_resource, $cursor); // get data frame
			$raw_frame= fread($queue_resource, $length);

			if($frame_cursor !== false){
				$frame_start= 0;
				$frame_end= strpos($raw_frame, "\n");
				$frame= substr($raw_frame, $frame_start, $frame_end - $frame_start );
				$value= unserialize(trim($frame));
				
				if($frame_replace !== false){
					$length= mb_strlen($frame);
					$frame= serialize($frame_replace);
						
					for($i= 0; $i< $length; $i++) $frame.= ' ';
					$frame.= "\n";

					fseek($queue_resource, $cursor); 
					fwrite($queue_resource, $frame, $length);
					fflush($queue_resource);
				}
			} else {
				if($length == 4096){
					$frame_end= strrpos($raw_frame, "\n");
					$frame_start= strripos($raw_frame, "\n", -3);
				} else {
					$frame_start= 0;
					$frame_end= strripos($raw_frame, "\n", -1);
				}

				$frame= substr($raw_frame, $frame_start + 1, $frame_end - $frame_start - 1);
				$value= unserialize(trim($frame));
				
				$crop= mb_strlen($frame) + 1;
					
				if($frames_size_average < $crop){ // average size data frame
					$frames_size_average= $crop;
				}
					
				if($stat['size'] - $crop >= 0) $trunc= $stat['size'] - $crop;
				else $trunc= $stat['size']; // truncate file

				ftruncate($queue_resource, $trunc);
				fflush($queue_resource);
			}
			
			flock($queue_resource, LOCK_UN);
		}
		
		fclose($queue_resource);

		if( // data frame size failure, retry
			$value === false && 
			isset($frame) &&
			$frames_size_average != 0 &&
			isset($stat['size']) &&
			$stat['size'] > 0
		){	
			$frames_size_average= 4096;
			$value= queue_address_pop(false, $frame_cursor, $frame_replace);
		}
	
		
		
		return $value;
	}
	
	
queue_address_manager(true); // call in multithreading context api cron.php, in worker mode
queue_address_manager(false);  // call in multithreading context api cron.php, in handler mode
?>
