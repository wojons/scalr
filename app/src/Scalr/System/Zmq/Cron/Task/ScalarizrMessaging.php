<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception, stdClass;
use \ADODB_Exception;
use \DBServer;
use \DBFarm;
use \DBFarmRole;
use \BundleTask;
use \SERVER_PROPERTIES;
use \EC2_SERVER_PROPERTIES;
use \CLOUDSTACK_SERVER_PROPERTIES;
use \OPENSTACK_SERVER_PROPERTIES;
use \RACKSPACE_SERVER_PROPERTIES;
use \EUCA_SERVER_PROPERTIES;
use \GCE_SERVER_PROPERTIES;
use \SERVER_PLATFORMS;
use \SERVER_STATUS;
use \SERVER_SNAPSHOT_CREATION_STATUS;
use \SERVER_SNAPSHOT_CREATION_TYPE;
use \AMAZON_EBS_STATE;
use \EC2_EBS_MOUNT_STATUS;
use \MESSAGE_STATUS;
use \MYSQL_STORAGE_ENGINE;
use \MYSQL_BACKUP_TYPE;
use \SERVER_REPLACEMENT_TYPE;
use \SZR_KEY_TYPE;
use \FARM_STATUS;
use \ROLE_TAGS;
use \ROLE_BEHAVIORS;
use \Logger;
use \FarmLogMessage;
use \ServerSnapshotCreateInfo;
use \Scalr_Db_Msr;
use \Scalr_Dm_DeploymentTask;
use \Scalr_Role_Behavior;
use \Scalr_Role_Behavior_RabbitMQ;
use \Scalr_Model;
use \Scalr_Storage_Volume;
use \Scalr_Storage_Snapshot;
use \Scalr_Service_Cloud_Rackspace;
use \Scalr_Net_Scalarizr_UpdateClient;
use \BeforeHostUpEvent;
use \CustomEvent;
use \EBSVolumeAttachedEvent;
use \EBSVolumeMountedEvent;
use \HostDownEvent;
use \HostInitEvent;
use \HostInitFailedEvent;
use \HostUpEvent;
use \MysqlBackupCompleteEvent;
use \MysqlBackupFailEvent;
use \NewMysqlMasterUpEvent;
use \RebootCompleteEvent;
use \RebootBeginEvent;
use \RebundleCompleteEvent;
use \RebundleFailedEvent;
use \Scalr_Messaging_MsgMeta;
use \Scalr_Messaging_JsonSerializer;
use \Scalr_Messaging_XmlSerializer;
use \Scalr_Messaging_Msg_AmiScriptsMigrationResult;
use \Scalr_Messaging_Msg_BeforeHostUp;
use \Scalr_Messaging_Msg_BlockDeviceAttached;
use \Scalr_Messaging_Msg_BlockDeviceMounted;
use \Scalr_Messaging_Msg_DbMsr;
use \Scalr_Messaging_Msg_DeployResult;
use \Scalr_Messaging_Msg_FireEvent;
use \Scalr_Messaging_Msg_Hello;
use \Scalr_Messaging_Msg_HostInit;
use \Scalr_Messaging_Msg_HostUp;
use \Scalr_Messaging_Msg_HostUpdate;
use \Scalr_Messaging_Msg_HostDown;
use \Scalr_Messaging_Msg_InitFailed;
use \Scalr_Messaging_Msg_MongoDb;
use \Scalr_Messaging_Msg_Mysql_CreateDataBundleResult;
use \Scalr_Messaging_Msg_Mysql_CreateBackupResult;
use \Scalr_Messaging_Msg_Mysql_CreatePmaUserResult;
use \Scalr_Messaging_Msg_Mysql_PromoteToMasterResult;
use \Scalr_Messaging_Msg_OperationResult;
use \Scalr_Messaging_Msg_RabbitMq_SetupControlPanelResult;
use \Scalr_Messaging_Msg_RebootStart;
use \Scalr_Messaging_Msg_RebootFinish;
use \Scalr_Messaging_Msg_RebundleResult;
use \Scalr_Messaging_Msg_Win_HostDown;
use \Scalr_Messaging_Msg_Win_PrepareBundleResult;
use \Scalr_Messaging_Msg_UpdateControlPorts;
use Scalr\Modules\PlatformFactory;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr\Exception\ServerNotFoundException;
use Scalr\Service\Aws\Ec2\DataType\VolumeFilterNameType;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
use Scalr\Modules\Platforms\Cloudstack\Helpers\CloudstackHelper;
use Scalr\Modules\Platforms\Rackspace\RackspacePlatformModule;
use Scalr\Db\ConnectionPool;

/**
 * ServerTerminate
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (07.11.2014)
 */
class ScalarizrMessaging extends AbstractTask
{
    /**
     * @var \ADODB_mysqli
     */
    private $db;

    /**
     * @var \Scalr_Messaging_XmlSerializer
     */
    private $serializer;

    /**
     * @var \Scalr_Messaging_JsonSerializer
     */
    private $jsonSerializer;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->db = \Scalr::getDb();
        $this->serializer = new Scalr_Messaging_XmlSerializer();
        $this->jsonSerializer = new Scalr_Messaging_JsonSerializer();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        $config = $this->config();

