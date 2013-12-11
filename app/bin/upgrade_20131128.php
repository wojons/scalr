#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131128();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131128
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;


        $db->Execute("ALTER TABLE `server_alerts` ADD INDEX `farm_roleid` (`farm_roleid`)");

        $db->Execute("DELETE FROM server_alerts WHERE farm_roleid NOT IN (SELECT id FROM farm_roles)");

        $db->Execute("ALTER TABLE `server_alerts` ADD CONSTRAINT `server_alerts_ibfk_3` FOREIGN KEY (`farm_roleid`) REFERENCES `farm_roles`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION;");
    }
}
