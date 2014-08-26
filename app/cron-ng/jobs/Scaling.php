<?php

use Scalr\Modules\PlatformFactory;

class Scalr_Cronjob_Scaling extends Scalr_System_Cronjob_MultiProcess_DefaultWorker
{
    static function getConfig () {
        return array(
            "description"      => "Roles scaling",
            "processPool"      => array(
                "daemonize"         => false,
                "workerMemoryLimit" => 40000,  // 40Mb
                "startupTimeout"    => 10000,  // 10 seconds
                "workTimeout"       => 120000, // 120 seconds
                "size"              => 14      // 14 workers
            ),
            "waitPrevComplete" => true,
            "fileName"         => __FILE__,
            "getoptRules"      => array(
                'farm-id-s'        => 'Affect only this farm'
            )
        );
    }

    private $logger;

    /**
     * @var \ADODB_mysqli
     */
    private $db;

    function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
        $this->db = $this->getContainer()->adodb;
    }

    function startForking ($workQueue)
    {
        // Reopen DB connection after daemonizing
        $this->db = $this->getContainer()->adodb;
    }

    function startChild ()
    {
        // Reopen DB connection in child
        $this->db = $this->getContainer()->adodb;
        // Reconfigure observers;
        Scalr::ReconfigureObservers();
    }

    function enqueueWork ($workQueue)
    {
        $this->logger->info("Fetching completed farms...");

        $farmid = $this->runOptions['getopt']->getOption('farm-id');

        if ($farmid) {
            $rows = $this->db->GetAll("
                SELECT farms.id FROM farms
                INNER JOIN clients ON clients.id = farms.clientid
                WHERE clients.status='Active' AND farms.status=? AND farms.id=?",
                array(FARM_STATUS::RUNNING, $farmid)
            );
        } else {
            $rows = $this->db->GetAll("
                SELECT farms.id FROM farms
                INNER JOIN clients ON clients.id = farms.clientid
                WHERE clients.status='Active' AND farms.status=?",
                array(FARM_STATUS::RUNNING)
            );
        }

        foreach ($rows as $row) {
            $workQueue->put($row['id']);
        }

        $this->logger->info(sprintf("Found %d farms.", count($rows)));
    }

    function handleWork ($farmId)
    {
        $DBFarm = DBFarm::LoadByID($farmId);

        $GLOBALS["SUB_TRANSACTIONID"] = abs(crc32(posix_getpid().$farmId));
        $GLOBALS["LOGGER_FARMID"] = $farmId;

        if ($DBFarm->Status != FARM_STATUS::RUNNING) {
            $this->logger->warn("[FarmID: {$DBFarm->ID}] Farm terminated. There is no need to scale it.");
            return;
        }

        foreach ($DBFarm->GetFarmRoles() as $DBFarmRole) {
            for ($i = 0; $i < 10; $i++) {
                if ($DBFarmRole->NewRoleID != '') {
                    $this->logger->warn("[FarmID: {$DBFarm->ID}] Role '{$DBFarmRole->GetRoleObject()->name}' being synchronized. This role will not be scalled.");
                    continue 2;
                }

                if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_ENABLED) != '1' &&
                    !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB) &&
                    !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ) &&
                    !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER)) {
                    $this->logger->info("[FarmID: {$DBFarm->ID}] Scaling disabled for role '{$DBFarmRole->GetRoleObject()->name}'. Skipping...");
                    continue 2;
                }

                // Get polling interval in seconds
                $polling_interval = $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_POLLING_INTERVAL)*60;
                $dt_last_polling = $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_LAST_POLLING_TIME);
                if ($dt_last_polling && $dt_last_polling+$polling_interval > time() && $i == 0) {
                    $this->logger->info("Polling interval: every {$polling_interval} seconds");
                    continue;
                }

                // Set Last polling time
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_LAST_POLLING_TIME, time(), DBFarmRole::TYPE_LCL);

                // Get current count of running and pending instances.
                $this->logger->info(sprintf("Processing role '%s'", $DBFarmRole->GetRoleObject()->name));

                $scalingManager = new Scalr_Scaling_Manager($DBFarmRole);
                $scalingDecision = $scalingManager->makeScalingDecition();
                $scalingDecisionAlgorithm = $scalingManager->decisonInfo;

                if ($scalingDecision == Scalr_Scaling_Decision::STOP_SCALING)
                    return;

                if ($scalingDecision == Scalr_Scaling_Decision::NOOP)
                    continue 2;

                elseif ($scalingDecision == Scalr_Scaling_Decision::DOWNSCALE) {
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
                        if (time() - $last_down_scale_data_time < $timeout_interval * 60) {
                            // if the launch time is too small to terminate smth in this role -> go to the next role in foreach()
                            Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($DBFarm->ID,
                                sprintf("Waiting for downscaling timeout on farm %s, role %s",
                                    $DBFarm->Name,
                                    $DBFarmRole->GetRoleObject()->name
                                )
                            ));
                            continue 2;
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
                                    SELECT server_id FROM servers
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
                    }

                    if ($DBServer && $got_valid_instance) {
                        $this->logger->info(sprintf("Server '%s' selected for termination...", $DBServer->serverId));
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
                                            $DBFarm->ID,
                                            sprintf("Farm %s, role %s scaling down ({$scalingDecisionAlgorithm}). Server '%s' will be terminated in %s minutes. Launch time: %s",
                                                $DBFarm->Name,
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
                            //Check safe shutdown
                            if ($DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_SCALING_SAFE_SHUTDOWN) == 1) {
                                if ($DBServer->IsSupported('0.11.3')) {
                                    try {
                                        $res  = $DBServer->scalarizr->system->callAuthShutdownHook();
                                    } catch (Exception $e) {
                                        $res = $e->getMessage();
                                    }
                                } else {
                                    Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage($DBFarm->ID, sprintf("Safe shutdown enabled, but not supported by scalarizr installed on server '%s'. Ignoring.",
                                        $DBServer->serverId
                                    )));
                                }

                                if ($res != '1') {
                                    Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID, sprintf("Safe shutdown enabled. Server '%s'. Script returned '%s', server won't be terminated while return value not '1'",
                                        $DBServer->serverId,
                                        $res
                                    )));
                                    break;
                                }
                            }

                            $terminateStrategy = $DBFarmRole->GetSetting(Scalr_Role_Behavior::ROLE_BASE_TERMINATE_STRATEGY);
                            if (!$terminateStrategy)
                                $terminateStrategy = 'terminate';

                            try {
                                if ($terminateStrategy == 'terminate') {
                                    $DBServer->terminate(DBServer::TERMINATE_REASON_SCALING_DOWN, false);

                                    $DBFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_DOWNSCALE_DATETIME, time(), DBFarmRole::TYPE_LCL);

                                    Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($DBFarm->ID, sprintf(
                                        "Farm %s, role %s scaling down ({$scalingDecisionAlgorithm}). Server '%s' marked as 'Pending terminate' and will be fully terminated in 3 minutes.",
                                        $DBFarm->Name,
                                        $DBServer->GetFarmRoleObject()->GetRoleObject()->name,
                                        $DBServer->serverId
                                    )));
                                } else {
                                    $DBServer->suspend('SCALING_DOWN', false);

                                    $DBFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_DOWNSCALE_DATETIME, time(), DBFarmRole::TYPE_LCL);

                                    Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($DBFarm->ID, sprintf(
                                        "Farm %s, role %s scaling down ({$scalingDecisionAlgorithm}). Server '%s' marked as 'Pending suspend' and will be fully suspended in 3 minutes.",
                                        $DBFarm->Name,
                                        $DBServer->GetFarmRoleObject()->GetRoleObject()->name,
                                        $DBServer->serverId
                                    )));
                                }
                            } catch (Exception $e) {
                                $this->logger->fatal(sprintf("Cannot %s %s: %s",
                                    $terminateStrategy,
                                    $DBFarm->ID,
                                    $DBServer->serverId,
                                    $e->getMessage()
                                ));
                            }
                        }
                    } else {
                        $this->logger->warn(sprintf(
                            "[FarmID: %s] Scalr unable to determine what instance it should terminate (FarmRoleID: %s). Skipping...",
                            $DBFarm->ID,
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
                            Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($DBFarm->ID,
                                sprintf("Waiting for upscaling timeout on farm %s, role %s",
                                    $DBFarm->Name,
                                    $DBFarmRole->GetRoleObject()->name
                                )
                            ));
                            continue 2;
                        }
                    }// end Timeout instance's count increase


                    //Check DBMsr. Do not start slave during slave2master process
                    $isDbMsr = $DBFarmRole->GetRoleObject()->getDbMsrBehavior();
                    if ($isDbMsr) {
                        if ($DBFarmRole->GetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER)) {
                            $runningServers = $DBFarmRole->GetRunningInstancesCount();
                            if ($runningServers > 0) {
                                Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID,
                                    sprintf("Role is in slave2master promotion process. Do not launch new slaves while there is no active slaves")
                                ));
                                continue 2;
                            } else {
                                $DBFarmRole->SetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER, 0, DBFarmRole::TYPE_LCL);
                            }
                        }
                    }

                    if ($DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_ONE_BY_ONE) == 1) {
                        $pendingInstances = $DBFarmRole->GetPendingInstancesCount();
                        if ($pendingInstances > 0) {
                            Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($DBFarm->ID,
                                sprintf("There are %s pending intances of %s role on % farm. Waiting...",
                                    $pendingInstances,
                                    $DBFarmRole->GetRoleObject()->name,
                                    $DBFarm->Name
                                )
                            ));
                            continue 2;
                        }
                    }

                    $fstatus = $this->db->GetOne("SELECT status FROM farms WHERE id=? LIMIT 1", array($DBFarm->ID));
                    if ($fstatus != FARM_STATUS::RUNNING) {
                        $this->logger->warn("[FarmID: {$DBFarm->ID}] Farm terminated. There is no need to scale it.");
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
                        Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID, sprintf("Farm %s, role %s scaling up ($scalingDecisionAlgorithm). Found server to resume. ServerID = %s.",
                            $DBFarm->Name,
                            $suspendedServer->GetFarmRoleObject()->GetRoleObject()->name,
                            $suspendedServer->serverId
                        )));
                    }

                    if ($terminateStrategy == 'terminate' || !$suspendedServer ||
                        (!PlatformFactory::isOpenstack($suspendedServer->platform) &&
                        $suspendedServer->platform != SERVER_PLATFORMS::EC2)) {
                        $ServerCreateInfo = new ServerCreateInfo($DBFarmRole->Platform, $DBFarmRole);

                        try {
                            $DBServer = Scalr::LaunchServer($ServerCreateInfo, null, false, DBServer::LAUNCH_REASON_SCALING_UP);

                            $DBFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_UPSCALE_DATETIME, time(), DBFarmRole::TYPE_LCL);

                            Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($DBFarm->ID, sprintf("Farm %s, role %s scaling up ($scalingDecisionAlgorithm). Starting new instance. ServerID = %s.",
                                $DBFarm->Name,
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
        }
    }
}
