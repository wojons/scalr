<?php

use Scalr\Model\Entity;

class MySQLMaintenanceProcess implements \Scalr\System\Pcntl\ProcessInterface
{
    public $ThreadArgs;
    public $ProcessDescription = "Maintenance mysql role on farms";
    public $Logger;
    public $IsDaemon;

    public function __construct()
    {
        // Get Logger instance
        $this->Logger = \Scalr::getContainer()->logger(__CLASS__);
    }

    public function OnStartForking()
    {
        $db = \Scalr::getDb();

        //TODO: roles

        $this->ThreadArgs = $db->GetAll("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?)",
            array(ROLE_BEHAVIORS::MYSQL)
        );
    }

    public function OnEndForking()
    {

    }

    public function StartThread($mysql_farm_role)
    {
        // Reconfigure observers;
        Scalr::ReconfigureObservers();

        $db = \Scalr::getDb();

        $DBFarmRole = DBFarmRole::LoadByID($mysql_farm_role['id']);

           try {
            $DBFarm = $DBFarmRole->GetFarmObject();
           } catch(Exception $e) {
               return;
           }

        //skip terminated farms
        if ($DBFarm->Status != FARM_STATUS::RUNNING)
            return;

        $tz = Scalr_Environment::init()->loadById($DBFarm->EnvID)->getPlatformConfigValue(Scalr_Environment::SETTING_TIMEZONE);
        if ($tz)
            date_default_timezone_set($tz);

        //
        // Check replication status
        //
        $this->Logger->info("[FarmID: {$DBFarm->ID}] Checking replication status");

        $servers = $DBFarmRole->GetServersByFilter(array(
            'status'		=> SERVER_STATUS::RUNNING
        ));

        //
        // Check backups and mysql bandle procedures
        //

        //Backups
        if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BCP_ENABLED) && $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BCP_EVERY) != 0)
        {
            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_IS_BCP_RUNNING) == 1)
            {
                // Wait for timeout time * 2 (Example: NIVs problem with big mysql snapshots)
                // We must wait for running bundle process.
                $bcp_timeout = ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BCP_EVERY)*60)*5;
                if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_LAST_BCP_TS)+$bcp_timeout < time())
                    $bcp_timeouted = true;

                if (!empty($bcp_timeouted))
                {
                    $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BCP_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
                    $this->Logger->info("[FarmID: {$DBFarm->ID}] MySQL Backup already running. Timeout. Clear lock.");
                }
            }
            else
            {
                $timeout = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BCP_EVERY)*60;
                if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_LAST_BCP_TS)+$timeout < time())
                {
                    $this->Logger->info("[FarmID: {$DBFarm->ID}] Need new backup");

                    $servers = $DBFarm->GetMySQLInstances(false, true);

                    if (empty($servers[0]))
                        $servers = $DBFarm->GetMySQLInstances(true);
                    else
                        $servers = array_reverse($servers);

                    $DBServer = isset($servers[0]) ? $servers[0] : null;

                    if ($DBServer)
                    {
                        if ($DBServer->status == SERVER_STATUS::RUNNING)
                        {
                            $msg = new Scalr_Messaging_Msg_Mysql_CreateBackup($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_ROOT_PASSWORD));
                            $DBServer->SendMessage($msg);

                            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BCP_RUNNING, 1, Entity\FarmRoleSetting::TYPE_LCL);
                            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_BCP_SERVER_ID, $DBServer->serverId, Entity\FarmRoleSetting::TYPE_LCL);
                        }
                    }
                    else
                        $this->Logger->info("[FarmID: {$DBFarm->ID}] There is no running mysql instances for run backup procedure!");
                }
            }
        }

        if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_ENABLED) && $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_EVERY) != 0)
        {
            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_IS_BUNDLE_RUNNING) == 1)
            {
                // Wait for timeout time * 2 (Example: NIVs problem with big mysql snapshots)
                // We must wait for running bundle process.
                $bundle_timeout = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_EVERY)*(3600*2);
                if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_LAST_BUNDLE_TS)+$bundle_timeout < time())
                    $bundle_timeouted = true;

                if (!empty($bundle_timeouted))
                {
                    $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BUNDLE_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
                    $this->Logger->info("[FarmID: {$DBFarm->ID}] MySQL Bundle already running. Timeout. Clear lock.");
                }
            }
            else
            {
                /*
                 * Check bundle window
                 */
                $bundleEvery = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_EVERY);
                $timeout = $bundleEvery*3600;
                $lastBundleTime = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_LAST_BUNDLE_TS);

                $performBundle = false;
                if ($bundleEvery % 24 == 0)
                {
                    if ($lastBundleTime)
                    {
                        $days = $bundleEvery / 24;
                        $bundleDay = (int)date("md", strtotime("+{$days} day", $lastBundleTime));

                        if ($bundleDay > (int)date("md"))
                            return;
                    }

                    $pbwFrom = (int)($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_WINDOW_START_HH).$DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_WINDOW_START_MM));
                    $pbwTo = (int)($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_WINDOW_END_HH).$DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_WINDOW_END_MM));
                    if ($pbwFrom && $pbwTo) {
                        $current_time = (int)date("Hi");
                        if ($pbwFrom <= $current_time && $pbwTo >= $current_time)
                            $performBundle = true;
                    }
                    else
                        $performBundle = true;
                }
                else
                {
                    //Check timeout
                    if ($lastBundleTime+$timeout < time()) {
                        $pbwFrom = (int)($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_WINDOW_START_HH).$DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_WINDOW_START_MM));
                        $pbwTo = (int)($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_WINDOW_END_HH).$DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_WINDOW_END_MM));
                        if ($pbwFrom && $pbwTo) {
                            $current_time = (int)date("Hi");
                            if ($pbwFrom <= $current_time && $pbwTo >= $current_time)
                                $performBundle = true;
                        }
                        else
                            $performBundle = true;
                    }
                }

                if ($performBundle)
                {
                    $this->Logger->info("[FarmID: {$DBFarm->ID}] Need mySQL bundle procedure");

                    // Rebundle
                    $servers = $DBFarm->GetMySQLInstances(true, false);
                    $DBServer = isset($servers[0]) ? $servers[0] : null;

                    if ($DBServer)
                    {
                        if ($DBServer->status == SERVER_STATUS::RUNNING)
                        {
                            $DBServer->SendMessage(new Scalr_Messaging_Msg_Mysql_CreateDataBundle());

                            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BUNDLE_RUNNING, 1, Entity\FarmRoleSetting::TYPE_LCL);
                            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_SERVER_ID, $DBServer->serverId, Entity\FarmRoleSetting::TYPE_LCL);
                        }
                    }
                    else
                        $this->Logger->info("[FarmID: {$DBFarm->ID}] There is no running mysql master instances for run bundle procedure!");
                }
            }
        }
    }
}
