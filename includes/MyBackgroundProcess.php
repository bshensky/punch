<?php

// require __DIR__ . '/vendor/autoload.php';
require_once '/home/bshensky/vendor/autoload.php';

use Cocur\BackgroundProcess\BackgroundProcess;

//
//
class MyBackgroundProcess extends BackgroundProcess {
    public function getPid() {
       return trim(preg_replace('/\s+/', '', parent::getPid())); // The original object failed to chomp() the pid info ("99999\n"). This override fixes that.
    }
}