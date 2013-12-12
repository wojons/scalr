#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130812();
$ScalrUpdate->Run();

class Update20130812
{

    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $time = microtime(true);

        //Get all farm_roles with defined Security groups
        $settings = $db->Execute("SELECT * FROM farm_role_settings WHERE `name`='dns.exclude_role'");
        while ($setting = $settings->FetchRow()) {
            try {
                $dbFarmRole = DBFarmRole::LoadByID($setting['farm_roleid']);
                if ($setting['value'] == 1) {
                    $dbFarmRole->SetSetting(DBFarmRole::SETTING_DNS_CREATE_RECORDS, 0);
                    $dbFarmRole->SetSetting(DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS, null);
                    $dbFarmRole->SetSetting(DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS, null);
                } else {
                    $dbFarmRole->SetSetting(DBFarmRole::SETTING_DNS_CREATE_RECORDS, 1);

                    if (!$dbFarmRole->GetSetting(DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS))
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS, "ext-" . $dbFarmRole->GetRoleObject()->name);

                    if (!$dbFarmRole->GetSetting(DBFarmRole::SETTING_DNS_INT_RECORD_ALIAS))
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_DNS_INT_RECORD_ALIAS, "int-" . $dbFarmRole->GetRoleObject()->name);
                }

                $dbFarmRole->SetSetting(DBFarmRole::SETTING_EXCLUDE_FROM_DNS, null);

            } catch (Exception $e){}
        }

        $settings = $db->Execute("SELECT * FROM farm_role_settings WHERE `name`='dns.create_records' AND value='1'");
        while ($setting = $settings->FetchRow()) {
            try {
                $dbFarmRole = DBFarmRole::LoadByID($setting['farm_roleid']);

                if (!$dbFarmRole->GetSetting(DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS))
                    $dbFarmRole->SetSetting(DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS, "ext-" . $dbFarmRole->GetRoleObject()->name);

                if (!$dbFarmRole->GetSetting(DBFarmRole::SETTING_DNS_INT_RECORD_ALIAS))
                    $dbFarmRole->SetSetting(DBFarmRole::SETTING_DNS_INT_RECORD_ALIAS, "int-" . $dbFarmRole->GetRoleObject()->name);

            } catch (Exception $e){}
        }

        $db->Execute("ALTER TABLE `farm_roles` ADD `alias` VARCHAR(50) NULL AFTER `farmid`");

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
