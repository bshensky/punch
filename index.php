<?php

define("MINIMUM_CRON_GRANULARITY", 60);
define("DEFAULT_CONFIG_DIR", "conf/");
define("DEFAULT_CONFIG_FILE", "default.conf");
define("DEFAULT_PRINT_FORMAT", "text/plaintext");

require_once 'config.php';

require __DIR__ . '/vendor/autoload.php';
// require_once '/home/bshensky/vendor/autoload.php';
require_once 'includes/SpawnTranscoder.php';
require_once 'includes/JobFlusher.php';
require_once 'includes/JobController.php';

$q = ((isset($_REQUEST['q'])) && (!empty($_REQUEST['q']))) ? $_REQUEST['q'] : 'spawn';
// the following seems to work with the mod_action handler for existing m3u8 files.  If the .htaccess file is rearchitected, this will need servicing too.
if (isset($_SERVER['PATH_TRANSLATED']) && (!empty($_SERVER['PATH_TRANSLATED'])) && file_exists($_SERVER['PATH_TRANSLATED'])) {
	$q = 'ping';
}

$j = new JobController($config['memcached']);

switch($q) {
	case 'expire':
	case 'retire': {
		// retire expire encoders - best to cron this as often as you are comfortable
		$threshold = ((isset($_REQUEST['threshold'])) && (!empty($_REQUEST['threshold']))) ? $_REQUEST['threshold'] : MINIMUM_CRON_GRANULARITY;
		$jobs_killed = $j->expire_old_jobs_per_threshold($threshold);
		if (!empty($jobs_killed)) {
			echo "killed: " . implode(',', $jobs_killed);
		}
		break;
	}
	case 'clean':
	case 'clear': {
		// put down all encoders - start from ground zero
		$j->flush_all_jobs();
		break;
	}
	case 'debug':
	case 'print': {
		// reveal all jobs
		$format = ((isset($_REQUEST['format'])) && (!empty($_REQUEST['format']))) ? $_REQUEST['format'] : DEFAULT_PRINT_FORMAT;
		$j->print_r_jobs($format);
		break;
	}
	case 'ping': {
		// touch the job associated with the passed source
		// the following seems to work with the mod_action handler for existing m3u8 files.  If the .htaccess file is rearchitected, this will need servicing too.
		$source_file = basename($_SERVER['REQUEST_URI']);
		// scrape all up to the final .m3u8, and ditch any parameters appended to the spec
		// WAS: $source_url = preg_replace('/(.*).m3u8(\?.*)$/i', '\1', urldecode($source_file));
		$source_url = preg_replace('/(.*)(.m3u8.*)$/i', '\1', urldecode($source_file));
		if ($job = $j->job_of_source($source_url)) {
			$j->ping_job($job);	
		}
		passthru_m3u8($_SERVER['PATH_TRANSLATED'], $source_file);
		break;
	}
	case 'spawn': {
		// spawn and register an encoder since caller asked for an m3u8 file that does not yet exist
		$source = $_REQUEST['source'];
		$conf = DEFAULT_CONFIG_DIR . (((isset($_REQUEST['conf'])) && (!empty($_REQUEST['conf']))) ? $_REQUEST['conf'] : DEFAULT_CONFIG_FILE);
		//echo "source=" . $source . " and conf=" . $conf . "\n"; die(0);
		if (!(count($j->get_jobs()) <= $config['max_jobs'])) {
			http_response_code(408);
		} elseif ($process = spawn_transcoder($source, $conf, $config['spawn_transcoder_timeout'])) {
			$j->register_this_job($process, $source, $conf);
		}
		break;
	}
	default:
		break;
}
