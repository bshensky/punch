<?php

//
//
class JobFlusher {

	//
	//
	function flush_job_files($job) {
		$source_escaped = str_replace("/", "%2f", $job['source']);
		$filemaskhead = dirname($_SERVER["SCRIPT_FILENAME"]) . "/data/{$source_escaped}";
		@unlink($filemaskhead . '.m3u8'); // do this first
		$deletefiles = glob($filemaskhead . '.*');
		foreach ($deletefiles as $filename) {
			@unlink($filename);
		}	
	}

	//
	//
	function flush_job($job) {
		$found_process = FALSE;
		while ($job['process']->isRunning()) {
			$found_process = $job['process']->stop();
		}
		self::flush_job_files($job);
		return($found_process);
	}

	//
	//
	function flush_garbage_files() {
		$filemaskhead = dirname($_SERVER["SCRIPT_FILENAME"]) . "/data/*";
		$deletefiles = glob($filemaskhead . '.*');
		foreach ($deletefiles as $filename) {
			@unlink($filename);
		}		
	}

}
