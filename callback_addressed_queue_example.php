<?php
define("CRON_SITE_ROOT", preg_match('/\/$/',$_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR);
define("CRON_DAT_FILE", CRON_SITE_ROOT . 'cron/dat/cron_test.dat');

	function queue_address_manager($mode){ // example: multicore queue
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_test.dat';
		$index_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_index_test.dat';
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
			
			// example 1, get first element
			$multicore_long_time_micro_job= queue_address_pop($frame_size, $index[0]);
			
			// example 2, linear read
			for($i= 100; $i < 800; $i++){ // execution time:  0.037011861801147, 1000 cycles, address mode
				$multicore_long_time_micro_job= queue_address_pop($frame_size, $index[$i]);
			}
			
			// example 3, replace frames in file
			for($i= 10; $i < 500; $i++){ // execution time:  0.076093912124634, 1000 cycles, address mode, frame_replace
				$multicore_long_time_micro_job= queue_address_pop($frame_size, $index[$i], true);
				unset($index[$i]);
			}
			
			
			// example 4, random access
			shuffle($index);
			for($i= 0; $i < 10; $i++){// execution time: 0.035359859466553, 1000 cycles, address mode, random access
				$multicore_long_time_micro_job= queue_address_pop($frame_size, $index[$i]);
			}


			// example 5, use LIFO mode
			// execution time: 0.051764011383057 end - start, 1000 cycles
			while(true){ // example: loop from the end
				$multicore_long_time_micro_job= queue_address_pop($frame_size);
				
				if($multicore_long_time_micro_job === false) {
					break 1;
				} elseif($multicore_long_time_micro_job !== true) {
					// $content= file_get_contents($multicore_long_time_micro_job['url']);
					// file_put_contents('cron/temp/url-' . $multicore_long_time_micro_job['count'] . '.html', $content);
					
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


	// frame_size - set frame size
	// frame_cursor - false for LIFO mode, get frame from cursor position
	// frame_replace - false is off, delete frame
	function queue_address_pop($frame_size, $frame_cursor= false, $frame_replace= false){ // pop data frame from stack
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

			if($frame_cursor !== false){
				$cursor= $frame_cursor;
			} else {
				if($stat['size'] - $frame_size > 0) $cursor= $stat['size'] - $frame_size;
				else $cursor= 0;
			}

			fseek($queue_resource, $cursor); // get data frame
			$raw_frame= fread($queue_resource, $frame_size);
			$value= unserialize(trim($raw_frame));
			
			if($frame_cursor !== false){
				if($frame_replace !== false){ // replace frame
					$frame_replace= serialize($frame_replace);
					$frame_replace_size= mb_strlen($frame_replace);
						
					for($i= $frame_replace_size; $i< $frame_size - 1; $i++) $frame_replace.= ' ';
					$frame_replace.= "\n";

					if(mb_strlen($frame_replace) == $frame_size){
						fseek($queue_resource, $cursor); 
						fwrite($queue_resource, $frame_replace, $frame_size);
						fflush($queue_resource);
					}
				}
				
			} else { // LIFO mode				
				if($stat['size'] - $frame_size >= 0) $trunc= $stat['size'] - $frame_size;
				else $trunc= 0; // truncate file

				ftruncate($queue_resource, $trunc);
				fflush($queue_resource);
			}
			
			flock($queue_resource, LOCK_UN);
		}
		
		fclose($queue_resource);
		return $value;
	}
	
	
queue_address_manager(true); // call in multithreading context api cron.php, in worker mode
queue_address_manager(false);  // call in multithreading context api cron.php, in handler mode
?>
