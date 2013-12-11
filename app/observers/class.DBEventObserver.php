<?php

use \Scalr\Server\Alerts;

class DBEventObserver extends EventObserver
{
    /**
     * Observer name
     *
     * @var unknown_type
     */
    public $ObserverName = 'DB';

    public function OnCheckFailed(CheckFailedEvent $event) {

        $serverAlerts = new Alerts($event->dBServer);
        $hasActiveAlert = $serverAlerts->hasActiveAlert($event->check);
        if (!$hasActiveAlert) {
            $serverAlerts->createAlert($event->check, $event->details);
        }
    }

    public function OnCheckRecovered(CheckRecoveredEvent $event) {

        $serverAlerts = new Alerts($event->dBServer);
        $hasActiveAlert = $serverAlerts->hasActiveAlert($event->check);
        if ($hasActiveAlert) {
            $serverAlerts->solveAlert($event->check);
        }
    }

    /**
     * Update database when 'mysqlBckComplete' event recieved from instance
     *
     * @param MysqlBackupCompleteEvent $event
     */
    public function OnMysqlBackupComplete(MysqlBackupCompleteEvent $event)
    {
        try
        {
            $DBFarmRole = $event->DBServer->GetFarmRoleObject();
            $farm_roleid = $DBfarmRole->ID;
        }
        catch(Exception $e) {
            return;
        }

        if ($event->Operation == MYSQL_BACKUP_TYPE::DUMP)
        {
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_LAST_BCP_TS, time(), DBFarmRole::TYPE_LCL);
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_IS_BCP_RUNNING, 0, DBFarmRole::TYPE_LCL);

            switch ($event->DBServer->platform) {
                case SERVER_PLATFORMS::EC2:
                    $provider = 's3'; break;
                case SERVER_PLATFORMS::RACKSPACE:
                    $provider = 'cf'; break;
                default:
                    $provider = 'unknown'; break;
            }

            $backup = Scalr_Db_Backup::init();
            $backup->service = 'mysql';
            $backup->platform = $event->DBServer->platform;
            $backup->provider = $provider;
            $backup->envId = $event->DBServer->envId;
            $backup->farmId = $event->DBServer->farmId;
            $backup->cloudLocation = $event->DBServer->GetCloudLocation();
            $backup->status = Scalr_Db_Backup::STATUS_AVAILABLE;

            $total = 0;
            foreach ($event->backupParts as $item) {
                if (is_object($item) && $item->size) {
                    $backup->addPart(str_replace(array("s3://", "cf://"), array("", ""), $item->path), $item->size);
                    $total = $total+(int)$item->size;
                } else {
                    $backup->addPart(str_replace(array("s3://", "cf://"), array("", ""), $item), 0);
                }
            }

