#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131030();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131030
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $rec = $db->GetAssoc('DESCRIBE scripting_log');

        if (empty($rec['run_as'])) {
            $db->Execute("ALTER TABLE `scripting_log` ADD `run_as` VARCHAR(20) NULL AFTER `exec_exitcode`");
        }

        $info = $db->GetRow("SHOW TABLE STATUS WHERE Name = 'servers_history'");
        if ($info['Engine'] != 'InnoDB') {
            $db->Execute("ALTER TABLE `servers_history` ENGINE = InnoDB ROW_FORMAT = COMPACT");
        }

        $constraintName = 'client_id';

        $row = $db->GetRow("
            SELECT * FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE INFORMATION_SCHEMA.TABLE_CONSTRAINTS.CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_SCHEMA = '" . $container->config('scalr.connections.mysql.name') . "'
            AND INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_NAME = 'servers_history'
            AND CONSTRAINT_NAME = '" . $constraintName . "'
        ");

        if (empty($row['CONSTRAINT_NAME'])) {
            $db->BeginTrans();
            // Cleanup zomby records
            $db->Execute("
                DELETE FROM `servers_history` WHERE client_id NOT IN (SELECT id FROM clients)
            ");

            // Create foreign key
            $db->Execute("
            ALTER TABLE `servers_history` ADD  CONSTRAINT `{$constraintName}` FOREIGN KEY (`client_id`)
                REFERENCES `clients`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ");

            $db->CommitTrans();
        }
    }
}
