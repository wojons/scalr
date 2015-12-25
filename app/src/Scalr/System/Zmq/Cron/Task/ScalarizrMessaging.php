<?php

namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception, stdClass;
use \ADODB_Exception;
use \DBServer;
use \DBFarmRole;
use \BundleTask;
use \SERVER_PROPERTIES;
use \EC2_SERVER_PROPERTIES;
use \CLOUDSTACK_SERVER_PROPERTIES;
use \OPENSTACK_SERVER_PROPERTIES;
use \RACKSPACE_SERVER_PROPERTIES;
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
use Scalr\Modules\Platforms\Cloudstack\Helpers\CloudstackHelper;
use Scalr\Db\ConnectionPool;
use Scalr\Model\Entity;

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

        $replicaMessages = $this->getReplicaTypes('type', '[\w-]+');

        $replicaAccounts = $this->getReplicaAccounts();

        if (!empty($replicaMessages)) {
            //m_priority column will be non empty if there is at least one message of this type for current unuque server
            $stmt = ", MAX(FIND_IN_SET(m.`message_name`, '" . join(',', $replicaMessages) . "')) `m_priority` ";
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
              . '.' . ($row['m_priority'] > 0 ? $replicaMessages[$row['m_priority'] - 1] : 'all')
              . '.' . (!empty($replicaAccounts) ? (in_array($row['account_id'], $replicaAccounts) ? $row['account_id'] : 'all') : 'all');

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
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        $serverId = $request->serverId;

        $logger = \Scalr::getContainer()->logger(__CLASS__);

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
                                    // clear information about last request
                                    $dbserver->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::STORAGE_GROW_OPERATION_ID, null);
                                    $dbserver->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::STORAGE_GROW_SERVER_ID, null);
                                    $dbserver->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::STORAGE_GROW_LAST_ERROR, null);

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
                                    $dbserver->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::VOLUME_ID, $volumeConfig->id, Entity\FarmRoleSetting::TYPE_LCL);
                                    if ($engine == MYSQL_STORAGE_ENGINE::EBS) {
                                        $dbserver->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::DATA_STORAGE_EBS_SIZE, $volumeConfig->size, Entity\FarmRoleSetting::TYPE_CFG);
                                        $dbserver->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::DATA_STORAGE_EBS_TYPE, $volumeConfig->volumeType, Entity\FarmRoleSetting::TYPE_CFG);
                                        if ($volumeConfig->volumeType == 'io1') {
                                            $dbserver->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::DATA_STORAGE_EBS_IOPS, $volumeConfig->iops, Entity\FarmRoleSetting::TYPE_CFG);
                                        }
                                    } elseif ($engine == MYSQL_STORAGE_ENGINE::RAID_EBS) {
                                        $dbserver->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::DATA_STORAGE_RAID_DISK_SIZE, $volumeConfig->size, Entity\FarmRoleSetting::TYPE_CFG);
                                        $dbserver->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::DATA_STORAGE_RAID_EBS_DISK_TYPE, $volumeConfig->volumeType, Entity\FarmRoleSetting::TYPE_CFG);
                                        if ($volumeConfig->volumeType == 'io1') {
                                            $dbserver->GetFarmRoleObject()->SetSetting(Scalr_Db_Msr::DATA_STORAGE_RAID_EBS_DISK_IOPS, $volumeConfig->iops, Entity\FarmRoleSetting::TYPE_CFG);
                                        }
                                    }
                                    // Remove old
                                    $storageVolume->delete($oldVolumeId);
                                } catch (Exception $e) {
                                    \Scalr::getContainer()->logger(__CLASS__)->error(new FarmLogMessage(
                                        $dbserver->farmId,
                                        "Cannot save storage volume: {$e->getMessage()}",
                                        !empty($dbserver->serverId) ? $dbserver->serverId : null
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

                            } else if ($message->name == 'Grow MySQL/Percona data volume' || $message->name == 'mysql.grow-volume') {
                                $dbserver->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::STORAGE_GROW_LAST_ERROR, is_object($message->error) ? $message->error->message : $message->error);
                                $dbserver->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::STORAGE_GROW_OPERATION_ID, null);
                                $dbserver->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::STORAGE_GROW_SERVER_ID, null);
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

                        // Check API port;
                        $currentApiPort = $dbserver->GetProperty(SERVER_PROPERTIES::SZR_API_PORT);
                        if (!$currentApiPort) $currentApiPort = 8010;
                        if ($apiPort && $apiPort != $currentApiPort) {
                            $logger->warn(new FarmLogMessage(
                                $dbserver->farmId,
                                "Scalarizr API port was changed from {$currentApiPort} to {$apiPort}",
                                $dbserver->serverId
                            ));

                            $dbserver->SetProperty(SERVER_PROPERTIES::SZR_API_PORT, $apiPort);
                        }
                        // Check Control port
                        $currentCtrlPort = $dbserver->GetProperty(SERVER_PROPERTIES::SZR_CTRL_PORT);
                        if (!$currentCtrlPort) $currentCtrlPort = 8013;
                        if ($ctrlPort && $ctrlPort != $currentCtrlPort) {
                            $logger->warn(new FarmLogMessage(
                                $dbserver->farmId,
                                "Scalarizr Control port was changed from {$currentCtrlPort} to {$ctrlPort}",
                                $dbserver->serverId
                            ));

                            $dbserver->SetProperty(SERVER_PROPERTIES::SZR_CTRL_PORT, $ctrlPort);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_Win_HostDown) {
                        $event = $this->onHostDown($message, $dbserver);
                        if ($event === false) {
                            $doNotProcessMessage = true;
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
                        $event = $this->onHostDown($message, $dbserver);

                        if ($event == false) {
                            $doNotProcessMessage = true;
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_RebootStart) {
                        $event = new RebootBeginEvent($dbserver);
                    } elseif ($message instanceof Scalr_Messaging_Msg_RebootFinish) {
                        if ($dbserver->status == \SERVER_STATUS::RESUMING) {
                            
                            try {
                                // UPDATE IPs
                                $p = PlatformFactory::NewPlatform($dbserver->platform);
                                $ipaddresses = $p->GetServerIPAddresses($dbserver);
                                if (($ipaddresses['remoteIp'] && !$dbserver->remoteIp) || ($ipaddresses['localIp'] && !$dbserver->localIp)) {
                                    $dbserver->remoteIp = $update['remoteIp'] = $ipaddresses['remoteIp'];
    
                                    if (!$dbserver->localIp) {
                                        $update['localIp'] = $ipaddresses['localIp'] ? $ipaddresses['localIp'] : $message->localIp;
                                    }
                                }
                                
                                // Update type after resume on EC2
                                if ($dbserver->platform == \SERVER_PLATFORMS::EC2) {
                                    $cacheKey = sprintf('%s:%s', $dbserver->envId, $dbserver->cloudLocation);
                                    $type = $p->instancesListCache[$cacheKey][$dbserver->GetCloudServerID()]['type'];
                                
                                    if ($type != $dbserver->getType()) {
                                        $dbserver->setType($type);
                                    }
                                }
                            } catch (Exception $e) {
                                if (stristr($e->getMessage(), "AWS Error. Request DescribeInstances failed. Cannot establish connection to AWS server")) {
                                    $doNotProcessMessage = true;
                                } else {
                                    throw $e;
                                }
                            }
                                
                            // Set cloudstack Static IP if needed
                            if (PlatformFactory::isCloudstack($dbserver->platform) && !$dbserver->remoteIp) {
                                $remoteIp = CloudstackHelper::getSharedIP($dbserver);
                            
                                if ($remoteIp) {
                                    $dbserver->remoteIp = $update['remoteIp'] = $remoteIp;
                                }
                            }
                            
                            if (!$doNotProcessMessage) {
                                if (!empty($update)) {
                                    $dbserver->update($update);
                                    unset($update);
                                }
                                
                                $event = new \ResumeCompleteEvent($dbserver);
                            }
                        } elseif ($dbserver->status == \SERVER_STATUS::SUSPENDED) {
                            //We need to wait for Poller to update status to RESUMING before processing this message
                            $doNotProcessMessage = true;
                        } elseif ($dbserver->status == \SERVER_STATUS::RUNNING) {
                            if (!$dbserver->localIp && $message->localIp) {
                                $dbserver->update([ 'localIp' => $message->localIp ]);
                            }

                            $event = new RebootCompleteEvent($dbserver);
                        }
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
                                /* @var $volume \Scalr\Service\Aws\Ec2\DataType\VolumeData */
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
                            $farmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_PMA_USER, $message->pmaUser, Entity\FarmRoleSetting::TYPE_LCL);
                            $farmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_PMA_PASS, $message->pmaPassword, Entity\FarmRoleSetting::TYPE_LCL);
                        } else {
                            $farmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_PMA_REQUEST_TIME, "", Entity\FarmRoleSetting::TYPE_LCL);
                            $farmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_PMA_REQUEST_ERROR, $message->lastError, Entity\FarmRoleSetting::TYPE_LCL);
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

                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_SERVER_ID, $dbserver->serverId, Entity\FarmRoleSetting::TYPE_LCL);
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_URL, $mgmtURL, Entity\FarmRoleSetting::TYPE_LCL);
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME, "", Entity\FarmRoleSetting::TYPE_LCL);
                        } else {
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_SERVER_ID, "", Entity\FarmRoleSetting::TYPE_LCL);
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME, "", Entity\FarmRoleSetting::TYPE_LCL);
                            $farmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_ERROR_MSG, $message->lastError, Entity\FarmRoleSetting::TYPE_LCL);
                        }
                    } elseif ($message instanceof Scalr_Messaging_Msg_AmiScriptsMigrationResult) {
                        try {
                            //Open security group:
                            if ($dbserver->platform == SERVER_PLATFORMS::EC2) {
                                $info = PlatformFactory::NewPlatform($dbserver->platform)->GetServerExtendedInformation($dbserver);
                                $sg = empty($info['Security groups']) ? [] : explode(", ", $info['Security groups']);
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

                if ($event instanceof \AbstractServerEvent) {
                    \Scalr::FireEvent($dbserver->farmId, $event);
                }


            } catch (Exception $e) {
                $logger->error($e->getMessage());
            }
        }

        return $request;
    }

    private function onHostDown(\Scalr_Messaging_Msg $message, DBServer $dbserver)
    {
        // If insatnce is already SUSPENDED or TERMINATED it means that hostdown was already processed by CloudPoller
        // and no need to process it again
        if (in_array($dbserver->status, array(\SERVER_STATUS::SUSPENDED, \SERVER_STATUS::TERMINATED)))
            return true;

        $p = PlatformFactory::NewPlatform($dbserver->platform);
        $status = $p->GetServerRealStatus($dbserver);
        if ($dbserver->isOpenstack()) {
            $status = $p->GetServerRealStatus($dbserver);
            if (stristr($status->getName(), 'REBOOT') || stristr($status->getName(), 'HARD_REBOOT')) {
                //Hard reboot
                $isRebooting = true;
            } elseif ($status->isRunning()) {
                // Soft reboot
                $isRebooting = true;
            } elseif (!$status->isTerminated()) {
                $isStopping = true;
            }
        } elseif ($dbserver->platform == \SERVER_PLATFORMS::GCE) {
            if ($status->getName() == 'STOPPING') {
                // We don't know is this shutdown or stop so let's ignore HostDown
                // and wait for status change
                return false;
            } elseif ($status->getName() == 'RUNNING') {
                $isRebooting = true;
            } elseif ($status->isSuspended() && $dbserver->status != \SERVER_STATUS::PENDING_TERMINATE) {
                $isStopping = true;
            }
        } else {
            if ($status->isRunning()) {
                $isRebooting = true;
            } elseif (!$status->isTerminated()) {
                $isStopping = true;
            }
        }

        if ($isStopping) {
            $event = new HostDownEvent($dbserver);
            $event->isSuspended = true;
        } elseif ($isRebooting) {
            $event = new RebootBeginEvent($dbserver);
        } else {
            if ($dbserver->farmId) {
                $wasHostDownFired = $this->db->GetOne("SELECT id FROM events WHERE event_server_id = ? AND type = ? AND is_suspend = '0'", array(
                    $dbserver->serverId, 'HostDown'
                ));

                //TODO:

                if (!$wasHostDownFired)
                    $event = new HostDownEvent($dbserver);
            }
        }

        return $event;
    }

    private function onHello($message, DBServer $dbserver)
    {
        $logger = \Scalr::getContainer()->logger(__CLASS__);

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
                    $update['remoteIp'] = $message->remoteIp;
                }
                if (!$dbserver->localIp && $message->localIp) {
                    $update['localIp'] = $message->localIp;
                }
                if (!$message->behaviour) {
                    $message->behaviour = array('base');
                }
            }

            if (count($message->behaviour) == 1 && $message->behaviour[0] == ROLE_BEHAVIORS::CHEF)
                $message->behaviour[] = ROLE_BEHAVIORS::BASE;

            $dbserver->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR, @implode(",", $message->behaviour));

            if (!empty($update)) {
                $dbserver->update($update);
            }

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
                        OPENSTACK_SERVER_PROPERTIES::HOST_ID   => $osServer->hostId,
                        SERVER_PROPERTIES::ARCHITECTURE        => $message->architecture
                    ));

                    $dbserver->setType($osServer->flavor->id);

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
                                EC2_SERVER_PROPERTIES::AVAIL_ZONE    => $message->awsAvailZone,
                                EC2_SERVER_PROPERTIES::REGION        => substr($message->awsAvailZone, 0, -1),
                                SERVER_PROPERTIES::ARCHITECTURE      => $message->architecture
                            ));

                            $dbserver->setType($message->awsInstanceType);

                            break;

                        case SERVER_PLATFORMS::GCE:
                            $dbserver->SetProperties(array(
                                GCE_SERVER_PROPERTIES::CLOUD_LOCATION => $message->{$dbserver->platform}->cloudLocation,
                                GCE_SERVER_PROPERTIES::SERVER_ID      => $message->{$dbserver->platform}->serverId,
                                GCE_SERVER_PROPERTIES::SERVER_NAME    => $message->{$dbserver->platform}->serverName,
                                SERVER_PROPERTIES::ARCHITECTURE       => $message->architecture
                            ));

                            $dbserver->setType($message->{$dbserver->platform}->machineType);
                            break;

                        case SERVER_PLATFORMS::RACKSPACE:
                            $env = $dbserver->GetEnvironmentObject();
                            $ccProps = $env->cloudCredentials("{$dbserver->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER)}." . SERVER_PLATFORMS::RACKSPACE)->properties;
                            $cs = Scalr_Service_Cloud_Rackspace::newRackspaceCS(
                                $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_USERNAME],
                                $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_API_KEY],
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
                                RACKSPACE_SERVER_PROPERTIES::HOST_ID    => $csServer->hostId,
                                SERVER_PROPERTIES::ARCHITECTURE         => $message->architecture
                            ));

                            $dbserver->setType($csServer->flavorId);
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
        $logger = \Scalr::getContainer()->logger(__CLASS__);

        if ($dbserver->status == SERVER_STATUS::PENDING) {
            $platform = PlatformFactory::NewPlatform($dbserver->platform);
            // Update server crypto key
            $srv_props = array();
            if ($message->cryptoKey) {
                $srv_props[SERVER_PROPERTIES::SZR_KEY] = trim($message->cryptoKey);
                $srv_props[SERVER_PROPERTIES::SZR_KEY_TYPE] = SZR_KEY_TYPE::PERMANENT;
            }

            if ($dbserver->isCloudstack()) {
                $remoteIp = CloudstackHelper::getSharedIP($dbserver);
            }
            if ($dbserver->isOpenstack()) {
                if ($dbserver->farmRoleId) {
                    $ipPool = $dbserver->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::OPENSTACK_IP_POOL);
                    if ($ipPool && empty($dbserver->remoteIp)) {
                        return false;
                    } else {
                        $remoteIp = $dbserver->remoteIp;
                    }
                }

                if (!$dbserver->cloudLocationZone) {
                    $info = $platform->GetServerExtendedInformation($dbserver);
                    if (!empty($info['Availability zone'])) {
                        $dbserver->cloudLocationZone = $update['cloudLocationZone'] = $info['Availability zone'];
                        $dbserver->SetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION_ZONE, $dbserver->cloudLocationZone);
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
            $update['remoteIp'] = $remoteIp;
            $dbserver->update($update);

            //Update auto-update settings
            //TODO: Check auto-update client version
            if ($dbserver->IsSupported('0.7.225') && !$dbserver->IsSupported('2.7.10')) {
                $dbserver->SetProperties($srv_props);
                try {
                    $repo = $dbserver->GetFarmRoleObject()->GetSetting(Scalr_Role_Behavior::ROLE_BASE_SZR_UPD_REPOSITORY);
                    if (!$repo)
                        $repo = $dbserver->GetFarmObject()->GetSetting(Entity\FarmSetting::SZR_UPD_REPOSITORY);
                    $schedule = $dbserver->GetFarmObject()->GetSetting(Entity\FarmSetting::SZR_UPD_SCHEDULE);
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
                if (!$master[0] && !(int) $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_SLAVE_TO_MASTER)) {
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
                    $dbFarmRole->SetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER, 0, Entity\FarmRoleSetting::TYPE_LCL);
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
        $logger = \Scalr::getContainer()->logger(__CLASS__);

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
                        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_REPL_PASSWORD, $mysqlData->replPassword, Entity\FarmRoleSetting::TYPE_LCL);
                        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_ROOT_PASSWORD, $mysqlData->rootPassword, Entity\FarmRoleSetting::TYPE_LCL);
                        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_STAT_PASSWORD, $mysqlData->statPassword, Entity\FarmRoleSetting::TYPE_LCL);
                    }
                    $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_LOG_FILE, $mysqlData->logFile, Entity\FarmRoleSetting::TYPE_LCL);
                    $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_LOG_POS, $mysqlData->logPos, Entity\FarmRoleSetting::TYPE_LCL);
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
                                            'type'         => $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_DATA_STORAGE_ENGINE),
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
                                $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_SCALR_VOLUME_ID, $storageVolume->id, Entity\FarmRoleSetting::TYPE_LCL);
                            } catch (Exception $e) {
                                $logger->error(new FarmLogMessage(
                                    $event->DBServer->farmId,
                                    "Cannot save storage volume: {$e->getMessage()}",
                                    !empty($event->DBServer->serverId) ? $event->DBServer->serverId : null
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
                                            'type'        => $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_DATA_STORAGE_ENGINE),
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
                                $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_SCALR_SNAPSHOT_ID, $storageSnapshot->id, Entity\FarmRoleSetting::TYPE_LCL);
                            } catch (Exception $e) {
                                $logger->error(new FarmLogMessage(
                                    $event->DBServer->farmId,
                                    "Cannot save storage snapshot: {$e->getMessage()}",
                                    !empty($event->DBServer->serverId) ? $event->DBServer->serverId : null
                                ));
                            }
                        }
                    } else {
                        //@deprecated
                        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_SNAPSHOT_ID, $mysqlData->snapshotId, Entity\FarmRoleSetting::TYPE_LCL);
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
        $logger = \Scalr::getContainer()->logger(__CLASS__);

        $dbserver->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::MYSQL_SLAVE_TO_MASTER, 0, Entity\FarmRoleSetting::TYPE_LCL);

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
                                    'type'         => $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_DATA_STORAGE_ENGINE),
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
                            $dbserver->farmId,
                            "Cannot save storage volume: {$e->getMessage()}",
                            !empty($dbserver->serverId) ? $dbserver->serverId : null
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
                            'type'        => $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_DATA_STORAGE_ENGINE),
                            'platform'    => $dbserver->platform,
                            'description' => "MySQL data bundle created automatically by Scalr",
                            'ismysql'     => true
                        ));
                        $snapshot->setConfig($message->snapshotConfig);
                        $snapshot->save(true);
                        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_SCALR_SNAPSHOT_ID, $snapshot->id, Entity\FarmRoleSetting::TYPE_LCL);
                        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_LOG_FILE, $message->logFile, Entity\FarmRoleSetting::TYPE_LCL);
                        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_LOG_POS, $message->logPos, Entity\FarmRoleSetting::TYPE_LCL);
                    } catch (Exception $e) {
                        $logger->error(new FarmLogMessage(
                            $dbserver->farmId,
                            "Cannot save storage snapshot: {$e->getMessage()}",
                            !empty($dbserver->serverId) ? $dbserver->serverId : null
                        ));
                    }
                }
            } else {
                // TODO: delete old slave volume if new one was created
                $dbFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_MASTER_EBS_VOLUME_ID, $message->volumeId, Entity\FarmRoleSetting::TYPE_LCL);
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
