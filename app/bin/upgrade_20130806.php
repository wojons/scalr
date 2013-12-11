#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

use \Scalr\Acl\Acl;
use \Scalr\Acl\Resource;
use \Scalr\Acl\Role;

set_time_limit(0);

$ScalrUpdate = new Update20130806();
$ScalrUpdate->Run();

class Update20130806
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $script = "
UPDATE `acl_roles` SET name = 'No access' WHERE role_id = 1;

ALTER TABLE `account_team_user_acls`
    DROP FOREIGN KEY `fk_4c65ed7fea80e0f3792d`;

ALTER TABLE `account_team_user_acls`
    ADD CONSTRAINT `fk_4c65ed7fea80e0f3792d`
    FOREIGN KEY `fk_4c65ed7fea80e0f3792d` (`account_role_id`)
    REFERENCES `acl_account_roles` (`account_role_id`);

ALTER TABLE `acl_account_roles` ADD COLUMN `color` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 AFTER `name`;
";

        $time = microtime(true);

        foreach (preg_split('/;/', $script) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt == '') continue;
            $db->Execute($stmt);
        }

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
