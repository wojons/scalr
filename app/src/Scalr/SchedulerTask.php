<?php

use Scalr\LogCollector\AuditLogger;
use Scalr\LoggerAwareTrait;

class Scalr_SchedulerTask extends Scalr_Model
{
    use LoggerAwareTrait;

    protected $dbTableName = 'scheduler';
    protected $dbPrimaryKey = 'id';
    protected $dbMessageKeyNotFound = 'Scheduler task #%s not found in database';

    const SCRIPT_EXEC = 'script_exec';
    const TERMINATE_FARM = 'terminate_farm';
    const LAUNCH_FARM = 'launch_farm';
    const FIRE_EVENT = 'fire_event';

    const STATUS_ACTIVE = "Active";
    const STATUS_SUSPENDED = "Suspended";

    const TARGET_FARM = 'farm';
    const TARGET_ROLE = 'role';
    const TARGET_INSTANCE = 'instance';

    protected $dbPropertyMap = array(
        'id'                    => 'id',
        'name'                  => 'name',
        'type'                  => 'type',
        'comments'              => 'comments',
        'target_id'             => array('property' => 'targetId'),
        'target_server_index'   => array('property' => 'targetServerIndex'),
        'target_type'           => array('property' => 'targetType'),
        'script_id'             => array('property' => 'scriptId'),
        'start_time'            => array('property' => 'startTime'),
        'last_start_time'       => array('property' => 'lastStartTime'),
        'restart_every'         => array('property' => 'restartEvery'),
        'config'                => array('property' => 'config', 'type' => 'serialize'),
        'timezone'              => 'timezone',
        'status'                => 'status',
        'account_id'            => array('property' => 'accountId'),
        'env_id'                => array('property' => 'envId')
    );

    public
        $id,
        $name,
        $type,
        $comments,
        $targetId,
        $targetServerIndex,
        $targetType,
        $scriptId,
        $startTime,
        $lastStartTime,
        $restartEvery,
        $config,
        $timezone,
        $status,
        $accountId,
        $envId;

    /**
     *
     * @return Scalr_SchedulerTask
     */
    public static function init($className = null)
    {
        return parent::init();
    }

    public static function getTypeByName($name)
    {
        switch($name) {
            case self::SCRIPT_EXEC:
                return "Execute script";
            case self::TERMINATE_FARM:
                return "Terminate farm";
            case self::LAUNCH_FARM:
                return "Launch farm";
            case self::FIRE_EVENT:
                return "Fire event";
        }
    }

    public function updateLastStartTime()
    {
        $this->lastStartTime = date('Y-m-d H:i:s');
        $this->db->Execute("UPDATE scheduler SET last_start_time = ? WHERE id = ?", [$this->lastStartTime, $this->id]);
    }

    /**
     * Checks whether this task was executed recently
     *
     * @return boolean Returns TRUE if either the task was executed less than 30 seconds ago or
     *                 it has never been executed.
     */
    public function isExecutedRecently()
    {
        //Last start time less than 30 seconds
        return $this->lastStartTime && (time() - strtotime($this->lastStartTime)) < 30;
    }

