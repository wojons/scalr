#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131021();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131021
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);


        $db->Execute("ALTER TABLE `farm_role_settings` CHANGE `type` `type` TINYINT(1) NULL DEFAULT NULL;");

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
