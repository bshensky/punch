<?php

use \Memcached;

//
//
class JobController {
	
	private $m;

    public function __construct($memcache_connect) {
        $this->m = new Memcached();
		if (empty($memcache_connect['host'])) { $memcache_connect['host'] = 'localhost'; }
		if (empty($memcache_connect['port'])) { $memcache_connect['port'] = '11211'; }
		$this->m->addServer($memcache_connect['host'], $memcache_connect['port']);
    }
	
	//
	//
	function get_jobs() {
		if (!($jobs = $this->m->get('jobs'))) {
			if ($this->m->getResultCode() == Memcached::RES_NOTFOUND) {
				$jobs = array();
			}
		}
		return $jobs;
	}
	
	//
	//
    function set_jobs($jobs) {
		return $this->m->set('jobs', $jobs);
	}
	
	//
	//
	function register_this_job($process, $source, $conf = '') {
		$jobs = $this->get_jobs();
		$job = $process->getPid();
		$ip = getenv('HTTP_CLIENT_IP')?:
			  getenv('HTTP_X_FORWARDED_FOR')?:
			  getenv('HTTP_X_FORWARDED')?:
			  getenv('HTTP_FORWARDED_FOR')?:
			  getenv('HTTP_FORWARDED')?:
			  getenv('REMOTE_ADDR');
		$jobs[$job] = array('source' => $source, 'process' => $process, 'created' => time(), 'updated' => time(), 'ip' => $ip, 'conf' => $conf);
		$this->set_jobs($jobs);
	}

	//
	//
	function print_r_jobs($format = 'text/plaintext') {
		$jobs = $this->get_jobs();
		switch($format) {
			case 'text/html':
				echo '<html><head><meta http-equiv="refresh" content="3" /></head><body><pre>' . print_r($jobs, TRUE). '</pre></body></html>';
			    break;
			case 'application/json':
				echo json_encode($jobs);
			    break;
			default:
				print_r($jobs);
			    break;
		}
		return TRUE;
	}

	//
	//
	function flush_all_jobs() {
		$jobs = $this->get_jobs();
		foreach($jobs as $key => $job) {
			JobFlusher::flush_job($job);
			unset($jobs[$key]);
		}
		$this->set_jobs($jobs);
		JobFlusher::flush_garbage_files();
		return $jobs;
	}
	
	//
	//
	function ping_job($job) {
		$jobs = $this->get_jobs();
		$jobs[$job]['updated'] = time();
		$this->set_jobs($jobs);
	}

	//
	//
	function job_of_source($source) {
		if (!($jobs = $this->get_jobs())) {
			return FALSE;
		}
		foreach($jobs as $k => $v) {
			if ((!empty($v['source'])) && ($v['source'] == $source)) {
				return $k;
			}
		}
		return FALSE;
	}

	//
	//
	function expire_old_jobs($threshold = MINIMUM_CRON_GRANULARITY) {
		$jobs_expired = array();
		if (!($jobs = $this->get_jobs())) {
			return $jobs_expired;
		}
		foreach($jobs as $k => $v) {
			if ((time() - $v['updated']) >= $threshold) {
				JobFlusher::flush_job($v);
				unset($jobs[$k]);
				$jobs_expired[] = $k;
			}
		}
		$this->set_jobs($jobs);
		return $jobs_expired;	
	}

	//
	//
	function expire_old_jobs_per_threshold($threshold = MINIMUM_CRON_GRANULARITY) {
		if ($threshold >= MINIMUM_CRON_GRANULARITY) {
			return $this->expire_old_jobs($threshold);
		} else {
			// the threshold given is less than the minimum cron granularity (one minute); run multiple times within the minute
			$jobs_expired = array();
			$i = MINIMUM_CRON_GRANULARITY;
			while ($i >= 0) {
				$jobs_expired = array_merge($jobs_expired, $this->expire_old_jobs($threshold));
				$i = $i - $threshold;
				if ($i > 0) {
					sleep($threshold);
				}
			}
			return $jobs_expired;
		}
	}	
}
