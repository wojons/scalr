#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131029();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131029
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;


        $table = 'servers';
        $key = 'dtshutdownscheduled';

        $idxName = 'idx_' . $key;
        $row = $db->GetRow("SHOW INDEXES FROM `" . $table . "` WHERE `key_name` = ?", array(
            $idxName
        ));

        if (!empty($row)) {
            print "Nothing to do. Index $idxName does exist in $table.\n";
        } else {
            $db->Execute("ALTER TABLE `" . $table . "` ADD INDEX `" . $idxName . "` (`" . $key . "`)");
        }
    }
}
