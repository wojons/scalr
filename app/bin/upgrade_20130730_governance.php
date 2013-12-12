<?php

define("NO_TEMPLATES", 1);

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130730Governance();
$ScalrUpdate->Run();

class Update20130730Governance
{
    public function Run()
    {
        global $db;

        $time = microtime(true);

        $db->Execute("CREATE TABLE IF NOT EXISTS `governance` (
              `env_id` INT NOT NULL ,
              `name` VARCHAR(60) NOT NULL ,
              `enabled` INT(1) NOT NULL,
              `value` TEXT NULL ,
              PRIMARY KEY (`env_id`, `name`) )
            ENGINE = InnoDB
        ");

        $db->Execute("ALTER TABLE `governance` ADD FOREIGN KEY (  `env_id` ) REFERENCES `client_environments` (
            `id`
            ) ON DELETE CASCADE ON UPDATE NO ACTION ;
        ");

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n\n", $t);
    }

    public function migrate()
    {
    }
}
