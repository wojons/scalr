#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131121();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131121
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        if (! $db->GetRow('DESCRIBE global_variables flag_hidden')) {
            $db->Execute("ALTER TABLE global_variables ADD `flag_hidden` TINYINT(1)  NULL  DEFAULT '0' AFTER flag_required");
            $db->Execute("ALTER TABLE `global_variables` CHANGE `scope` `scope` ENUM('env','role','farm','farmrole','server') NULL  DEFAULT NULL");
            $db->Execute("ALTER TABLE `global_variables` ADD `server_id` VARCHAR(36) NULL DEFAULT NULL AFTER farm_role_id");
            $db->Execute("ALTER TABLE `global_variables` DROP FOREIGN KEY `global_variables_ibfk_1`");
            $db->Execute("ALTER TABLE `global_variables` DROP INDEX `name`, DROP INDEX `farm_id`, DROP INDEX `role_id`, DROP INDEX `farm_role_id`, DROP PRIMARY KEY");
            $db->Execute("ALTER TABLE `global_variables` ADD PRIMARY KEY (`env_id`,`role_id`,`farm_id`,`farm_role_id`,`server_id`,`name`)");
            $db->Execute("ALTER TABLE `global_variables` ADD CONSTRAINT `fk_global_variables_client_environments_env_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION");
        }
    }
}
