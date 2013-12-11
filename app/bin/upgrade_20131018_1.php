#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131018_1();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131018_1
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $rec = $db->GetAssoc('DESCRIBE farm_role_settings');

        if (!empty($rec['type'])) {
            print "Process termination. This update has been already applied.\n";
            return;
        }

        $db->Execute("ALTER TABLE `farm_role_settings` ADD `type` ENUM('1','2') NULL ;");

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
