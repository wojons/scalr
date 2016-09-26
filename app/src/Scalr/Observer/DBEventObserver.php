<?php
namespace Scalr\Observer;

use Scalr\Server\Alerts;
use Scalr\Model\Entity\SshKey;
use Scalr\Model\Entity;
use Exception;
use Scalr_Db_Backup;
use SERVER_PROPERTIES;
use SERVER_PLATFORMS;
use FarmTerminatedEvent;
use DBFarm;
use FARM_STATUS;
use SERVER_STATUS;
use CheckFailedEvent;
use CheckRecoveredEvent;
use MysqlBackupCompleteEvent;
use MysqlBackupFailEvent;
use NewMysqlMasterUpEvent;
use MYSQL_BACKUP_TYPE;
use Scalr_Storage_Snapshot;
use ROLE_BEHAVIORS;
use SERVER_SNAPSHOT_CREATION_STATUS;
use NewDbMsrMasterUpEvent;
use HostInitEvent;
use RebundleCompleteEvent;
use RebundleFailedEvent;
use FarmLaunchedEvent;
use Scalr_Governance;
use DateTime;
use DateInterval;
use Scalr_Scaling_Decision;
use ServerCreateInfo;
use Scalr_Scaling_Manager;
use ResumeCompleteEvent;
use HostUpEvent;
use RebootCompleteEvent;
use Scalr_Db_Msr;
use IPAddressChangedEvent;
use LOG_CATEGORY;
use HostDownEvent;
use RebootBeginEvent;
use DBServer;
use FarmLogMessage;
use BundleTask;

