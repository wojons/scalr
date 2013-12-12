#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130903();
$ScalrUpdate->Run();

class Update20130903
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $idxName = 'idx_unique';

        $row = $db->GetRow("SHOW INDEXES FROM `account_team_envs` WHERE `key_name` = ?", array($idxName));

        if (!empty($row)) {
            print "Nothing to do. Index already exists.\n";
            return;
        }

        $time = microtime(true);

        //Selects duplicates if they exist.
        $duplicates = $db->GetAll("
            SELECT `id`, `env_id`, `team_id`
            FROM `account_team_envs`
            GROUP BY `env_id`, `team_id`
            HAVING count(`id`) > 1
        ");
        foreach ($duplicates as $rec) {
            //Removes duplicates
            $db->Execute("
                DELETE FROM `account_team_envs`
                WHERE `env_id` = ? AND `team_id` = ? AND `id` <> ?
            ", array(
                $rec['env_id'],
                $rec['team_id'],
                $rec['id'],
            ));
        }

        $db->Execute("
            ALTER TABLE `account_team_envs` ADD UNIQUE INDEX `" . $idxName . "`(`team_id`, `env_id`)
        ");

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}