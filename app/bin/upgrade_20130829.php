#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130829();
$ScalrUpdate->Run();

class Update20130829
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $db->Execute("ALTER TABLE `messages` ADD `dtadded` DATETIME NULL AFTER `dtlasthandleattempt`;");
        $db->Execute("ALTER TABLE `scripting_log` ADD `execution_id` VARCHAR(36) NULL ;");
        $db->Execute("ALTER TABLE `scripting_log` ADD INDEX `execution_id` (`execution_id`(36));");

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
