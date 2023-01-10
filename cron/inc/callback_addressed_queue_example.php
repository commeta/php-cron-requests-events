<?php

if(isset($job['queue_address_manager'])){
	// true - call in multithreading context api cron.php, in worker mode
	// false - call in multithreading context api cron.php, in handler mode
	
	
	$start= microtime(true);
	queue_address_manager($job['queue_address_manager']);
	$time= microtime(true) - $start;
	
	if(CRON_LOG_LEVEL > 3){
		if(CRON_LOG_FILE){
			@file_put_contents(
				CRON_LOG_FILE, 
				sprintf("%f INFO: queue_manager %f %d %d\n", microtime(true), $time, getmypid(), $job_process_id),
				FILE_APPEND | LOCK_EX
			);
		}
	}
	
	
	// 12 thread: AMD Ryzen 5 2600X Six-Core Processor
	// PHP 8.2.0 with Zend OPcache Jit enable, PHP-FPM
	// 4 process, concurency
	// 1673385541.105537 INFO: queue_manager 0.013440 674899 2

	// 1673385551.415635 INFO: queue_manager 3
	// 1673385551.415714 INFO: queue_manager 0.318399 674897 3
	// 1673385551.415715 INFO: queue_manager 2
	// 1673385551.415816 INFO: queue_manager 0.316251 674899 5
	// 1673385551.417642 INFO: queue_manager 1
	// 1673385551.417729 INFO: queue_manager 0.319278 674898 4
	// 1673385551.417729 INFO: queue_manager 0
	// 1673385551.417840 INFO: queue_manager 0.317326 674896 6

}

?>
