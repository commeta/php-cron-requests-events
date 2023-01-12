<?php

if(isset($job['param'])){
	// true - call in multithreading context api cron.php, in worker mode
	// false - call in multithreading context api cron.php, in handler mode
	
	
	$start= microtime(true);
	queue_address_manager($job['param']);
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
	

// fragment log file
###########################################

	// 12 thread: AMD Ryzen 5 2600X Six-Core Processor
	// PHP 8.2.0 with Zend OPcache Jit enable, PHP-FPM
	// 1673479681.945600 INFO: queue_manager 0.014953 678399 2

	// 1673479692.254133 INFO: queue_manager 0.317006 678399 4
	// 1673479692.254179 INFO: queue_manager 0.316919 678400 5
	// 1673479692.256135 INFO: queue_manager 0.320812 678401 3
	// 1673479692.256229 INFO: queue_manager 0.317146 678402 6
###########################################


	
###########################################
	// 1 core: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
	// PHP 7.4.3 with Zend OPcache
	// 1673479321.668813 INFO: queue_manager 0.087133 7452 2
	
	// 1673479332.170000 INFO: queue_manager 0.571906 7464 3
	// 1673479332.170225 INFO: queue_manager 0.432375 7469 5
	// 1673479332.172232 INFO: queue_manager 0.376356 7470 6
	// 1673479332.173088 INFO: queue_manager 0.500547 7467 4	
	
	
	
}


?>