    /**
     * Executes the task
     *
     * @return bool $manual  optional Whether task is executed by manual
     * @throws Exception
     */
    public function execute($manual = false)
    {
        $farmRoleNotFound = false;
        $logger = $this->getLogger() ?: \Scalr::getContainer()->logger(__CLASS__);

        switch ($this->type) {
            case self::LAUNCH_FARM:
                try {
                    $farmId = $this->targetId;

                    $DBFarm = DBFarm::LoadByID($farmId);

                    if ($DBFarm->Status == FARM_STATUS::TERMINATED) {
                        // launch farm
                        Scalr::FireEvent($farmId, new FarmLaunchedEvent(
                            true, // Mark instances as Active
                            null, // User
                            ['service.scheduler.task_id' => $this->id]
                        ));

                        $logger->info(sprintf("Farm #{$farmId} successfully launched"));
                    } elseif ($DBFarm->Status == FARM_STATUS::RUNNING) {
                        // farm is running
                        $logger->info(sprintf("Farm #{$farmId} is already running"));
                    } else {
                        // farm can't be launched
                        $logger->info(sprintf("Farm #{$farmId} can't be launched because of it's status: {$DBFarm->Status}"));
                    }
                } catch (Exception $e) {
                    $farmRoleNotFound  = true;
                    $logger->info(sprintf("Farm #{$farmId} was not found and can't be launched"));
                }

                break;

            case self::TERMINATE_FARM:
                try {
                    // get config settings
                    $farmId = $this->targetId;

                    $deleteDNSZones = (int)$this->config['deleteDNSZones'];
                    $deleteCloudObjects = (int)$this->config['deleteCloudObjects'];
                    $keepCloudObjects = $deleteCloudObjects == 1 ? 0 : 1;

                    $DBFarm = DBFarm::LoadByID($farmId);

                    if ($DBFarm->Status == FARM_STATUS::RUNNING) {
                        // Terminate farm
                        Scalr::FireEvent($farmId, new FarmTerminatedEvent(
                            $deleteDNSZones,
                            $keepCloudObjects,
                            false,
                            $keepCloudObjects,
                            true, // Force termination
                            null, // User
                            ['service.scheduler.task_id' => $this->id]
                        ));

                        $logger->info(sprintf("Farm successfully terminated"));
                    } else {
                        $logger->info(sprintf("Farm #{$farmId} can't be terminated because of it's status"));
                    }
                } catch (Exception $e) {
                    $farmRoleNotFound  = true;
                    $logger->info(sprintf("Farm #{$farmId} was not found and can't be terminated"));
                }
                break;

            case self::FIRE_EVENT:

                switch($this->targetType) {
                    case self::TARGET_FARM:
                        $DBFarm = DBFarm::LoadByID($this->targetId);
                        $farmId = $DBFarm->ID;
                        $farmRoleId = null;

                        $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE `status` IN (?,?) AND farm_id = ?",
                            array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmId)
                        );
                        break;

                    case self::TARGET_ROLE:
                        $farmRoleId = $this->targetId;
                        $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE `status` IN (?,?) AND farm_roleid = ?",
                            array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmRoleId)
                        );
                        break;

                    case self::TARGET_INSTANCE:
                        $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE `status` IN (?,?) AND farm_roleid = ? AND `index` = ? ",
                            array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $this->targetId, $this->targetServerIndex)
                        );
                        break;
                }

                if (count($servers) == 0)
                    throw new Exception("No running Servers found. Event was not fired.");

                foreach ($servers as $server) {
                    /* @var $dbServer DBServer */
                    $dbServer = DBServer::LoadByID($server['server_id']);
                    Scalr::FireEvent($dbServer->farmId, new CustomEvent($dbServer, $this->config['eventName'], (array)$this->config['eventParams']));
                }

                break;

            case self::SCRIPT_EXEC:
                // generate event name
                $eventName = "Scheduler (TaskID: {$this->id})";
                if ($manual)
                    $eventName .= ' (manual)';

                try {
                    if (! \Scalr\Model\Entity\Script::findPk($this->config['scriptId']))
                        throw new Exception('Script not found');

                    // get executing object by target_type variable
                    switch($this->targetType) {
                        case self::TARGET_FARM:
                            $DBFarm = DBFarm::LoadByID($this->targetId);
                            $farmId = $DBFarm->ID;
                            $farmRoleId = null;

                            $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE is_scalarized = 1 AND `status` IN (?,?) AND farm_id = ?",
                                array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmId)
                            );
                            break;

                        case self::TARGET_ROLE:
                            $farmRoleId = $this->targetId;
                            $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE is_scalarized = 1 AND `status` IN (?,?) AND farm_roleid = ?",
                                array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmRoleId)
                            );
                            break;

                        case self::TARGET_INSTANCE:
                            $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE is_scalarized = 1 AND `status` IN (?,?) AND farm_roleid = ? AND `index` = ? ",
                                array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $this->targetId, $this->targetServerIndex)
                            );
                            break;
                    }

                    if ($servers) {
                        $scriptSettings = array(
                            'version' => $this->config['scriptVersion'],
                            'scriptid' => $this->config['scriptId'],
                            'timeout' => $this->config['scriptTimeout'],
                            'issync' => $this->config['scriptIsSync'],
                            'params' => serialize($this->config['scriptOptions']),
                            'type' => Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_SCALR
                        );

                        // send message to start executing task (starts script)
                        foreach ($servers as $server) {
                            $DBServer = DBServer::LoadByID($server['server_id']);

                            $msg = new Scalr_Messaging_Msg_ExecScript($eventName);
                            $msg->setServerMetaData($DBServer);

                            $script = Scalr_Scripting_Manager::prepareScript($scriptSettings, $DBServer);

                            if ($script) {
                                $DBServer->executeScript($script, $msg);
                                $this->getContainer()->auditlogger->log('script.execute', $script, $DBServer, $this->id);
                            }
                        }
                    } else {
                        $farmRoleNotFound = true;
                    }
                } catch (Exception $e) {
                    // farm or role not found.
                    $farmRoleNotFound  = true;
                    $logger->warn(sprintf("Farm, role or instances were not found, script can't be executed"));
                }
                break;
        }

        return !$farmRoleNotFound;
    }
}
