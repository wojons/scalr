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
DELETE FROM `acl_account_role_resources`
WHERE resource_id IN (0x200, 0x201, 0x203);

DELETE FROM `acl_role_resources`
WHERE resource_id IN (0x200, 0x201, 0x203) OR role_id = 1;
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
