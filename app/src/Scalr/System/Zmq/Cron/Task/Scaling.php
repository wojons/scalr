<?php

namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception;
use stdClass;
use Scalr\Modules\PlatformFactory;
use Scalr\System\Zmq\Cron\AbstractTask;
use \DBServer;
use \FARM_STATUS;
use \Scalr_Account;
use \Scalr_Environment;
use \DBFarm;
use \DBFarmRole;
use \ROLE_BEHAVIORS;
use \SERVER_PLATFORMS;
use \SERVER_PROPERTIES;
use \EC2_SERVER_PROPERTIES;
use \SERVER_STATUS;
use \ServerCreateInfo;
use \Scalr_Db_Msr;
use \Scalr_Role_Behavior;
use \Scalr_Scaling_Manager;
use \Scalr_Scaling_Decision;
use \LOG_CATEGORY;
use \FarmLogMessage;
use Scalr\Model\Entity;

/**
 * Scaling
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (26.11.2014)
 */
class Scaling extends AbstractTask
{

    /**
     * @var \ADODB_mysqli
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->db = \Scalr::getDb();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        //This is necessary for the next query
        $this->db->Execute("SET @fid := NULL, @num := NULL");

        //Selects one Farm Role from each Farm with synchronous lauhch type and
        //all Farm Roles from each Farm with asynchronous launch type
        $rs = $this->db->Execute("
            SELECT * FROM (
                SELECT IF(f.`farm_roles_launch_order` = 1, @num := IF(@fid = f.`id`, @num + 1, 1), 1) `row_number`,
            	    @fid := f.`id` `farm_id`,
            	    f.`name` `farm_name`,
            	    fr.`id` `farm_role_id`,
            	    rs.`value` `dt_last_polling`,
            	    rs2.`value` `polling_interval`,
                    f.`farm_roles_launch_order`
                FROM `farms` f
                JOIN `clients` c ON c.`id` = f.`clientid`
                JOIN `client_environments` ce ON ce.`id` = f.`env_id`
                JOIN `farm_roles` fr ON fr.`farmid` = f.`id`
                LEFT JOIN `farm_role_settings` rs  ON rs.`farm_roleid` = fr.`id` AND rs.`name` = ?
                LEFT JOIN `farm_role_settings` rs2 ON rs2.`farm_roleid` = fr.`id` AND rs2.`name` = ?
                WHERE c.`status` = ? AND ce.`status` = ? AND f.`status` = ?
                AND (rs.`value` IS NULL OR UNIX_TIMESTAMP() > rs.`value` + IFNULL(rs2.`value`, 1) * 60)
                ORDER BY f.`id`, fr.`launch_index`
            ) t WHERE t.`row_number` = 1
        ", [
            Entity\FarmRoleSetting::SCALING_LAST_POLLING_TIME,
            Entity\FarmRoleSetting::SCALING_POLLING_INTERVAL,
            Scalr_Account::STATUS_ACTIVE,
            Scalr_Environment::STATUS_ACTIVE,
            FARM_STATUS::RUNNING
        ]);

        while ($row = $rs->FetchRow()) {
            $obj = new stdClass();
            $obj->farmId = $row['farm_id'];
            $obj->farmName = $row['farm_name'];

            if (!$row['farm_roles_launch_order']) {
                //Asynchronous launch order
                $obj->farmRoleId = $row['farm_role_id'];
                $obj->lastPollingTime = $row['last_polling_time'];
                $obj->pollingInterval = $row['polling_interval'];
            }

            $queue->append($obj);
        }

        if ($count = $queue->count()) {
            $this->getLogger()->info("%d running farm roles found.", $count);
        }

        return $queue;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        //Warming up static DI cache
        \Scalr::getContainer()->warmup();

        // Reconfigure observers
        \Scalr::ReconfigureObservers();

        if (!isset($request->farmRoleId)) {
            //This is the farm with synchronous launch of roles
            try {
                $DBFarm = DBFarm::LoadByID($request->farmId);

                if ($DBFarm->Status != FARM_STATUS::RUNNING) {
                    $this->getLogger()->warn("[FarmID: %d] Farm isn't running. There is no need to scale it.", $DBFarm->ID);
                    return false;
                }
            } catch (Exception $e) {
                $this->getLogger()->error("Could not load farm '%s' with ID:%d", $request->farmName, $request->farmId);
                throw $e;
            }

            //Gets the list of the roles
            $list = $DBFarm->GetFarmRoles();
        } else {
            //This is asynchronous lauhch
            try {
                $DBFarmRole = DBFarmRole::LoadByID($request->farmRoleId);

                if ($DBFarmRole->getFarmStatus() != FARM_STATUS::RUNNING) {
                    //We don't need to handle inactive farms
                    return false;
                }
            } catch (Exception $e) {
                $this->getLogger()->error("Could not load FarmRole with ID:%d", $request->farmRoleId);
                throw $e;
            }

            $list = [$DBFarmRole];
        }

        $this->getLogger()->debug(
            "Processing %s FarmRoles",
            count($list)
        );

        foreach ($list as $DBFarmRole) {
            // Set Last polling time
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_LAST_POLLING_TIME, time(), Entity\FarmRoleSetting::TYPE_LCL);

            $disabledScaling = false;
            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_ENABLED) != '1') {
                if ($DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB) ||
                    $DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ) ||
                    $DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER)) {
                    // For Mongo, RabbitMQ and VPC Router we need to launch first instance (or maintain 1 instance running)
                    // When 1 instance is already running, the rest is fully manual
                    $roleTotalInstances = $DBFarmRole->GetRunningInstancesCount() + $DBFarmRole->GetPendingInstancesCount();

                    if ($roleTotalInstances != 0) {
                        $disabledScaling = true;
                    }
                } else {
                    $disabledScaling = true;
                }

                if ($disabledScaling) {
                    $this->getLogger()->info(
                        "[FarmID: %d] Scaling is disabled for role '%s'. Skipping...",
                        $request->farmId, $DBFarmRole->Alias
                    );
                    continue;
                }
            }

            $farmRoleName = $DBFarmRole->Alias ? $DBFarmRole->Alias : $DBFarmRole->GetRoleObject()->name;

            // Get current count of running and pending instances.
            $this->getLogger()->info(sprintf("Processing role '%s'", $farmRoleName));

            $scalingManager = new Scalr_Scaling_Manager($DBFarmRole);

            //Replacing the logger
            $scalingManager->logger = $this->getLogger();

            $scalingDecision = $scalingManager->makeScalingDecision();
            $scalingDecisionAlgorithm = $scalingManager->decisonInfo;

            $this->getLogger()->info(sprintf("Decision '%s' (%s)", $scalingDecision, $scalingDecisionAlgorithm));

            if ($scalingDecision == Scalr_Scaling_Decision::STOP_SCALING) {
                return;
            }

            if ($scalingDecision == Scalr_Scaling_Decision::NOOP) {
                continue;
            } else if ($scalingDecision == Scalr_Scaling_Decision::DOWNSCALE) {
                /*
                 Timeout instance's count decrease. Decreases instances count after scaling
                 resolution the spare instances are running for selected timeout interval
                 from scaling EditOptions
                */

