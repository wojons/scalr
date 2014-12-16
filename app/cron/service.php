#!/usr/bin/env php
<?php

/**
 * Scalr crontab service
 *
 * For better reliability service is expected to be started by crontab at every seconds.
 * It ensures that each task will be started at specified time.
 * Crontab config is in the config.yml.
 * It's also responsible for ZMQ MDP Broker availability.
 *
 * @author Vitaliy Demidov  <vitaliy@scalr.com>
 * @since  5.0.1 (10.09.2013)
 */

declare(ticks = 1);

define('SCALR_MULTITHREADING', true);

require_once __DIR__ . "/../src/prepend.inc.php";

use Scalr\System\Zmq\Cron\Launcher;
use Scalr\System\Zmq\Cron\PidFile;

set_time_limit(0);

$logger = Scalr::getContainer()->logger('cron/service.php')->setLevel(Scalr::config('scalr.crontab.log_level'));

$oPid = new PidFile(CACHEPATH . '/cron.service.pid', '/service.php');
//If we start service each minute we should eliminate exrtra messages like "Another process already running..."
$oPid->pidExistLevel = 'WARN';
$oPid->setLogger($logger)->create();

$interrupt = 0;

//Signal handler callback function
$sigHandler = function ($signo = null) use (&$interrupt, $oPid, $logger) {
    static $once = 0;

    $interrupt++;

    if ($once++) return;

    //Reporting about termination
    $logger->log("SERVICE", "Service recieved termination SIGNAL:%d", intval($signo));

    //We should stop all running tasks.
    //It's enough to terminate all running clients.
    Launcher::terminateClients();

    //Without this workers may not be terminated
    sleep(1);

    //Terminating an MDP broker, not sure we have to do so.
    Launcher::terminateBroker();

    //Removing pid file
    $oPid->remove();
};

pcntl_signal(SIGINT, $sigHandler);
pcntl_signal(SIGTERM, $sigHandler);
pcntl_signal(SIGHUP, $sigHandler);

register_shutdown_function($sigHandler);

$previous = new DateTime('-5 minutes');

$logger->log("SERVICE", "Starting service");

while (!$interrupt) {
    // System tymezone is considered to be used here
    $startTime = new DateTime('now');

    //We run every minute
    if ($startTime->format('i') == $previous->format('i')) {
        //Sleep interval, first sleep will be long
        sleep(isset($sleep) ? 1 : 60 - $startTime->format('i'));

        unset($startTime);

        //Next sleeps will be shorter
        $sleep = true;

        continue;
    }

    //Update previous start time with current
    unset($previous);
    $previous = $startTime;

    try {
        //Initializes task factory
        $launcher = new Launcher($startTime);

        //Performs health check at each start
        Launcher::healthcheck();

        $launcher->setLogger($logger)->launch();
    } catch (ZMQException $e) {
        if ($e->getCode() == 4) {
            //Catches ETERM
            $interrupt++;
            usleep(1);
            break;
        }

        $logger->fatal("Launcher error: %s", $e->getMessage());
    }

    //Release resources
    unset($launcher);
    unset($startTime);
    unset($sleep);

    if ($previous->format('i') % 5 === 0) {
        $logger->debug("Memory usage: %0.2f Kb", memory_get_usage() / 1024);
    }
}