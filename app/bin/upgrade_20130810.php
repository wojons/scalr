#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130810();
$ScalrUpdate->Run();

class Update20130810
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $script = "
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x165, `granted` = 1;
INSERT IGNORE`acl_role_resources` SET `role_id` = 10, `resource_id` = 0x153, `granted` = 1;

INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`) VALUES
(10, 0x100, 'not-owned-farms', 1);
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
