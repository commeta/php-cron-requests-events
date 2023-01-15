<?php
##########

// Example get param, function called in parallel process cron.php
// this code replace examples $cron_settings and $cron_jobs variables, add function get_param();
$process_id= getmypid();
$cron_root= dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR;

###########################
$cron_settings=[
	'log_file'=> false, // Path to log file, false - disables logging
	'dat_file'=> $cron_root . 'cron/dat/' . (string) $process_id . '.dat', // Path to the thread manager system file
	'delete_dat_file_on_exit'=> true,
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
$cron_jobs= [
	$process_id=>  [ // CRON Job
		'interval'=> 0, // start interval 1 sec
		'function'=> 'get_param',
		'param'=> $process_id,
		'multithreading' => false
	]
];


###########################
if(isset($_REQUEST["cron"])):
	touch($cron_settings['dat_file'], time() - $cron_settings['delay']);

	function get_param($process_id){
		global $cron_settings, $cron_resource, $cron_root;

		$frame_size= 64;
	
		while(true){ // example: loop from the end
			$frame= queue_address_pop($frame_size);
			$value= unserialize($frame);
				
			if($frame === '') { // end queue
				break 1;
			} else {
				if(!is_dir($cron_root . 'cron/log')) mkdir($cron_root . 'cron/log', 0755, true);

				file_put_contents(
					$cron_root . 'cron/log/cron.log', 
					sprintf(
						"%f Info: get_param while %s\n", 
						microtime(true), 
						print_r($value, true)),
					FILE_APPEND | LOCK_EX
				);
				
			}
		}
		
		if(isset($cron_resource) && is_resource($cron_resource)){// check global resource
			flock($cron_resource, LOCK_UN);
			fclose($cron_resource);
			unset($cron_resource);
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

if(!function_exists('queue_address_pop')) { 
	// frame_size - set frame size (int)
	// frame_cursor - PHP_INT_MAX for LIFO mode, get frame from cursor position (int)
	// frame_replace - empty('') is off, replace frame (string)
	// return value from stack frame, empty string '' if error or lifo queue end (string)
	function queue_address_pop($frame_size= 0, $frame_cursor= PHP_INT_MAX, $frame_replace= '', $callback= '') // :string 
	{ // pop data frame from stack
		global $cron_settings;
		
		$queue_resource= fopen($cron_settings['queue_file'], "r+");
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

if(!function_exists('send_param_and_parallel_launch')) { 
	function send_param_and_parallel_launch($params, $frame_size){
		global $cron_settings;
		$cron_root_dir= dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR;
		
		$queue_file=  $cron_root_dir .  'cron/dat/queue.dat';
		$cron_settings= ['queue_file'=> $queue_file];
		
		if(!is_dir(dirname($queue_file))) mkdir(dirname($queue_file), 0755, true);
		if(!file_exists($queue_file)) touch($queue_file);

		queue_address_push($params, $frame_size);
		include($cron_root_dir . 'cron.php');
	}
}
?>
