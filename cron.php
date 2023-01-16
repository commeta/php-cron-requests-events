<?php
/*
 * PHP CRON use request events 
 * Project url: https://github.com/commeta/php-cron-requests-events
 * 
 * can install use:
 * wget "https://github.com/commeta/php-cron-requests-events/archive/refs/heads/main.zip"
 * 
 * Copyright 2023 commeta <dcs-spb@ya.ru>
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
 
// declare(strict_types = 1); // strict typing PHP > 7.0

////////////////////////////////////////////////////////////////////////
###########################
# Settings

// Example settings
include('cron/inc/cron_settings.conf.php');

// Example parallel function launch settings
// include('cron/inc/cron_launch.conf.php');


////////////////////////////////////////////////////////////////////////
// Functions
if(!function_exists('open_cron_socket')) { 
	function open_cron_socket($cron_requests_events_url_key, $job_process_id= '') // :void 
	{ // Start job in parallel process
		if(
			isset($_SERVER['HTTPS']) &&
			($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1) ||
			isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
			$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
		) {
			$protocol= 'https';
		} else {
			$protocol= 'http';
		}

		$document_root= preg_match('/\/$/',$_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR;
		
		if(isset($_SERVER["HTTP_HOST"])) {
			$host= strtolower($_SERVER["HTTP_HOST"]);
						
			if($job_process_id !== '') $cron_requests_events_url_key.= '&job_process_id=' . $job_process_id;
		} elseif( // sapi: CLI
			defined('STDIN') ||
			php_sapi_name() === 'cli' ||
			array_key_exists('SHELL', $_ENV) ||
			!array_key_exists('REQUEST_METHOD', $_SERVER)
		) {
			$protocol= 'https';
			$host= "localhost";
			$document_root= dirname(__FILE__) . DIRECTORY_SEPARATOR; // site root path
			
			echo "Request: " . $protocol . '://' . $host . "/" . basename(__FILE__) . "?cron=" . $cron_requests_events_url_key . "\n";
			echo 'or change $host= "localhost" to your domain' . "\n";
		}
		
		$cron_requests_events_url= $protocol . '://' . $host . '/' . 
			str_replace($document_root , '', dirname(__FILE__) . DIRECTORY_SEPARATOR) . 
			basename(__FILE__) ."?cron=" . $cron_requests_events_url_key;
			
		if(
			is_callable("shell_exec") && 
			defined("PHP_BINDIR") && 
			is_executable(PHP_BINDIR . DIRECTORY_SEPARATOR . 'php') 
		){
			if($protocol ===  'https') {
				shell_exec(
					PHP_BINDIR . DIRECTORY_SEPARATOR . 'php -r \'file_get_contents("' . $cron_requests_events_url . 
					'", false, stream_context_create(["ssl"=>["verify_peer"=>false,"verify_peer_name"=>false],"http"=>["timeout"=>1]]));\' > /dev/null &');
			} else {
				shell_exec(
					PHP_BINDIR . DIRECTORY_SEPARATOR . 'php -r \'file_get_contents("' . $cron_requests_events_url . 
					'", false, stream_context_create(["http"=>["timeout"=>1]]));\' > /dev/null &');
			}
		} else {
			@fclose( 
				@fopen(
					$cron_requests_events_url, 
					'r', 
					false, 
					stream_context_create([ // block mode
						'http'=>[ // script responce time 0.004 * 10 (Linux Kernel Load Average\OPcache\FLOPS) = 0.04 timeout start process
							'timeout' => 0.04 // it will be necessary to increase for high loaded systems
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
	$_REQUEST["cron"] === $cron_requests_events_settings['url_key']
){
	////////////////////////////////////////////////////////////////////////
	// Functions: system api 
	function write_cron_session() // :void 
	{
		global  $cron_requests_events_resource, $cron_requests_events_session;
		
		$serialized= serialize($cron_requests_events_session);
		rewind($cron_requests_events_resource);
		fwrite($cron_requests_events_resource, $serialized);
		ftruncate($cron_requests_events_resource, mb_strlen($serialized));
	}

	function _die($return= '') // :void 
	{
		global $cron_requests_events_resource, $cron_requests_events_dat_file, $cron_requests_events_settings;
		
		if(isset($cron_requests_events_resource) && is_resource($cron_requests_events_resource)){// check global resource
			write_cron_session();
			flock($cron_requests_events_resource, LOCK_UN);
			fclose($cron_requests_events_resource);
		}
				
		if($return === 'restart'){ // restart cron
			if(isset($cron_requests_events_dat_file)) touch($cron_requests_events_dat_file, time() - $cron_requests_events_settings['delay']);
			open_cron_socket($cron_requests_events_settings['url_key']);
		}
		
		if(
			$cron_requests_events_settings['delete_dat_file_on_exit'] &&
			basename($cron_requests_events_settings['dat_file']) !== 'cron.dat'
		) {
			unlink($cron_requests_events_settings['dat_file']);
		}
		
		exit();
	}
	

	function fcgi_finish_request() // :void 
	{
		header($_SERVER['SERVER_PROTOCOL'] . " 200 OK");
		header('Content-Encoding: none');
		header('Content-Length: ' . (string) ob_get_length());
		header('Connection: close');
		http_response_code(200);
		
		// check if fastcgi_finish_request is callable
		if(is_callable('fastcgi_finish_request')) {
			session_write_close();
			fastcgi_finish_request();
		}

		while(ob_get_level()) ob_end_clean();
		ob_start();
		@ob_end_flush();
		@ob_flush();
		@flush();
	}


	function init_background_cron() // :void 
	{
		global $cron_requests_events_settings;
		
		ignore_user_abort(true);
		fcgi_finish_request();

		if (is_callable('proc_nice')) {
			proc_nice(19);
		}

		if($cron_requests_events_settings['daemon_mode']){
			set_time_limit(0);
			ini_set('MAX_EXECUTION_TIME', "0");
		} else {
			set_time_limit(600);
			ini_set('MAX_EXECUTION_TIME', "600");
		}
		
		ini_set('error_reporting', "E_ALL");
		ini_set('display_errors', "1"); // 1 to debug
		ini_set('display_startup_errors', "1");
		
		register_shutdown_function('_die');
	}

	function cron_log_rotate() // :void 
	{ // LOG Rotate
		global $cron_requests_events_session, $cron_requests_events_settings;

		if(!isset($cron_requests_events_session['log_rotate_last_update'])) {
			$cron_requests_events_session['log_rotate_last_update']= 0;
		}

		if(
			$cron_requests_events_settings['daemon_mode'] && 
			$cron_requests_events_session['log_rotate_last_update'] > time() - 600
		){
			return;
		}
		
		$cron_requests_events_session['log_rotate_last_update']= time();

		if(
			$cron_requests_events_settings['log_file'] != '' && 
			@filesize($cron_requests_events_settings['log_file']) > $cron_requests_events_settings['log_rotate_max_size'] / $cron_requests_events_settings['log_rotate_max_files']
		) {
			@rename($cron_requests_events_settings['log_file'], $cron_requests_events_settings['log_file'] . "." . (string) time());
			
			file_put_contents(
				$cron_requests_events_settings['log_file'], 
				date('m/d/Y H:i:s',time()) . " INFO: log rotate\n", 
				FILE_APPEND | LOCK_EX
			);
				
			$the_oldest = time();
			$log_old_file = '';
			$log_files_size = 0;
						
			foreach(glob($cron_requests_events_settings['log_file'] . '*') as $file_log_rotate){
				$log_files_size+= @filesize($file_log_rotate);
				if ($file_log_rotate === $cron_requests_events_settings['log_file']) {
					continue;
				}
					
				$log_mtime = @filectime($file_log_rotate);
				if ($log_mtime < $the_oldest) {
					$log_old_file = $file_log_rotate;
					$the_oldest = $log_mtime;
				}
			}

			if ($log_files_size >  $cron_requests_events_settings['log_rotate_max_size']) {
				if (file_exists($log_old_file)) {
					unlink($log_old_file);
					file_put_contents(
						$cron_requests_events_settings['log_file'], 
						date('m/d/Y H:i:s', time()) . " INFO: log removal\n",
						FILE_APPEND | LOCK_EX
					);
				}
			}
		}
	}

	
	function callback_connector($job, $job_process_id, $mode) // :void 
	{
		global $cron_requests_events_session, $cron_requests_events_settings;
		
		if($job['multithreading'] && $mode){ // multithreading\singlethreading
			open_cron_socket($cron_requests_events_settings['url_key'], (string) $job_process_id); 
		} else {
			
			if(isset($job['function'])){ // use call function mode
				if(isset($job['param'])) call_user_func($job['function'], $job['param']);
				else call_user_func($job['function']);
			}
			
			if(file_exists($job['callback'])) {
				include $job['callback'];
					
			} elseif(!isset($job['function'])) {
				if($cron_requests_events_settings['log_file'] != ''){
					file_put_contents(
						$cron_requests_events_settings['log_file'],
						implode(' ', [
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'ERROR:',
							'job_process_id' => (string) $job_process_id,
							'callback' => $job['callback'],
							'mode' => $job['multithreading'] ? 'multithreading' : 'singlethreading',
						]) . "\n",
						FILE_APPEND | LOCK_EX
					);
				}
			}
			
		}
	}
			
	function cron_session_init($job, $job_process_id) // :void 
	{
		global $cron_requests_events_session;
		static $init= [];
		
		if(isset($init[$job_process_id])) return;
		$init[$job_process_id]= true;
		
		if(isset($cron_requests_events_session[$job_process_id]['md5'])) {
			if($cron_requests_events_session[$job_process_id]['md5'] !== md5(serialize($job))){
				$cron_requests_events_session[$job_process_id]['md5']= md5(serialize($job));
				$cron_requests_events_session[$job_process_id]['last_update']= 0;
				$cron_requests_events_session[$job_process_id]['complete']= false;
			}
		} else {
			$cron_requests_events_session[$job_process_id]['md5']= md5(serialize($job));
		}
		
		if(!isset($cron_requests_events_session[$job_process_id]['last_update'])) {
			$cron_requests_events_session[$job_process_id]['last_update']= 0;
		}
						
		if(!isset($cron_requests_events_session[$job_process_id]['complete'])){
			$cron_requests_events_session[$job_process_id]['complete']= false;
		}
	}
	
	function cron_check_job($job, $job_process_id, $mode) // :void 
	{
		global $cron_requests_events_session;
		$time= time();
		
		if(isset($job['date']) || isset($job['time'])){
			$time_stamp= 0;
			$unlocked= false;
			
			if( // unlock job
				isset($job['time']) &&
				!isset($job['date']) &&
				$cron_requests_events_session[$job_process_id]['last_update'] !== 0 &&
				$cron_requests_events_session[$job_process_id]['complete']  &&
				date('d-m-Y', $time) !== date('d-m-Y', $cron_requests_events_session[$job_process_id]['last_update'])
			){
				$cron_requests_events_session[$job_process_id]['complete']= false;
			}
			
			if($cron_requests_events_session[$job_process_id]['complete']) return;
			
			if(isset($job['date'])) $d= explode('-', $job['date']);		
			if(isset($job['time'])) $t= explode(':', $job['time']);
			
			if(isset($job['date']) && isset($job['time'])){ // check date time, one - time
				$time_stamp= mktime(intval($t[0]), intval($t[1]), intval($t[2]), intval($d[1]), intval($d[0]), intval($d[2]));
				
				if($time_stamp < $time) $unlocked= true;
			} else {
				if(isset($job['date'])){ // check date, one - time
					$time_stamp= mktime(0, 0, 0, intval($d[1]), intval($d[0]), intval($d[2]));
					
					if($time_stamp < $time) $unlocked= true;
				}
				if(isset($job['time'])){ // check time, every day
					$time_stamp= mktime(intval($t[0]), intval($t[1]), intval($t[2]));
					
					if($time_stamp < $time) $unlocked= true;
				}
			}
			
			if($unlocked){
				callback_connector($job, $job_process_id, $mode);
				$cron_requests_events_session[$job_process_id]['complete']= true;
				$cron_requests_events_session[$job_process_id]['last_update']= time();
			}
		} else {
			if(
				$cron_requests_events_session[$job_process_id]['last_update'] + $job['interval'] < $time
			){
				callback_connector($job, $job_process_id, $mode);
				$cron_requests_events_session[$job_process_id]['complete']= true;
				$cron_requests_events_session[$job_process_id]['last_update']= time();
			}
		}
	}
	
 
	function singlethreading_dispatcher() // :void 
	{ // main loop job list
		global $cron_requests_events_jobs;
		
		foreach($cron_requests_events_jobs as $job_process_id=> $job){
			cron_session_init($job, $job_process_id);
			cron_check_job($job, $job_process_id, true);
		}
	}
	
	

	function memory_profiler() // :void 
	{
		global $cron_requests_events_jobs, $cron_requests_events_settings, $cron_requests_events_inc;
		static  $profiler= [];
		$time= time();
		
		if(!isset($profiler['time'])) $profiler['time']= $time;
		if($profiler['time'] > $time - 15){ // delayed start
			return;
		}
		$profiler['time']= $time;
		
		if(!isset($profiler['memory_get_usage'])){
			$profiler['memory_get_usage']= 0;
		}
		
		if(!isset($profiler['filemtime'])){
			$profiler['filemtime']= filemtime(__FILE__);
		}
		
		if($profiler['filemtime'] !== filemtime(__FILE__) || !file_exists(__FILE__)){ // write in main file event, restart
			_die('restart');
		}
		
		if(!isset($profiler['filemtime_cron_settings.conf.php'])){
			$profiler['filemtime_cron_settings.conf.php']= filemtime($cron_requests_events_inc . 'cron_settings.conf.php');
		}
		
		if($profiler['filemtime_cron_settings.conf.php'] !== filemtime($cron_requests_events_inc . 'cron_settings.conf.php')){ // write in cron_settings file event, restart
			_die('restart');
		}
		
		
		if($profiler['memory_get_usage'] < memory_get_usage()){
			$profiler['memory_get_usage']= memory_get_usage();
			
			if($cron_requests_events_settings['log_file'] != '' && $cron_requests_events_settings['log_level'] > 3){
				file_put_contents(
					$cron_requests_events_settings['log_file'],
					implode(' ', [
						'date'=> date('m/d/Y H:i:s', $time),
						'message'=> 'INFO:',
						'name' => 'memory_get_usage',
						'value' => (string) $profiler['memory_get_usage'],
					]) . "\n",
					FILE_APPEND | LOCK_EX
				);
			}
		} 
		
		if(!isset($profiler['callback_time'])) $profiler['callback_time']= $time;
		if($profiler['callback_time'] > $time - 60){ // delayed start
			return;
		}
		$profiler['callback_time']= $time;
		
		foreach($cron_requests_events_jobs as $job){
			if(is_file($job['callback'])){
				$filemtime_callback= filemtime($job['callback']);
				
				if(!isset($profiler['filemtime_' . $job['callback']])){
					$profiler['filemtime_' . $job['callback']]= $filemtime_callback;
					continue;
				}
				
				if($profiler['filemtime_' . $job['callback']] !== $filemtime_callback){ // write in callback file event, restart
					_die('restart');
				}
			}
		}
		
		
	}


	////////////////////////////////////////////////////////////////////////
	// start in background
	init_background_cron();
	$cron_requests_events_session= [];
	
	////////////////////////////////////////////////////////////////////////
	// multithreading Dispatcher
	if( // job in parallel process. For long tasks, a separate dispatcher is needed
		isset($_GET["job_process_id"])
	){
		$job_process_id= intval($_GET["job_process_id"]);
		if(isset($cron_requests_events_jobs[$job_process_id])) {
			$job= $cron_requests_events_jobs[$job_process_id];

			if($job['multithreading']){
				// Dispatcher init
				$job_process_id= intval($_GET["job_process_id"]);
				$cron_requests_events_dat_file= dirname($cron_requests_events_settings['dat_file']) . DIRECTORY_SEPARATOR . (string) $job_process_id . '.dat';
				if(!file_exists($cron_requests_events_dat_file)) touch($cron_requests_events_dat_file);
				
				$cron_requests_events_resource= fopen($cron_requests_events_dat_file, "r+");
				if(flock($cron_requests_events_resource, LOCK_EX | LOCK_NB)) {
					$stat= fstat($cron_requests_events_resource);
					$cs= unserialize(fread($cron_requests_events_resource, $stat['size']));
					if(is_array($cs)) $cron_requests_events_session= $cs;
					
					cron_session_init($job, $job_process_id);
					cron_check_job($job, $job_process_id, false);
					
					write_cron_session();
					flock($cron_requests_events_resource, LOCK_UN);
				}
				
				fclose($cron_requests_events_resource);
			}
		}
		
		_die();
	}

	
	////////////////////////////////////////////////////////////////////////
	// Dispatcher init
	$cron_requests_events_dat_file= $cron_requests_events_settings['dat_file'];
	
	if(filemtime($cron_requests_events_settings['dat_file']) + $cron_requests_events_settings['delay'] > time()) _die();
	
	$cron_requests_events_resource= fopen($cron_requests_events_settings['dat_file'], "r+");
	if(flock($cron_requests_events_resource, LOCK_EX | LOCK_NB)) {
		$stat= fstat($cron_requests_events_resource);
		$cs= unserialize(@fread($cron_requests_events_resource, $stat['size']));
		if(is_array($cs)) $cron_requests_events_session= $cs;
		
		if(
			$cron_requests_events_settings['log_file'] != '' && 
			!is_dir(dirname($cron_requests_events_settings['log_file']))
		) {
			mkdir(dirname($cron_requests_events_settings['log_file']), 0755, true);
		}
		
		//###########################################
		// check jobs
		singlethreading_dispatcher();

		while($cron_requests_events_settings['daemon_mode']){
			singlethreading_dispatcher();
			memory_profiler();
				
			if($cron_requests_events_settings['log_file'] != ''){
				cron_log_rotate();
			}
				
			sleep($cron_requests_events_settings['delay']); // delay in infinite loop
		}
		
		//###########################################
		if($cron_requests_events_settings['log_file'] != '') cron_log_rotate();
		write_cron_session();
		flock($cron_requests_events_resource, LOCK_UN);
	}

	fclose($cron_requests_events_resource);
	_die();
} else {
	////////////////////////////////////////////////////////////////////////
	// check time out to start in background 
	if(file_exists($cron_requests_events_settings['dat_file'])){
		if($cron_requests_events_settings['daemon_mode']){
			$cron_requests_events_resource= fopen($cron_requests_events_settings['dat_file'], "r");
			$cron_requests_events_started= true;
			
			if(flock($cron_requests_events_resource, LOCK_EX | LOCK_NB)) {
				$cron_requests_events_started= false;
				flock($cron_requests_events_resource, LOCK_UN);
			}
			
			fclose($cron_requests_events_resource);
			
			if(!$cron_requests_events_started) open_cron_socket($cron_requests_events_settings['url_key']);
		} else {
			if(filemtime($cron_requests_events_settings['dat_file']) + $cron_requests_events_settings['delay'] < time()){
				open_cron_socket($cron_requests_events_settings['url_key']);
			}
		}
	} else {
		if(basename($cron_requests_events_settings['dat_file']) === 'cron.dat') {
			if(!is_dir(dirname($cron_requests_events_settings['dat_file']))) mkdir(dirname($cron_requests_events_settings['dat_file']), 0755, true);
			file_put_contents($cron_requests_events_settings['dat_file'], serialize([]));
			touch($cron_requests_events_settings['dat_file'], time() - $cron_requests_events_settings['delay']);
		}
		
		if($cron_requests_events_settings['log_file'] != '') {
			if(!is_dir(dirname($cron_requests_events_settings['log_file']))) mkdir(dirname($cron_requests_events_settings['log_file']), 0755, true);
			touch($cron_requests_events_settings['log_file']);
		}
		
		open_cron_socket($cron_requests_events_settings['url_key']);
	}
}

?>
