#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130808();
$ScalrUpdate->Run();

class Update20130808
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        //Get all farm_roles with defined Security groups
        $settings = $db->Execute("SELECT * FROM farm_role_settings WHERE `name`='aws.additional_security_groups'");
        while ($setting = $settings->FetchRow()) {

            try {
                $dbFarmRole = DBFarmRole::LoadByID($setting['farm_roleid']);
                $dbFarmRole->SetSetting(DBFarmRole::SETTING_AWS_SG_LIST_APPEND, 1);

                $list = trim(trim(str_replace("scalr.ip-pool", "", $setting['value']), ","));

                $dbFarmRole->SetSetting(DBFarmRole::SETTING_AWS_SG_LIST, $list);
            } catch (Exception $e) {}
        }



        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
