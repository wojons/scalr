#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20131007();
$ScalrUpdate->Run();

class Update20131007
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $idxName = 'idx_time';

        $row = $db->GetRow("SHOW INDEXES FROM `logentries` WHERE `key_name` = ?", array(
            $idxName
        ));

        if (!empty($row)) {
            print "Nothing to do. Index does exist.\n";
            return;
        }

        $db->Execute('ALTER TABLE logentries ADD INDEX `idx_time` (`time`)');

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}