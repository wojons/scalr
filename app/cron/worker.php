#!/usr/bin/env php
<?php

/**
 * Worker launcher
 *
 * It's used to launch ZMQ MDP worker
 *
 * @author Vitaliy Demidov  <vitaliy@scalr.com>
 * @since  5.0.1 (10.09.2013)
 */

declare(ticks = 1);

define('SCALR_MULTITHREADING', true);

require_once __DIR__ . "/../src/prepend.inc.php";

use Scalr\System\Zmq\Mdp\Worker;
use Scalr\System\Zmq\Cron\ErrorPayload;
use Scalr\System\Zmq\Cron\AbstractPayload;
use Scalr\System\Zmq\Zmsg;
use Scalr\System\Zmq\Mdp\Mdp;

set_time_limit(0);

$opt = getopt('', ['name:']);

if (empty($opt['name']) || !preg_match('/^[\w]+(\.|$)/', $opt['name'])) {
    printf("Usage: worker.php --name=service [options]\n");
    exit;
}

//Service name is expected
$service = $opt['name'];

//The name of the service might be composite (scalarizr_messaging.HostInit.655)
if (($dot = strpos($service, '.')) !== false) {
    $cls = \Scalr::camelize(substr($service, 0, $dot));
} else {
    //name of the class in camel case
    $cls = \Scalr::camelize($service);
}

//Checking if task class exists.
if (!file_exists(SRCPATH . '/Scalr/System/Zmq/Cron/Task/' . $cls . '.php')) {
    printf("Launch error. File %s does not exist.\n", SRCPATH . '/Scalr/System/Zmq/Cron/Task/' . $cls . '.php');
    exit;
}

$taskClass = 'Scalr\\System\\Zmq\\Cron\\Task\\' . $cls;

/* @var $task \Scalr\System\Zmq\Cron\AbstractTask */
$task = new $taskClass();

$config = $task->config();

//Initializes MDP Worker
$worker = (new Worker(Scalr::config('scalr.crontab.sockets.broker'), $service, true))
    ->setHeartbeat(Scalr::config('scalr.crontab.heartbeat.delay'))
    ->setLiveness(Scalr::config('scalr.crontab.heartbeat.liveness'))
    ->setLogger(\Scalr::getContainer()->logger('cron/worker.php')->setLevel(\Scalr::config('scalr.crontab.log_level')))
    ->connect()
;

$interrupt = 0;
//Signal handler callback function
$sigHandler = function ($signo = null) use (&$interrupt, $task, $worker) {
    static $once = 0;

    $interrupt++;

    if ($once++) return;

    $task->log("DEBUG", "Worker received termination SIGNAL:%d", intval($signo));

    //Prevents zombifying
    if ($task->isServiceRegistered() !== false) {
        //Disconnect worker
        //NOTE! If broker is offline at this moment, the process is hanging while worker is sending the message
        $worker->send(Mdp::WORKER_DISCONNECT);

        $task->log('DEBUG', 'Worker sent a disconnect message.');
        //Waits while zmq processes the message before exit
        usleep(1);
    }

    //IMPORTANT! We should not exit here because current task should successfully end
};

pcntl_signal(SIGINT, $sigHandler);
pcntl_signal(SIGTERM, $sigHandler);
pcntl_signal(SIGHUP, $sigHandler);

register_shutdown_function($sigHandler);

$reply = null;

while (!$interrupt) {
    try {
        $request = $worker->recv($reply);
    } catch (\ZMQException $e) {
        if ($e->getCode() == 4) {
            $interrupt++;
            usleep(1);
            break;
        }
        throw $e;
    }

    $payload = unserialize($request->getLast());

    if (!($payload instanceof AbstractPayload)) {
        $payload = new ErrorPayload($payload);
        $payload->message = sprintf('Unexpected request: %s', $request->getLast());
    } else {
        try {
            $payload->setBody($task->worker($payload->body));
            $payload->code = 200;
        } catch (Exception $e) {
            $task->log('ERROR', "Worker %s failed with exception:%s - %s", $task->getName(), get_class($e), $e->getMessage());
            $payload = $payload->error(500, $e->getMessage());
        }
    }

    //It checks memory usage for demonized tasks
    if ($config->daemon && !$task->checkMemoryUsage()) {
        //Adds the pid of the process to payload to handle it on client's side
        $payload->dw = posix_getpid();

        //It does not even exit execution loop. Client should start a replacement in its time.
        //We cannot start worker from here because it won't be correctly terminated by client.
    }

    $reply = new Zmsg();
    $reply->setLast(serialize($payload));

    unset($payload);
    unset($request);
}
