#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131120();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131120
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $cnt = 0;

        $rec = $db->GetAssoc('DESCRIBE bundle_tasks');

        if (empty($rec['created_by_id'])) {
            $db->Execute("ALTER TABLE `bundle_tasks`  ADD `created_by_id` INT(11) NULL ,  ADD `created_by_email` VARCHAR(100) NULL ;");
            $cnt++;
        }

        if (!$cnt) {
            print "Terminated. This upgrade has already been applied.\n";
            exit;
        }
    }
}
