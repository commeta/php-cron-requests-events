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
// Variables
define("CRON_SITE_ROOT", preg_match('/\/$/',$_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR);

$cron_delay= 180; // interval between requests in seconds, 1 to max int, increases the accuracy of the job timer hit
$cron_log_rotate_max_size= 10 * 1024 * 1024; // 10 in MB
$cron_log_rotate_max_files= 5;
$cron_url_key= 'my_secret_key'; // change this!


// Functions
function cron_session_add_event(& $fp, $event){
	$GLOBALS['cron_session']['finish']= time();
	$GLOBALS['cron_session']['events'][]= $event;
	write_cron_session($fp);

	file_put_contents(
		CRON_SITE_ROOT . "cron/log/cron.log",
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


if(
	isset($_REQUEST["cron"]) &&
	$_REQUEST["cron"] == $cron_url_key
){
	ignore_user_abort(true);
	
	// check if fastcgi_finish_request is callable
	if(is_callable('fastcgi_finish_request')) {
		session_write_close();
		fastcgi_finish_request();
	}

	ob_start();
	
	header(filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_STRING).' 200 OK');
	header('Content-Encoding: none');
	header('Content-Length: '.ob_get_length());
	header('Connection: close');

	ob_end_flush();
	ob_flush();
	flush();

	if (is_callable('proc_nice')) {
		proc_nice(15);
	}

	set_time_limit(600);
	ini_set('MAX_EXECUTION_TIME', 600);
	
	if(filemtime(CRON_SITE_ROOT.'cron/cron.dat') + $cron_delay > time()) die();
	if(!is_dir(CRON_SITE_ROOT.'cron/log')) mkdir(CRON_SITE_ROOT.'cron/log', 0755);

	
	////////////////////////////////////////////////////////////////////////
	// Init
	$fp= fopen(CRON_SITE_ROOT.'cron/cron.dat', "r+");
	if(flock($fp, LOCK_EX | LOCK_NB)) {
		$cs=unserialize(fread($fp, filesize(CRON_SITE_ROOT.'cron/cron.dat')));
		
		if(is_array($cs) ){
			$GLOBALS['cron_session']= $cs;
		} else {
			$GLOBALS['cron_session']= [
				'finish'=> time()
			];
		}

		$GLOBALS['cron_session']['events']= [];
		write_cron_session($fp);

		

		//###########################################
		// CRON Job 1
		if(!isset($GLOBALS['cron_session']['job1']['last_update'])) $GLOBALS['cron_session']['job1']['last_update']= 0;

		// Job timer
		if($GLOBALS['cron_session']['job1']['last_update'] + 60 * 60 * 24 < time() ){ // Trigger an event if the time has expired, in seconds
			cron_session_add_event($fp, [
				'date'=> date('m/d/Y H:i:s', time()),
				'message'=> 'INFO: start cron',
			]);

			// write_cron_session reset $cron_delay counter, strongly recommend call this after every job!
			$GLOBALS['cron_session']['job1']['last_update']= time();
			write_cron_session($fp);
		}
		


		// CRON Job 1
		// CRON Job 2
		// CRON Job 3
		// CRON Job 4
		// CRON Job 5
		

		//###########################################
		
		
		// LOG Rotate
		if(@filesize(CRON_SITE_ROOT . "cron/log/cron.log") >  $cron_log_rotate_max_size / $cron_log_rotate_max_files) {
			rename(CRON_SITE_ROOT . "cron/log/cron.log", CRON_SITE_ROOT . "cron/log/cron.log" . "." . time());
			@file_put_contents(
				CRON_SITE_ROOT . "cron/log/cron.log", 
				date('m/d/Y H:i:s',time()) . "INFO: log rotate\n", 
				FILE_APPEND | LOCK_EX
			);
			
			$the_oldest = time();
			$log_old_file = '';
			$log_files_size = 0;
					
			foreach(glob(CRON_SITE_ROOT . "cron/log/cron.log" . '*') as $file_log_rotate){
				$log_files_size+= filesize($file_log_rotate);
				if ($file_log_rotate == CRON_SITE_ROOT . "cron/log/cron.log") {
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
						CRON_SITE_ROOT . "cron/log/cron.log", 
						date('m/d/Y H:i:s', time()) . "INFO: log removal\n",
						FILE_APPEND | LOCK_EX
					);
				}
			}
		}
		
		// END Jobs
		flock($fp, LOCK_UN);
	}

	fclose($fp);

	die();
}

if(file_exists(CRON_SITE_ROOT.'cron/cron.dat')){
	if(filemtime(CRON_SITE_ROOT.'cron/cron.dat') + $cron_delay < time()){
		@fclose(
			@fopen(
				'https://' . strtolower(@$_SERVER["HTTP_HOST"]) . "/cron.php?cron=" . $cron_url_key, 
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
} else {
	mkdir(CRON_SITE_ROOT.'cron', 0755);
	touch(CRON_SITE_ROOT.'cron/cron.dat');
}

?>
