<?php
define("CRON_SITE_ROOT", preg_match('/\/$/',$_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR);
define("CRON_DAT_FILE", CRON_SITE_ROOT . 'cron/dat/cron_test.dat');


$start_memory = memory_get_usage();
$start = microtime(true);
//include('cron.php');
echo memory_get_usage() - $start_memory . ' ';
printf("%4.2f", microtime(true) - $start);

//die();
echo "<br>\n";


	function queue_manager($mode){ // example: multicore queue
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_test.dat';
		$index_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_index_test.dat';
		if(!file_exists($dat_file)) touch($dat_file);
		
		if($mode){
			// example: multicore queue worker
			// use:
			// queue_push($multicore_long_time_micro_job); // add micro job in queue from worker process
			
			$index= []; // index - address array, frame_cursor is key of array, frame size 95 byte
			
			for($i= 0; $i < 1000; $i++){
				$frame_cursor= queue_push([
					'url'=> "https://multicore_long_time_micro_job?param=" . $i,
					'count'=> $i
				], 95); // frame size 95 byte
				
				if($frame_cursor) $index[$i]= $frame_cursor; 
			}
			
			if(count($index) == 1000){ // SIZE DATA FRAME ERROR if count elements != 1000
				file_put_contents($index_file, serialize($index), LOCK_EX); 
				// 13783 bytes index file size
				// 95000 bytes db file size
			}
			
		} else {
			// example: multicore queue handler
			// use:
			// $multicore_long_time_micro_job= queue_pop(); // get micro job from queue in children processess 
			// exec $multicore_long_time_micro_job - in a parallel thread
			
			
			// use index mode
			$index= unserialize(file_get_contents($index_file));
			$multicore_long_time_micro_job= queue_pop(95);
			
			
			for($i= 0; $i < 1000; $i++){
				$multicore_long_time_micro_job= queue_pop(95, $index[$i]);
				
				print_r([
					'microtime'=>microtime(true),
					'INFO'=>" INFO: queue_manager ", 
					'i'=>$i,
					'index'=>$index[$i],
					'count'=>$multicore_long_time_micro_job['count']
				]);
			}
			
			
			
			/*
			// use LIFO mode
			$start= true;
			while($start){
				$multicore_long_time_micro_job= queue_pop(95);
				
				if($multicore_long_time_micro_job === false) {
					$start= false;
					break;
				} else {
					// $content= file_get_contents($multicore_long_time_micro_job['url']);
					// file_put_contents('cron/temp/url-' . $multicore_long_time_micro_job['count'] . '.html', $content);
					
						print_r([
								microtime(true) . 
								" INFO: queue_manager " . 
								$multicore_long_time_micro_job['count'] . " \n",
						]);
					
				}
			}
			*/
			
			
		}
	}



	function queue_push($value, $frame_size= false){ // push data frame in stack
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue_test.dat';
		$queue_resource= fopen($dat_file, "r+");
		$cursor= false;


		if($frame_size !== false){
			$queue= serialize($value);
			$value_size= mb_strlen($queue);
			$value_size++; // reserved byte
			
			if($frame_size > $value_size){ // fill
				for($i= $value_size; $i< $frame_size; $i++) $queue.= ' ';
			} else {
				return false;
			}
			
			$queue.= "\n";
		} else {
			$queue= serialize($value) . "\n";
			$frame_size= mb_strlen($queue);
		}

		if(flock($queue_resource, LOCK_EX)) {
			$stat= fstat($queue_resource);
			
			fseek($queue_resource, $stat['size']);
			fwrite($queue_resource, $queue, $frame_size);
			ftruncate($queue_resource, $stat['size'] + $frame_size);
			fflush($queue_resource);
			flock($queue_resource, LOCK_UN);
			
			$cursor= $stat['size'] + $frame_size;
		}
		
		fclose($queue_resource);
		return $cursor;
	}




	function queue_pop($frame_size= false, $frame_cursor= false, $frames_count= 1){ // pop data frame from stack
		static $size_average= 0;
		
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
			
			if($size_average != 0) $length= $size_average;
			
			if($frame_size != false){
				$length= $frame_size;
			}
			
			if($stat['size'] - $length > 0) $cursor= $stat['size'] - $length;
			else $cursor= 0;

			if($frame_cursor != false){
				$cursor= $frame_cursor;
			}

			fseek($queue_resource, $cursor); // get data frame
			$stripe= fread($queue_resource, $length);
			$stripe_array= explode("\n", $stripe);
						
			if(is_array($stripe_array) && count($stripe_array) > 1){
				if($size_average == 0){
					$max_size= 0;

					foreach($stripe_array as $v){ // max size data frame
						if($max_size < mb_strlen($v)) $max_size= mb_strlen($v);
					}
					
					$size_average= $max_size +1;
				}
				
				array_pop($stripe_array);
				$value= array_pop($stripe_array); // get value
				
				if($frame_cursor === false){
					$crop= mb_strlen($value) + 1;
					
					if($size_average < $crop){ // average size data frame
						$size_average= $crop;
					}
					
					if($stat['size'] - $crop >= 0) $trunc= $stat['size'] - $crop;
					else $trunc= $stat['size']; // truncate file

					ftruncate($queue_resource, $trunc);
					fflush($queue_resource);
				}
				
				$value= unserialize(trim($value));
			}
			
			flock($queue_resource, LOCK_UN);
		}
		
		fclose($queue_resource);

		print_r([
			'crop'=> $crop,
			'length'=> $length,
			'size_average'=> $size_average,
			'cursor'=> $cursor,
			'max_size'=> $max_size,
			'stripe_array'=> $stripe_array,
			'value'=> $value,
			'trunc'=> $trunc,
			'stripe'=> $stripe
		]);


		if( // data frame size failure, retry
			$value === false && 
			isset($stripe) &&
			$size_average != 0 &&
			isset($stat['size']) &&
			$stat['size'] > 0
		){
			
			$size_average= 0;
			$value= queue_pop();
		}
		
		return $value;
	}
	
	
queue_manager(true);
queue_manager(false);
	

?>
