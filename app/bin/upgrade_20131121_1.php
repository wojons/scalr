#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131121();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131121
{
    public function hasTableIndex($table, $index)
    {
        $res = Scalr::getDb()->getRow("SHOW INDEXES FROM " . $table . " WHERE `key_name` = ?", array(
                $index
            ));

        return $res ? true : false;
    }


    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        if (! $this->hasTableIndex('global_variables', 'role_id')) {
            $db->Execute('ALTER TABLE `global_variables` ADD INDEX (`role_id`)');
            $db->Execute('ALTER TABLE `global_variables` ADD INDEX (`farm_id`)');
            $db->Execute('ALTER TABLE `global_variables` ADD INDEX (`farm_role_id`)');
            $db->Execute('ALTER TABLE `global_variables` ADD INDEX (`server_id`)');
        }
    }
}
