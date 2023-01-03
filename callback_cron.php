<?php
// Write log


if(CRON_LOG_LEVEL > 3){
	if(CRON_LOG_FILE){
		@file_put_contents(
			CRON_LOG_FILE, 
			time() . " INFO: start " . $process_id . ":" . $cron_session['start_counter'] . " \n",
			FILE_APPEND | LOCK_EX
		);
	}
}



// Save sesson variables, in Job name context (multithreading), autoload in next start session
if(isset($cron_session['start_counter'])){
	$cron_session['start_counter']++;
} else {
	$cron_session['start_counter']= 0;
}


?>
