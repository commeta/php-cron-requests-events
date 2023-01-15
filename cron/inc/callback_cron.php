<?php

// Write log
if($cron_requests_events_settings['log_level'] > 2){
	if($cron_requests_events_settings['log_file']){
		file_put_contents(
			$cron_requests_events_settings['log_file'], 
			sprintf("%d INFO: start %d %d %d \n", time(), getmypid(), $job_process_id, $cron_requests_events_session['start_counter']),
			FILE_APPEND | LOCK_EX
		);
	}
}



// Save sesson variables, in Job context, autoload in next start session
if(isset($cron_requests_events_session['start_counter'])){
	$cron_requests_events_session['start_counter']++;
} else {
	$cron_requests_events_session['start_counter']= 0;
}


?>
