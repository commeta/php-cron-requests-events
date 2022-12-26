<?php
// Example callback script, for /cron/inc/callback_cron.php

// Write log
@file_put_contents(
	CRON_LOG_FILE, 
	microtime() . " DEBUG: start  microtime:" . 
		print_r([
			$GLOBALS['cron_session']['my_var'],
			$_SERVER['QUERY_STRING'], 
			$_SERVER['SERVER_NAME'], 
			$_SERVER['REQUEST_METHOD'], 
			$_SERVER['REQUEST_URI']
		] , true) . " \n",
	FILE_APPEND | LOCK_EX
);

// Save sesson variables, in Job name context, autoload in next start session
$GLOBALS['cron_session']['my_var']= 'example parameter';
write_cron_session($fp); 

?>
