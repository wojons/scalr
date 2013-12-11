#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130807();
$ScalrUpdate->Run();

class Update20130807
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $script = "
ALTER TABLE `account_team_users` ADD UNIQUE INDEX `idx_unique`(`team_id`, `user_id`);
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
