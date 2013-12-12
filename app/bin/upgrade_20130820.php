#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

use \Scalr\Acl\Acl;
use \Scalr\Acl\Resource;
use \Scalr\Acl\Role;

set_time_limit(0);

$ScalrUpdate = new Update20130820();
$ScalrUpdate->Run();

class Update20130820
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $script = "
ALTER TABLE `farms` ADD INDEX `idx_created_by_id`(`created_by_id`), ADD INDEX `idx_changed_by_id`(`changed_by_id`);
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
