#!/usr/bin/env php
<?php

/**
 * Client process launcher
 *
 * @author Vitaliy Demidov  <vitaliy@scalr.com>
 * @since  5.0.1 (22.09.2013)
 */

declare(ticks = 1);

define('SCALR_MULTITHREADING', true);

require_once __DIR__ . "/../src/prepend.inc.php";

use Scalr\System\Zmq\Cron\PidFile;

set_time_limit(0);

$opt = getopt('', ['name:']);

if (empty($opt['name']) || !preg_match('/^[\w]+$/', $opt['name'])) {
    printf("Usage: client.php --name=service [options]\n");
    exit;
}

//Service name is expected
$service = $opt['name'];

//name of the class in camel case
$cls = Scalr::camelize($service);

$logger = Scalr::getContainer()->logger('cron/client.php')->setLevel(Scalr::config('scalr.crontab.log_level'));

//Checking if task class exists.
if (!file_exists(SRCPATH . '/Scalr/System/Zmq/Cron/Task/' . $cls . '.php')) {
    $logger->fatal("Launch error. File %s does not exist.", SRCPATH . '/Scalr/System/Zmq/Cron/Task/' . $cls . '.php');
    exit;
}

$taskClass = 'Scalr\\System\\Zmq\\Cron\\Task\\' . $cls;

/* @var $task \Scalr\System\Zmq\Cron\AbstractTask */
$task = new $taskClass();

$taskConfig = $task->config();

$oPid = new PidFile(CACHEPATH . '/cron.client.' . $task->getName() . '.pid', '/client.php');
$oPid->setLogger($logger);

//If it's demonized task it should not bark to log that another process already running
if ($taskConfig->daemon) {
    $oPid->pidExistLevel = 'WARN';
}

$oPid->create();

$interrupt = 0;
//Signal handler callback function
$sigHandler = function ($signo = null) use (&$interrupt, $oPid, $task, $taskConfig) {
    static $once = 0;

    $interrupt++;

    if ($once++) return;

    $task->log(($taskConfig->daemon ? 'SERVICE' : 'DEBUG'), "Client recieved termination SIGNAL:%d", intval($signo));

    //Terminating child processes (workers)
    $task->shutdown();

    //Removing pid file
    $oPid->remove();

    //No use to proceed
    exit;
};

pcntl_signal(SIGINT, $sigHandler);
pcntl_signal(SIGTERM, $sigHandler);
pcntl_signal(SIGHUP, $sigHandler);

register_shutdown_function($sigHandler);

if ($taskConfig->daemon) {
    $task->log('SERVICE', 'Starting %s...', $task->getName());
}

while (!$interrupt) {
    try {
        $task->run();
    } catch (ZMQException $e) {
        if ($e->getCode() == 4) {
            $interrupt++;
            usleep(1);
            break;
        } else {
            throw $e;
        }
    }

    //It does not need to repeat if it's daemon
    if (!$taskConfig->daemon)
        break;

    //It checks memory usage for demonized services
    if (!$task->checkMemoryUsage()) {
        break;
    }

    $idle = isset($taskConfig->idle) ? $taskConfig->idle : 5;
    $task->log("DEBUG", "Idling for %d sec", $idle);
    sleep($idle);
}
