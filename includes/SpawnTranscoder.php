<?php

require_once 'MyBackgroundProcess.php';

define('EXEC_TIMEOUT_POLL_MS', 250000);
define('HTTP_CUSTOM_HEADER_RESPONDING_TO', 'X-Transcoder-In-Response-To: ');

//
//
function spawn_transcoder($source, $config_file = 'default', $timeout = 20) {
	// Because we employ "AllowEncodedSlashes NoDecode", the local file must only have slashes encoded in the filename
	$source_escaped = str_replace("/", "%2f", $source);
	// In the substitution, we must ensure that the hls_segment_filename value is percent-escaped as it represents a sprintf string
	$source_escaped_pct_escaped = str_replace("%", "%%", $source_escaped);
	$exec_subst = array( 
		'[SOURCE]' => $source,
		'[SOURCE-ESCAPED]' => $source_escaped,
		'[SOURCE-ESCAPED-PCT-ESCAPED]' => $source_escaped_pct_escaped,
		'[BASEURL]' => baseurl() . '/data/' 
		);
	if (!($exec = @file_get_contents($config_file, FILE_USE_INCLUDE_PATH))) {
		if (!($exec = file_get_contents($config_file . '.conf', FILE_USE_INCLUDE_PATH))) {
			header(HTTP_CUSTOM_HEADER_RESPONDING_TO . urlencode($config_file));
			http_response_code(400);
			return FALSE;		
		}
	}
	$exec = trim(preg_replace('/\s+/', ' ', $exec)); // the exec command should not have newlines in it
	$exec = str_replace(array_keys($exec_subst), array_values($exec_subst), $exec); // to allow token substitution
	$process = new MyBackgroundProcess($exec);
	$process->run();
	// first check to see if we have obvious error coming back from the job spawn
	null; 
	// if not, wait for the creation of the playlist file
	$filename = dirname($_SERVER["SCRIPT_FILENAME"]) . "/data/{$source_escaped}.m3u8";
	// sleep in EXEC_TIMEOUT_POLL_MS millisecond increments as we wait for exec to start output...
	for ($waits = ($timeout * 1000000); (($waits > 0) && (!file_exists($filename))); $waits = $waits - EXEC_TIMEOUT_POLL_MS) {
		usleep(EXEC_TIMEOUT_POLL_MS);
	}
	if (file_exists($filename)) {
		passthru_m3u8($filename, $filename);
		return $process;
	} else {
		$process->stop();
		header(HTTP_CUSTOM_HEADER_RESPONDING_TO . urlencode($exec));
		http_response_code(408);
		return FALSE;
	}
}

function passthru_m3u8($filename, $ref = '') {
	header('Content-Type: application/x-mpegURL');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	if (!empty($ref)) {
		header(HTTP_CUSTOM_HEADER_RESPONDING_TO . urlencode($ref));
	}
	header('Content-Length: ' . filesize($filename));
	return readfile($filename);
}

function baseurl() {
	$baseurl = 'http' . (is_secure() ? 's' : '') . '://' . $_SERVER["SERVER_NAME"];
	$baseurl .= ((!in_array($_SERVER["SERVER_PORT"], array('80', '443'))) ? (':' . $_SERVER["SERVER_PORT"]) : '');
	$baseurl .= dirname($_SERVER["SCRIPT_NAME"]);
	return $baseurl;
}

function is_secure() {
  return
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $_SERVER['SERVER_PORT'] == 443;
}
