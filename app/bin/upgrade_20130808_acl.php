#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130808();
$ScalrUpdate->Run();

class Update20130808
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $script = "
DELETE FROM `acl_account_role_resource_permissions`
WHERE resource_id = 0x100 AND perm_id IN ('create', 'configure');

DELETE FROM `acl_account_role_resource_permissions`
WHERE resource_id = 0x104 AND perm_id IN ('create', 'configure');

DELETE FROM `acl_account_role_resource_permissions`
WHERE resource_id = 0x106 AND perm_id IN ('create', 'edit');

DELETE FROM `acl_role_resource_permissions`
WHERE resource_id IN (0x100, 0x104) AND perm_id IN ('create', 'configure');

DELETE FROM `acl_role_resource_permissions`
WHERE resource_id = 0x106 AND perm_id IN ('create', 'edit');

INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`) VALUES
(10, 0x100, 'manage', 1),
(10, 0x104, 'manage', 1),
(10, 0x106, 'manage', 1);

INSERT IGNORE `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`)
SELECT `account_role_id`, `resource_id`, 'manage', `granted`
FROM `acl_account_role_resources`
WHERE resource_id IN (0x100, 0x104, 0x106);

ALTER TABLE `account_teams` CHARACTER SET = utf8;
ALTER TABLE `account_teams` ADD COLUMN `account_role_id` VARCHAR(20) COMMENT 'Default ACL role for team users';
ALTER TABLE `account_teams` ADD INDEX `idx_account_role_id` (`account_role_id`);
ALTER TABLE `account_teams`
    ADD CONSTRAINT `FK_315e023acf4b65b9203`
    FOREIGN KEY (`account_role_id`)
    REFERENCES `acl_account_roles` (`account_role_id`);
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
