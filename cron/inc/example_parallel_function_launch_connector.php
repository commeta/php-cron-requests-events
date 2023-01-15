<?php
// Example send param, before include('cron.php'):
// works in the same subdirectory with the cron.php

###########################
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


function send_param_and_parallel_launch($params, $cron_root_dir, $frame_size){
	global $cron_settings;
	
	$queue_file=  $cron_root_dir .  '/cron/dat/queue.dat';
	$cron_settings= ['queue_file'=> $queue_file];
	
	if(!is_dir(dirname($queue_file))) mkdir(dirname($queue_file), 0755, true);
	if(!file_exists($queue_file)) touch($queue_file);

	queue_address_push($params, $frame_size);
	include($cron_root_dir . '/cron.php');
}


###########################
$params= [
	'process_id'=> getmypid(),
];

send_param_and_parallel_launch(serialize($params), dirname(__FILE__), 64);

?>
