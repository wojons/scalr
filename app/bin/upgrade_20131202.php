#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131202();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131202
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;


        $db->Execute("ALTER TABLE `events` ADD INDEX `idx_type` (`type`(25));");
    }
}
