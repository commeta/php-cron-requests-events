<?php
// Example send param, before include('cron.php'):

###########################
function send_param_and_parallel_launch($params){
	global $cron_settings;
	
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
	
	
	
	$frame_size= 4096; // 1 cache page
	$cron_root= dirname(__FILE__) . DIRECTORY_SEPARATOR;
	$cron_settings= ['queue_file'=> $cron_root . 'cron/dat/queue.dat'];
	if(!file_exists($cron_settings['queue_file'])) touch($cron_settings['queue_file']);

	queue_address_push($params, $frame_size);
}


###########################
$params= ['process_id'=> getmypid()];
send_param_and_parallel_launch(serialize($params));

include('cron.php');
?>
