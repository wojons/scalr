#!/usr/bin/env php
<?php

// ACL deployment script.
// If you have successfully run upgrade_20130910_deploy_acl.php script once you should not execute this script.

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130917();
$ScalrUpdate->Run();

class Update20130917
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        print "Checking integrity.\n";

        //Check database
        $u20130730 = $db->getOne("SHOW TABLES LIKE 'acl_roles'");
        if ($u20130730 !== 'acl_roles') {
            print "Upgrade terminated. Old database schema. Please run php app/bin/upgrade_20130730.php\n";
            exit();
        }

        $u20130731 = $db->GetCol("SELECT role_id FROM acl_roles WHERE role_id IN (1, 10)");
        if (!in_array(1, $u20130731) || !in_array(10, $u20130731)) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130731.php\n";
            exit();
        }

        $u = $db->getRow("SHOW INDEXES FROM `account_team_envs` WHERE `key_name` = 'fk_account_team_envs_client_environments1'");
        if (!$u) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130802.php\n";
            exit();
        }

        $u = null;
        $res = $db->Execute('DESCRIBE acl_account_roles');
        while ($rec = $res->GetAssoc()) {
            if (isset($rec['color']))
                $u = true;
        }
        if (!$u) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130806.php\n";
            exit();
        }

        $u = $db->getRow("SHOW INDEXES FROM `account_team_users` WHERE `key_name` = 'idx_unique'");
        if (!$u) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130807.php\n";
            exit();
        }

        $u = $db->getRow("SHOW INDEXES FROM `account_teams` WHERE `key_name` = 'idx_account_role_id'");
        $u2 = $db->GetOne("SELECT role_id FROM acl_role_resource_permissions WHERE resource_id = 0x106 AND perm_id = 'manage'");
        if (!$u || empty($u2)) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130808_acl.php\n";
            exit();
        }

        $u = $db->getRow("SELECT * FROM `acl_account_role_resources` WHERE resource_id = 0x200");
        if (!empty($u)) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130808_acl2.php\n";
            exit();
        }

        $u = $db->getRow("SELECT * FROM `acl_role_resource_permissions` WHERE perm_id = 'not-owned-farms'");
        if (!$u) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130810.php\n";
            exit();
        }

        $u = $db->getRow("SHOW INDEXES FROM `farms` WHERE `key_name` = 'idx_created_by_id'");
        if (!$u) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130820.php\n";
            exit();
        }

        $u = $db->GetRow("SHOW INDEXES FROM `account_team_envs` WHERE `key_name` = 'idx_unique'");
        if (!$u) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130903.php\n";
            exit();
        }

        $u = $db->GetAssoc('DESCRIBE acl_account_roles');
        if (empty($u['is_automatic'])) {
            print "Upgrade terminated. Please run php app/bin/upgrade_20130916.php\n";
            exit();
        }

        print "Integrity seems to be OK.\n";
        print "Deploying new ACL.\n";


        $i = $db->GetOne("SELECT COUNT(`account_role_id`) AS `cnt` FROM `acl_account_roles`");
        if ($i > 0) {
            printf("Upgrade terminated. There are %d account roles already initialized for this installation.\n", $i);
            exit();
        }

        $counter = 0;
        $acl = \Scalr::getContainer()->acl;
        $rs = $db->Execute("SELECT id FROM `clients` ORDER BY `status`");
        while ($rec = $rs->FetchRow()) {
            $account = \Scalr_Account::init();
            $account->loadById($rec['id']);

            //Creates default account roles
            $roles = $account->initializeAcl();

            /* @var $fullAccessRole \Scalr\Acl\Role\AccountRoleObject */
            $fullAccessRole = $roles['account_role'][$acl::ROLE_ID_FULL_ACCESS];

            //Assigns full access role as default to all defined teams for an each account.
            $db->Execute("
                UPDATE `account_teams` SET account_role_id = ?
                WHERE `account_id` = ?
                AND account_role_id IS NULL
            ", array($fullAccessRole->getRoleId(), $account->id));

            unset($account);
            unset($fullAccessRole);
            if ($counter % 500 === 0)
                print '.';
        }

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}