#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131023();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131023
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $info = $db->GetRow("SHOW TABLE STATUS WHERE Name = 'farm_role_settings'");
        if ($info['Engine'] != 'InnoDB') {
            $db->Execute("ALTER TABLE `farm_role_settings` ENGINE = InnoDB ROW_FORMAT = COMPACT");
        }

        $constraintName = 'farm_role_settings_ibfk_1';

        $row = $db->GetRow("
            SELECT * FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE INFORMATION_SCHEMA.TABLE_CONSTRAINTS.CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_SCHEMA = '" . $container->config('scalr.connections.mysql.name') . "'
            AND INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_NAME = 'farm_role_settings'
            AND CONSTRAINT_NAME = '" . $constraintName . "'
        ");

        if (!empty($row['CONSTRAINT_NAME'])) {
            print "Exit. Constraint with name " . $constraintName . " already exists.\n";
            exit();
        }

        $db->BeginTrans();

        // Cleanup zomby records
        $db->Execute("
            DELETE FROM `farm_role_settings` WHERE farm_roleid NOT IN (SELECT id FROM farm_roles)
        ");

        // Create foreign key
        $db->Execute("
            ALTER TABLE `farm_role_settings` ADD CONSTRAINT `" . $constraintName . "`
                FOREIGN KEY (`farm_roleid`)
                REFERENCES `farm_roles`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION
        ");

        $db->CommitTrans();
    }
}
