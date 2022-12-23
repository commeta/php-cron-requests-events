<?php

if(
	isset($_REQUEST["cron"]),
	is_file(__DIR__.'/cron.php'),
){
	if(!file_exists(__DIR__.'/cron.dat')) touch(__DIR__.'/cron.dat');
	if(filemtime(__DIR__.'/cron.dat') + 60 > time()) die();
	include(__DIR__.'/cron.php');
	die();
}

if(file_exists(__DIR__.'/cron.dat')){
	if(filemtime(__DIR__.'/cron.dat') + 60 < time()){
		@fclose(
			@fopen(
				'https://' . strtolower(@$_SERVER["HTTP_HOST"]) . "/?cron=", 
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
	touch(__DIR__.'/cron.dat');
}

?>
