#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

use \Scalr\Acl\Acl;

set_time_limit(0);

$ScalrUpdate = new Update20130731();
$ScalrUpdate->Run();

class Update20130731
{
    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $script = "
INSERT IGNORE INTO `acl_roles` (`role_id`, `name`) VALUES
(1, 'Everything forbidden'),
(10, 'Full access');

INSERT IGNORE INTO `acl_role_resources` (`role_id`, `resource_id`, `granted`) VALUES
(1, " . Acl::RESOURCE_FARMS . ", 0),
(10, " . Acl::RESOURCE_FARMS . ", 1);

INSERT IGNORE INTO `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`) VALUES
(1, " . Acl::RESOURCE_FARMS . ", '" . Acl::PERM_FARMS_LAUNCH . "', 0),
(1, " . Acl::RESOURCE_FARMS . ", '" . Acl::PERM_FARMS_TERMINATE . "', 0),

(10, " . Acl::RESOURCE_FARMS . ", '" . Acl::PERM_FARMS_LAUNCH . "', 1),
(10, " . Acl::RESOURCE_FARMS . ", '" . Acl::PERM_FARMS_TERMINATE . "', 1);
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
