#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20131001();
$ScalrUpdate->Run();

class Update20131001
{
    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $db->Execute("INSERT IGNORE `acl_role_resource_permissions` SET `role_id` = 10, `resource_id` = 0x104, `perm_id` = 'create', `granted` = 1");

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}