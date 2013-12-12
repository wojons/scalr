#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130816();
$ScalrUpdate->Run();

class Update20130816
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        $farms = $db->Execute("SELECT id, created_by_id, created_by_email, clientid FROM farms");
        while ($farm = $farms->FetchRow()) {
            if (!$farm['created_by_email'] && !$farm['created_by_id']) {
                $accountOwner = Scalr_Account::init()->loadById($farm['clientid'])->getOwner();

                $db->Execute("UPDATE farms SET created_by_id = ?, created_by_email = ? WHERE id = ?", array(
                    $accountOwner->id,
                    $accountOwner->getEmail(),
                    $farm['id']
                ));
            }
        }

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
