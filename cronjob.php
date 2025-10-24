<?php
// Log to see when the cronjob.php is triggered
//file_put_contents(__DIR__.'/cron_trigger.log', date('Y-m-d H:i:s')."\n", FILE_APPEND);

define('MODE', 'CRON');
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__)).'/');
set_include_path(ROOT_PATH);

require 'includes/common.php';
require 'includes/classes/Cronjob.class.php';

// Run all due cronjobs once per system call
$cronjobsTodo = Cronjob::getNeedTodoExecutedJobs();

if (empty($cronjobsTodo)) {
    exit;
}

foreach ($cronjobsTodo as $cronjobID) {
    try {
        Cronjob::execute($cronjobID);
    } catch (Exception $e) {
        file_put_contents(__DIR__.'/cron_error.log', '['.date('Y-m-d H:i:s')."] ".$e->getMessage()."\n", FILE_APPEND);
    }
}
