<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception;
use stdClass;
use Scalr\Modules\PlatformFactory;
use Scalr\System\Zmq\Cron\AbstractTask;
use \DBServer;
use \FARM_STATUS;
use \Scalr_Environment;
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
use \Logger;
use \FarmLogMessage;

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

        $rs = $this->db->Execute("
            SELECT f.id `farm_id`, f.name `farm_name`, fr.id `farm_role_id`
            FROM farms f
            JOIN clients c ON c.id = f.clientid
            JOIN client_environments ce ON ce.id = f.env_id
            JOIN farm_roles fr ON fr.farmid = f.id
            WHERE c.status = 'Active' AND ce.status = ? AND f.status = ?
            ORDER BY fr.launch_index
        ", [Scalr_Environment::STATUS_ACTIVE, FARM_STATUS::RUNNING]);

        while ($row = $rs->FetchRow()) {
            $obj = new stdClass();
            $obj->farmId = $row['farm_id'];
            $obj->farmRoleId = $row['farm_role_id'];
            $obj->farmName = $row['farm_name'];

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
        $logger = Logger::getLogger(__CLASS__);

        if (!isset($request->farmRoleId)) {
            $this->getLogger()->error("Invalid request. No farm role identifier.");
            return;
        }

        try {
            $DBFarmRole = DBFarmRole::LoadByID($request->farmRoleId);

            if ($DBFarmRole->getFarmStatus() != FARM_STATUS::RUNNING) {
                //We don't need to handle inactive farms
                return false;
            }
        } catch (Exception $e) {
            $this->getLogger()->error("Could not load farm with ID:%d", $request->farmRoleId);
            throw $e;
        }

        //Warming up static DI cache
        \Scalr::getContainer()->warmup();

        // Reconfigure observers
        \Scalr::ReconfigureObservers();

        for ($i = 0; $i < 10; $i++) {
            if ($DBFarmRole->NewRoleID != '') {
                $logger->warn("[FarmID: {$request->farmId}] Role '{$DBFarmRole->GetRoleObject()->name}' is synchronized. This role will not be scalled.");
                return false;
            }

            if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_ENABLED) != '1' &&
                !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB) &&
                !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ) &&
                !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER)) {
                $logger->info("[FarmID: {$request->farmId}] Scaling disabled for role '{$DBFarmRole->GetRoleObject()->name}'. Skipping...");
                return false;
            }

            // Get polling interval in seconds
            $polling_interval = $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_POLLING_INTERVAL) * 60;
            $dt_last_polling = $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_LAST_POLLING_TIME);

            if ($dt_last_polling && ($dt_last_polling + $polling_interval) > time() && $i == 0) {
                $logger->info("Polling interval: every {$polling_interval} seconds");
                continue;
            }

            // Set Last polling time
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_LAST_POLLING_TIME, time(), DBFarmRole::TYPE_LCL);

            // Get current count of running and pending instances.
            $logger->info(sprintf("Processing role '%s'", $DBFarmRole->GetRoleObject()->name));

            $scalingManager = new Scalr_Scaling_Manager($DBFarmRole);
            $scalingDecision = $scalingManager->makeScalingDecition();
            $scalingDecisionAlgorithm = $scalingManager->decisonInfo;

            if ($scalingDecision == Scalr_Scaling_Decision::STOP_SCALING) {
                return;
            }

            if ($scalingDecision == Scalr_Scaling_Decision::NOOP) {
                return false;
            } else if ($scalingDecision == Scalr_Scaling_Decision::DOWNSCALE) {
                /*
                 Timeout instance's count decrease. Decreases instances count after scaling
                 resolution the spare instances are running for selected timeout interval
                 from scaling EditOptions
                */

                // We have to check timeout limits before new scaling (downscaling) process will be initiated
                if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_DOWNSCALE_TIMEOUT_ENABLED)) {
                    // if the farm timeout is exceeded
                    // checking timeout interval.

                    $last_down_scale_data_time =  $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_DOWNSCALE_DATETIME);
                    $timeout_interval = $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_DOWNSCALE_TIMEOUT);

                    // check the time interval to continue scaling or cancel it...
                    if ((time() - $last_down_scale_data_time) < $timeout_interval * 60) {
                        // if the launch time is too small to terminate smth in this role -> go to the next role in foreach()
                        Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($request->farmId,
                            sprintf("Waiting for downscaling timeout on farm %s, role %s",
                                $request->farmName,
                                $DBFarmRole->GetRoleObject()->name
                            )
                        ));
                        return false;
                    }
                } // end Timeout instance's count decrease

                $sort = ($DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_KEEP_OLDEST) == 1) ? 'DESC' : 'ASC';

                $servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status = ? AND farm_roleid=? ORDER BY dtadded {$sort}",
                    array(SERVER_STATUS::RUNNING, $DBFarmRole->ID)
                );

                $got_valid_instance = false;

                // Select instance that will be terminated
                //
                // * Instances ordered by uptime (oldest wil be choosen)
                // * Instance cannot be mysql master
                // * Choose the one that was rebundled recently
                while (!$got_valid_instance && count($servers) > 0) {
                    $item = array_shift($servers);
                    $DBServer = DBServer::LoadByID($item['server_id']);

                    if ($DBServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
                        $serversCount = count($DBServer->GetFarmRoleObject()->GetServersByFilter(array(), array('status' => array(SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED, SERVER_STATUS::TROUBLESHOOTING))));
                        if ($DBServer->index == 1 && $serversCount > 1)
                            continue;
                    }

                    if ($DBServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED))
                        continue;

                    // Exclude db master
                    if ($DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER) != 1 && $DBServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER) != 1) {
                        // We do not want to delete the most recently synced instance. Because of LA fluctuation.
                        // I.e. LA may skyrocket during sync and drop dramatically after sync.
                        if ($DBServer->dateLastSync != 0) {
                            $chk_sync_time = $this->db->GetOne("
                                SELECT server_id
                                FROM servers
                                WHERE dtlastsync > {$DBServer->dateLastSync}
                                AND farm_roleid='{$DBServer->farmRoleId}'
                                AND status NOT IN('".SERVER_STATUS::TERMINATED."', '".SERVER_STATUS::TROUBLESHOOTING."', '".SERVER_STATUS::SUSPENDED."')
                                LIMIT 1
                            ");
                            if ($chk_sync_time) {
                                $got_valid_instance = true;
                            }
                        } else {
                            $got_valid_instance = true;
                        }
                    }

                    //Check safe shutdown
                    if ($DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_SCALING_SAFE_SHUTDOWN) == 1) {
                        if ($DBServer->IsSupported('0.11.3')) {
                            try {
                                $res  = $DBServer->scalarizr->system->callAuthShutdownHook();
                            } catch (Exception $e) {
                                $res = $e->getMessage();
                            }
                        } else {
                            Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage($request->farmId, sprintf("Safe shutdown enabled, but not supported by scalarizr installed on server '%s'. Ignoring.",
                                $DBServer->serverId
                            )));
                        }

                        if ($res != '1') {
                            Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($request->farmId, sprintf("Safe shutdown enabled. Server '%s'. Script returned '%s' skipping it.",
                                $DBServer->serverId,
                                $res
                            )));
                            $got_valid_instance = false;
                        }
                    }
                }

                if ($DBServer && $got_valid_instance) {
                    $logger->info(sprintf("Server '%s' selected for termination...", $DBServer->serverId));
                    $allow_terminate = false;

                    if ($DBServer->platform == SERVER_PLATFORMS::EC2) {
                        $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);
                        // Shutdown an instance just before a full hour running
                        if (!$DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_SCALING_IGNORE_FULL_HOUR)) {
                            $response = $aws->ec2->instance->describe($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID))->get(0);
                            if ($response && count($response->instancesSet)) {
                                $launch_time = $response->instancesSet->get(0)->launchTime->getTimestamp();
                                $time = 3600 - (time() - $launch_time) % 3600;
                                // Terminate instance in < 10 minutes for full hour.
                                if ($time <= 600) {
                                    $allow_terminate = true;
                                } else {
                                    $timeout = round(($time - 600) / 60, 1);

                                    Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                                        $request->farmId,
                                        sprintf("Farm %s, role %s scaling down ({$scalingDecisionAlgorithm}). Server '%s' will be terminated in %s minutes. Launch time: %s",
                                            $request->farmName,
                                            $DBServer->GetFarmRoleObject()->GetRoleObject()->name,
                                            $DBServer->serverId,
                                            $timeout,
                                            $response->instancesSet->get(0)->launchTime->format('c')
                                        )
                                    ));
                                }
                            }
                        } else {
                            $allow_terminate = true;
                        }
                        //Releases memory
                        $DBServer->GetEnvironmentObject()->getContainer()->release('aws');
                        unset($aws);
                    } else {
                        $allow_terminate = true;
                    }

                    if ($allow_terminate) {
                        $terminateStrategy = $DBFarmRole->GetSetting(Scalr_Role_Behavior::ROLE_BASE_TERMINATE_STRATEGY);
                        if (!$terminateStrategy)
                            $terminateStrategy = 'terminate';

                        try {
                            if ($terminateStrategy == 'terminate') {
                                $DBServer->terminate(DBServer::TERMINATE_REASON_SCALING_DOWN, false);

                                $DBFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_DOWNSCALE_DATETIME, time(), DBFarmRole::TYPE_LCL);

                                Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($request->farmId, sprintf(
                                    "Farm %s, role %s scaling down ({$scalingDecisionAlgorithm}). Server '%s' marked as 'Pending terminate' and will be fully terminated in 3 minutes.",
                                    $request->farmName,
                                    $DBServer->GetFarmRoleObject()->GetRoleObject()->name,
                                    $DBServer->serverId
                                )));
                            } else {
                                $DBServer->suspend('SCALING_DOWN', false);

                                $DBFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_DOWNSCALE_DATETIME, time(), DBFarmRole::TYPE_LCL);

                                Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($request->farmId, sprintf(
                                    "Farm %s, role %s scaling down ({$scalingDecisionAlgorithm}). Server '%s' marked as 'Pending suspend' and will be fully suspended in 3 minutes.",
                                    $request->farmName,
                                    $DBServer->GetFarmRoleObject()->GetRoleObject()->name,
                                    $DBServer->serverId
                                )));
                            }
                        } catch (Exception $e) {
                            $logger->fatal(sprintf("Cannot %s %s: %s",
                                $terminateStrategy,
                                $request->farmId,
                                $DBServer->serverId
                            ));
                        }
                    }
                } else {
                    $logger->warn(sprintf(
                        "[FarmID: %s] Scalr unable to determine what instance it should terminate (FarmRoleID: %s). Skipping...",
                        $request->farmId,
                        $DBFarmRole->ID
                    ));
                }

                break;
            } elseif ($scalingDecision == Scalr_Scaling_Decision::UPSCALE) {
                /*
                Timeout instance's count increase. Increases  instance's count after
                scaling resolution 'need more instances' for selected timeout interval
                from scaling EditOptions
                */
                if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_UPSCALE_TIMEOUT_ENABLED)) {
                    // if the farm timeout is exceeded
                    // checking timeout interval.
                    $last_up_scale_data_time =  $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_UPSCALE_DATETIME);
                    $timeout_interval = $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_UPSCALE_TIMEOUT);

                    // check the time interval to continue scaling or cancel it...
                    if (time() - $last_up_scale_data_time < $timeout_interval * 60) {
                        // if the launch time is too small to terminate smth in this role -> go to the next role in foreach()
                        Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($request->farmId,
                            sprintf("Waiting for upscaling timeout on farm %s, role %s",
                                $request->farmName,
                                $DBFarmRole->GetRoleObject()->name
                            )
                        ));
                        return false;
                    }
                }// end Timeout instance's count increase


                //Check DBMsr. Do not start slave during slave2master process
                $isDbMsr = $DBFarmRole->GetRoleObject()->getDbMsrBehavior();
                if ($isDbMsr) {
                    if ($DBFarmRole->GetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER)) {
                        $runningServers = $DBFarmRole->GetRunningInstancesCount();
                        if ($runningServers > 0) {
                            Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($request->farmId,
                                sprintf("Role is in slave2master promotion process. Do not launch new slaves while there is no active slaves")
                            ));
                            return false;
                        } else {
                            $DBFarmRole->SetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER, 0, DBFarmRole::TYPE_LCL);
                        }
                    }
                }

                if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_ONE_BY_ONE) == 1) {
                    $pendingInstances = $DBFarmRole->GetPendingInstancesCount();
                    if ($pendingInstances > 0) {
                        Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($request->farmId,
                            sprintf("There are %s pending intances of %s role on % farm. Waiting...",
                                $pendingInstances,
                                $DBFarmRole->GetRoleObject()->name,
                                $request->farmName
                            )
                        ));
                        return false;
                    }
                }

                $fstatus = $this->db->GetOne("SELECT status FROM farms WHERE id=? LIMIT 1", array($request->farmId));
                if ($fstatus != FARM_STATUS::RUNNING) {
                    $logger->warn("[FarmID: {$request->farmId}] Farm terminated. There is no need to scale it.");
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
                    Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($request->farmId, sprintf("Farm %s, role %s scaling up ($scalingDecisionAlgorithm). Found server to resume. ServerID = %s.",
                        $request->farmName,
                        $suspendedServer->GetFarmRoleObject()->GetRoleObject()->name,
                        $suspendedServer->serverId
                    )));
                }

                if ($terminateStrategy == 'terminate' || !$suspendedServer ||
                    (!PlatformFactory::isOpenstack($suspendedServer->platform) &&
                    $suspendedServer->platform != SERVER_PLATFORMS::EC2)) {
                    $ServerCreateInfo = new ServerCreateInfo($DBFarmRole->Platform, $DBFarmRole);

                    try {
                        $DBServer = \Scalr::LaunchServer($ServerCreateInfo, null, false, DBServer::LAUNCH_REASON_SCALING_UP);

                        $DBFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_UPSCALE_DATETIME, time(), DBFarmRole::TYPE_LCL);

                        Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($request->farmId, sprintf("Farm %s, role %s scaling up ($scalingDecisionAlgorithm). Starting new instance. ServerID = %s.",
                            $request->farmName,
                            $DBServer->GetFarmRoleObject()->GetRoleObject()->name,
                            $DBServer->serverId
                        )));
                    } catch (Exception $e) {
                        Logger::getLogger(LOG_CATEGORY::SCALING)->error($e->getMessage());
                    }
                } else {
                    //TODO: Check if server already resuming
                    $platform = PlatformFactory::NewPlatform($suspendedServer->platform);
                    $platform->ResumeServer($suspendedServer);
                }
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\AbstractTask::config()
     */
    public function config()
    {
        $config = parent::config();

        if ($config->daemon) {
            //Report warning to php-warnings.log
            trigger_error(sprintf("Demonized mode is not allowed for '%s' task. Forcing normal mode.", $this->name), E_USER_WARNING);

            //Forces normal mode
            $config->daemon = false;
        }

        return $config;
    }
}