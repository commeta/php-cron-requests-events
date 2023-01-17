<?php

// Write log
cron_log(sprintf("%d INFO: start %d %d %d", time(), getmypid(), $job_process_id, $cron_requests_events_session['start_counter']), 5);




// Save sesson variables, in Job context, autoload in next start session
if(isset($cron_requests_events_session['start_counter'])){
	$cron_requests_events_session['start_counter']++;
} else {
	$cron_requests_events_session['start_counter']= 0;
}


?>
