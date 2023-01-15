<?php
// Example get param, function called in parallel process cron.php
// this code replace examples $cron_settings and $cron_jobs variables, add function get_param();
$cron_root= dirname(__FILE__) . DIRECTORY_SEPARATOR;
$process_id= getmypid();

###########################$cron_settings['delete_dat_file']
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
$cron_jobs= [];

###########################
$cron_jobs[$process_id]= [ // CRON Job
	'interval'=> 0, // start interval 1 sec
	'function'=> 'get_param',
	'param'=> $process_id,
	'multithreading' => false
];

##########
if(isset($_REQUEST["cron"])) {
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
}

?>
