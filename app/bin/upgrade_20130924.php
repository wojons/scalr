#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130924();
$ScalrUpdate->Run();

class Update20130924
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $db->Execute("ALTER TABLE `services_ssl_certs` CHANGE `name` `name` VARCHAR(80) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;");

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}