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

###########################
$cron_jobs[]= [ // CRON Job 1, example
	'interval' => 0, // start interval 1 sec
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => false
];
##########

###########################
$cron_jobs[]= [ // CRON Job 2, multithreading example
	'interval' => 10, // start interval 10 sec
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => true
];
##########
 
###########################
for( // CRON job 3, multicore example, four cores, use with queue_manager()
	$i= 0;
	$i< 4; // Max processor cores
	$i++	
) {
	$cron_jobs[]= [ // CRON Job 3, multicore example
		'date' => '31-12-2022', // "day-month-year" execute job on the specified date
		'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
		'multithreading' => true
	];
}
##########

  
###########################
$cron_jobs[]= [ // CRON Job 4, multithreading example
	'time' => '05:05:01', // "hours:minutes:seconds" execute job on the specified time every day
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
define("CRON_LOG_LEVEL", 2);

define("CRON_URL_KEY", 'my_secret_key'); // change this!
define("CRON_SECURITY", false); // set true for high danger environment


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
		static $wget= false;
		
		if($process_id !== false) $cron_url_key.= '&process_id=' . $process_id;
		$cron_url= 'https://' . strtolower(@$_SERVER["HTTP_HOST"]) . "/". basename(__FILE__) ."?cron=" . $cron_url_key;

		
		if(strtolower(PHP_OS) == 'linux' && $wget == false) {
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
	}


	function queue_manager($mode){ // example: multicore queue
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue.dat';
		if(!file_exists($dat_file)) touch($dat_file);
		
		if($mode){
			// example: multicore queue worker
			// use:
			// queue_push($multicore_long_time_micro_job); // add micro job in queue from worker process
			
			for($i= 0; $i < 1000; $i++){
				queue_push([
					'url'=> "https://multicore_long_time_micro_job?param=" . $i,
					'count'=> $i
				]);
				
			}
			
		} else {
			// example: multicore queue handler
			// use:
			// $multicore_long_time_micro_job= queue_pop(); // get micro job from queue in children processess 
			// exec $multicore_long_time_micro_job - in a parallel thread
			
			$start= true;
			while($start){
				$multicore_long_time_micro_job= queue_pop();
				
				if($multicore_long_time_micro_job === false) {
					$start= false;
					break;
				} else {
					// $content= file_get_contents($multicore_long_time_micro_job['url']);
					// file_put_contents('cron/temp/url-' . $multicore_long_time_micro_job['count'] . '.html', $content);
					
					if(CRON_LOG_LEVEL > 3){
						if(CRON_LOG_FILE){
							@file_put_contents(
								CRON_LOG_FILE, 
								time() . " INFO: queue_manager " . $multicore_long_time_micro_job['count'] . " \n",
								FILE_APPEND | LOCK_EX
							);
						}
					}
					
				}
			}
		}
	}
	
	
	function queue_push($value){ // push data frame in stack
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue.dat';
		$queue_resource= fopen($dat_file, "r+");

		if(flock($queue_resource, LOCK_EX)) { // 
			$stat= fstat($queue_resource);
			
			$queue= serialize($value) . "\n";
			fseek($queue_resource, $stat['size']);
			fwrite($queue_resource, $queue, mb_strlen($queue));
			ftruncate($queue_resource, $stat['size'] + mb_strlen($queue));
			fflush($queue_resource);
			
			flock($queue_resource, LOCK_UN);
		}
		
		fclose($queue_resource);
	}
	
	function queue_pop(){ // pop data frame from stack
		static $size_average= 0;
		
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . 'queue.dat';
		$queue_resource= fopen($dat_file, "r+");
		$value= false;
		
		if(flock($queue_resource, LOCK_EX)) {
			$stat= fstat($queue_resource);
			
			if($stat['size'] < 1){ // queue file is empty
				flock($queue_resource, LOCK_UN);
				fclose($queue_resource);
				return false;
			}

			if($stat['size'] < 4096) $length= $stat['size']; // set buffer size  equal max size queue value
			else $length= 4096;
			
			if($size_average != 0) $length= $size_average;
			
			if($stat['size'] - $length > 0) $cursor= $stat['size'] - $length;
			else $cursor= 0;

			fseek($queue_resource, $cursor); // get data frame
			$stripe= fread($queue_resource, $length);
			$stripe_array= explode("\n", $stripe);
						
			if(is_array($stripe_array) && count($stripe_array) > 1){
				if($size_average == 0){
					$max_size= 0;

					foreach($stripe_array as $v){ // max size data frame
						if($max_size < mb_strlen($v)) $max_size= mb_strlen($v);
					}
					
					$size_average= $max_size +1;
				}
				
				array_pop($stripe_array);
				$value= array_pop($stripe_array); // get value
				$crop= mb_strlen($value) + 1;
				
				if($size_average < $crop){ // average size data frame
					$size_average= $crop;
				}
				
				if($stat['size'] - $crop >= 0) $trunc= $stat['size'] - $crop;
				else $trunc= $stat['size']; // truncate file

				ftruncate($queue_resource, $trunc);
				fflush($queue_resource);
				
				$value= unserialize($value);
			}
			
			flock($queue_resource, LOCK_UN);
		}
		
		fclose($queue_resource);

		if( // data frame size failure, retry
			$value === false && 
			isset($stripe) &&
			$size_average != 0 &&
			isset($stat['size']) &&
			$stat['size'] > 0
		){
			
			$size_average= 0;
			$value= queue_pop();
		}
		
		return $value;
	}
	

	function _die($return= ''){
		global $cron_resource, $cron_session, $cron_limit_exception, $cron_dat_file;
		$cron_limit_exception->disable();
		
		if(isset($cron_resource) && is_resource($cron_resource)){// check global resource
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
	}

	function cron_log_rotate(){ // LOG Rotate
		static  $counter= false;
		
		if(CRON_DELAY == 0 && $counter === false) $counter= time();
		if(CRON_DELAY == 0 && $counter > time() - 600){
			return true;
		}
		$counter= time();

		if(CRON_LOG_FILE && filesize(CRON_LOG_FILE) >  CRON_LOG_ROTATE_MAX_SIZE / CRON_LOG_ROTATE_MAX_FILES) {
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

			if ($log_files_size >  CRON_LOG_ROTATE_MAX_SIZE) {
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

	
	function callback_connector(& $job, & $cron_session, $mode, $process_id){ 
		if($mode){ // multithreading\singlethreading
			open_cron_socket(CRON_URL_KEY, $process_id); 
		} else {
			// include connector
			$cron_session[$process_id]['last_update']= time();
			
			if(file_exists($job['callback'])) {
				if(CRON_SECURITY) {
					$cron_security_md5_before_include= md5(serialize([$cron_session, $job]));
					$cron_security_variables_before_include= [$cron_session, $job];
				}
				
				include $job['callback'];
				
				if(CRON_SECURITY && $cron_security_md5_before_include != md5(serialize([$cron_session, $job]))) {
					list($cron_session, $job) = $cron_security_variables_before_include;
				}
			} else {
				if(CRON_LOG_FILE){
					file_put_contents(
						CRON_LOG_FILE,
						implode(' ', [
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'ERROR:',
							'process_id' => $process_id,
							'callback' => $job['callback'],
							'mode' => $job['multithreading'] ? 'multithreading' : 'singlethreading',
						]) . "\n",
						FILE_APPEND | LOCK_EX
					);
				}
			}
		}
		
		$cron_session[$process_id]['complete']= true;
	}
	
		
	function cron_session_init(& $cron_session, & $job, $process_id){
		static $init= [];
		
		if(isset($init[$process_id])) return true;
		$init[$process_id]= true;
		
		if(isset($cron_session[$process_id]['md5'])) {
			if($cron_session[$process_id]['md5'] != md5(serialize($job))){
				$cron_session[$process_id]= [];
				$cron_session[$process_id]['md5']= md5(serialize($job));
			}
		} else {
			$cron_session[$process_id]['md5']= md5(serialize($job));
		}
		
		if(!isset($cron_session[$process_id]['last_update'])) {
			$cron_session[$process_id]['last_update']= 0;
		}
						
		if(!isset($cron_session[$process_id]['complete'])){
			$cron_session[$process_id]['complete']= false;
		}
	}
	
	function cron_check_job(& $cron_session, & $job, $mode, $main, $process_id){
		if(isset($job['date']) || isset($job['time'])){
			$time_stamp= false;
			
			if(isset($job['date'])) $d= explode('-', $job['date']);		
			if(isset($job['time'])) $t= explode(':', $job['time']);
			
			if(isset($job['date']) && isset($job['time'])){ // check date time, one - time
				$time_stamp= mktime(intval($t[0]), intval($t[1]), intval($t[2]), intval($d[1]), intval($d[0]), intval($d[2]));
			} else {
				if(isset($job['date'])){ // check date, one - time
					$time_stamp= mktime(0, 0, 0, intval($d[1]), intval($d[0]), intval($d[2]));
				}
				if(isset($job['time'])){ // check time, every day
					$time_stamp= mktime(intval($t[0]), intval($t[1]), intval($t[2]));
					
					if( // unlock job
						date('d-m-Y', time()) != date('d-m-Y', $cron_session[$process_id]['last_update']) &&
						$cron_session[$process_id]['complete']
					){
						$cron_session[$process_id]['complete']= false;
					}
				}
			}
			
			if(
				$time_stamp < time() &&
				$cron_session[$process_id]['complete'] === false
			){
				callback_connector($job, $cron_session, $mode, $process_id);
			}
		} else {
			if(
				$cron_session[$process_id]['last_update'] + $job['interval'] < time()
			){
				callback_connector($job, $cron_session, $mode, $process_id);
			}
		}
	}
	
 
	function singlethreading_dispatcher(& $cron_jobs, & $cron_session){ // main loop job list
		foreach($cron_jobs as $process_id=> & $job){
			cron_session_init($cron_session, $job, $process_id);
			cron_check_job($cron_session, $job, $job['multithreading'], true, $process_id);
		}
	}
	
	
	function multithreading_dispatcher(& $job, & $cron_resource, & $cron_session, & $cron_dat_file){  // main loop job list
		// Dispatcher init
		$process_id= intval($_GET["process_id"]);
		$cron_dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . $process_id . '.dat';
		if(!file_exists($cron_dat_file)) touch($cron_dat_file);
		
		$cron_resource= fopen($cron_dat_file, "r+");
		if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
			$stat= fstat($cron_resource);
			$cs= unserialize(@fread($cron_resource, $stat['size']));
			if(is_array($cs)) $cron_session= $cs;
			
			cron_session_init($cron_session, $job, $process_id);
			cron_check_job($cron_session, $job, false, false, $process_id);
			write_cron_session($cron_resource, $cron_session);

			// END Job
			flock($cron_resource, LOCK_UN);
		}

		fclose($cron_resource);
	}


	function memory_profiler(& $cron_jobs){
		static  $profiler= [];
		
		if(!isset($profiler['time'])) $profiler['time']= time();
		if($profiler['time'] > time() - 15){
			return true;
		}
		$profiler['time']= time();
		
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
		
		if(!isset($profiler['callback_time'])) $profiler['callback_time']= time();
		if($profiler['callback_time'] > time() - 60){
			return true;
		}
		$profiler['callback_time']= time();
		
		foreach($cron_jobs as $job){
			if(is_file($job['callback'])){
				$filemtime_callback= filemtime($job['callback']);
				
				if(!isset($profiler['filemtime_' . $job['callback']])){
					$profiler['filemtime_' . $job['callback']]= $filemtime_callback;
				}
				
				if($profiler['filemtime_' . $job['callback']] != $filemtime_callback){ // write in callback file event, restart
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
	
	$cron_limit_exception= new time_limit_exception;
	$cron_limit_exception->enable();

	////////////////////////////////////////////////////////////////////////
	// multithreading 
	if( // job in parallel process. For long tasks, a separate dispatcher is needed
		isset($_GET["process_id"])
	){
		$process_id= intval($_GET["process_id"]);
		$job= $cron_jobs[$process_id];
		
		if(isset($cron_jobs[$process_id]) && $job['multithreading']){
			multithreading_dispatcher($job, $cron_resource, $cron_session, $cron_dat_file);
		}
		
		_die();
	}

	
	////////////////////////////////////////////////////////////////////////
	// Dispatcher init
	if(@filemtime(CRON_DAT_FILE) + CRON_DELAY > time()) _die();

	$cron_resource= fopen(CRON_DAT_FILE, "r+");
	if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
		$stat= fstat($cron_resource);
		$cs= unserialize(@fread($cron_resource, $stat['size']));
		if(is_array($cs)) $cron_session= $cs;
		
		if(CRON_LOG_FILE && !is_dir(dirname(CRON_LOG_FILE))) {
			mkdir(dirname(CRON_LOG_FILE), 0755, true);
		}

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
					cron_log_rotate();
				}
				
				sleep(1);
			}
		}
		
		//###########################################
		if(CRON_LOG_FILE) cron_log_rotate();
		
		// END Jobs
		flock($cron_resource, LOCK_UN);
	}

	fclose($cron_resource);
	_die();
} else {
	////////////////////////////////////////////////////////////////////////
	// check time out to start in background 
	if(file_exists(CRON_DAT_FILE)){
		if(CRON_DELAY == 0){
			$cron_resource= fopen(CRON_DAT_FILE, "r");
			$cron_started= true;
			
			if(flock($cron_resource, LOCK_EX | LOCK_NB)) {
					$cron_started= false;
					flock($cron_resource, LOCK_UN);
			}
			
			fclose($cron_resource);
			
			if(!$cron_started) open_cron_socket(CRON_URL_KEY);
		} else {
			if(filemtime(CRON_DAT_FILE) + CRON_DELAY < time()){
				open_cron_socket(CRON_URL_KEY);
			}
		}
	} else {
		@mkdir(dirname(CRON_DAT_FILE), 0755, true);
		touch(CRON_DAT_FILE, time() - CRON_DELAY);
		
		if(CRON_LOG_FILE) {
			mkdir(dirname(CRON_LOG_FILE), 0755, true);
			touch(CRON_LOG_FILE);
		}
	}
}

unset($cron_jobs);
?>
