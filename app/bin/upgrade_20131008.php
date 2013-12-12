#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20131008();
$ScalrUpdate->Run();

class Update20131008
{
    protected function addIndex($table, $key = 'dtadded')
    {
        $db = \Scalr::getDb();
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

    public function Run()
    {
        $container = \Scalr::getContainer();

        $time = microtime(true);

        $this->addIndex('syslog');

        $this->addIndex('scripting_log');

        $this->addIndex('events');

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}