        try {
            //Removing pending messages for terminated farms
            $this->db->Execute("
                DELETE m FROM `messages` m, `servers` s, `farms` f
                WHERE s.`server_id` = m.`server_id` AND f.`id` = s.`farm_id`
                AND m.`type`= ? AND m.`status` = ? AND f.`status` = ?
            ", ["in", MESSAGE_STATUS::PENDING, FARM_STATUS::TERMINATED]);

            //Removing pending messages for disappeared/terminated servers
            $this->db->Execute("
                DELETE m FROM `messages` m
                WHERE m.`type`= ? AND m.`status` = ?
                AND NOT EXISTS (SELECT 1 FROM `servers` s WHERE s.`server_id` = m.`server_id`)
            ", ["in", MESSAGE_STATUS::PENDING]);
        } catch (ADODB_Exception $e) {
            if ($e->getCode() == ConnectionPool::ER_LOCK_DEADLOCK) {
                $this->getLogger()->warn("DEADLOCK happened while removing messages of terminated farms");
            } else {
                throw $e;
            }
        }

        $types = [];
        if (!empty($config->replicate['type'])) {
            //The first type in the list has the most priority
            foreach (array_reverse(array_values($config->replicate['type'])) as $n => $messageName) {
                if (!preg_match('/^[\w\d-]+$/i', $messageName)){
                    // Invalid message name
                    continue;
                }
                $types[$n] = $messageName;
            }
        }

        if (!empty($types)) {
            //m_priority column will be non empty if there is at least one message of this type for current unuque server
            $stmt = ", MAX(FIND_IN_SET(m.`message_name`, '" . join(',', $types) . "')) `m_priority` ";
        } else {
            $stmt = ", 0 `m_priority` ";
        }

        //Worker must handle messages for an each server sequentially and synchronously in the same order as they come
        $rows = $this->db->Execute("
            SELECT m.`server_id`, s.`client_id` AS `account_id`
            " . $stmt . "
            FROM `messages` m
            INNER JOIN `servers` s ON s.`server_id` = m.`server_id`
            WHERE m.type = ?
            AND m.status = ?
            GROUP BY m.`server_id`
            ORDER BY `m_priority`
        ", ["in", MESSAGE_STATUS::PENDING]);

        while ($row = $rows->FetchRow()) {
            $t = new stdClass();
            $t->serverId = $row['server_id'];

            //Adjusts object with custom routing address.
            //It determines which of the workers pool should handle the task.
            //Handles priority of message names in the same order as it is provided in the config
            $t->address = $this->name
              . '.' . ($row['m_priority'] > 0 ? $types[$row['m_priority'] - 1] : 'all')
              . '.' . (!empty($config->replicate['account']) ? (in_array($row['account_id'], $config->replicate['account']) ? $row['account_id'] : 'all') : 'all');

            $queue->append($t);
        }

        if ($cnt = count($queue)) {
            $this->getLogger()->info(
                "%d server%s %s waiting for processing",
                $cnt, ($cnt > 1 ? 's' : ''), ($cnt > 1 ? 'are' : 'is')
            );
        }

        return $queue;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\AbstractTask::launchWorkers()
     */
    protected function launchWorkers($address = null)
    {
        $config = $this->config();

        //It launches different pools of workers according to replication schema defined in the config
        foreach (array_merge((!empty($config->replicate['type']) ? $config->replicate['type'] : []), ['all']) as $type) {
            if (!empty($config->replicate['account'])) {
                foreach ($config->replicate['account'] as $acc) {
                    parent::launchWorkers($this->name . '.' . $type . '.' . $acc);
                }
            }

            parent::launchWorkers($this->name . '.' . $type . '.all');
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        $serverId = $request->serverId;

        $logger = Logger::getLogger(__CLASS__);

        $this->log("INFO", "Processing messages for %s server", $serverId);

        try {
            $dbserver = DBServer::LoadByID($serverId);

            if ($dbserver->farmId) {
                if ($dbserver->GetFarmObject()->Status == FARM_STATUS::TERMINATED) {
                    throw new ServerNotFoundException("Farm related to this server has been terminated.");
                }
            }
        } catch (ServerNotFoundException $e) {
            //By some reason server does not exist
            $this->db->Execute("
                DELETE m FROM messages m
                WHERE m.server_id = ? AND m.`type` = ? AND m.`status` = ?
            ", [$serverId, "in", MESSAGE_STATUS::PENDING]);

            return false;
        }

        //Warming up static DI cache
        \Scalr::getContainer()->warmup();

        // Reconfigure observers
        \Scalr::ReconfigureObservers();

        $rs = $this->db->Execute("
            SELECT m.* FROM messages m
            WHERE m.server_id = ? AND m.type = ? AND m.status = ?
            ORDER BY m.dtadded ASC
        ", [$serverId, "in", MESSAGE_STATUS::PENDING]);

        while ($row = $rs->FetchRow()) {
            try {
                if ($row["message_format"] == 'xml') {
                    $message = $this->serializer->unserialize($row["message"]);
                } else {
                    $message = $this->jsonSerializer->unserialize($row["message"]);
                    $dbserver->SetProperty(SERVER_PROPERTIES::SZR_MESSAGE_FORMAT, 'json');
                }

                $message->messageIpAddress = $row['ipaddress'];
                $event = null;
                $startTime = microtime(true);

                // Update scalarizr package version
                if ($message->meta[Scalr_Messaging_MsgMeta::SZR_VERSION]) {
                    $dbserver->setScalarizrVersion($message->meta[Scalr_Messaging_MsgMeta::SZR_VERSION]);
                }

                if ($message->meta[Scalr_Messaging_MsgMeta::SZR_UPD_CLIENT_VERSION]) {
                    $dbserver->SetProperty(
                        SERVER_PROPERTIES::SZR_UPD_CLIENT_VERSION,
                        $message->meta[Scalr_Messaging_MsgMeta::SZR_UPD_CLIENT_VERSION]);
                }

                if ($dbserver->GetProperty(SERVER_PROPERTIES::SYSTEM_IGNORE_INBOUND_MESSAGES))
                    continue;

                if ($message instanceof \Scalr_Messaging_Msg)
                    $this->log('INFO', "Handling '%s' for '%s' server", $message->getName(), $serverId);

                try {
                    if ($message instanceof Scalr_Messaging_Msg_OperationResult) {
                        if ($message->status == 'ok' || $message->status == 'completed') {
                            if ($message->name == 'Grow MySQL/Percona data volume' || $message->name == 'mysql.grow-volume') {
                                $volumeConfig = $message->data ? $message->data : $message->result;
                                $oldVolumeId = $dbserver->GetFarmRoleObject()->GetSetting(Scalr_Db_Msr::VOLUME_ID);
                                $engine = $dbserver->GetFarmRoleObject()->GetSetting(Scalr_Db_Msr::DATA_STORAGE_ENGINE);
                                try {
                                    $storageVolume = Scalr_Storage_Volume::init();
                                    try {
                                        $storageVolume->loadById($volumeConfig->id);
                                        $storageVolume->setConfig($volumeConfig);
                                        $storageVolume->save();
                                    } catch (Exception $e) {
                                        if (strpos($e->getMessage(), 'not found')) {
                                            $storageVolume->loadBy(array(
                                                'id'           => $volumeConfig->id,
                                                'client_id'    => $dbserver->clientId,
                                                'env_id'       => $dbserver->envId,
                                                'name'         => "'{$volumeConfig->tags->service}' data volume",
                                                'type'         => $engine,
                                                'platform'     => $dbserver->platform,
                                                'size'         => $volumeConfig->size,
                                                'fstype'       => $volumeConfig->fstype,
                                                'purpose'      => $volumeConfig->tags->service,
                                                'farm_roleid'  => $dbserver->farmRoleId,
                                                'server_index' => $dbserver->index
                                            ));
                                            $storageVolume->setConfig($volumeConfig);
                                            $storageVolume->save(true);
                                        } else {
                                            throw $e;
                                        }
                                    }
                                    $dbserver->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::VOLUME_ID, $volumeConfig->id, DBFarmRole::TYPE_LCL);
                                    if ($engine == MYSQL_STORAGE_ENGINE::EBS) {
                                        $dbserver->GetFarmRoleObject()->SetSetting(
                                            Scalr_Db_Msr::DATA_STORAGE_EBS_SIZE, $volumeConfig->size, DBFarmRole::TYPE_CFG
                                        );
                                    } elseif ($engine == MYSQL_STORAGE_ENGINE::RAID_EBS) {
                                        $dbserver->GetFarmRoleObject()->SetSetting(
                                            Scalr_Db_Msr::DATA_STORAGE_RAID_DISK_SIZE, $volumeConfig->size, DBFarmRole::TYPE_CFG
                                        );
                                    }
                                    // Remove old
                                    $storageVolume->delete($oldVolumeId);
                                } catch (Exception $e) {
                                    Logger::getLogger(__CLASS__)->error(new FarmLogMessage(
                                        $dbserver->farmId, "Cannot save storage volume: {$e->getMessage()}"
                                    ));
                                }
                            }
                        } elseif ($message->status == 'error' || $message->status == 'failed') {

                            if ($message->name == 'Initialization' || $message->name == 'system.init') {
                                $dbserver->SetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED, 1);
                                if (is_object($message->error))
                                	$errorText = $message->error->message;
                                elseif ($message->error)
                                	$errorText = $message->error;

                                $dbserver->SetProperty(SERVER_PROPERTIES::SZR_IS_INIT_ERROR_MSG, $errorText);

                                $event = new HostInitFailedEvent($dbserver, $errorText);
                            }
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_InitFailed) {
                    	$errorText = $message->reason;

                    	$dbserver->SetProperty(SERVER_PROPERTIES::SZR_IS_INIT_ERROR_MSG, $errorText);
                    	$event = new HostInitFailedEvent($dbserver, $errorText);
                    } elseif ($message instanceof \Scalr_Messaging_Msg_RuntimeError) {
                        $logger->fatal(new FarmLogMessage(
                            $dbserver->farmId,
                            "Scalarizr failed to launch on server '{$dbserver->getNameByConvention()}' with runtime error: {$message->message}",
                            $dbserver->serverId
                        ));
                    } elseif ($message instanceof Scalr_Messaging_Msg_UpdateControlPorts) {
                        $apiPort = $message->api;
                        $ctrlPort = $message->messaging;
                        $snmpPort = $message->snmp;
                        // Check API port;
                        $currentApiPort = $dbserver->GetProperty(SERVER_PROPERTIES::SZR_API_PORT);
                        if (!$currentApiPort) $currentApiPort = 8010;
                        if ($apiPort && $apiPort != $currentApiPort) {
                            $logger->warn(new FarmLogMessage(
                                $dbserver->farmId, "Scalarizr API port was changed from {$currentApiPort} to {$apiPort}", $dbserver->serverId
                            ));
                            $dbserver->SetProperty(SERVER_PROPERTIES::SZR_API_PORT, $apiPort);
                        }
                        // Check Control port
                        $currentCtrlPort = $dbserver->GetProperty(SERVER_PROPERTIES::SZR_CTRL_PORT);
                        if (!$currentCtrlPort) $currentCtrlPort = 8013;
                        if ($ctrlPort && $ctrlPort != $currentCtrlPort) {
                            $logger->warn(new FarmLogMessage(
                                $dbserver->farmId, "Scalarizr Control port was changed from {$currentCtrlPort} to {$ctrlPort}", $dbserver->serverId
                            ));
                            $dbserver->SetProperty(SERVER_PROPERTIES::SZR_CTRL_PORT, $ctrlPort);
                        }
                        //Check SNMP port
                        $currentSnmpPort = $dbserver->GetProperty(SERVER_PROPERTIES::SZR_SNMP_PORT);
                        if (!$currentSnmpPort) $currentSnmpPort = 8014;
                        if ($snmpPort && $snmpPort != $currentSnmpPort) {
                            $logger->warn(new FarmLogMessage(
                                $dbserver->farmId, "Scalarizr SNMP port was changed from {$currentSnmpPort} to {$snmpPort}", $dbserver->serverId
                            ));
                            $dbserver->SetProperty(SERVER_PROPERTIES::SZR_SNMP_PORT, $snmpPort);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_Win_HostDown) {
                        $status = PlatformFactory::NewPlatform($dbserver->platform)->GetServerRealStatus($dbserver);
                        if ($status->isRunning()) {
                            $event = new RebootBeginEvent($dbserver);
                        } else {
                            if ($dbserver->platform == SERVER_PLATFORMS::EC2) {
                                if (!$status->isTerminated()) {
                                    //Stopping
                                    $logger->error(new FarmLogMessage(
                                        $dbserver->farmId, "Server is in '{$status->getName()}' state. Ignoring HostDown event.", $dbserver->serverId
                                    ));
                                    $isStopping = true;
                                }
                            }

                            if ($isStopping) {
                                $dbserver->SetProperties([
                                    SERVER_PROPERTIES::REBOOTING  => 0,
                                    SERVER_PROPERTIES::RESUMING   => 0
                                ]);

                                $dbserver->remoteIp = "";
                                $dbserver->localIp = "";

                                $dbserver->status = SERVER_STATUS::SUSPENDED;
                                $dbserver->Save();
                            }

                            $event = new HostDownEvent($dbserver);
                            $event->isSuspended = true;
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_Win_PrepareBundleResult) {
                        try {
                            $bundleTask = BundleTask::LoadById($message->bundleTaskId);
                        } catch (Exception $e) {}
                        if ($bundleTask) {
                            if ($bundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::PREPARING) {
                                if ($message->status == 'ok') {
                                    $metaData = array(
                                        'szr_version' => $message->meta[Scalr_Messaging_MsgMeta::SZR_VERSION],
                                        'os'          => $message->os,
                                        'software'    => $message->software
                                    );
                                    $bundleTask->setMetaData($metaData);
                                    $bundleTask->Save();
                                    PlatformFactory::NewPlatform($bundleTask->platform)->CreateServerSnapshot($bundleTask);
                                } else {
                                    $bundleTask->SnapshotCreationFailed("PrepareBundle procedure failed: {$message->lastError}");
                                }
                            }
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_DeployResult) {
                        try {
                            $deploymentTask = Scalr_Model::init(Scalr_Model::DM_DEPLOYMENT_TASK)->loadById($message->deployTaskId);
                        } catch (Exception $e) {
                        }
                        if ($deploymentTask) {
                            if ($message->status == 'error') {
                                $deploymentTask->status = Scalr_Dm_DeploymentTask::STATUS_FAILED;
                                $deploymentTask->lastError = $message->lastError;
                            } else {
                                $deploymentTask->status = Scalr_Dm_DeploymentTask::STATUS_DEPLOYED;
                                $deploymentTask->dtDeployed = date("Y-m-d H:i:s");
                            }
                            $deploymentTask->save();
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_Hello) {
                        $event = $this->onHello($message, $dbserver);
                    } elseif ($message instanceof Scalr_Messaging_Msg_FireEvent) {
                        //Validate event
                        $isEventExist = $this->db->GetOne("
                            SELECT id FROM event_definitions
                            WHERE name = ? AND ((env_id = ? AND account_id = ?) OR (env_id IS NULL AND account_id = ?) OR (env_id IS NULL AND account_id IS NULL))
                            LIMIT 1
                        ", array(
                            $message->eventName,
                            $dbserver->envId,
                            $dbserver->clientId,
                            $dbserver->clientId
                        ));
                        if ($isEventExist) {
                            $event = new CustomEvent($dbserver, $message->eventName, (array)$message->params);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_HostUpdate) {
                        try {
                            $dbFarmRole = $dbserver->GetFarmRoleObject();
                        } catch (Exception $e) { }
                        if ($dbFarmRole instanceof DBFarmRole) {
                            foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $behavior)
                                $behavior->handleMessage($message, $dbserver);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_MongoDb) {
                        /********* MONGODB *********/
                        try {
                            $dbFarmRole = $dbserver->GetFarmRoleObject();
                        } catch (Exception $e) {
                        }
                        if ($dbFarmRole instanceof DBFarmRole) {
                            foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $behavior)
                                $behavior->handleMessage($message, $dbserver);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_DbMsr) {
                        /********* DBMSR *********/
                        try {
                            $dbFarmRole = $dbserver->GetFarmRoleObject();
                        } catch (Exception $e) {
                        }
                        if ($dbFarmRole instanceof DBFarmRole) {
                            foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $behavior)
                                $behavior->handleMessage($message, $dbserver);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_HostInit) {
                        $event = $this->onHostInit($message, $dbserver);

                        try {
                            $dbserver->updateTimelog('ts_hi', $message->secondsSinceBoot, $message->secondsSinceStart);
                        } catch (Exception $e) {}

                        if (!$event)
                            continue;
                    } elseif ($message instanceof Scalr_Messaging_Msg_HostUp) {
                        $event = $this->onHostUp($message, $dbserver);

                        try {
                            $dbserver->updateTimelog('ts_hu');
                        } catch (Exception $e) {
                        }

                    } elseif ($message instanceof Scalr_Messaging_Msg_HostDown) {
                        $ignoreHostDown = false;

                        $p = PlatformFactory::NewPlatform($dbserver->platform);
                        $status = $p->GetServerRealStatus($dbserver);

                        if ($dbserver->isOpenstack()) {
                            if (stristr($status->getName(), 'REBOOT') || stristr($status->getName(), 'HARD_REBOOT')) {
                                $logger->error(new FarmLogMessage(
                                    $dbserver->farmId, "Rackspace server is in " . $status->getName() . " state. Ignoring HostDown message.", $dbserver->serverId
                                ));
                                $isRebooting = true;
                            }
                        } elseif ($dbserver->platform == \SERVER_PLATFORMS::GCE) {
                            if ($status->getName() == 'STOPPING') {
                                // We don't know is this shutdown or stop so let's ignore HostDown
                                // and wait for status change
                                $doNotProcessMessage = true;
                                $ignoreHostDown = true;
                            } elseif ($status->getName() == 'RUNNING') {
                                $isRebooting = true;
                            } elseif ($status->isSuspended() && $dbserver->status != \SERVER_STATUS::PENDING_TERMINATE) {
                                $isStopping = true;
                            }
                        } else {
                            if ($p->getResumeStrategy() == 'init') {
                                //TODO: Check is is stopping or shutting-down procedure.
                                if (!$status->isTerminated()) {
                                    $isStopping = true;
                                }
                            }
                        }

                        if ($isStopping) {
                            $dbserver->SetProperties([
                                SERVER_PROPERTIES::REBOOTING  => 0,
                                SERVER_PROPERTIES::RESUMING   => 0
                            ]);

                            $dbserver->remoteIp = "";
                            $dbserver->localIp = "";

                            $dbserver->status = SERVER_STATUS::SUSPENDED;
                            $dbserver->Save();

                            $event = new HostDownEvent($dbserver);
                            $event->isSuspended = true;

                        } elseif ($isRebooting) {
                            $event = new RebootBeginEvent($dbserver);
                        } elseif (!$ignoreHostDown) {
                            if ($dbserver->farmId) {
                                $wasHostDownFired = $this->db->GetOne("SELECT id FROM events WHERE event_server_id = ? AND type = ? AND is_suspend = '0'", array(
                                    $dbserver->serverId, 'HostDown'
                                ));

                                if (!$wasHostDownFired)
                                    $event = new HostDownEvent($dbserver);
                            }
                        }

                    } elseif ($message instanceof Scalr_Messaging_Msg_RebootStart) {
                        $event = new RebootBeginEvent($dbserver);
                    } elseif ($message instanceof Scalr_Messaging_Msg_RebootFinish) {
                        if (!$dbserver->localIp && $message->localIp) {
                            $dbserver->localIp = $message->localIp;
                            $dbserver->Save();
                        }

                        $event = new RebootCompleteEvent($dbserver);
                    } elseif ($message instanceof Scalr_Messaging_Msg_BeforeHostUp) {
                        $event = new BeforeHostUpEvent($dbserver);

                        try {
                            $dbserver->updateTimelog('ts_bhu');
                        } catch (Exception $e) {}

                    } elseif ($message instanceof Scalr_Messaging_Msg_BlockDeviceAttached) {
                        if ($dbserver->platform == SERVER_PLATFORMS::EC2) {
                            $aws = $dbserver->GetEnvironmentObject()->aws($dbserver->GetProperty(EC2_SERVER_PROPERTIES::REGION));
                            $instanceId = $dbserver->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
                            //The main goal of using filters there is to considerably decrease the size of the response.
                            $volumes = $aws->ec2->volume->describe(null, array(
                                array(
                                    'name' => VolumeFilterNameType::attachmentInstanceId(),
                                    'value' => (string) $instanceId
                                ),
                                array(
                                    'name' => VolumeFilterNameType::attachmentDevice(),
                                    'value' => (string) $message->deviceName
                                ),
                                array(
                                    'name' => VolumeFilterNameType::status(),
                                    'value' => AMAZON_EBS_STATE::IN_USE
                                )
                            ));
                            foreach ($volumes as $volume) {
                                /* @var $volume Scalr\Service\Aws\Ec2\DataType\VolumeData */
                                if ($volume->status == AMAZON_EBS_STATE::IN_USE &&
                                    count($volume->attachmentSet) &&
                                    $volume->attachmentSet[0]->instanceId == $instanceId &&
                                    $volume->attachmentSet[0]->device == $message->deviceName) {
                                    $message->volumeId = $volume->volumeId;
                                }
                            }
                            //Releases memory
                            unset($volumes);
                            $dbserver->GetEnvironmentObject()->getContainer()->release('aws');
                            unset($aws);
                        }
                        $event = new EBSVolumeAttachedEvent($dbserver, $message->deviceName, $message->volumeId);
                    } elseif ($message instanceof Scalr_Messaging_Msg_BlockDeviceMounted) {
                        // Single volume
                        $ebsinfo = $this->db->GetRow("
                            SELECT * FROM ec2_ebs WHERE volume_id=? LIMIT 1
                        ", array(
                            $message->volumeId
                        ));
                        if ($ebsinfo) {
                            $this->db->Execute("
                                UPDATE ec2_ebs
                                SET mount_status=?, isfsexist='1'
                                WHERE id=?
                            ", array(
                                EC2_EBS_MOUNT_STATUS::MOUNTED,
                                $ebsinfo['id']
                            ));
                        }
                        $event = new EBSVolumeMountedEvent(
                            $dbserver, $message->mountpoint, $message->volumeId, $message->deviceName
                        );
                    } elseif ($message instanceof Scalr_Messaging_Msg_RebundleResult) {
                        if ($message->status == Scalr_Messaging_Msg_RebundleResult::STATUS_OK) {
                            $metaData = array(
                                'szr_version' => $message->meta[Scalr_Messaging_MsgMeta::SZR_VERSION],
                                'dist'        => $message->dist,
                                'os'          => $message->os,
                                'software'    => $message->software
                            );
                            if ($dbserver->platform == SERVER_PLATFORMS::EC2) {
                                if ($message->aws) {
                                    if ($message->aws->rootDeviceType == 'ebs') {
                                        $tags[] = ROLE_TAGS::EC2_EBS;
                                    }
                                    if ($message->aws->virtualizationType == 'hvm') {
                                        $tags[] = ROLE_TAGS::EC2_HVM;
                                    }
                                } else {
                                    $aws = $dbserver->GetEnvironmentObject()->aws($dbserver);
                                    try {
                                        $info = $aws->ec2->image->describe($dbserver->GetProperty(EC2_SERVER_PROPERTIES::AMIID))->get(0);
                                        if ($info->rootDeviceType == 'ebs') {
                                            $tags[] = ROLE_TAGS::EC2_EBS;
                                        } else {
                                            try {
                                                $bundleTask = BundleTask::LoadById($message->bundleTaskId);
                                                if ($bundleTask->bundleType == SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS) {
                                                    $tags[] = ROLE_TAGS::EC2_EBS;
                                                }
                                            } catch (Exception $e) {
                                            }
                                        }
                                        if ($info->virtualizationType == 'hvm') {
                                            $tags[] = ROLE_TAGS::EC2_HVM;
                                        }
                                        unset($info);
                                    } catch (Exception $e) {
                                        $metaData['tagsError'] = $e->getMessage();
                                        try {
                                            $bundleTask = BundleTask::LoadById($message->bundleTaskId);
                                            if ($bundleTask->bundleType == SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS) {
                                                $tags[] = ROLE_TAGS::EC2_EBS;
                                            }
                                        } catch (Exception $e) {
                                        }
                                    }
                                    //Releases memory
                                    $dbserver->GetEnvironmentObject()->getContainer()->release('aws');
                                    unset($aws);
                                }
                            }
                            $metaData['tags'] = $tags;
                            $event = new RebundleCompleteEvent($dbserver, $message->snapshotId, $message->bundleTaskId, $metaData);
                        } else if ($message->status == Scalr_Messaging_Msg_RebundleResult::STATUS_FAILED) {
                            $event = new RebundleFailedEvent($dbserver, $message->bundleTaskId, $message->lastError);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_Mysql_CreateDataBundleResult) {
                        if ($message->status == "ok") {
                            $event = new MysqlBackupCompleteEvent($dbserver, MYSQL_BACKUP_TYPE::BUNDLE, array(
                                'snapshotConfig' => $message->snapshotConfig,
                                'logFile'        => $message->logFile,
                                'logPos'         => $message->logPos,
                                'dataBundleSize' => $message->dataBundleSize,
                               /* @deprecated */
                               'snapshotId'      => $message->snapshotId
                            ));
                        } else {
                            $event = new MysqlBackupFailEvent($dbserver, MYSQL_BACKUP_TYPE::BUNDLE);
                            $event->lastError = $message->lastError;
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_Mysql_CreateBackupResult) {
                        if ($message->status == "ok") {
                            $event = new MysqlBackupCompleteEvent($dbserver, MYSQL_BACKUP_TYPE::DUMP, array());
                            $event->backupParts = $message->backupParts;
                        } else {
                            $event = new MysqlBackupFailEvent($dbserver, MYSQL_BACKUP_TYPE::DUMP);
                            $event->lastError = $message->lastError;
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_Mysql_PromoteToMasterResult) {
                        $event = $this->onMysql_PromoteToMasterResult($message, $dbserver);
                    } elseif ($message instanceof Scalr_Messaging_Msg_Mysql_CreatePmaUserResult) {
                        $farmRole = DBFarmRole::LoadByID($message->farmRoleId);
                        if ($message->status == "ok") {
                            $farmRole->SetSetting(DBFarmRole::SETTING_MYSQL_PMA_USER, $message->pmaUser, DBFarmRole::TYPE_LCL);
                            $farmRole->SetSetting(DBFarmRole::SETTING_MYSQL_PMA_PASS, $message->pmaPassword, DBFarmRole::TYPE_LCL);
                        } else {
                            $farmRole->SetSetting(DBFarmRole::SETTING_MYSQL_PMA_REQUEST_TIME, "", DBFarmRole::TYPE_LCL);
                            $farmRole->SetSetting(DBFarmRole::SETTING_MYSQL_PMA_REQUEST_ERROR, $message->lastError, DBFarmRole::TYPE_LCL);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_RabbitMq_SetupControlPanelResult) {
                        $farmRole = $dbserver->GetFarmRoleObject();
                        if ($message->status == "ok") {
                            $mgmtHost = $dbserver->getSzrHost();

                            if ($message->port) {
                                $mgmtURL = "http://{$mgmtHost}:{$message->port}/mgmt";
                            } elseif ($message->cpanelUrl) {
                                $info = parse_url($message->cpanelUrl);
                                $mgmtURL = "http://{$mgmtHost}:{$info['port']}/mgmt";
                            }

                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_SERVER_ID, $dbserver->serverId, DBFarmRole::TYPE_LCL);
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_URL, $mgmtURL, DBFarmRole::TYPE_LCL);
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME, "", DBFarmRole::TYPE_LCL);
                        } else {
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_SERVER_ID, "", DBFarmRole::TYPE_LCL);
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME, "", DBFarmRole::TYPE_LCL);
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_ERROR_MSG, $message->lastError, DBFarmRole::TYPE_LCL);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_AmiScriptsMigrationResult) {
                        try {
                            //Open security group:
                            if ($dbserver->platform == SERVER_PLATFORMS::EC2) {
                                $info = PlatformFactory::NewPlatform($dbserver->platform)->GetServerExtendedInformation($dbserver);
                                $sg = explode(", ", $info['Security groups']);
                                foreach ($sg as $sgroup) {
                                    if ($sgroup != 'default') {
                                        // For Scalarizr
                                        $group_rules = array(
                                            array('rule' => 'tcp:8013:8013:0.0.0.0/0'),
                                            array('rule' => 'udp:8014:8014:0.0.0.0/0'),
                                        );
                                        $aws = $dbserver->GetEnvironmentObject()->aws($dbserver);
                                        $ipPermissions = new \Scalr\Service\Aws\Ec2\DataType\IpPermissionList();
                                        foreach ($group_rules as $rule) {
                                            $group_rule = explode(":", $rule["rule"]);
                                            $ipPermissions->append(new \Scalr\Service\Aws\Ec2\DataType\IpPermissionData(
                                                $group_rule[0], $group_rule[1], $group_rule[2],
                                                new \Scalr\Service\Aws\Ec2\DataType\IpRangeData($group_rule[3])));
                                        }
                                        $aws->ec2->securityGroup->authorizeIngress($ipPermissions, null, $sgroup);
                                        $dbserver->GetEnvironmentObject()->getContainer()->release('aws');
                                        unset($aws);
                                        unset($ipPermissions);
                                        break;
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            $logger->fatal($e->getMessage());
                        }
                        $dbserver->SetProperty(SERVER_PROPERTIES::SZR_SNMP_PORT, 8014);
                        $dbserver->SetProperty(SERVER_PROPERTIES::SZR_VESION, "0.7.217");
                        if ($message->mysql) {
                            $event = $this->onHostUp($message, $dbserver, true);
                        }
                    }
                    $handle_status = MESSAGE_STATUS::HANDLED;
                } catch (Exception $e) {
                    $handle_status = MESSAGE_STATUS::FAILED;
                    $logger->error(sprintf("Cannot handle message '%s' (message_id: %s) " . "from server '%s' (server_id: %s). %s",
                        $message->getName(),
                        $message->messageId,
                        $dbserver->remoteIp ? $dbserver->remoteIp : '*no-ip*',
                        $dbserver->serverId,
                        $e->getMessage() . "({$e->getFile()}:{$e->getLine()})"
                    ));
                }

                if (!$doNotProcessMessage) {
                    $totalTime = microtime(true) - $startTime;
                    $this->db->Execute("
                        UPDATE messages
                        SET status = ?, processing_time = ?, dtlasthandleattempt = NOW()
                        WHERE messageid = ?
                    ", array(
                        $handle_status,
                        $totalTime,
                        $message->messageId
                    ));
                } else {
                    $logger->info(sprintf("Handle message '%s' (message_id: %s) " . "from server '%s' (server_id: %s) is postponed due to status transition",
                        $message->getName(),
                        $message->messageId,
                        $dbserver->remoteIp ? $dbserver->remoteIp : '*no-ip*',
                        $dbserver->serverId
                    ));
                }

                if ($event) {
                    \Scalr::FireEvent($dbserver->farmId, $event);
                }


            } catch (Exception $e) {
                $logger->error($e->getMessage());
            }
        }

        return $request;
    }

    private function onHello($message, DBServer $dbserver)
    {
        $logger = Logger::getLogger(__CLASS__);

        if ($dbserver->status == SERVER_STATUS::TEMPORARY) {
            $bundleTask = BundleTask::LoadById($dbserver->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BUNDLE_TASK_ID));
            $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::PENDING;
            $bundleTask->Log("Received Hello message from scalarizr on server. Creating image");
            $bundleTask->osFamily = $message->dist->distributor;
            $bundleTask->osName = $message->dist->codename;
            $bundleTask->osVersion = $message->dist->release;
            $bundleTask->designateType($dbserver->platform, $bundleTask->osFamily, null, $bundleTask->osVersion);
            $bundleTask->save();
        }
        if ($dbserver->status == SERVER_STATUS::IMPORTING) {
            if (!$dbserver->remoteIp || !$dbserver->localIp) {
                if (!$dbserver->remoteIp && $message->remoteIp && $dbserver->platform != SERVER_PLATFORMS::IDCF) {
                    $dbserver->remoteIp = $message->remoteIp;
                }
                if (!$dbserver->localIp && $message->localIp) {
                    $dbserver->localIp = $message->localIp;
                }
                if (!$message->behaviour) {
                    $message->behaviour = array('base');
                }
            }

            if (count($message->behaviour) == 1 && $message->behaviour[0] == ROLE_BEHAVIORS::CHEF)
                $message->behaviour[] = ROLE_BEHAVIORS::BASE;

            $dbserver->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR, @implode(",", $message->behaviour));
            $dbserver->save();

            $importVersion = $dbserver->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_VERSION);

            if ($importVersion == 2) {
                $dbserver->SetProperties(array(
                    SERVER_PROPERTIES::ARCHITECTURE      => $message->architecture
                ));
            } else {
                if ($dbserver->isOpenstack()) {
                    $env = $dbserver->GetEnvironmentObject();
                    $os = $env->openstack($dbserver->platform, $dbserver->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

                    $csServer = null;
                    $list = $os->servers->list(true);
                    do {
                        foreach ($list as $_tmp) {
                            $ipaddresses = array();
                            if (!is_array($_tmp->addresses)) {
                                $_tmp->addresses = (array)$_tmp->addresses;
                            }
                            foreach ($_tmp->addresses as $net => $addresses) {
                                foreach ($addresses as $addr) {
                                    if ($addr->version == 4) {
                                        array_push($ipaddresses, $addr->addr);
                                    }
                                }
                            }

                            if ($_tmp->accessIPv4)
                                array_push($ipaddresses, $_tmp->accessIPv4);

                            if (in_array($dbserver->localIp, $ipaddresses) ||
                                in_array($dbserver->remoteIp, $ipaddresses)) {
                                $osServer = $_tmp;
                            }
                        }
                    } while (false !== ($list = $list->getNextPage()));

                    if (!$osServer) {
                        $logger->error(sprintf(
                            "Server not found on Openstack (server_id: %s, remote_ip: %s, local_ip: %s)",
                            $dbserver->serverId, $dbserver->remoteIp, $dbserver->localIp
                        ));
                        return;
                    }

                    $dbserver->SetProperties(array(
                        OPENSTACK_SERVER_PROPERTIES::SERVER_ID => $osServer->id,
                        OPENSTACK_SERVER_PROPERTIES::NAME      => $osServer->name,
                        OPENSTACK_SERVER_PROPERTIES::IMAGE_ID  => $osServer->image->id,
                        OPENSTACK_SERVER_PROPERTIES::FLAVOR_ID => $osServer->flavor->id,
                        OPENSTACK_SERVER_PROPERTIES::HOST_ID   => $osServer->hostId,
                        SERVER_PROPERTIES::ARCHITECTURE        => $message->architecture
                    ));
                } elseif ($dbserver->isCloudstack()) {
                    $dbserver->SetProperties(array(
                        CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID      => $message->cloudstack->instanceId,
                        CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $message->cloudstack->availZone,
                        SERVER_PROPERTIES::ARCHITECTURE              => $message->architecture
                    ));
                } else {
                    switch ($dbserver->platform) {
                        case SERVER_PLATFORMS::EC2:
                            $dbserver->SetProperties(array(
                                EC2_SERVER_PROPERTIES::AMIID         => $message->awsAmiId,
                                EC2_SERVER_PROPERTIES::INSTANCE_ID   => $message->awsInstanceId,
                                EC2_SERVER_PROPERTIES::INSTANCE_TYPE => $message->awsInstanceType,
                                EC2_SERVER_PROPERTIES::AVAIL_ZONE    => $message->awsAvailZone,
                                EC2_SERVER_PROPERTIES::REGION        => substr($message->awsAvailZone, 0, -1),
                                SERVER_PROPERTIES::ARCHITECTURE      => $message->architecture
                            ));
                            break;

                        case SERVER_PLATFORMS::EUCALYPTUS:
                            $dbserver->SetProperties(array(
                                EUCA_SERVER_PROPERTIES::EMIID         => $message->awsAmiId,
                                EUCA_SERVER_PROPERTIES::INSTANCE_ID   => $message->awsInstanceId,
                                EUCA_SERVER_PROPERTIES::INSTANCE_TYPE => $message->awsInstanceType,
                                EUCA_SERVER_PROPERTIES::AVAIL_ZONE    => $message->awsAvailZone,
                                SERVER_PROPERTIES::ARCHITECTURE       => $message->architecture
                            ));
                            break;

                        case SERVER_PLATFORMS::GCE:
                            $dbserver->SetProperties(array(
                                GCE_SERVER_PROPERTIES::CLOUD_LOCATION => $message->{$dbserver->platform}->cloudLocation,
                                GCE_SERVER_PROPERTIES::SERVER_ID      => $message->{$dbserver->platform}->serverId,
                                GCE_SERVER_PROPERTIES::SERVER_NAME    => $message->{$dbserver->platform}->serverName,
                                GCE_SERVER_PROPERTIES::MACHINE_TYPE   => $message->{$dbserver->platform}->machineType,
                                SERVER_PROPERTIES::ARCHITECTURE       => $message->architecture
                            ));
                            break;

                        case SERVER_PLATFORMS::RACKSPACE:
                            $env = $dbserver->GetEnvironmentObject();
                            $cs = Scalr_Service_Cloud_Rackspace::newRackspaceCS(
                                $env->getPlatformConfigValue(
                                    RackspacePlatformModule::USERNAME, true,
                                    $dbserver->GetProperty(RACKSPACE_SERVER_PROPERTIES::DATACENTER)
                                ),
                                $env->getPlatformConfigValue(
                                    RackspacePlatformModule::API_KEY, true,
                                    $dbserver->GetProperty(RACKSPACE_SERVER_PROPERTIES::DATACENTER)
                                ),
                                $dbserver->GetProperty(RACKSPACE_SERVER_PROPERTIES::DATACENTER)
                            );
                            $csServer = null;
                            $list = $cs->listServers(true);
                            if ($list) {
                                foreach ($list->servers as $_tmp) {
                                    if ($_tmp->addresses->public && in_array($message->remoteIp, $_tmp->addresses->public)) {
                                        $csServer = $_tmp;
                                    }
                                }
                            }
                            if (!$csServer) {
                                $logger->error(sprintf(
                                    "Server not found on CloudServers (server_id: %s, remote_ip: %s, local_ip: %s)",
                                    $dbserver->serverId, $message->remoteIp, $message->localIp
                                ));
                                return;
                            }
                            $dbserver->SetProperties(array(
                                RACKSPACE_SERVER_PROPERTIES::SERVER_ID  => $csServer->id,
                                RACKSPACE_SERVER_PROPERTIES::NAME       => $csServer->name,
                                RACKSPACE_SERVER_PROPERTIES::IMAGE_ID   => $csServer->imageId,
                                RACKSPACE_SERVER_PROPERTIES::FLAVOR_ID  => $csServer->flavorId,
                                RACKSPACE_SERVER_PROPERTIES::HOST_ID    => $csServer->hostId,
                                SERVER_PROPERTIES::ARCHITECTURE         => $message->architecture
                            ));
                            break;
                    }
                }
            }

            //TODO: search for existing bundle task

            // Bundle image
            $creInfo = new ServerSnapshotCreateInfo(
                $dbserver,
                $dbserver->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_ROLE_NAME),
                SERVER_REPLACEMENT_TYPE::NO_REPLACE,
                $dbserver->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OBJECT)
            );
            $bundleTask = BundleTask::Create($creInfo);
            $bundleTask->osFamily = $message->dist->distributor;
            $bundleTask->osName = $message->dist->codename;
            $bundleTask->osVersion = $message->dist->release;
            $bundleTask->designateType($dbserver->platform, $bundleTask->osFamily, null, $bundleTask->osVersion);

            $bundleTask->setDate("started");

            $bundleTask->createdByEmail = $dbserver->GetProperty(SERVER_PROPERTIES::LAUNCHED_BY_EMAIL);
            $bundleTask->createdById = $dbserver->GetProperty(SERVER_PROPERTIES::LAUNCHED_BY_ID);

            if ($importVersion == 2)
                $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::ESTABLISHING_COMMUNICATION;

            $bundleTask->Save();

            $dbserver->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BUNDLE_TASK_ID, $bundleTask->id);
        }
    }

    private function onHostInit($message, DBServer $dbserver)
    {
        $logger = Logger::getLogger(__CLASS__);

        if ($dbserver->status == SERVER_STATUS::PENDING) {
            $platform = PlatformFactory::NewPlatform($dbserver->platform);
            // Update server crypto key
            $srv_props = array();
            if ($message->cryptoKey) {
                $srv_props[SERVER_PROPERTIES::SZR_KEY] = trim($message->cryptoKey);
                $srv_props[SERVER_PROPERTIES::SZR_KEY_TYPE] = SZR_KEY_TYPE::PERMANENT;
            }
            $srv_props[SERVER_PROPERTIES::SZR_SNMP_PORT] = $message->snmpPort;
            if ($dbserver->isCloudstack()) {
                $remoteIp = CloudstackHelper::getSharedIP($dbserver);
            }
            if ($dbserver->isOpenstack()) {
                if ($dbserver->farmRoleId) {
                    $ipPool = $dbserver->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_OPENSTACK_IP_POOL);
                    if ($ipPool && empty($dbserver->remoteIp)) {
                        return false;
                    } else {
                        $remoteIp = $dbserver->remoteIp;
                    }
                }
            }
            if (!$remoteIp) {
                $ips = $platform->GetServerIPAddresses($dbserver);
                if ($ips['remoteIp'])
                    $remoteIp = $ips['remoteIp'];
                else
                    $remoteIp = $message->remoteIp ? $ips['remoteIp'] : '';
            }
            $dbserver->remoteIp = $remoteIp;
            $dbserver->Save();

            //Update auto-update settings
            //TODO: Check auto-update client version
            if ($dbserver->IsSupported('0.7.225') && !$dbserver->IsSupported('2.7.10')) {
                $dbserver->SetProperties($srv_props);
                try {
                    $repo = $dbserver->GetFarmRoleObject()->GetSetting(Scalr_Role_Behavior::ROLE_BASE_SZR_UPD_REPOSITORY);
                    if (!$repo)
                        $repo = $dbserver->GetFarmObject()->GetSetting(DBFarm::SETTING_SZR_UPD_REPOSITORY);
                    $schedule = $dbserver->GetFarmObject()->GetSetting(DBFarm::SETTING_SZR_UPD_SCHEDULE);
                    if ($repo && $schedule) {
                        $updateClient = new Scalr_Net_Scalarizr_UpdateClient($dbserver);
                        $updateClient->configure($repo, $schedule);
                    }
                } catch (Exception $e) {}
            }
            // MySQL specific
            $dbFarmRole = $dbserver->GetFarmRoleObject();
            if ($dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
                $master = $dbFarmRole->GetFarmObject()->GetMySQLInstances(true);
                // If no masters in role this server becomes it
                if (!$master[0] && !(int) $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_SLAVE_TO_MASTER)) {
                    $srv_props[SERVER_PROPERTIES::DB_MYSQL_MASTER] = 1;
                }
            }
            //MSR Replication Master
            //TODO: MySQL
            if ($dbFarmRole->GetRoleObject()->getDbMsrBehavior()) {
                $servers = $dbFarmRole->GetServersByFilter(array(
                    'status' => array(
                        SERVER_STATUS::INIT,
                        SERVER_STATUS::RUNNING
                    )
                ));
                if (!$dbFarmRole->GetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER)) {
                    $masterFound = false;
                    foreach ($servers as $server) {
                        if ($server->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER)) $masterFound = true;
                    }
                    if (!$masterFound) $srv_props[Scalr_Db_Msr::REPLICATION_MASTER] = 1;
                } elseif ($dbFarmRole->GetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER) && count($servers) == 0) {
                    $dbFarmRole->SetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER, 0, DBFarmRole::TYPE_LCL);
                    $srv_props[Scalr_Db_Msr::REPLICATION_MASTER] = 1;
                }
            }
            $dbserver->SetProperties($srv_props);
            return new HostInitEvent($dbserver, $message->localIp, $remoteIp, $message->sshPubKey);
        } else {
            /*
               $logger->error("Strange situation. Received HostInit message"
                       . " from server '{$dbserver->serverId}' ({$message->remoteIp})"
                       . " with state {$dbserver->status}!");
            */
            //TOOD: Check if instance terminating we probably can cancel termination and continue initialization
        }
    }

    /**
     * @param Scalr_Messaging_Msg $message
     * @param DBServer $dbserver
     */
    private function onHostUp($message, $dbserver, $skipStatusCheck = false)
    {
        $logger = Logger::getLogger(__CLASS__);

        if ($dbserver->status == SERVER_STATUS::INIT || $skipStatusCheck) {
            $event = new HostUpEvent($dbserver, "");
            $dbFarmRole = $dbserver->GetFarmRoleObject();
            foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $behavior)
                $behavior->handleMessage($message, $dbserver);
                //TODO: Move MySQL to MSR
                /****** MOVE TO MSR ******/
                //TODO: Legacy MySQL code
            if ($dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
                if (!$message->mysql) {
                    $logger->error(sprintf(
                        "Strange situation. HostUp message from MySQL behavior doesn't contains `mysql` property. Server %s (%s)",
                        $dbserver->serverId, $dbserver->remoteIp
                    ));
                    return;
                }
                $mysqlData = $message->mysql;
                if ($dbserver->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER)) {
                    if ($mysqlData->rootPassword) {
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_REPL_PASSWORD, $mysqlData->replPassword, DBFarmRole::TYPE_LCL);
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_ROOT_PASSWORD, $mysqlData->rootPassword, DBFarmRole::TYPE_LCL);
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_STAT_PASSWORD, $mysqlData->statPassword, DBFarmRole::TYPE_LCL);
                    }
                    $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_LOG_FILE, $mysqlData->logFile, DBFarmRole::TYPE_LCL);
                    $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_LOG_POS, $mysqlData->logPos, DBFarmRole::TYPE_LCL);
                    if ($dbserver->IsSupported("0.7")) {
                        if ($mysqlData->volumeConfig) {
                            try {
                                $storageVolume = Scalr_Storage_Volume::init();
                                try {
                                    $storageVolume->loadById($mysqlData->volumeConfig->id);
                                    $storageVolume->setConfig($mysqlData->volumeConfig);
                                    $storageVolume->save();
                                } catch (Exception $e) {
                                    if (strpos($e->getMessage(), 'not found')) {
                                        $storageVolume->loadBy(array(
                                            'id'           => $mysqlData->volumeConfig->id,
                                            'client_id'    => $dbserver->clientId,
                                            'env_id'       => $dbserver->envId,
                                            'name'         => "MySQL data volume",
                                            'type'         => $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_DATA_STORAGE_ENGINE),
                                            'platform'     => $dbserver->platform,
                                            'size'         => $mysqlData->volumeConfig->size,
                                            'fstype'       => $mysqlData->volumeConfig->fstype,
                                            'purpose'      => ROLE_BEHAVIORS::MYSQL,
                                            'farm_roleid'  => $dbserver->farmRoleId,
                                            'server_index' => $dbserver->index
                                        ));
                                        $storageVolume->setConfig($mysqlData->volumeConfig);
                                        $storageVolume->save(true);
                                    } else
                                        throw $e;
                                }
                                $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_SCALR_VOLUME_ID, $storageVolume->id, DBFarmRole::TYPE_LCL);
                            } catch (Exception $e) {
                                $logger->error(new FarmLogMessage(
                                    $event->DBServer->farmId, "Cannot save storage volume: {$e->getMessage()}"
                                ));
                            }
                        }
                        if ($mysqlData->snapshotConfig) {
                            try {
                                $storageSnapshot = Scalr_Storage_Snapshot::init();
                                try {
                                    $storageSnapshot->loadById($mysqlData->snapshotConfig->id);
                                    $storageSnapshot->setConfig($mysqlData->snapshotConfig);
                                    $storageSnapshot->save();
                                } catch (Exception $e) {
                                    if (strpos($e->getMessage(), 'not found')) {
                                        $storageSnapshot->loadBy(array(
                                            'id'          => $mysqlData->snapshotConfig->id,
                                            'client_id'   => $dbserver->clientId,
                                            'farm_id'     => $dbserver->farmId,
                                            'farm_roleid' => $dbserver->farmRoleId,
                                            'env_id'      => $dbserver->envId,
                                            'name'        => sprintf(_("MySQL data bundle #%s"), $mysqlData->snapshotConfig->id),
                                            'type'        => $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_DATA_STORAGE_ENGINE),
                                            'platform'    => $dbserver->platform,
                                            'description' => sprintf(
                                                _("MySQL data bundle created on Farm '%s' -> Role '%s'"),
                                                $dbFarmRole->GetFarmObject()->Name,
                                                $dbFarmRole->GetRoleObject()->name
                                            ),
                                            'ismysql'     => true,
                                            'service'     => ROLE_BEHAVIORS::MYSQL
                                        ));
                                        $storageSnapshot->setConfig($mysqlData->snapshotConfig);
                                        $storageSnapshot->save(true);
                                    } else
                                        throw $e;
                                }
                                $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_SCALR_SNAPSHOT_ID, $storageSnapshot->id, DBFarmRole::TYPE_LCL);
                            } catch (Exception $e) {
                                $logger->error(new FarmLogMessage(
                                    $event->DBServer->farmId, "Cannot save storage snapshot: {$e->getMessage()}"
                                ));
                            }
                        }
                    } else {
                        //@deprecated
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_SNAPSHOT_ID, $mysqlData->snapshotId, DBFarmRole::TYPE_LCL);
                    }
                }
            }
            return $event;
        } else {
            $logger->info(
                "Strange situation. Received HostUp message"
              . " from server '{$dbserver->serverId}' ('{$message->remoteIp})"
              . " with state {$dbserver->status}!"
            );
        }
    }

    /**
     * @param Scalr_Messaging_Msg_Mysql_PromoteToMasterResult $message
     * @param DBServer $dbserver
     */
    private function onMysql_PromoteToMasterResult($message, DBServer $dbserver)
    {
        $logger = Logger::getLogger(__CLASS__);

        $dbserver->GetFarmRoleObject()->SetSetting(DBFarmRole::SETTING_MYSQL_SLAVE_TO_MASTER, 0, DBFarmRole::TYPE_LCL);

        if ($message->status == Scalr_Messaging_Msg_Mysql_PromoteToMasterResult::STATUS_OK) {
            $dbFarm = $dbserver->GetFarmObject();
            $dbFarmRole = $dbserver->GetFarmRoleObject();
            $oldMaster = $dbFarm->GetMySQLInstances(true);
            if ($dbserver->IsSupported("0.7")) {
                if ($message->volumeConfig) {
                    try {
                        $storageVolume = Scalr_Storage_Volume::init();
                        try {
                            $storageVolume->loadById($message->volumeConfig->id);
                            $storageVolume->setConfig($message->volumeConfig);
                            $storageVolume->save();
                        } catch (Exception $e) {
                            if (strpos($e->getMessage(), 'not found')) {
                                $storageVolume->loadBy(array(
                                    'id'           => $message->volumeConfig->id,
                                    'client_id'    => $dbserver->clientId,
                                    'env_id'       => $dbserver->envId,
                                    'name'         => "MySQL data volume",
                                    'type'         => $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_DATA_STORAGE_ENGINE),
                                    'platform'     => $dbserver->platform,
                                    'size'         => $message->volumeConfig->size,
                                    'fstype'       => $message->volumeConfig->fstype,
                                    'purpose'      => ROLE_BEHAVIORS::MYSQL,
                                    'farm_roleid'  => $dbserver->farmRoleId,
                                    'server_index' => $dbserver->index
                                ));
                                $storageVolume->setConfig($message->volumeConfig);
                                $storageVolume->save(true);
                            } else {
                                throw $e;
                            }
                        }
                    } catch (Exception $e) {
                        $logger->error(new FarmLogMessage(
                            $dbserver->farmId, "Cannot save storage volume: {$e->getMessage()}"
                        ));
                    }
                }
                if ($message->snapshotConfig) {
                    try {
                        $snapshot = Scalr_Model::init(Scalr_Model::STORAGE_SNAPSHOT);
                        $snapshot->loadBy(array(
                            'id'          => $message->snapshotConfig->id,
                            'client_id'   => $dbserver->clientId,
                            'env_id'      => $dbserver->envId,
                            'name'        => "Automatical MySQL data bundle",
                            'type'        => $dbFarmRole->GetSetting(DBFarmRole::SETTING_MYSQL_DATA_STORAGE_ENGINE),
                            'platform'    => $dbserver->platform,
                            'description' => "MySQL data bundle created automatically by Scalr",
                            'ismysql'     => true
                        ));
                        $snapshot->setConfig($message->snapshotConfig);
                        $snapshot->save(true);
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_SCALR_SNAPSHOT_ID, $snapshot->id, DBFarmRole::TYPE_LCL);
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_LOG_FILE, $message->logFile, DBFarmRole::TYPE_LCL);
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_LOG_POS, $message->logPos, DBFarmRole::TYPE_LCL);
                    } catch (Exception $e) {
                        $logger->error(new FarmLogMessage(
                            $dbserver->farmId, "Cannot save storage snapshot: {$e->getMessage()}"
                        ));
                    }
                }
            } else {
                // TODO: delete old slave volume if new one was created
                $dbFarmRole->SetSetting(DBFarmRole::SETTING_MYSQL_MASTER_EBS_VOLUME_ID, $message->volumeId, DBFarmRole::TYPE_LCL);
            }
            return new NewMysqlMasterUpEvent($dbserver, "", $oldMaster[0]);
        } elseif ($message->status == Scalr_Messaging_Msg_Mysql_PromoteToMasterResult::STATUS_FAILED) {
            $dbserver->SetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER, 0);
            $dbserver->SetProperty(Scalr_Db_Msr::REPLICATION_MASTER, 0);
            // XXX: Need to do smth
            $logger->error(sprintf(
                "Promote to Master failed for server %s. Last error: %s",
                $dbserver->serverId, $message->lastError
            ));
        }
    }
}