class DBEventObserver extends AbstractEventObserver
{
    /**
     * Observer name
     *
     * @var string
     */
    public $ObserverName = 'DB';

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnCheckFailed()
     */
    public function OnCheckFailed(CheckFailedEvent $event)
    {
        $serverAlerts = new Alerts($event->DBServer);
        $hasActiveAlert = $serverAlerts->hasActiveAlert($event->check);
        if (!$hasActiveAlert) {
            $serverAlerts->createAlert($event->check, $event->details);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnCheckRecovered()
     */
    public function OnCheckRecovered(CheckRecoveredEvent $event)
    {
        $serverAlerts = new Alerts($event->DBServer);
        $hasActiveAlert = $serverAlerts->hasActiveAlert($event->check);
        if ($hasActiveAlert) {
            $serverAlerts->solveAlert($event->check);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnMysqlBackupComplete()
     */
    public function OnMysqlBackupComplete(MysqlBackupCompleteEvent $event)
    {
        try {
            $DBFarmRole = $event->DBServer->GetFarmRoleObject();
            $farm_roleid = $DBFarmRole->ID;
        } catch (Exception $e) {
            return;
        }

        if ($event->Operation == MYSQL_BACKUP_TYPE::DUMP) {
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_LAST_BCP_TS, time(), Entity\FarmRoleSetting::TYPE_LCL);
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BCP_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);

            switch ($event->DBServer->platform) {
                case SERVER_PLATFORMS::EC2:
                    $provider = 's3';
                    break;

                default:
                    $provider = 'unknown';
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
        } elseif ($event->Operation == MYSQL_BACKUP_TYPE::BUNDLE) {
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_LAST_BUNDLE_TS, time(), Entity\FarmRoleSetting::TYPE_LCL);
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BUNDLE_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);

            if (!is_array($event->SnapshotInfo)) {
                $event->SnapshotInfo = array('snapshotId' => $event->SnapshotInfo);
            }

            if ($event->SnapshotInfo['snapshotId']) {
                if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_DATA_STORAGE_ENGINE) == \MYSQL_STORAGE_ENGINE::EBS) {
                    $this->DB->Execute("
                        INSERT INTO ebs_snaps_info
                        SET snapid = ?,
                            comment = ?,
                            dtcreated = NOW(),
                            region = ?,
                            ebs_array_snapid = '0',
                            is_autoebs_master_snap = '1',
                            farm_roleid = ?
                    ", [
                        $event->SnapshotInfo['snapshotId'],
                        _('MySQL Master volume snapshot'),
                        $event->DBServer->GetProperty(\EC2_SERVER_PROPERTIES::REGION),
                        $DBFarmRole->ID
                    ]);

                    // Scalarizr stuff
                    $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_SNAPSHOT_ID, $event->SnapshotInfo['snapshotId'], Entity\FarmRoleSetting::TYPE_LCL);

                    $snapshotConfig = new \stdClass();
                    $snapshotConfig->type = 'ebs';
                    $snapshotConfig->id = $event->SnapshotInfo['snapshotId'];

                    $event->SnapshotInfo['snapshotConfig'] = $snapshotConfig;
                }
            }

            if ($event->SnapshotInfo['logFile']) {
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_LOG_FILE, $event->SnapshotInfo['logFile'], Entity\FarmRoleSetting::TYPE_LCL);
            }

            if ($event->SnapshotInfo['logPos']) {
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_LOG_POS, $event->SnapshotInfo['logPos'], Entity\FarmRoleSetting::TYPE_LCL);
            }

            try {
                $storageSnapshot = Scalr_Storage_Snapshot::init();
                $storageSnapshot->loadBy(array(
                    'id'			=> $event->SnapshotInfo['snapshotConfig']->id,
                    'client_id'		=> $event->DBServer->clientId,
                    'farm_id'		=> $event->DBServer->farmId,
                    'farm_roleid'	=> $event->DBServer->farmRoleId,
                    'env_id'		=> $event->DBServer->envId,
                    'name'			=> sprintf(_("MySQL data bundle #%s"), $event->SnapshotInfo['snapshotConfig']->id),
                    'type'			=> $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_DATA_STORAGE_ENGINE),
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
                    Entity\FarmRoleSetting::MYSQL_SCALR_SNAPSHOT_ID,
                    $storageSnapshot->id,
                    Entity\FarmRoleSetting::TYPE_LCL
                );
            } catch (Exception $e) {
                $this->Logger->fatal("Cannot save storage snapshot: {$e->getMessage()}");
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnMysqlBackupFail()
     */
    public function OnMysqlBackupFail(MysqlBackupFailEvent $event)
    {
        try {
            $DBFarmRole = $event->DBServer->GetFarmRoleObject();
        } catch(Exception $e) {
            return;
        }

        if ($event->Operation == MYSQL_BACKUP_TYPE::DUMP) {
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BCP_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
        } elseif ($event->Operation == MYSQL_BACKUP_TYPE::BUNDLE) {
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BUNDLE_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnNewMysqlMasterUp()
     */
    public function OnNewMysqlMasterUp(NewMysqlMasterUpEvent $event)
    {
        if ($event->OldMasterDBServer instanceof DBServer) {
            $event->OldMasterDBServer->SetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER, 0);
        }

        $event->DBServer->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::MYSQL_LAST_BUNDLE_TS, time(), Entity\FarmRoleSetting::TYPE_LCL);

        $event->DBServer->SetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER, 1);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnNewDbMsrMasterUp()
     */
    public function OnNewDbMsrMasterUp(NewDbMsrMasterUpEvent $event)
    {
        $event->DBServer->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::DATA_BUNDLE_LAST_TS, time(), Entity\FarmRoleSetting::TYPE_LCL);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostInit()
     */
    public function OnHostInit(HostInitEvent $event)
    {
        $event->DBServer->update([
            'localIp'  => $event->InternalIP,
            'remoteIp' => $event->ExternalIP,
            'status'   => SERVER_STATUS::INIT
        ]);

        $event->DBServer->SetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED, false);

        try {
            $key = (new SshKey())->loadGlobalByFarmId(
                $event->DBServer->envId,
                $event->DBServer->platform,
                $event->DBServer->GetFarmRoleObject()->CloudLocation,
                $event->DBServer->farmId
            );

            if ($key && !$key->publicKey) {
                $key->publicKey = $event->PublicKey;
                $key->save();
            }
        } catch (Exception $e) {
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnRebundleComplete()
     */
    public function OnRebundleComplete(RebundleCompleteEvent $event)
    {
        try {
            $BundleTask = BundleTask::LoadById($event->BundleTaskID);

            $BundleTask->osFamily = $event->MetaData['dist']->distributor;
            $BundleTask->osName = $event->MetaData['dist']->codename;
            $BundleTask->osVersion = $event->MetaData['dist']->release;

            $BundleTask->Save();
        } catch (Exception $e) {
            \Scalr::getContainer()->logger(__CLASS__)->fatal("Rebundle complete event without bundle task.");
            return;
        }

        if ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS) {
            $BundleTask->SnapshotCreationComplete($event->SnapshotID, $event->MetaData);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnRebundleFailed()
     */
    public function OnRebundleFailed(RebundleFailedEvent $event)
    {
        try {
            $BundleTask = BundleTask::LoadById($event->BundleTaskID);
        } catch (Exception $e) {
            \Scalr::getContainer()->logger(__CLASS__)->fatal("Rebundle complete event without bundle task.");

            return;
        }

        $msg = 'Received RebundleFailed event from server';

        if ($event->LastErrorMessage) {
            $msg .= ". Reason: {$event->LastErrorMessage}";
        }

        $BundleTask->SnapshotCreationFailed($msg);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnFarmLaunched()
     */
    public function OnFarmLaunched(FarmLaunchedEvent $event)
    {
        $DBFarm = DBFarm::LoadByID($this->FarmID);

        $this->DB->Execute("UPDATE farms SET status = ?, dtlaunched = NOW() WHERE id = ? LIMIT 1", [
            FARM_STATUS::RUNNING, $this->FarmID
        ]);

        $this->getContainer()->auditlogger->log('farm.launch', $DBFarm, $event->auditLogExtra);

        $governance = new Scalr_Governance($DBFarm->EnvID);

        if ($governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE) &&
            $DBFarm->GetSetting(Entity\FarmSetting::LEASE_STATUS)) {
            $dt = new DateTime();

            $dt->add(new DateInterval('P' . intval($governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE, 'defaultLifePeriod')) . 'D'));

            $DBFarm->SetSetting(Entity\FarmSetting::LEASE_EXTEND_CNT, 0);
            $DBFarm->SetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE, $dt->format('Y-m-d H:i:s'));
            $DBFarm->SetSetting(Entity\FarmSetting::LEASE_NOTIFICATION_SEND, '');
        }

        $roles = $DBFarm->GetFarmRoles();

        foreach ($roles as $dbFarmRole) {
            if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_ENABLED) && !$DBFarm->GetSetting(Entity\FarmSetting::EC2_VPC_ID)) {
                $scalingManager = new Scalr_Scaling_Manager($dbFarmRole);

                $scalingDecision = $scalingManager->makeScalingDecision();

                if ($scalingDecision == Scalr_Scaling_Decision::UPSCALE) {
                    $ServerCreateInfo = new ServerCreateInfo($dbFarmRole->Platform, $dbFarmRole);

                    try {
                        $DBServer = \Scalr::LaunchServer(
                            $ServerCreateInfo, null, true, DBServer::LAUNCH_REASON_FARM_LAUNCHED,
                            isset($event->userId) ? $event->userId : null
                        );

                        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_UPSCALE_DATETIME, time(), Entity\FarmRoleSetting::TYPE_LCL);

                        $role = $dbFarmRole->GetRoleObject();
                        \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                            $DBFarm->ID,
                            sprintf("Farm %s, role %s scaling up. Starting new instance. ServerID = %s.",
                                !empty($DBFarm->Name) ? $DBFarm->Name : null,
                                !empty($role->name) ? $role->name : null,
                                !empty($DBServer->serverId) ? $DBServer->serverId : null
                            ),
                            !empty($DBServer->serverId) ? $DBServer->serverId : null,
                            !empty($DBServer->envId) ? $DBServer->envId : null,
                            !empty($DBServer->farmRoleId) ? $DBServer->farmRoleId : null
                        ));
                    } catch (Exception $e) {
                        \Scalr::getContainer()->logger(LOG_CATEGORY::SCALING)->error($e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnFarmTerminated()
     */
    public function OnFarmTerminated(FarmTerminatedEvent $event)
    {
        $dbFarm = DBFarm::LoadByID($this->FarmID);

        //Tracks Audit Log farm.terminate event
        \Scalr::getContainer()->auditlogger->log('farm.terminate', $dbFarm, $event->auditLogExtra);

        $dbFarm->Status = FARM_STATUS::TERMINATED;
        $dbFarm->TermOnSyncFail = $event->TermOnSyncFail;
        $dbFarm->save();

        $dbFarm->SetSetting(Entity\FarmSetting::LEASE_NOTIFICATION_SEND, '');
        $dbFarm->SetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE, '');

        $servers = $dbFarm->GetServersByFilter([], []);

        if (count($servers) == 0) {
            return;
        }

        //TERMINATE RUNNING INSTANCES
        foreach ($servers as $dbServer) {
            /* @var $dbServer DBServer */
            if ($this->DB->GetOne("
                SELECT id
                FROM bundle_tasks
                WHERE server_id=? AND status NOT IN ('success','failed')
                LIMIT 1
            ", [$dbServer->serverId])) {
                continue;
            }

            try {
                if (!in_array($dbServer->status, [SERVER_STATUS::PENDING_TERMINATE, SERVER_STATUS::TERMINATED])) {
                    $dbServer->terminate(DBServer::TERMINATE_REASON_FARM_TERMINATED, true, (!empty($event->userId) ? $event->userId : null));
                }
            } catch (Exception $e) {
                $this->Logger->error($e->getMessage());
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnResumeComplete()
     */
    public function OnResumeComplete(ResumeCompleteEvent $event)
    {
        $event->DBServer->updateStatus(SERVER_STATUS::RUNNING);
        $event->DBServer->SetProperties([
            SERVER_PROPERTIES::REBOOTING => 0,
            SERVER_PROPERTIES::SYSTEM_FORCE_RESUME_INITIATED   => null
        ]);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostUp()
     */
    public function OnHostUp(HostUpEvent $event)
    {
        $this->DB->Execute("UPDATE servers_history SET scu_collecting = '1' WHERE server_id = ?", [$event->DBServer->serverId]);

        if ($event->ReplUserPass) {
            $event->DBServer->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::MYSQL_STAT_PASSWORD, $event->ReplUserPass, Entity\FarmRoleSetting::TYPE_LCL);
        }

        $event->DBServer->update(['dateInitialized' => date("Y-m-d H:i:s")]);

        $event->DBServer->updateStatus(SERVER_STATUS::RUNNING);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnRebootComplete()
     */
    public function OnRebootComplete(RebootCompleteEvent $event)
    {
        $event->DBServer->SetProperty(SERVER_PROPERTIES::REBOOTING, 0);

        try {
            $DBFarmRole = $event->DBServer->GetFarmRoleObject();

            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BCP_SERVER_ID) == $event->DBServer->serverId) {
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BCP_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }

            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_SERVER_ID) == $event->DBServer->serverId) {
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BUNDLE_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }

            if ($DBFarmRole->GetSetting(Scalr_Db_Msr::DATA_BACKUP_SERVER_ID) == $event->DBServer->serverId) {
                $DBFarmRole->SetSetting(Scalr_Db_Msr::DATA_BACKUP_IS_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }

            if ($DBFarmRole->GetSetting(Scalr_Db_Msr::DATA_BUNDLE_SERVER_ID) == $event->DBServer->serverId) {
                $DBFarmRole->SetSetting(Scalr_Db_Msr::DATA_BUNDLE_IS_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }
        } catch (Exception $e) {
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnRebootBegin()
     */
    public function OnRebootBegin(RebootBeginEvent $event)
    {
        $event->DBServer->SetProperty(SERVER_PROPERTIES::REBOOTING, 1);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostDown()
     */
    public function OnHostDown(HostDownEvent $event)
    {
        if ($event->isSuspended) {
            $event->DBServer->SetProperties([
                SERVER_PROPERTIES::REBOOTING  => 0
            ]);

            $event->DBServer->update([
                'status'   => SERVER_STATUS::SUSPENDED,
                'remoteIp' => "",
                'localIp'  => "",
            ]);
        }

        if ($event->DBServer->IsRebooting()) {
            return;
        }

        $this->DB->Execute("UPDATE servers_history SET scu_collecting = '0' WHERE server_id = ?", array($event->DBServer->serverId));

        //TODO: move to alerts;
        $this->DB->Execute("UPDATE server_alerts SET status='resolved' WHERE server_id = ?", array($event->DBServer->serverId));

        try {
            $DBFarmRole = $event->DBServer->GetFarmRoleObject();

            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BCP_SERVER_ID) == $event->DBServer->serverId) {
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BCP_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }

            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_BUNDLE_SERVER_ID) == $event->DBServer->serverId) {
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_IS_BUNDLE_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }

            if ($DBFarmRole->GetSetting(Scalr_Db_Msr::DATA_BACKUP_SERVER_ID) == $event->DBServer->serverId) {
                $DBFarmRole->SetSetting(Scalr_Db_Msr::DATA_BACKUP_IS_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }

            if ($DBFarmRole->GetSetting(Scalr_Db_Msr::DATA_BUNDLE_SERVER_ID) == $event->DBServer->serverId) {
                $DBFarmRole->SetSetting(Scalr_Db_Msr::DATA_BUNDLE_IS_RUNNING, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }
        } catch (Exception $e) {
        }

        //Check active bundle task:
        $bundle_task_id = $this->DB->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND status IN (?,?) LIMIT 1", [
            $event->DBServer->serverId,
            SERVER_SNAPSHOT_CREATION_STATUS::PENDING,
            SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS
        ]);

        if ($bundle_task_id && $event->DBServer->platform != SERVER_PLATFORMS::GCE) {
            $BundleTask = BundleTask::LoadById($bundle_task_id);
            $BundleTask->SnapshotCreationFailed("Server was terminated before image was created.");
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnIPAddressChanged()
     */
    public function OnIPAddressChanged(IPAddressChangedEvent $event)
    {
        \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
            $this->FarmID,
            sprintf("IP changed for server %s. New public IP: %s New private IP: %s",
                !empty($event->DBServer->serverId) ? $event->DBServer->serverId : null,
                !empty($event->NewIPAddress) ? $event->NewIPAddress : null,
                !empty($event->NewLocalIPAddress) ? $event->NewLocalIPAddress : null
            ),
            !empty($event->DBServer->serverId) ? $event->DBServer->serverId : null,
            !empty($event->DBServer->envId) ? $event->DBServer->envId : null,
            !empty($event->DBServer->farmRoleId) ? $event->DBServer->farmRoleId : null
        ));

        if ($event->NewIPAddress !== null) {
            $event->DBServer->remoteIp = $event->NewIPAddress;
        }

        if ($event->NewLocalIPAddress !== null) {
            $event->DBServer->localIp = $event->NewLocalIPAddress;
        }

        $event->DBServer->Save();
    }
}
