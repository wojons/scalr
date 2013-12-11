#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130916();
$ScalrUpdate->Run();

class Update20130916
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $rec = $db->GetAssoc('DESCRIBE acl_account_roles');

        if (!empty($rec['is_automatic'])) {
            print "Process termination. This update has been already applied.\n";
            return;
        }

        $db->Execute("ALTER TABLE `acl_account_roles` ADD `is_automatic` INT(1) NOT NULL DEFAULT 0 COMMENT 'Whether the role is created automatically.'");
        $db->Execute("ALTER TABLE `acl_account_roles` ADD INDEX `idx_accountid_roleid` (`account_id`, `role_id`, `is_automatic`)");

        $counter = 0;
        $acl = \Scalr::getContainer()->acl;
        $rs = $db->Execute("SELECT id FROM `clients` ORDER BY `status`");
        while ($rec = $rs->FetchRow()) {
            $accountId = $rec['id'];
            $db->Execute("
                UPDATE `acl_account_roles` SET `is_automatic` = 1
                WHERE account_id = ? AND role_id = ?
                AND name = 'Full access (no admin)'
                LIMIT 1
            ", array(
                $accountId,
                $acl::ROLE_ID_FULL_ACCESS
            ));
            $db->Execute("
                UPDATE `acl_account_roles` SET `is_automatic` = 1
                WHERE account_id = ? AND role_id = ?
                AND name = 'No access'
                LIMIT 1
            ", array(
                $accountId,
                $acl::ROLE_ID_EVERYTHING_FORBIDDEN
            ));
        }

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}