#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131018();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131018
{
    public function run()
    {
        $container = \Scalr::getContainer();
        $db = $container->adodb;

        $row = $db->GetRow("SHOW TABLES LIKE 'upgrades'");
        if ($row) {
            print "Tables already exist. Terminating.\n";
            exit();
        }

        $script = <<<EOL
            CREATE TABLE `upgrades` (
             `uuid` VARBINARY(16) NOT NULL COMMENT 'Unique identifier of update',
             `released` DATETIME NOT NULL COMMENT 'The time when upgrade script is issued',
             `appears` DATETIME NOT NULL COMMENT 'The time when upgrade does appear',
             `applied` DATETIME DEFAULT NULL COMMENT 'The time when update is successfully applied',
             `status` TINYINT NOT NULL COMMENT 'Upgrade status',
             `hash` VARBINARY (20) COMMENT 'SHA1 hash of the upgrade file',
             PRIMARY KEY (`uuid`),
             INDEX `idx_status` (`status`),
             INDEX `idx_appears` (`appears`)
            ) ENGINE = InnoDB;

            CREATE TABLE `upgrade_messages` (
              `uuid` VARBINARY(16) NOT NULL COMMENT 'upgrades.uuid reference',
              `created` DATETIME NOT NULL COMMENT 'Creation timestamp',
              `message` TEXT COMMENT 'Error messages',
              INDEX idx_uuid (`uuid`),
              CONSTRAINT `upgrade_messages_ibfk_1` FOREIGN KEY (`uuid`) REFERENCES `upgrades` (`uuid`) ON DELETE CASCADE
            ) ENGINE = InnoDB;
EOL;

        $lines = array_filter(preg_split('/;[\s\r\n]*/m', $script));

        foreach ($lines as $stmt) {
            $db->Execute($stmt);
        }
    }
}
