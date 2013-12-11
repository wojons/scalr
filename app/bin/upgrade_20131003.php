#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20131003();
$ScalrUpdate->Run();

class Update20131003
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $idxName = 'idx_ishandled';

        $row = $db->GetRow("SHOW INDEXES FROM `events` WHERE `key_name` = ?", array(
            $idxName
        ));

        if (!empty($row)) {
            print "Nothing to do. Index does exist.\n";
            return;
        }

        $db->Execute("ALTER TABLE `events` ADD INDEX `idx_ishandled` (`ishandled`)");

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}