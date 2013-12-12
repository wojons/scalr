#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131022();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131022
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $f = $db->getRow("SHOW INDEXES FROM `dm_deployment_task_logs` WHERE `key_name` = 'idx_dm_deployment_task_id'");
        if ($f) {
            print "Index already exists. Exit.\n";
            exit();
        }

        $db->Execute("ALTER TABLE `dm_deployment_task_logs` ADD INDEX `idx_dm_deployment_task_id` (`dm_deployment_task_id`)");

    }
}
