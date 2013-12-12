#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130910();
$ScalrUpdate->Run();

class Update20130910
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $db->Execute("ALTER TABLE `roles` ADD `dtadded` DATETIME NULL , ADD `added_by_userid` INT(11) NULL , ADD `added_by_email` VARCHAR(50) NULL ;");

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}