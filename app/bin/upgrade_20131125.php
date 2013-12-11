#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$time = microtime(true);

$ScalrUpdate = new Update20131125();
$ScalrUpdate->run();

print "Done.\n";
$t = round(microtime(true) - $time, 2);
printf("Upgrade process took %0.2f seconds\n\n", $t);

class Update20131125
{
    public function run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $cnt = 0;
        $rows = $db->GetAll("SELECT name, env_id, farm_id FROM `global_variables` WHERE flag_final = 1 AND scope = 'farm' group by env_id, name");
        foreach ($rows as $row) {
            $rw = $db->GetAll('SELECT * FROM `global_variables` WHERE name = ? AND env_id = ? AND farm_id = ? AND scope = "farmrole"', array(
                $row['name'], $row['env_id'], $row['farm_id']
            ));

            foreach ($rw as $r) {
                $db->Execute('DELETE FROM global_variables WHERE name = ? AND env_id = ? AND farm_id = ? AND scope = "farmrole"', array(
                    $r['name'], $r['env_id'], $r['farm_id']
                ));
                $cnt++;
            }
        }
        echo "removed {$cnt} records (final)\n";

        $cnt = 0;
        $rows = $db->GetAll("SELECT g.*, f.name AS fname FROM global_variables g
            LEFT JOIN farms f ON g.farm_id = f.id
            WHERE farm_id != 0 AND ISNULL(f.name)
        ");
        foreach ($rows as $row) {
            $cnt++;
            $db->Execute('DELETE FROM `global_variables` WHERE name = ? AND farm_id = ?', array($row['name'], $row['farm_id']));
        }
        echo "removed {$cnt} records (farm)\n";

        $cnt = 0;
        $rows = $db->GetAll("SELECT g.*, r.name AS rname FROM global_variables g
            LEFT JOIN roles r ON g.role_id = r.id
            WHERE role_id != 0 AND ISNULL(r.name)
        ");
        foreach ($rows as $row) {
            $cnt++;
            $db->Execute('DELETE FROM `global_variables` WHERE name = ? AND role_id = ?', array($row['name'], $row['role_id']));
        }
        echo "removed {$cnt} records (role)\n";

        $cnt = 0;
        $rows = $db->GetAll("SELECT g.*, fr.role_id AS fr_role_id FROM global_variables g
            LEFT JOIN farm_roles fr ON g.farm_role_id = fr.id
            WHERE farm_role_id != 0 AND ISNULL(fr.role_id)
        ");
        foreach ($rows as $row) {
            $cnt++;
            $db->Execute('DELETE FROM `global_variables` WHERE name = ? AND farm_role_id = ?', array($row['name'], $row['farm_role_id']));
        }
        echo "removed {$cnt} records (farmrole)\n";
    }
}
