#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20131017();
$ScalrUpdate->Run();

class Update20131017
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $db->BeginTrans();
        try {
            $db->Execute('ALTER TABLE `farm_role_scripts` ADD `run_as` VARCHAR(15) NULL ;');

            $db->CommitTrans();

        } catch (Exception $e) {
            $db->RollbackTrans();
            throw $e;
        }


        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
