#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

use \Scalr\Acl\Acl;
use \Scalr\Acl\Resource;
use \Scalr\Acl\Role;

set_time_limit(0);

$ScalrUpdate = new Update20130802();
$ScalrUpdate->Run();

class Update20130802
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $script = "
INSERT IGNORE `acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x100, `perm_id` = 'create', `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x100, `perm_id` = 'clone', `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x100, `perm_id` = 'configure', `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x101, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x105, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x102, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x103, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x104, `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x104, `perm_id` = 'create', `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x104, `perm_id` = 'configure', `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x104, `perm_id` = 'clone', `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x104, `perm_id` = 'bundletasks', `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x106, `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x106, `perm_id` = 'create', `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x106, `perm_id` = 'edit', `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x106, `perm_id` = 'execute', `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x106, `perm_id` = 'fork', `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x110, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x111, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x112, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x123, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x126, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x122, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x124, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x125, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x121, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x120, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x132, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x131, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x130, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x140, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x141, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x142, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x150, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x151, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x152, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x162, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x164, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x163, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x160, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x161, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x170, `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x170, `perm_id` = 'remove', `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x171, `granted` = 1;
INSERT IGNORE`acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x171, `perm_id` = 'phpmyadmin', `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x172, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x180, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x181, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x182, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x190, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x203, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x202, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x200, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x201, `granted` = 1;

DELETE FROM `acl_account_role_resource_permissions` WHERE `resource_id` = 0x100 AND `perm_id` = 'edit';
DELETE FROM `acl_role_resource_permissions` WHERE `resource_id` = 0x100 AND `perm_id` = 'edit';

ALTER TABLE `account_team_envs` ADD INDEX `fk_account_team_envs_client_environments1` (`env_id`);
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
