#!/usr/bin/env php
<?php

require_once __DIR__ . "/../src/prepend.inc.php";

set_time_limit(0);

$broker = (new \Scalr\System\Zmq\Mdp\Broker(true))
    ->setHeartbeat(\Scalr::config('scalr.crontab.heartbeat.delay'))
    ->setLiveness(\Scalr::config('scalr.crontab.heartbeat.liveness'))
    ->setLogger(\Scalr::getContainer()->logger('cron/broker.php')->setLevel(\Scalr::config('scalr.crontab.log_level')))
    ->bind(\Scalr::config('scalr.crontab.sockets.broker'))
    ->listen()
;