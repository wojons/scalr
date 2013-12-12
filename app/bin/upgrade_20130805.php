#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130805();
$ScalrUpdate->Run();

class Update20130805
{
    public function Run()
    {
        $time = microtime(true);

        $container = Scalr::getContainer();
        $db = $container->adodb;


        $db->Execute("ALTER TABLE `servers` ADD `os_type` ENUM('linux','windows') NULL DEFAULT 'linux' ;");

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
