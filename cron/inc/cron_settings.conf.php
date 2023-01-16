<?php

$cron_settings_conf= true;
include('cron_launch.conf.php');

$cron_requests_events_settings=[
	'log_file'=> $cron_requests_events_log . 'cron.log', // Path to log file, empty string '' - disables logging
	'dat_file'=> $cron_requests_events_dat . 'cron.dat', // Path to the thread manager system file
	'delete_dat_file_on_exit'=> false, // Used in tasks with the specified time and/or date, controlled mode
	'queue_file'=> $cron_requests_events_dat . 'queue.dat', // Path to the multiprocess queue system file
	'site_root'=> '',
	'delay'=> 1, // Timeout until next run in seconds
	'daemon_mode'=> true, // true\false resident mode (background service)
	'log_rotate_max_size'=> 10 * 1024 * 1024, // Maximum log size log 10 in MB
	'log_rotate_max_files'=> 5, // Store max 5 archived log files
	'log_level'=> 5, // Log verbosity: 2 warning, 5 debug info
	'url_key'=> 'my_secret_key', // Launch key in URI
];

###########################
# EXAMPLES
$cron_requests_events_jobs= [];

###########################
$cron_requests_events_jobs[]= [ // CRON Job 1, example
	'interval' => 0, // start interval 1 sec
	'callback' => $cron_requests_events_inc . "callback_cron.php",
	'multithreading' => false
];
##########


###########################
$cron_requests_events_jobs[]= [ // CRON Job 2, multithreading example
	'interval' => 10, // start interval 10 sec
	'callback' => $cron_requests_events_inc . "callback_cron.php",
	'multithreading' => true
];
##########


###########################
$cron_requests_events_jobs[]= [ // CRON Job 3, multicore example
	'time' => '03:39:00', // "hours:minutes:seconds" execute job on the specified time every day
	//'callback' => $cron_requests_events_inc . "callback_addressed_queue_example.php",
	'function' => "queue_address_manager", // if need file include: comment this, uncomment callback
	'param' => true, // use with queue_address_manager(true), in worker mode
	'multithreading' => true
];


for( // CRON job 3, multicore example, four cores, 
	$i= 0;
	$i< 4; // Max processor cores
	$i++	
) {
	$cron_requests_events_jobs[]= [ // CRON Job 3, multicore example
		'time' => '03:39:10', //  "hours:minutes:seconds" execute job on the specified time every day
		//'callback' => $cron_requests_events_inc . "callback_addressed_queue_example.php",
		'function' => "queue_address_manager", // if need file include: comment this, uncomment callback
		'param' => false, // use with queue_address_manager(false), in handler mode
		'multithreading' => true
	];
}

##########


###########################
$cron_requests_events_jobs[]= [ // CRON Job 4, multithreading example
	'date' => '10-01-2023', // "day-month-year" execute job on the specified date
	'callback' => $cron_requests_events_inc . "callback_cron.php",
	'multithreading' => true
];
##########


?>
