#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20131001_1();
$ScalrUpdate->Run();

class Update20131001_1
{
    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $db->Execute("ALTER TABLE `messages` ADD `event_id` VARCHAR(36) NULL ;");
        $db->Execute("ALTER TABLE `events` ADD `msg_expected` INT(11) NULL , ADD `msg_created` INT(11) NULL , ADD `msg_sent` INT(11) NULL ;");

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}