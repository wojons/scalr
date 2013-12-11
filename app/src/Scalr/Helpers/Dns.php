<?php
    class Scalr_Helpers_Dns
    {
        public static function farmValidateRoleSettings($settings, $rolename)
        {

        }

        public static function farmUpdateRoleSettings(DBFarmRole $DBFarmRole, $oldSettings, $newSettings)
        {
            /*
            if ($newSettings[DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS] != $oldSettings[DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS])
                $update = true;

            if ($newSettings[DBFarmRole::SETTING_DNS_INT_RECORD_ALIAS] != $oldSettings[DBFarmRole::SETTING_DNS_INT_RECORD_ALIAS])
                $update = true;

            //SLOW!!!

            $zones = DBDNSZone::loadByFarmId($DBFarmRole->FarmID);
            foreach ($zones as $zone)
            {
                $zones = DBDNSZone::loadByFarmId($DBFarmRole->FarmID);
                foreach ($zones as $zone)
                {
                    $zone->updateSystemRecords();
                    $zone->save();
                }
            }
            */
        }
    }

?>