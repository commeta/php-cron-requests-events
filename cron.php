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

$GLOBALS['cron_jobs']= [];

$GLOBALS['cron_jobs'][]= [ // CRON Job 1, example
	'name' => 'job1',
	'interval' => 60 * 60 * 24, // 1 start in 24 hours
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => false
];

$GLOBALS['cron_jobs'][]= [ // CRON Job 2, multithreading example
	'name' => 'job2multithreading',
	'interval' => 60 * 60 * 24, // 1 start in 24 hours
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => true
];


for( // CRON job 3, multithreading example, four core
	$i= 0;
	$i< 4; // Max processor cores
	$i++	
) {
	$GLOBALS['cron_jobs'][]= [ // CRON Job 3, multithreading example
		'name' => 'multithreading_' . $i,
		'interval' => 60 * 60 * 24, // 1 start in 24 hours
		'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
		'multithreading' => true
	];
}

 
 
////////////////////////////////////////////////////////////////////////
// Variables
define("CRON_LOG_FILE", CRON_SITE_ROOT . 'cron/log/cron.log');
define("CRON_DAT_FILE", CRON_SITE_ROOT . 'cron/dat/cron.dat');

define("CRON_DELAY", 180);  // interval between requests in seconds, 1 to max int, increases the accuracy of the job timer hit
define("CRON_LOG_ROTATE_MAX_SIZE", 10 * 1024 * 1024); // 10 in MB
define("CRON_LOG_ROTATE_MAX_FILES", 5);
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
	// Functions
	function cron_session_add_event(& $fp, $event){
		$GLOBALS['cron_session']['finish']= time();
		$GLOBALS['cron_session']['events'][]= $event;
		write_cron_session($fp);

		file_put_contents(
			CRON_LOG_FILE,
			implode(' ', $event) . "\n",
			FILE_APPEND | LOCK_EX
		);
	}

	function write_cron_session(& $fp){ 
		$serialized= serialize($GLOBALS['cron_session']);

		rewind($fp);
		fwrite($fp, $serialized);
		ftruncate($fp, mb_strlen($serialized));
		fflush($fp);
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

		set_time_limit(600);
		ini_set('MAX_EXECUTION_TIME', 600);
	}

	function cron_log_rotate($cron_log_rotate_max_size, $cron_log_rotate_max_files){ // LOG Rotate
		if(@filesize(CRON_LOG_FILE) >  $cron_log_rotate_max_size / $cron_log_rotate_max_files) {
			rename(CRON_LOG_FILE, CRON_LOG_FILE . "." . time());
			@file_put_contents(
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
					@file_put_contents(
						CRON_LOG_FILE, 
						date('m/d/Y H:i:s', time()) . "INFO: log removal\n",
						FILE_APPEND | LOCK_EX
					);
				}
			}
		}
	}


	function multithreading_dispatcher(){
		// Dispatcher init
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . $_GET["process_id"] . '.dat';
		
		
		// Check interval
		foreach($GLOBALS['cron_jobs'] as $job) {
			if($job['name'] == $_GET["process_id"] && $job['multithreading']) {
				if(@filemtime($dat_file) + $job['interval'] > time()) die();
			}
		}

		
		touch($dat_file);
		$fp= fopen($dat_file, "r+");
		
		
		if(flock($fp, LOCK_EX | LOCK_NB)) {
			$cs=unserialize(@fread($fp, filesize($dat_file)));
				
			if(is_array($cs) ){
				$GLOBALS['cron_session']= $cs;
			} else {
				$GLOBALS['cron_session']= [
					'finish'=> 0
				];
			}
			
			$GLOBALS['cron_session']['events']= [];


			foreach($GLOBALS['cron_jobs'] as $job) {
				if($job['name'] == $_GET["process_id"] && $job['multithreading']) {
					// include connector
					if(file_exists($job['callback'])) {
						include $job['callback'];

						cron_session_add_event($fp, [
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'INFO:',
							'name' => $job['name'],
							'callback' => $job['callback'],
							'mode' => 'multithreading'
						]);
					} else {
						cron_session_add_event($fp, [
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'ERROR:',
							'name' => $job['name'],
							'callback' => $job['callback'],
							'mode' => 'multithreading'
						]);
					}
					
					break;
				}
			}
			
			$GLOBALS['cron_session']['finish']= time();
			write_cron_session($fp);

			// END Job
			flock($fp, LOCK_UN);
		}

		fclose($fp);
		die();
	}

	
	////////////////////////////////////////////////////////////////////////
	// start in background
	init_background_cron();
	foreach($GLOBALS['cron_jobs'] as $k => $job){
		$GLOBALS['cron_jobs'][$k]['name']= mb_eregi_replace("[^a-zA-Z0-9_]", '', $job['name']);
	}

	


	////////////////////////////////////////////////////////////////////////
	// multithreading 
	if( // job in parallel process. For long tasks, a separate dispatcher is needed
		isset($_GET["process_id"])
	){
		foreach($GLOBALS['cron_jobs'] as $job) {
			if( $job['name'] == $_GET["process_id"] && $job['multithreading']) {
				multithreading_dispatcher();
			}
		}
		
		die();
	}
	////////////////////////////////////////////////////////////////////////
	
	
	
	if(@filemtime(CRON_DAT_FILE) + CRON_DELAY > time()) die();
	
	////////////////////////////////////////////////////////////////////////
	// Dispatcher init
	touch(CRON_DAT_FILE);
	$fp= fopen(CRON_DAT_FILE, "r+");
	
	if(flock($fp, LOCK_EX | LOCK_NB)) {
		$cs= unserialize(@fread($fp, filesize(CRON_DAT_FILE)));
		
		if(is_array($cs) ){
			$GLOBALS['cron_session']= $cs;
		} else {
			$GLOBALS['cron_session']= [
				'finish'=> 0
			];
		}

		$GLOBALS['cron_session']['events']= [];
		
		if(!is_dir(dirname(CRON_LOG_FILE))) {
			mkdir(dirname(CRON_LOG_FILE), 0755, true);
		}
		
		
		//###########################################
		// check jobs
		
		foreach($GLOBALS['cron_jobs'] as $job){
			if(!isset($GLOBALS['cron_session'][$job['name']]['last_update'])) {
				$GLOBALS['cron_session'][$job['name']]['last_update']= 0;
			}
			
			if($job['multithreading']){ // refresh last update
				$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . $job['name'] . '.dat';
				if(file_exists($dat_file)){
					$GLOBALS['cron_session'][$job['name']]['last_update']= filemtime($dat_file);
				}
			}
			
			
			// Job timer
			if($GLOBALS['cron_session'][$job['name']]['last_update'] + $job['interval']  < time()){
				if($job['multithreading']){  // start multithreading example
					open_cron_socket(CRON_URL_KEY, $job['name']); 
				} else {
					// include connector
					if(file_exists($job['callback'])){
						include $job['callback'];
						
						cron_session_add_event($fp, [
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'INFO:',
							'name' => $job['name'],
							'callback' => $job['callback'],
							'mode' => 'singlethreading'
						]);
					} else {
						cron_session_add_event($fp, [
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'ERROR:',
							'name' => $job['name'],
							'callback' => $job['callback'],
							'mode' => 'singlethreading'
						]);
					}
				}
				
				// write_cron_session reset CRON_DELAY counter, strongly recommend call this after every job!
				$GLOBALS['cron_session'][$job['name']]['last_update']= time();
				write_cron_session($fp);
			}
		}
		
		
		//###########################################
		cron_log_rotate(CRON_LOG_ROTATE_MAX_SIZE, CRON_LOG_ROTATE_MAX_FILES);
		
		
		
		
		
		$GLOBALS['cron_session']['finish']= time();
		write_cron_session($fp);
		
		// END Jobs
		flock($fp, LOCK_UN);
	}

	fclose($fp);

	die();
} else {
	
	////////////////////////////////////////////////////////////////////////
	// check time out to start in background 
	if(file_exists(CRON_DAT_FILE)){
		if(filemtime(CRON_DAT_FILE) + CRON_DELAY < time()){
			open_cron_socket(CRON_URL_KEY);
		} 
	} else {
		@mkdir(dirname(CRON_DAT_FILE), 0755, true);
		touch(CRON_DAT_FILE, time() - CRON_DELAY);
		
		@mkdir(dirname(CRON_LOG_FILE), 0755, true);
		touch(CRON_LOG_FILE);
	}

	unset($GLOBALS['cron_jobs']);
}
?>