                // We have to check timeout limits before new scaling (downscaling) process will be initiated
                if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_DOWNSCALE_TIMEOUT_ENABLED)) {
                    // if the farm timeout is exceeded
                    // checking timeout interval.

                    $last_down_scale_data_time =  $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_DOWNSCALE_DATETIME);
                    $timeout_interval = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_DOWNSCALE_TIMEOUT);

                    // check the time interval to continue scaling or cancel it...
                    if ((time() - $last_down_scale_data_time) < $timeout_interval * 60) {
                        // if the launch time is too small to terminate smth in this role -> go to the next role in foreach()
                        \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                            $request->farmId,
                            sprintf("Waiting for downscaling timeout on farm %s, role %s",
                                $request->farmName,
                                $DBFarmRole->Alias
                            )
                        ));

                        continue;
                    }
                } // end Timeout instance's count decrease

                $sort = ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_KEEP_OLDEST) == 1) ? 'DESC' : 'ASC';

                $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status = ? AND farm_roleid=? ORDER BY dtadded {$sort}",
                    array(SERVER_STATUS::RUNNING, $DBFarmRole->ID)
                );

                $got_valid_instance = false;

                // Select instance that will be terminated
                //
                // Instances ordered by uptime (oldest wil be choosen)
                // Instance cannot be mysql master
                // Choose the one that was rebundled recently
                while (!$got_valid_instance && count($servers) > 0) {
                    $item = array_shift($servers);
                    $DBServer = DBServer::LoadByID($item['server_id']);

                    if ($DBServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
                        $serversCount = count($DBServer->GetFarmRoleObject()->GetServersByFilter(array(), array('status' => array(SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED))));
                        if ($DBServer->index == 1 && $serversCount > 1)
                            continue;
                    }

                    if ($DBServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED))
                        continue;

                    // Exclude db master
                    if ($DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER) != 1 && $DBServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER) != 1)
                        $got_valid_instance = true;

                    //Check safe shutdown
                    if ($DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::SCALING_SAFE_SHUTDOWN) == 1) {
                        if ($DBServer->IsSupported('0.11.3')) {
                            try {
                                $res  = $DBServer->scalarizr->system->callAuthShutdownHook();
                            } catch (Exception $e) {
                                $res = $e->getMessage();
                            }
                        } else {
                            \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                                $request->farmId,
                                sprintf("Safe shutdown enabled, but not supported by scalarizr installed on server '%s'. Ignoring.",
                                    $DBServer->serverId
                                ),
                                !empty($DBServer->serverId) ? $DBServer->serverId : null
                            ));
                        }

                        if ($res != '1') {
                            \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                                $request->farmId,
                                sprintf("Safe shutdown enabled. Server '%s'. Script returned '%s' skipping it.",
                                    $DBServer->serverId,
                                    $res
                                ),
                                !empty($DBServer->serverId) ? $DBServer->serverId : null
                            ));

                            $got_valid_instance = false;
                        }
                    }
                } // end while

                if ($DBServer && $got_valid_instance) {
                    $this->getLogger()->info(sprintf("Server '%s' selected for termination...", $DBServer->serverId));
                    $allow_terminate = false;

                    if ($DBServer->platform == SERVER_PLATFORMS::EC2) {
                        $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);
                        // Shutdown an instance just before a full hour running
                        if (!$DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::SCALING_IGNORE_FULL_HOUR)) {
                            $response = $aws->ec2->instance->describe($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID))->get(0);
                            if ($response && count($response->instancesSet)) {
                                $launch_time = $response->instancesSet->get(0)->launchTime->getTimestamp();
                                $time = 3600 - (time() - $launch_time) % 3600;
                                // Terminate instance in < 10 minutes for full hour.
                                if ($time <= 600) {
                                    $allow_terminate = true;
                                } else {
                                    $timeout = round(($time - 600) / 60, 1);

                                    \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                                        $request->farmId,
                                        sprintf("Farm %s, role %s scaling down ({$scalingDecisionAlgorithm}). Server '%s' will be terminated in %s minutes. Launch time: %s",
                                            $request->farmName,
                                            $DBServer->GetFarmRoleObject()->Alias,
                                            $DBServer->serverId,
                                            $timeout,
                                            $response->instancesSet->get(0)->launchTime->format('c')
                                        ),
                                        $DBServer->serverId
                                    ));
                                }
                            }
                        } else {
                            $allow_terminate = true;
                        }
                        //Releases memory
                        $DBServer->GetEnvironmentObject()->getContainer()->release('aws');
                        unset($aws);
                    } else
                        $allow_terminate = true;

                    if ($allow_terminate) {
                        $terminateStrategy = $DBFarmRole->GetSetting(Scalr_Role_Behavior::ROLE_BASE_TERMINATE_STRATEGY);
                        if (!$terminateStrategy)
                            $terminateStrategy = 'terminate';

                        try {
                            if ($terminateStrategy == 'terminate') {
                                $DBServer->terminate(DBServer::TERMINATE_REASON_SCALING_DOWN, false);

                                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_DOWNSCALE_DATETIME, time(), Entity\FarmRoleSetting::TYPE_LCL);

                                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                                    $request->farmId,
                                    sprintf("Farm %s, role %s scaling down ({$scalingDecisionAlgorithm}). Server '%s' marked as 'Pending terminate' and will be fully terminated in 3 minutes.",
                                        $request->farmName,
                                        $DBServer->GetFarmRoleObject()->Alias,
                                        $DBServer->serverId
                                    ),
                                    $DBServer->serverId
                                ));
                            } else {
                                $DBServer->suspend('SCALING_DOWN', false);

                                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_DOWNSCALE_DATETIME, time(), Entity\FarmRoleSetting::TYPE_LCL);

                                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                                    $request->farmId, sprintf("Farm %s, role %s scaling down ({$scalingDecisionAlgorithm}). Server '%s' marked as 'Pending suspend' and will be fully suspended in 3 minutes.",
                                        $request->farmName,
                                        $DBServer->GetFarmRoleObject()->Alias,
                                        $DBServer->serverId
                                    ),
                                    $DBServer->serverId
                                ));
                            }
                        } catch (Exception $e) {
                            $this->getLogger()->fatal(sprintf("Cannot %s %s: %s",
                                $terminateStrategy,
                                $request->farmId,
                                $DBServer->serverId
                            ));
                        }
                    }
                } else {
                    $this->getLogger()->warn(sprintf(
                        "[FarmID: %s] Scalr unable to determine what instance it should terminate (FarmRoleID: %s). Skipping...",
                        $request->farmId,
                        $DBFarmRole->ID
                    ));
                }

                //break;
            } elseif ($scalingDecision == Scalr_Scaling_Decision::UPSCALE) {
                /*
                Timeout instance's count increase. Increases  instance's count after
                scaling resolution 'need more instances' for selected timeout interval
                from scaling EditOptions
                */
                if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_UPSCALE_TIMEOUT_ENABLED)) {
                    // if the farm timeout is exceeded
                    // checking timeout interval.
                    $last_up_scale_data_time =  $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_UPSCALE_DATETIME);
                    $timeout_interval = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_UPSCALE_TIMEOUT);

                    // check the time interval to continue scaling or cancel it...
                    if (time() - $last_up_scale_data_time < $timeout_interval * 60) {
                        // if the launch time is too small to terminate smth in this role -> go to the next role in foreach()
                        \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(sprintf("Waiting for upscaling timeout on farm %s, role %s",
                            $request->farmName,
                            $DBFarmRole->Alias
                        ));

                        continue;
                    }
                }// end Timeout instance's count increase


                //Check DBMsr. Do not start slave during slave2master process
                $isDbMsr = $DBFarmRole->GetRoleObject()->getDbMsrBehavior();
                if ($isDbMsr) {
                    if ($DBFarmRole->GetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER)) {
                        $runningServers = $DBFarmRole->GetRunningInstancesCount();
                        if ($runningServers > 0) {
                            \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                                $request->farmId,
                                sprintf("Role is in slave2master promotion process. Do not launch new slaves while there is no active slaves")
                            ));

                            continue;
                        } else {
                            $DBFarmRole->SetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER, 0, Entity\FarmRoleSetting::TYPE_LCL);
                        }
                    }
                }

                if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_ONE_BY_ONE) == 1) {
                    $pendingInstances = $DBFarmRole->GetPendingInstancesCount();
                    if ($pendingInstances > 0) {
                        \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                            $request->farmId,
                            sprintf("There are %s pending intances of %s role on % farm. Waiting...",
                                $pendingInstances,
                                $DBFarmRole->Alias,
                                $request->farmName
                            )
                        ));

                        continue;
                    }
                }

                $fstatus = $this->db->GetOne("SELECT status FROM farms WHERE id=? LIMIT 1", array($request->farmId));

                if ($fstatus != FARM_STATUS::RUNNING) {
                    $this->getLogger()->warn("[FarmID: {$request->farmId}] Farm terminated. There is no need to scale it.");
                    return;
                }

                $terminateStrategy = $DBFarmRole->GetSetting(Scalr_Role_Behavior::ROLE_BASE_TERMINATE_STRATEGY);

                if (!$terminateStrategy)
                    $terminateStrategy = 'terminate';

                $suspendedServer = null;

                if ($terminateStrategy == 'suspend') {
                    $suspendedServers = $DBFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::SUSPENDED));

                    if (count($suspendedServers) > 0)
                        $suspendedServer = array_shift($suspendedServers);
                }

                if ($terminateStrategy == 'suspend' && $suspendedServer) {
                    \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                        $request->farmId,
                        sprintf("Farm %s, role %s scaling up ($scalingDecisionAlgorithm). Found server to resume. ServerID = %s.",
                            $request->farmName,
                            $suspendedServer->GetFarmRoleObject()->Alias,
                            $suspendedServer->serverId
                        ),
                        !empty($suspendedServer->serverId) ? $suspendedServer->serverId : null
                    ));
                }

                if ($terminateStrategy == 'terminate' || !$suspendedServer ||
                    (!PlatformFactory::isOpenstack($suspendedServer->platform) &&
                    $suspendedServer->platform != SERVER_PLATFORMS::EC2 && $suspendedServer->platform != SERVER_PLATFORMS::GCE)) {
                    $ServerCreateInfo = new ServerCreateInfo($DBFarmRole->Platform, $DBFarmRole);

                    try {
                        $DBServer = \Scalr::LaunchServer($ServerCreateInfo, null, false, DBServer::LAUNCH_REASON_SCALING_UP);

                        $DBFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_UPSCALE_DATETIME, time(), Entity\FarmRoleSetting::TYPE_LCL);

                        \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                            $request->farmId,
                            sprintf("Farm %s, role %s scaling up ($scalingDecisionAlgorithm). Starting new instance. ServerID = %s.",
                                $request->farmName,
                                $DBServer->GetFarmRoleObject()->Alias,
                                $DBServer->serverId
                            ),
                            $DBServer->serverId
                        ));
                    } catch (Exception $e) {
                        \Scalr::getContainer()->logger(LOG_CATEGORY::SCALING)->error($e->getMessage());
                    }
                } else {
                    $platform = PlatformFactory::NewPlatform($suspendedServer->platform);
                    $platform->ResumeServer($suspendedServer);
                }
            }
        }

        return true;
    }
}
