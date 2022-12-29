<?php
/*
 * PHP CRON use request events
 * Copyright 2022 commeta <dcs-spb@ya.ru>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 * 
 */
 
 ////////////////////////////////////////////////////////////////////////
// CRON Jobs
define("CRON_SITE_ROOT", preg_match('/\/$/',$_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR);

$cron_jobs= [];

###########################
# EXAMPLES


##########
$cron_jobs[]= [ // CRON Job 1, example
	'name' => 'job1',
	'interval' => 0, // start interval 1 sec
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => false
];
##########

##########
$cron_jobs[]= [ // CRON Job 2, multithreading example
	'name' => 'job2multithreading',
	'interval' => 10, // start interval 10 sec
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => true
];
##########

##########
for( // CRON job 3, multithreading example, four core
	$i= 0;
	$i< 4; // Max processor cores
	$i++	
) {
	$cron_jobs[]= [ // CRON Job 3, multithreading example
		'name' => 'multithreading_' . $i,
		'date' => '31-12-2022', // "day-month-year" execute job on the specified date
		'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
		'multithreading' => true
	];
}
##########


##########
$cron_jobs[]= [ // CRON Job 4, multithreading example
	'name' => 'job4multithreading',
	'time' => '21:03:01', // "hours:minutes:seconds" execute job on the specified time every day
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => true
];
##########

////////////////////////////////////////////////////////////////////////
// Variables
define("CRON_LOG_FILE", CRON_SITE_ROOT . 'cron/log/cron.log'); // false switched off
define("CRON_DAT_FILE", CRON_SITE_ROOT . 'cron/dat/cron.dat');

define("CRON_DELAY", 0);  // interval between requests in seconds, 0 to max int, increases the accuracy of the job timer hit

define("CRON_LOG_ROTATE_MAX_SIZE", 10 * 1024 * 1024); // 10 in MB
define("CRON_LOG_ROTATE_MAX_FILES", 5);
define("CRON_LOG_LEVEL", 3);

define("CRON_URL_KEY", 'my_secret_key'); // change this!


////////////////////////////////////////////////////////////////////////
// Debug
/*
@file_put_contents(
	CRON_LOG_FILE, 
	microtime() . " DEBUG: start  microtime:" . 
		print_r([
			$_SERVER['QUERY_STRING'], 
			$_SERVER['SERVER_NAME'], 
			$_SERVER['REQUEST_METHOD'], 
			$_SERVER['REQUEST_URI']
		] , true) . " \n",
	FILE_APPEND | LOCK_EX
);
*/
 
////////////////////////////////////////////////////////////////////////
// Functions
if(!function_exists('open_cron_socket')) { 
	function open_cron_socket($cron_url_key, $process_id= false){ // Start job in parallel process
		if($process_id !== false) $cron_url_key.= '&process_id=' . $process_id;
		$cron_url= 'https://' . strtolower(@$_SERVER["HTTP_HOST"]) . "/". basename(__FILE__) ."?cron=" . $cron_url_key;

		$wget= false;
		if(strtolower(PHP_OS) == 'linux') {
			foreach(explode(':', getenv('PATH')) as $path){
				if(is_executable($path.'/wget')) {
					$wget= $path.'/wget';
					break 1;
				}
			}
		}
		
		if(
			is_callable("shell_exec") &&
			$wget
		){
			shell_exec($wget . ' -T 1 --delete-after -q "' . $cron_url . '" > /dev/null &');
		} else {
			@fclose( 
				@fopen(
					$cron_url, 
					'r', 
					false, 
					stream_context_create([
						'http'=>[
							'timeout' => 0.04
						]
					])
				)
			);
		}
	}
}



////////////////////////////////////////////////////////////////////////
// main
if(
	isset($_REQUEST["cron"]) &&
	$_REQUEST["cron"] == CRON_URL_KEY
){
	
	////////////////////////////////////////////////////////////////////////
	// Classes: system api
	class time_limit_exception { // Exit if time exceed time_limit
		protected $enabled= false;

		public function __construct() {
			register_shutdown_function( array($this, 'onShutdown') );
		}
		
		public function enable() {
			$this->enabled= true;
		}   
		
		public function disable() {
			$this->enabled= false;
		}   
		
		public function onShutdown() { 
			if ($this->enabled) { //Maximum execution time of $time_limit$ second exceeded
				_die();
			}   
		}   
	}
	
	
	// Functions: system api
	function write_cron_session(& $cron_resource, & $cron_session){ 
		$serialized= serialize($cron_session);

		rewind($cron_resource);
		fwrite($cron_resource, $serialized);
		ftruncate($cron_resource, mb_strlen($serialized));
		fflush($cron_resource);
	}

	function _touch($dat_file){
		static $old_time= 0;
		
		if($old_time != time()){
			$old_time= time();
			touch($dat_file);
		}
		
		// Note: if TICK_INTERRUPT is false, this function must be run per second
		// Description: function _touch() an update session file timestamp, to prevent start process
	}
	
	
	function tick_interrupt($s= false){
		static $old_time= 0;
		global $cron_dat_file;
		
		if(
			$old_time != time() && 
			isset($cron_dat_file) &&
			$cron_dat_file !== false
		){
			$old_time= time();
			
			if(is_file($cron_dat_file)){ // update mtime stream descriptor file
				_touch($cron_dat_file);
			}
		}

		// Note: use block interrupt operations with minimal delays
		// Example: sleep(10);
		// for($i=0;$i<10;$i++) sleep($i);
		// Description: function tick_interrupt() an update session file timestamp, to prevent start process
		//  sleep() blocking tick_interrupt()
	}

	function _die($return= ''){
		global $cron_resource, $cron_session, $cron_limit_exception;
		
		$cron_limit_exception->disable();
		
		if(isset($cron_resource) && is_resource($cron_resource)){
			write_cron_session($cron_resource, $cron_session);
		}
		
		die($return);
	}
	
	function cron_restart(){// restart cron
		global $cron_resource, $cron_session, $cron_limit_exception, $cron_dat_file;
		
		$cron_limit_exception->disable();
		
		if(isset($cron_resource) && is_resource($cron_resource)){
			write_cron_session($cron_resource, $cron_session);
			
			flock($cron_resource, LOCK_UN);
			fclose($cron_resource);
			unset($cron_resource);
		}
		
		if(isset($cron_dat_file) && is_file($cron_dat_file)){ // update mtime stream descriptor file
			$dat_file= $cron_dat_file;
			$cron_dat_file= false; // disable interrupt
			
			touch($dat_file, time() - 1);
		}
		
		open_cron_socket(CRON_URL_KEY);
		die();
	}
	

	function fcgi_finish_request(){
		// check if fastcgi_finish_request is callable
		if(is_callable('fastcgi_finish_request')) {
			session_write_close();
			fastcgi_finish_request();
		}

		while(ob_get_level()) ob_end_clean();
		
		ob_start();
		
		header(filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_STRING).' 200 OK');
		header('Content-Encoding: none');
		header('Content-Length: '.ob_get_length());
		header('Connection: close');
		http_response_code(200);

		@ob_end_flush();
		@ob_flush();
		@flush();
	}


	function init_background_cron(){
		ignore_user_abort(true);
		fcgi_finish_request();

		if (is_callable('proc_nice')) {
			proc_nice(15);
		}

		if(CRON_DELAY == 0){
			set_time_limit(0);
			ini_set('MAX_EXECUTION_TIME', 0);
		} else {
			set_time_limit(600);
			ini_set('MAX_EXECUTION_TIME', 600);
		}
		
		ini_set('error_reporting', E_ALL);
		ini_set('display_errors', 1); // 1 to debug
		ini_set('display_startup_errors', 1);
		
		if(is_callable('register_tick_function')) {
			declare(ticks=1);
			register_tick_function('tick_interrupt');
		}
	}

	function cron_log_rotate($cron_log_rotate_max_size, $cron_log_rotate_max_files){ // LOG Rotate
		if(CRON_LOG_FILE && filesize(CRON_LOG_FILE) >  $cron_log_rotate_max_size / $cron_log_rotate_max_files) {
			rename(CRON_LOG_FILE, CRON_LOG_FILE . "." . time());
			
			file_put_contents(
				CRON_LOG_FILE, 
				date('m/d/Y H:i:s',time()) . " INFO: log rotate\n", 
				FILE_APPEND | LOCK_EX
			);
				
			$the_oldest = time();
			$log_old_file = '';
			$log_files_size = 0;
						
			foreach(glob(CRON_LOG_FILE . '*') as $file_log_rotate){
				$log_files_size+= filesize($file_log_rotate);
				if ($file_log_rotate == CRON_LOG_FILE) {
					continue;
				}
					
				$log_mtime = filectime($file_log_rotate);
				if ($log_mtime < $the_oldest) {
					$log_old_file = $file_log_rotate;
					$the_oldest = $log_mtime;
				}
			}

			if ($log_files_size >  $cron_log_rotate_max_size) {
				if (file_exists($log_old_file)) {
					unlink($log_old_file);
					file_put_contents(
						CRON_LOG_FILE, 
						date('m/d/Y H:i:s', time()) . "INFO: log removal\n",
						FILE_APPEND | LOCK_EX
					);
				}
			}
		}
	}

	//function queue_manager(){}
	
	function check_date_time(& $job, & $cron_session){ 
		if(!isset($cron_session[$job['name']]['last_update'])) { // init
			$cron_session[$job['name']]['last_update']= 0;
		}
						
		if(!isset($cron_session[$job['name']]['complete'])){
			$cron_session[$job['name']]['complete']= false;
		}
		
		if(!isset($cron_session[$job['name']]['unlock'])){
			$cron_session[$job['name']]['unlock']= false;
		}
		
		if(!isset($cron_session[$job['name']]['unlocked'])){
			$cron_session[$job['name']]['unlocked']= false;
		}
		
		if(isset($job['date'])) {
			$d= explode('-', $job['date']);
		}
		if(isset($job['time'])) {
			$t= explode(':', $job['time']);
		}
		
		if(
			isset($job['date']) || 
			isset($job['time'])
		) {
			$cron_session[$job['name']]['mode']= false;
		} else {
			$cron_session[$job['name']]['mode']= true;
		}
		
		if(isset($job['date']) && isset($job['time'])){ // check date time, one - time
			if(
				!$cron_session[$job['name']]['complete'] && 
				$job['date'] == date('d-m-Y', time()) && // 23 over check
				(
					intval($t[0]) + 1 == intval(date("H")) ||
					(
						intval($t[0]) == intval(date("H"))  &&
						intval($t[1]) <= intval(date("i")) 
					)
				)
			){
				$cron_session[$job['name']]['lock']= false;
			} else {// lock job forever, dat!
				$cron_session[$job['name']]['lock']= true;
			}
		} else {
			if(isset($job['date'])){ // check date, one - time
				if(
					!$cron_session[$job['name']]['complete'] && 
					$job['date'] == date('d-m-Y', time())
				){
					$cron_session[$job['name']]['lock']= false;
				} else {// lock job forever
					$cron_session[$job['name']]['lock']= true;
				}
			}
				
			if(isset($job['time'])){ // check time, every day
				if( 
					intval($t[0]) + 1 == intval(date("H")) ||
					(
						intval($t[0]) == intval(date("H"))  &&
						intval($t[1]) <= intval(date("i")) 
					)
				){
					if(!$cron_session[$job['name']]['complete']){
						$cron_session[$job['name']]['lock']= false;
					} else {// lock job
						$cron_session[$job['name']]['lock']= true;
					}
				} else {// lock job
					$cron_session[$job['name']]['lock']= true;
				}
				
				// unlock job
				if(intval($t[0]) > intval(date("H")) && $cron_session[$job['name']]['unlocked'] === false){
					$cron_session[$job['name']]['unlock']= true;
					$cron_session[$job['name']]['lock']= true;
				}
			}
			
			//if(is_array($t) && is_array($d)){
				//$time_stamp= mktime(intval($t[0]), intval($t[1]), intval($t[2]), intval($d[1]), intval($d[0]), intval($d[2]));
			//}
		}
	}
	
	
	function callback_connector(& $job, & $cron_session, $mode= false){ 
		$cron_session[$job['name']]['complete']= true;
		
		if($mode){ // multithreading\singlethreading
			open_cron_socket(CRON_URL_KEY, $job['name']); 
		} else {
			// include connector
			if(file_exists($job['callback'])) {
				include $job['callback'];
			} else {
				if(CRON_LOG_FILE){
					file_put_contents(
						CRON_LOG_FILE,
						implode(' ', [
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'ERROR:',
							'name' => $job['name'],
							'callback' => $job['callback'],
							'mode' => $job['multithreading'] ? 'multithreading' : 'singlethreading',
						]) . "\n",
						FILE_APPEND | LOCK_EX
					);
				}
			}
		}

		$cron_session[$job['name']]['last_update']= time();
	}
	
	
	function save_value_to_cron_session($job_name, $key, $value){
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . $job_name . '.dat';
		
		$job_session= [];
		
		if(!file_exists($dat_file)) {
			$job_session[$job_name][$key]=  $value;
			file_put_contents($dat_file, serialize($job_session), LOCK_EX | LOCK_NB);
			return true;
		}
		
		$cron_resource= fopen($dat_file, "r+");
		$blocked= false;
		
		if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
			$cs= unserialize(@fread($cron_resource, filesize($dat_file)));
			if(is_array($cs)) $job_session= $cs;
			
			$job_session[$job_name][$key]=  $value;
			$blocked= true;
			
			write_cron_session($cron_resource, $job_session);
			flock($cron_resource, LOCK_UN);
		}
		
		fclose($cron_resource);
		return $blocked;
	}
	
	function lock_unlock_everday_time(& $cron_session, & $job){ // lock\unlock job to prevent next start
		if(
			$cron_session[$job['name']]['unlock'] === true  &&
			$cron_session[$job['name']]['unlocked'] === false &&
			$cron_session[$job['name']]['complete'] === true
		) {
			if(save_value_to_cron_session($job['name'], 'complete', false)){
				$cron_session[$job['name']]['complete']= false;
				$cron_session[$job['name']]['unlock']= false;
				$cron_session[$job['name']]['unlocked']= true;
			}
		}
	}
	
	function cron_start_date_time(& $cron_session, & $job, $mode, $main){ // start connector from date\time event 
			if(
				$cron_session[$job['name']]['mode'] === false &&
				$cron_session[$job['name']]['lock'] === false &&
				$cron_session[$job['name']]['complete'] === false
			){
				callback_connector($job, $cron_session, $mode);
				$cron_session[$job['name']]['unlocked']= false;
			}
			
			if($main) lock_unlock_everday_time($cron_session, $job);
	}
	
	function cron_start_interval(& $cron_session, & $job, $mode= false){ // start connector from interval event 
			if(
				$cron_session[$job['name']]['mode'] === true && 
				$cron_session[$job['name']]['last_update'] + $job['interval'] < time()
			){
				callback_connector($job, $cron_session, $mode);
			}
	}
	

	function singlethreading_dispatcher(& $cron_jobs, & $cron_session){ // main loop job list
		foreach($cron_jobs as & $job){
			$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . $job['name'] . '.dat';
			check_date_time($job, $cron_session);
			
			if($job['multithreading']){ // refresh last update
				if(file_exists($dat_file)){
					$cron_session[$job['name']]['last_update']= filemtime($dat_file);
				}
			}
			
			cron_start_date_time($cron_session, $job, $job['multithreading'], true);
			cron_start_interval($cron_session,  $job, $job['multithreading']);
		}
	}
	
	
	function multithreading_dispatcher(& $cron_jobs, & $cron_resource, & $cron_session, & $cron_dat_file){  // main loop job list
		// Dispatcher init
		$cron_dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . $_GET["process_id"] . '.dat';
		if(!file_exists($cron_dat_file)) touch($cron_dat_file);

		
		$cron_resource= fopen($cron_dat_file, "r+");
		if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
			$cs= unserialize(@fread($cron_resource, filesize($cron_dat_file)));
			if(is_array($cs)) $cron_session= $cs;
			
			foreach($cron_jobs as & $job) {
				if($job['name'] == $_GET["process_id"] && $job['multithreading']) {
					check_date_time($job, $cron_session);
					cron_start_date_time($cron_session, $job, false, false);
					cron_start_interval($cron_session,  $job);
				}
			}

			write_cron_session($cron_resource, $cron_session);

			// END Job
			flock($cron_resource, LOCK_UN);
		}

		fclose($cron_resource);
		_die();
	}


	function cron_config_profiler(& $cron_session, & $cron_jobs){ // session maintenance
		if(!isset($cron_session['filemtime'])){
			$cron_session['filemtime']= filemtime(__FILE__);
		}
		
		if($cron_session['filemtime'] != filemtime(__FILE__)){ // write in main file event, reset sessions
			foreach($cron_jobs as $job){
				if($job['multithreading']){
					$cron_dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . $job['name'] . '.dat';
					
					if(file_exists($cron_dat_file)) {
						unlink($cron_dat_file);
					}
					
					$cron_session= [];
				}
			}
		}
	}
	
	
	function memory_profiler(& $cron_jobs){
		static  $profiler= [];
		
		if(!isset($profiler['memory_get_usage'])){
			$profiler['memory_get_usage']= 0;
		}
		
		if(!isset($profiler['filemtime'])){
			$profiler['filemtime']= filemtime(__FILE__);
		}
		
		if($profiler['filemtime'] != filemtime(__FILE__)){ // write in main file event, restart
			cron_restart();
		}
		
		if($profiler['memory_get_usage'] < memory_get_usage()){
			$profiler['memory_get_usage']= memory_get_usage();
			
			if(CRON_LOG_FILE){
				file_put_contents(
					CRON_LOG_FILE,
					implode(' ', [
						'date'=> date('m/d/Y H:i:s', time()),
						'message'=> 'INFO:',
						'name' => 'memory_get_usage',
						'value' => $profiler['memory_get_usage'],
					]) . "\n",
					FILE_APPEND | LOCK_EX
				);
			}
		} 
		
		
		foreach($cron_jobs as $job){
			if(is_file($job['callback'])){
				if(!isset($profiler['filemtime_' . $job['callback']])){
					$profiler['filemtime_' . $job['callback']]= filemtime($job['callback']);
				}
				
				if($profiler['filemtime_' . $job['callback']] != filemtime($job['callback'])){ // write in callback file event, restart
					cron_restart();
				}
			}
		}
	}
	
	////////////////////////////////////////////////////////////////////////
	// start in background
	init_background_cron();
	
	$cron_dat_file= CRON_DAT_FILE;
	$cron_resource= true;
	$cron_session= [];
	
	foreach($cron_jobs as $k => $job){ // check job name symbols
		$cron_jobs[$k]['name']= mb_eregi_replace("[^a-zA-Z0-9_]", '', $job['name']);
	}

	$cron_limit_exception= new time_limit_exception;
	$cron_limit_exception->enable();

	////////////////////////////////////////////////////////////////////////
	// multithreading 
	if( // job in parallel process. For long tasks, a separate dispatcher is needed
		isset($_GET["process_id"])
	){
		foreach($cron_jobs as $job) {
			if($job['name'] == $_GET["process_id"] && $job['multithreading']) {
				multithreading_dispatcher($cron_jobs, $cron_resource, $cron_session, $cron_dat_file);
			}
		}
		
		_die();
	}

	
	////////////////////////////////////////////////////////////////////////
	// Dispatcher init
	if(@filemtime(CRON_DAT_FILE) + CRON_DELAY > time()) _die();

	$cron_resource= fopen(CRON_DAT_FILE, "r+");
	if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
		$cs= unserialize(@fread($cron_resource, filesize(CRON_DAT_FILE)));
		if(is_array($cs)) $cron_session= $cs;
		
		if(CRON_LOG_FILE && !is_dir(dirname(CRON_LOG_FILE))) {
			mkdir(dirname(CRON_LOG_FILE), 0755, true);
		}
		
		cron_config_profiler($cron_session, $cron_jobs);
		
		//###########################################
		// check jobs
		singlethreading_dispatcher($cron_jobs, $cron_session);
		write_cron_session($cron_resource, $cron_session);

		if(CRON_DELAY == 0){
			while(true){
				singlethreading_dispatcher($cron_jobs, $cron_session);
				write_cron_session($cron_resource, $cron_session);
				memory_profiler($cron_jobs);
				
				if(CRON_LOG_FILE){
					cron_log_rotate(CRON_LOG_ROTATE_MAX_SIZE, CRON_LOG_ROTATE_MAX_FILES);
				}
				
				sleep(1);
			}
		}
		
		//###########################################
		if(CRON_LOG_FILE) cron_log_rotate(CRON_LOG_ROTATE_MAX_SIZE, CRON_LOG_ROTATE_MAX_FILES);
		
		// END Jobs
		flock($cron_resource, LOCK_UN);
	}

	fclose($cron_resource);
	unset($cron_resource);

	_die();
} else {
	////////////////////////////////////////////////////////////////////////
	// check time out to start in background 
	if(file_exists(CRON_DAT_FILE)){
		if(filemtime(CRON_DAT_FILE) + CRON_DELAY < time()){
			$cron_resource= fopen(CRON_DAT_FILE, "r");
			$cron_started= true;
			
			if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
					$cron_started= false;
					flock($cron_resource, LOCK_UN);
			}
			
			fclose($cron_resource);
			
			if(!$cron_started) open_cron_socket(CRON_URL_KEY);
		} 
	} else {
		@mkdir(dirname(CRON_DAT_FILE), 0755, true);
		touch(CRON_DAT_FILE, time() - CRON_DELAY);
		
		if(CRON_LOG_FILE) {
			mkdir(dirname(CRON_LOG_FILE), 0755, true);
			touch(CRON_LOG_FILE);
		}
	}

	unset($cron_jobs);
}
?>
