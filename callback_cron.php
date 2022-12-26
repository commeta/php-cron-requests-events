<?php
// Example callback script, for /cron/inc/callback_cron.php

// Write log
@file_put_contents(
	CRON_LOG_FILE, 
	microtime() . " DEBUG: start  microtime:" . 
		print_r([
			$GLOBALS['cron_session']['start_counter'],
			$_SERVER['QUERY_STRING'], 
			$_SERVER['SERVER_NAME'], 
			$_SERVER['REQUEST_METHOD'], 
			$_SERVER['REQUEST_URI']
		] , true) . " \n",
	FILE_APPEND | LOCK_EX
);

// Auto saved sesson variables, in Job name context (multithreading), autoload in next start session
if(isset($GLOBALS['cron_session']['start_counter'])){
	$GLOBALS['cron_session']['start_counter']++;
} else {
	$GLOBALS['cron_session']['start_counter']= 0;
?>
