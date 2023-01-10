<?php

if(isset($job['queue_address_manager'])){
	// true - call in multithreading context api cron.php, in worker mode
	// false - call in multithreading context api cron.php, in handler mode
	queue_address_manager($job['queue_address_manager']);
}

?>
