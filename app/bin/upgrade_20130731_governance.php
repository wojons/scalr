#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130731Governance();
$ScalrUpdate->Run();

class Update20130731Governance
{
    public function Run()
    {
        global $db;

        $time = microtime(true);

        $db->Execute("TRUNCATE TABLE `governance`");
        
        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n\n", $t);
    }

}
