<?php
// Write log

/*
if(CRON_LOG_FILE){
	@file_put_contents(
		CRON_LOG_FILE, 
		microtime() . " DEBUG: start  microtime:" . 
			print_r([
				$cron_session['start_counter'],
				$_SERVER['QUERY_STRING'], 
				$_SERVER['SERVER_NAME'], 
				$_SERVER['REQUEST_METHOD'], 
				$_SERVER['REQUEST_URI']
			] , true) . " \n",
		FILE_APPEND | LOCK_EX
	);
}
*/

// Save sesson variables, in Job name context (multithreading), autoload in next start session
if(isset($cron_session['start_counter'])){
	$cron_session['start_counter']++;
} else {
	$cron_session['start_counter']= 0;
}


?>