            $backup->size = $total;
            $backup->save();

        }
        elseif ($event->Operation == MYSQL_BACKUP_TYPE::BUNDLE)
        {
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_LAST_BUNDLE_TS, time(), DBFarmRole::TYPE_LCL);
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_IS_BUNDLE_RUNNING, 0, DBFarmRole::TYPE_LCL);

            if (!is_array($event->SnapshotInfo))
                $event->SnapshotInfo = array('snapshotId' => $event->SnapshotInfo);

            if ($event->SnapshotInfo['snapshotId'])
            {
                if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_DATA_STORAGE_ENGINE) == MYSQL_STORAGE_ENGINE::EBS)
                {
                    $this->DB->Execute("INSERT INTO ebs_snaps_info SET snapid=?, comment=?, dtcreated=NOW(), region=?, ebs_array_snapid='0', is_autoebs_master_snap='1', farm_roleid=?",
                    array(
                        $event->SnapshotInfo['snapshotId'],
                        _('MySQL Master volume snapshot'),
                        $event->DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION),
                        $DBFarmRole->ID
                    ));

                    // Scalarizr stuff
                    $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_SNAPSHOT_ID, $event->SnapshotInfo['snapshotId'], DBFarmRole::TYPE_LCL);

                    $snapshotConfig = new stdClass();
                    $snapshotConfig->type = 'ebs';
                    $snapshotConfig->id = $event->SnapshotInfo['snapshotId'];

                    $event->SnapshotInfo['snapshotConfig'] = $snapshotConfig;
                }
            }

            if ($event->SnapshotInfo['logFile'])
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_LOG_FILE, $event->SnapshotInfo['logFile'], DBFarmRole::TYPE_LCL);

            if ($event->SnapshotInfo['logPos'])
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_LOG_POS, $event->SnapshotInfo['logPos'], DBFarmRole::TYPE_LCL);


            try {
                $storageSnapshot = Scalr_Storage_Snapshot::init();
                $storageSnapshot->loadBy(array(
                    'id'			=> $event->SnapshotInfo['snapshotConfig']->id,
                    'client_id'		=> $event->DBServer->clientId,
                    'farm_id'		=> $event->DBServer->farmId,
                    'farm_roleid'	=> $event->DBServer->farmRoleId,
                    'env_id'		=> $event->DBServer->envId,
                    'name'			=> sprintf(_("MySQL data bundle #%s"), $event->SnapshotInfo['snapshotConfig']->id),
                    'type'			=> $DBFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_DATA_STORAGE_ENGINE),
                    'platform'		=> $event->DBServer->platform,
                    'description'	=> sprintf(_("MySQL data bundle created on Farm '%s' -> Role '%s'"),
                        $DBFarmRole->GetFarmObject()->Name,
                        $DBFarmRole->GetRoleObject()->name
                    ),
                    'ismysql'		=> true,
                    'service'		=> ROLE_BEHAVIORS::MYSQL
                ));

                $storageSnapshot->setConfig($event->SnapshotInfo['snapshotConfig']);

                $storageSnapshot->save(true);

                $DBFarmRole->SetSetting(
                    DBFarmRole::SETTING_MYSQL_SCALR_SNAPSHOT_ID,
                    $storageSnapshot->id,
                    DBFarmRole::TYPE_LCL
                );
            }
            catch(Exception $e) {
                $this->Logger->fatal("Cannot save storage snapshot: {$e->getMessage()}");
            }
        }
    }

    /**
     * Update database when 'mysqlBckFail' event recieved from instance
     *
     * @param MysqlBackupFailEvent $event
     */
    public function OnMysqlBackupFail(MysqlBackupFailEvent $event)
    {
        try
        {
            $DBFarmRole = $event->DBServer->GetFarmRoleObject();
        }
        catch(Exception $e) {
            return;
        }

        if ($event->Operation == MYSQL_BACKUP_TYPE::DUMP)
        {
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_IS_BCP_RUNNING, 0, DBFarmRole::TYPE_LCL);
        }
        elseif ($event->Operation == MYSQL_BACKUP_TYPE::BUNDLE)
        {
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_IS_BUNDLE_RUNNING, 0, DBFarmRole::TYPE_LCL);
        }
    }

    /**
     * Update database when replication was broken on slave
     *
     * @param MySQLReplicationFailEvent $event
     */
    public function OnMySQLReplicationFail(MySQLReplicationFailEvent $event)
    {
        $event->DBServer->SetProperty(SERVER_PROPERTIES::DB_MYSQL_REPLICATION_STATUS, 0);
    }

    /**
     * Update database when replication was recovered on slave
     *
     * @param MySQLReplicationRecoveredEvent $event
     */
    public function OnMySQLReplicationRecovered(MySQLReplicationRecoveredEvent $event)
    {
        $event->DBServer->SetProperty(SERVER_PROPERTIES::DB_MYSQL_REPLICATION_STATUS, 1);
    }

    /**
     * Update database when 'newMysqlMaster' event recieved from instance
     *
     * @param NewMysqlMasterUpEvent $event
     * @deprecated
     */
    public function OnNewMysqlMasterUp(NewMysqlMasterUpEvent $event)
    {
        if ($event->OldMasterDBServer instanceof DBServer)
            $event->OldMasterDBServer->SetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER, 0);

        $event->DBServer->GetFarmRoleObject()->SetSetting(DBFarmRole::SETTING_MYSQL_LAST_BUNDLE_TS, time(), DBFarmRole::TYPE_LCL);

        $event->DBServer->SetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER, 1);
    }

    public function OnNewDbMsrMasterUp(NewDbMsrMasterUpEvent $event)
    {
        $event->DBServer->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::DATA_BUNDLE_LAST_TS, time(), DBFarmRole::TYPE_LCL);
    }

    /**
     * Update database when 'hostInit' event recieved from instance
     *
     * @param HostInitEvent $event
     *
     */
    public function OnHostInit(HostInitEvent $event)
    {
        $event->DBServer->SetProperty("tmp.status", $event->DBServer->status);


        $event->DBServer->localIp = $event->InternalIP;
        $event->DBServer->remoteIp = $event->ExternalIP;
        $event->DBServer->status = SERVER_STATUS::INIT;
        $event->DBServer->Save();

        $event->DBServer->SetProperty("tmp.status.processed", $event->DBServer->status);

        $this->DB->Execute("DELETE FROM server_operations WHERE server_id=?", array($event->DBServer->serverId));
        $event->DBServer->SetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED, false);

        try {
            $key = Scalr_Model::init(Scalr_Model::SSH_KEY)->loadGlobalByFarmId(
                $event->DBServer->farmId,
                $event->DBServer->GetFarmRoleObject()->CloudLocation,
                $event->DBServer->platform
            );

            if ($key && !$key->getPublic())
            {
                $key->setPublic($event->PublicKey);
                $key->save();
            }
        }
        catch(Exception $e) { }
    }

    /**
     * Update database when 'newAMI' event recieved from instance
     *
     * @param RebundleCompleteEvent $event
     *
     */
    public function OnRebundleComplete(RebundleCompleteEvent $event)
    {
        try {
            $BundleTask = BundleTask::LoadById($event->BundleTaskID);

            $BundleTask->osFamily = $event->MetaData['dist']->distributor;
            $BundleTask->osName = $event->MetaData['dist']->codename;
            $BundleTask->osVersion = $event->MetaData['dist']->release;

            $BundleTask->Save();
        }
        catch (Exception $e)
        {
            Logger::getLogger(__CLASS__)->fatal("Rebundle complete event without bundle task.");
            return;
        }

        if ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS)
            $BundleTask->SnapshotCreationComplete($event->SnapshotID, $event->MetaData);
    }

    /**
     * Called when scalr recived notify about rebundle failure from instance
     *
     * @param RebundleFailedEvent $event
     */
    public function OnRebundleFailed(RebundleFailedEvent $event)
    {
        try {
            $BundleTask = BundleTask::LoadById($event->BundleTaskID);
        } catch (Exception $e) {
            Logger::getLogger(__CLASS__)->fatal("Rebundle complete event without bundle task.");
            return;
        }

        $msg = 'Received RebundleFailed event from server';
        if ($event->LastErrorMessage) {
            $msg .= ". Reason: {$event->LastErrorMessage}";
        }

        $BundleTask->SnapshotCreationFailed($msg);
    }

    /**
     * Farm launched
     *
     * @param FarmLaunchedEvent $event
     */
    public function OnFarmLaunched(FarmLaunchedEvent $event)
    {
        $DBFarm = DBFarm::LoadByID($this->FarmID);

        // TODO: Refactoting -> Move to DBFarm class
        $this->DB->Execute("UPDATE farms SET status=?, dtlaunched=NOW() WHERE id=?",
            array(FARM_STATUS::RUNNING, $this->FarmID)
        );

        $governance = new Scalr_Governance($DBFarm->EnvID);
        if ($governance->isEnabled(Scalr_Governance::GENERAL_LEASE) && $DBFarm->GetSetting(DBFarm::SETTING_LEASE_STATUS)) {
            $dt = new DateTime();
            $dt->add(new DateInterval('P' . intval($governance->getValue(Scalr_Governance::GENERAL_LEASE, 'defaultLifePeriod')) . 'D'));
            $DBFarm->SetSetting(DBFarm::SETTING_LEASE_EXTEND_CNT, 0);
            $DBFarm->SetSetting(DBFarm::SETTING_LEASE_TERMINATE_DATE, $dt->format('Y-m-d H:i:s'));
            $DBFarm->SetSetting(DBFarm::SETTING_LEASE_NOTIFICATION_SEND, '');
        }

        $roles = $DBFarm->GetFarmRoles();
        foreach($roles as $dbFarmRole) {
            if ($dbFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_ENABLED) && !$DBFarm->GetSetting(DBFarm::SETTING_EC2_VPC_ID)) {
                $scalingManager = new Scalr_Scaling_Manager($dbFarmRole);
                $scalingDecision = $scalingManager->makeScalingDecition();
                if ($scalingDecision == Scalr_Scaling_Decision::UPSCALE) {
                    $ServerCreateInfo = new ServerCreateInfo($dbFarmRole->Platform, $dbFarmRole);
                    try {
                        $DBServer = Scalr::LaunchServer(
                            $ServerCreateInfo, null, true, "Farm launched",
                            (isset($event->userId) ? $event->userId : null)
                        );

                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_UPSCALE_DATETIME, time(), DBFarmRole::TYPE_LCL);

                        Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($DBFarm->ID, sprintf("Farm %s, role %s scaling up. Starting new instance. ServerID = %s.",
                            $DBFarm->Name,
                            $dbFarmRole->GetRoleObject()->name,
                            $DBServer->serverId
                        )));
                    } catch (Exception $e) {
                        Logger::getLogger(LOG_CATEGORY::SCALING)->error($e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Farm terminated
     *
     * @param FarmTerminatedEvent $event
     */
    public function OnFarmTerminated(FarmTerminatedEvent $event)
    {
        $dbFarm = DBFarm::LoadByID($this->FarmID);
        $dbFarm->Status = FARM_STATUS::TERMINATED;
        $dbFarm->TermOnSyncFail = $event->TermOnSyncFail;
        $dbFarm->save();

        $dbFarm->SetSetting(DBFarm::SETTING_LEASE_NOTIFICATION_SEND, '');

        $servers = $dbFarm->GetServersByFilter(array(), array());

        if (count($servers) == 0)
            return;

        //TERMINATE RUNNING INSTANCES
        foreach ($servers as $dbServer) {
            if ($this->DB->GetOne("
                    SELECT id
                    FROM bundle_tasks
                    WHERE server_id=? AND status NOT IN ('success','failed')
                    LIMIT 1
            ", array(
                $dbServer->serverId
            ))) {
                continue;
            }

            try {
                if (!in_array($dbServer->status, array(
                    SERVER_STATUS::PENDING_TERMINATE,
                    SERVER_STATUS::TERMINATED,
                    SERVER_STATUS::TROUBLESHOOTING
                ))) {
                    $dbServer->terminate('FARM_TERMINATED', true, (!empty($event->userId) ? $event->userId : null));
                }
            } catch (Exception $e) {
                $this->Logger->error($e->getMessage());
            }
        }
    }

    /**
     * Called when instance configured and upped
     *
     * @param HostUpEvent $event
     */
    public function OnHostUp(HostUpEvent $event)
    {
        $event->DBServer->status = SERVER_STATUS::RUNNING;

        $this->DB->Execute("UPDATE servers_history SET scu_collecting = '1' WHERE server_id = ?", array($event->DBServer->serverId));

        if ($event->ReplUserPass) {
            $event->DBServer->GetFarmRoleObject()->SetSetting(DBFarmRole::SETTING_MYSQL_STAT_PASSWORD, $event->ReplUserPass, DBFarmRole::TYPE_LCL);
        }

        if (defined("SCALR_SERVER_TZ")) {
            $tz = date_default_timezone_get();
            date_default_timezone_set(SCALR_SERVER_TZ);
        }

        $event->DBServer->SetProperty(SERVER_PROPERTIES::INITIALIZED_TIME, time());

        if (!empty($tz)) {
            date_default_timezone_set($tz);
        }

        $event->DBServer->Save();

        try {
            // If we need replace old instance to new one terminate old one.
            if ($event->DBServer->replaceServerID) {
                Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($this->FarmID, "Host UP. Terminating old server: {$event->DBServer->replaceServerID})."));

                try {
                    $oldDBServer = DBServer::LoadByID($event->DBServer->replaceServerID);
                } catch (Exception $e) {
                }

                Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($this->FarmID, "OLD Server found: {$oldDBServer->serverId})."));

                if ($oldDBServer) {
                    $oldDBServer->terminate('REPLACE_SERVER_FROM_SNAPSHOT');
                }
            }
        } catch (Exception $e) {
            $this->Logger->fatal($e->getMessage());
        }
    }

    /**
     * Called when reboot complete
     *
     * @param RebootCompleteEvent $event
     */
    public function OnRebootComplete(RebootCompleteEvent $event)
    {
        $event->DBServer->SetProperty(SERVER_PROPERTIES::REBOOTING, 0);

        try {
            $DBFarmRole = $event->DBServer->GetFarmRoleObject();

            if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_BCP_SERVER_ID) == $event->DBServer->serverId)
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_IS_BCP_RUNNING, 0, DBFarmRole::TYPE_LCL);

            if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_BUNDLE_SERVER_ID) == $event->DBServer->serverId)
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_IS_BUNDLE_RUNNING, 0, DBFarmRole::TYPE_LCL);

            if ($DBFarmRole->GetSetting(Scalr_Db_Msr::DATA_BACKUP_SERVER_ID) == $event->DBServer->serverId)
                $DBFarmRole->SetSetting(Scalr_Db_Msr::DATA_BACKUP_IS_RUNNING, 0, DBFarmRole::TYPE_LCL);

            if ($DBFarmRole->GetSetting(Scalr_Db_Msr::DATA_BUNDLE_SERVER_ID) == $event->DBServer->serverId)
                $DBFarmRole->SetSetting(Scalr_Db_Msr::DATA_BUNDLE_IS_RUNNING, 0, DBFarmRole::TYPE_LCL);
        }
        catch(Exception $e){}
    }

    /**
     * Called when instance receive reboot command
     *
     * @param RebootBeginEvent $event
     */
    public function OnRebootBegin(RebootBeginEvent $event)
    {
        $event->DBServer->SetProperty(SERVER_PROPERTIES::REBOOTING, 1);
    }

    /**
     * Called when instance going down
     *
     * @param HostDownEvent $event
     */
    public function OnHostDown(HostDownEvent $event)
    {
        if ($event->DBServer->IsRebooting())
            return;

//         if ($event->DBServer->status != SERVER_STATUS::TROUBLESHOOTING) {
//             $event->DBServer->status = SERVER_STATUS::TERMINATED;
//             $event->DBServer->dateShutdownScheduled = date("Y-m-d H:i:s");
//         }

        $this->DB->Execute("UPDATE servers_history SET scu_collecting = '0' WHERE server_id = ?", array($event->DBServer->serverId));

        //TODO: move to alerts;
        $this->DB->Execute("UPDATE server_alerts SET status='resolved' WHERE server_id = ?", array($event->DBServer->serverId));

        try {
            $DBFarmRole = $event->DBServer->GetFarmRoleObject();

            if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_BCP_SERVER_ID) == $event->DBServer->serverId)
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_IS_BCP_RUNNING, 0, DBFarmRole::TYPE_LCL);

            if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_BUNDLE_SERVER_ID) == $event->DBServer->serverId)
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_IS_BUNDLE_RUNNING, 0, DBFarmRole::TYPE_LCL);

            if ($DBFarmRole->GetSetting(Scalr_Db_Msr::DATA_BACKUP_SERVER_ID) == $event->DBServer->serverId)
                $DBFarmRole->SetSetting(Scalr_Db_Msr::DATA_BACKUP_IS_RUNNING, 0, DBFarmRole::TYPE_LCL);

            if ($DBFarmRole->GetSetting(Scalr_Db_Msr::DATA_BUNDLE_SERVER_ID) == $event->DBServer->serverId)
                $DBFarmRole->SetSetting(Scalr_Db_Msr::DATA_BUNDLE_IS_RUNNING, 0, DBFarmRole::TYPE_LCL);
        }
        catch(Exception $e){}

        if ($event->replacementDBServer)
        {
            $event->replacementDBServer->replaceServerID = null;
            $event->replacementDBServer->Save();
        }

        //Check active bundle task:
        $bundle_task_id = $this->DB->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND status IN (?,?) LIMIT 1", array(
            $event->DBServer->serverId,
            SERVER_SNAPSHOT_CREATION_STATUS::PENDING,
            SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS
        ));
        if ($bundle_task_id) {
            $BundleTask = BundleTask::LoadById($bundle_task_id);
            $BundleTask->SnapshotCreationFailed("Server was terminated before image was created.");
        }
    }

    public function OnIPAddressChanged(IPAddressChangedEvent $event)
    {
        Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($this->FarmID, "IP changed for server {$event->DBServer->serverId}. New IP address: {$event->NewIPAddress} New local IP address: {$event->NewLocalIPAddress}"));

        if ($event->NewIPAddress)
            $event->DBServer->remoteIp = $event->NewIPAddress;

        if ($event->NewLocalIPAddress)
            $event->DBServer->localIp = $event->NewLocalIPAddress;

        $event->DBServer->Save();
    }
}
