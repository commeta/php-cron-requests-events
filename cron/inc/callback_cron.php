<?php
// Write log
if(CRON_LOG_LEVEL > 2){
	if(CRON_LOG_FILE){
		@file_put_contents(
			CRON_LOG_FILE, 
			sprintf("%d INFO: start %d %d %d \n", time(), getmypid(), $job_process_id, $cron_session['start_counter']),
			FILE_APPEND | LOCK_EX
		);
	}
}



// Save sesson variables, in Job context, autoload in next start session
if(isset($cron_session['start_counter'])){
	$cron_session['start_counter']++;
} else {
	$cron_session['start_counter']= 0;
}


?>
