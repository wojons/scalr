#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130923();
$ScalrUpdate->Run();

class Update20130923
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $db->Execute('CREATE TABLE IF NOT EXISTS `farm_lease_requests` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `farm_id` int(11) NOT NULL,
              `requested_days` int(11) NOT NULL,
              `comment` text,
              `status` char(20) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `farm_id` (`farm_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ');

        print "Done.\n";
        $t = round(microtime(true) - $time, 2);
        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
