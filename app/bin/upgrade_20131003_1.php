#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20131003_1();
$ScalrUpdate->Run();

class Update20131003_1
{
    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $db->Execute("ALTER TABLE `events` CHANGE `msg_sent` `msg_sent` INT(11) NULL DEFAULT '0';");

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}