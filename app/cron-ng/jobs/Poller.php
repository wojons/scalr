<?php

use Scalr\Service\Aws\Ec2\DataType\InstanceAttributeType;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;

class Scalr_Cronjob_Poller extends Scalr_System_Cronjob_MultiProcess_DefaultWorker
{
    static function getConfig () {
        return array(
            "description" => "Main poller",
            "processPool" => array(
                "daemonize" => false,
                "workerMemoryLimit" => 40000,   // 40Mb
                "startupTimeout" => 10000, 		// 10 seconds
                "workTimeout" => 120000,		// 120 seconds
                "size" => 14					// 14 workers
            ),
            "waitPrevComplete" => true,
            "fileName" => __FILE__,
        );
    }

    private $cleanupInterval = 120000; // 2 minutes

    private $logger;

    /**
     * @var \ADODB_mysqli
     */
    private $db;

    private $lastCleanup;

    private $cleanupSem;

    function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
        $this->db = $this->getContainer()->adodb;
        $this->lastCleanup = new Scalr_System_Ipc_Shm(
            array("name" => "scalr.cronjob.poller.lastCleanup")
        );
        $this->cleanupSem = sem_get(
            Scalr_System_OS::getInstance()->tok("scalr.cronjob.poller.cleanupSem")
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr_System_Cronjob_MultiProcess_DefaultWorker::startForking()
     */
    function startForking($workQueue)
    {
        // Reopen DB connection after daemonizing
        $this->db = $this->getContainer()->adodb;

        // Check that time has come to cleanup dead servers
        $doCleanup = false;
        sem_acquire($this->cleanupSem);
        try {
            if (time() - (int)$this->lastCleanup->get(0) >= $this->cleanupInterval) {
                $doCleanup = true;
                $this->lastCleanup->put(0, time());
            }
        } catch (Exception $e) {
            sem_release($this->cleanupSem);
        }
        sem_release($this->cleanupSem);

        if ($doCleanup) {
            $this->logger->info("Cleanup dead servers");

            try {
                $importing_servers = $this->db->GetAll("
                    SELECT server_id FROM servers
                    WHERE status IN(?, ?)
                    AND `dtadded` < NOW() - INTERVAL 1 DAY
                ", array(
                    SERVER_STATUS::IMPORTING, SERVER_STATUS::TEMPORARY
                ));

                foreach ($importing_servers as $ts) {
                    $dbServer = DBServer::LoadByID($ts['server_id']);
                    if ($dbServer->status == SERVER_STATUS::TEMPORARY) {
                        try {
                            $dbServer->terminate(DBServer::TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER);
                        } catch (Exception $e) {}
                    } else if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                        $dbServer->Remove();
                    }
                }

                $pending_launch_servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status=?", array(SERVER_STATUS::PENDING_LAUNCH));
                try {
                    foreach ($pending_launch_servers as $ts) {
                        $DBServer = DBServer::LoadByID($ts['server_id']);
                        if ($DBServer->status == SERVER_STATUS::PENDING_LAUNCH) {
                            $account = Scalr_Account::init()->loadById($DBServer->clientId);
                            if ($account->status == Scalr_Account::STATUS_ACTIVE) {
                                Scalr::LaunchServer(null, $DBServer);
                            }
                        }
                    }
                } catch(Exception $e) {
                    Logger::getLogger(LOG_CATEGORY::FARM)->error(sprintf("Can't load server with ID #'%s'",
                        $ts['server_id'],
                        $e->getMessage()
                    ));
                }
            } catch (Exception $e) {
                $this->logger->fatal("Poller::cleanup failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see Scalr_System_Cronjob_MultiProcess_DefaultWorker::endForking()
     */
    function endForking()
    {
        $this->lastCleanup->delete();
        sem_remove($this->cleanupSem);
    }

    /**
     * {@inheritdoc}
     * @see Scalr_System_Cronjob_MultiProcess_DefaultWorker::startChild()
     */
    function startChild()
    {
        // Reopen DB connection in child
        $this->db = $this->getContainer()->adodb;
        // Reconfigure observers;
        Scalr::ReconfigureObservers();
    }

    /**
     * {@inheritdoc}
     * @see Scalr_System_Cronjob_MultiProcess_DefaultWorker::enqueueWork()
     */
    function enqueueWork($workQueue)
    {
        $this->logger->info("Fetching completed farms...");

        $rows = $this->db->GetAll("
            SELECT farms.id, farms.status
            FROM farms
            JOIN clients ON clients.id = farms.clientid
            JOIN client_environments ON client_environments.id = farms.env_id
            WHERE clients.status='Active'
            AND client_environments.status = 'Active'
        ");
        foreach ($rows as $row) {
            if ($this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id=?", array($row['id'])) != 0)
                $workQueue->put($row['id']);
            else {
                if ($row['status'] == FARM_STATUS::SYNCHRONIZING)
                    $this->db->Execute('UPDATE farms SET status = ? WHERE id = ?', array(FARM_STATUS::TERMINATED, $row['id']));
            }
        }

        $this->logger->info(sprintf("Found %d farms.", count($rows)));
    }

    private function openstackSetFloatingIp(DBServer $DBServer) {
        $ipPool = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_OPENSTACK_IP_POOL);        
        if (!$DBServer->remoteIp && (in_array($DBServer->status, array(SERVER_STATUS::PENDING, SERVER_STATUS::INIT, SERVER_STATUS::SUSPENDED))) && $ipPool) {
            //$ipAddress = \Scalr\Modules\Platforms\Openstack\Helpers\OpenstackHelper::setFloatingIpForServer($DBServer);

        	$osClient = $DBServer->GetEnvironmentObject()->openstack(
				$DBServer->platform, $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION)
            );
            if ($osClient->hasService('network')) {
                $platform = PlatformFactory::NewPlatform($DBServer->platform);
                $serverIps = $platform->GetServerIPAddresses($DBServer);
                
                /********* USE Quantum (Neuron) NETWORK *******/
                $ips = $osClient->network->floatingIps->list();
                //Check free existing IP
                $ipAssigned = false;
                $ipAddress = false;
                $ipInfo = false;
                foreach ($ips as $ip) {
                	if ($ip->fixed_ip_address == $serverIps['localIp'] && $ip->port_id) {
                        $ipAssigned = true;
                        $ipInfo = $ip;
                        break;
                    }

                    if (!$ip->fixed_ip_address && $ip->floating_ip_address && !$ip->port_id)
                        $ipInfo = $ip;
                }

                if ($ipInfo) {
                    Logger::getLogger("Openstack")->warn(new FarmLogMessage($DBServer->farmId,
                    	"Found free floating IP: {$ipInfo->floating_ip_address} for use (". json_encode($ipInfo) .")"
					));
                }

                if (!$ipInfo || !$ipAssigned) {
                	// Get instance port
                    $ports = $osClient->network->ports->list();
                    foreach ($ports as $port) {
                        /* */
                        if ($port->device_id == $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID))
                            $serverNetworkPort[] = $port;
                    }

                    if (count($serverNetworkPort) == 0) {
                        Logger::getLogger("Openstack")->error(new FarmLogMessage($DBServer->farmId,
                        	"Unable to identify network port of instance"
						));
                    } else {
                        $publicNetworkId = $ipPool;
                        while (count($serverNetworkPort) > 0) {
                            try {
                                $port = array_shift($serverNetworkPort);

                                if (!$ipInfo) {
                                    $ipInfo = $osClient->network->floatingIps->create($publicNetworkId, $port->id);

                                    Logger::getLogger("Openstack")->warn(new FarmLogMessage($DBServer->farmId,
                                    "Allocated new IP {$ipInfo->floating_ip_address} for port: {$port->id}"
                                    ));

                                } else {
                                    $osClient->network->floatingIps->update($ipInfo->id, $port->id);

                                    Logger::getLogger("Openstack")->warn(new FarmLogMessage($DBServer->farmId,
                                    	"Existing floating IP {$ipInfo->floating_ip_address} was used for port: {$port->id}"
                                    ));
                                }

                                $DBServer->SetProperties(array(
                                    OPENSTACK_SERVER_PROPERTIES::FLOATING_IP => $ipInfo->floating_ip_address,
                                    OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => $ipInfo->id,
                                ));

                                $ipAddress = $ipInfo->floating_ip_address;

                                break;
                            } catch (Exception $e) {
                            	$this->logger->error("Scalr unable to allocate new floating IP from pool: ". $e->getMessage());
                            }
                        }
                    }
                } else {
                    Logger::getLogger("Openstack")->warn(new FarmLogMessage($DBServer->farmId,
                    	"IP: {$ipInfo->floating_ip_address} already assigned"
                    ));
                    $ipAddress = $ipInfo->floating_ip_address;
                }
            } else {
                /********* USE NOVA NETWORK *******/
                //Check free existing IP
                $ipAssigned = false;
                $ipAddress = false;

                $ips = $osClient->servers->floatingIps->list($ipPool);
                foreach ($ips as $ip) {
                    //if (!$ip->instance_id) {
                    //	$ipAddress = $ip->ip;
                    //}


                    if ($ip->instance_id == $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID)) {
                        $ipAddress = $ip->ip;
                        $ipAssigned = true;
                    }
                }

                //If no free IP allocate new from pool
                if (!$ipAddress) {
                    $ip = $osClient->servers->floatingIps->create($ipPool);
                    $ipAddress = $ip->ip;

                    $DBServer->SetProperties(array(
                        OPENSTACK_SERVER_PROPERTIES::FLOATING_IP => $ip->ip,
                        OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => $ip->id,
                    ));
                }

                if (!$ipAssigned) {
                    //Associate floating IP with Instance
                    $osClient->servers->addFloatingIp($DBServer->GetCloudServerID(), $ipAddress);
                }
            }

            if ($ipAddress) {
                $DBServer->remoteIp = $ipAddress;
                $DBServer->Save();
                $DBServer->SetProperty(SERVER_PROPERTIES::SYSTEM_IGNORE_INBOUND_MESSAGES, null);
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see Scalr_System_Cronjob_MultiProcess_DefaultWorker::handleWork()
     */
    function handleWork($farmId)
    {
        $this->cleanup();

        $DBFarm = DBFarm::LoadByID($farmId);

        $account = Scalr_Account::init()->loadById($DBFarm->ClientID);
        $payAsYouGoTime = $account->getSetting(Scalr_Account::SETTING_BILLING_PAY_AS_YOU_GO_DATE);

        $GLOBALS["SUB_TRANSACTIONID"] = abs(crc32(posix_getpid().$farmId));
        $GLOBALS["LOGGER_FARMID"] = $farmId;

        $this->logger->info("[". $GLOBALS["SUB_TRANSACTIONID"]."] Begin polling farm (ID: {$DBFarm->ID}, Name: {$DBFarm->Name}, Status: {$DBFarm->Status})");

        // Collect information from database
        $servers_count = $this->db->GetOne("
            SELECT COUNT(*) FROM servers WHERE farm_id = ? AND status NOT IN (?,?)
        ", array(
            $DBFarm->ID, SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED
        ));
        $this->logger->info("[FarmID: {$DBFarm->ID}] Found {$servers_count} farm instances in database");

        if ($DBFarm->Status == FARM_STATUS::TERMINATED && $servers_count == 0)
            return;

        foreach ($DBFarm->GetServersByFilter(array(), array('status' => SERVER_STATUS::PENDING_LAUNCH)) as $DBServer) {
            /** @var DBServer $DBServer */
            try {
                if ($DBServer->cloudLocation) {
                    try {
                        Logger::getLogger(LOG_CATEGORY::FARM)->info(sprintf(
                            "Initializing cloud cache: {$DBServer->cloudLocation} ({$DBServer->serverId})"
                        ));
                        $p = PlatformFactory::NewPlatform($DBServer->platform);
                        $list = $p->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->cloudLocation);
                    } catch (Exception $e) {
                        Logger::getLogger(LOG_CATEGORY::FARM)->info(sprintf(
                            "Initializing cloud cache: FAILED"
                        ));
                    }
                }

                if ($DBServer->status != SERVER_STATUS::PENDING && $DBServer->status != SERVER_STATUS::PENDING_TERMINATE) {
                    if (!$p->IsServerExists($DBServer)) {
                        try {
                            $serverInfo = $p->GetServerExtendedInformation($DBServer);
                        } catch (Exception $e) {
                            Logger::getLogger(LOG_CATEGORY::FARM)->error(sprintf(
                                "[CRASH][FarmID: %d] Crash check for server '%s' failed: %s",
                                $DBFarm->ID,
                                $DBServer->serverId,
                                $e->getMessage()
                            ));
                        }

                        if (!$serverInfo) {
                        	if ($p->debugLog)
                        		Logger::getLogger('Openstack')->fatal($p->debugLog);
                        	
                            if (!in_array($DBServer->status, array(SERVER_STATUS::PENDING_TERMINATE, SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED))) {
                                if ($DBServer->GetProperty(SERVER_PROPERTIES::CRASHED) == 1) {
                                	if (PlatformFactory::isOpenstack($DBServer->platform)) {
                                		$DBServer->SetProperty(SERVER_PROPERTIES::MISSING, 1);
                                	} else {
	                                    $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);
	                                    Scalr::FireEvent($DBFarm->ID, new HostCrashEvent($DBServer));
                                	}
                                } else {
                                    $DBServer->SetProperties([
                                        SERVER_PROPERTIES::REBOOTING => 0,
                                        SERVER_PROPERTIES::CRASHED   => 1,
                                    	SERVER_PROPERTIES::MISSING   => 1
                                    ]);
                                    Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID, sprintf(
                                        "Server '%s' found in database but not found on %s. Crashed.",
                                        $DBServer->serverId,
                                        $DBServer->platform
                                    )));
                                }

                                continue;
                            }
                        } else {
                            Logger::getLogger(LOG_CATEGORY::FARM)->error(sprintf(
                                "[CRASH][FarmID: %d] False-positive crash check: %s (EnvID: %d)",
                                $DBFarm->ID,
                                $DBServer->serverId,
                                $DBServer->envId
                            ));
                        }
                    } else {
                        $DBServer->SetProperty(SERVER_PROPERTIES::CRASHED, "0");
                        $DBServer->SetProperty(SERVER_PROPERTIES::MISSING, "0");
                    }
                }
            } catch (Exception $e) {
                if (stristr($e->getMessage(), "AWS was not able to validate the provided access credentials") ||
                    stristr($e->getMessage(), "Unable to sign AWS API request. Please, check your X.509")
                ) {
                    $env = Scalr_Environment::init()->LoadById($DBFarm->EnvID);
                    $env->status = Scalr_Environment::STATUS_INACTIVE;
                    $env->save();
                    $env->setPlatformConfig(array(
                        'system.auto-disable-reason' => $e->getMessage()
                    ), false);

                    return;
                }

                if (stristr($e->getMessage(), "Could not connect to host"))
                    continue;

                print "[0][Farm: {$farmId}] {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}\n\n";
                continue;
            }

            /*
            try {
                $realStatus = $DBServer->GetRealStatus()->getName();
                if ($realStatus == 'stopped') {
                    $DBServer->SetProperty(SERVER_PROPERTIES::SUB_STATUS, $realStatus);
                    continue;
                } else {
                    if ($DBServer->GetProperty(SERVER_PROPERTIES::SUB_STATUS) == 'stopped')
                        $DBServer->SetProperty(SERVER_PROPERTIES::SUB_STATUS, "");
                }
            } catch (Exception $e) {}
            */

            try {
                if (!in_array($DBServer->status, array(
                        SERVER_STATUS::SUSPENDED,
                        SERVER_STATUS::TERMINATED,
                        SERVER_STATUS::PENDING_TERMINATE,
                        SERVER_STATUS::PENDING_SUSPEND))) {

                    $openstackErrorState = false;
                    if (PlatformFactory::isOpenstack($DBServer->platform)) {
                        if ($DBServer->GetRealStatus()->getName() === 'ERROR')
                            $openstackErrorState = true;
                    }

                    if ($DBServer->GetRealStatus()->isTerminated() || $openstackErrorState) {
                        Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID,
                            sprintf("Server '%s' (Platform: %s) not running (Real state: %s, Scalr status: %s).",
                                $DBServer->serverId, $DBServer->platform, $DBServer->GetRealStatus()->getName(), $DBServer->status
                            )
                        ));

                        $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);

                        $DBServer->SetProperties([
                            SERVER_PROPERTIES::REBOOTING => 0,
                            SERVER_PROPERTIES::RESUMING  => 0,
                        ]);

                        Scalr::FireEvent($DBFarm->ID, new HostDownEvent($DBServer));
                        continue;
                    } elseif ($DBServer->GetRealStatus()->isSuspended()) {

                        // if server was suspended when it was running
                        if ($DBServer->status == SERVER_STATUS::RUNNING) {
                            Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID,
                                sprintf("Server '%s' (Platform: %s) not running (Real state: %s, Scalr status: %s).",
                                    $DBServer->serverId, $DBServer->platform, $DBServer->GetRealStatus()->getName(), $DBServer->status
                                )
                            ));

                            $DBServer->SetProperties([
                                SERVER_PROPERTIES::REBOOTING  => 0,
                                SERVER_PROPERTIES::RESUMING   => 0,
                                SERVER_PROPERTIES::SUB_STATUS => "",
                            ]);

                            $DBServer->remoteIp = "";
                            $DBServer->localIp = "";

                            $DBServer->status = SERVER_STATUS::SUSPENDED;
                            $DBServer->Save();

                            Scalr::FireEvent($DBFarm->ID, new HostDownEvent($DBServer));
                            continue;
                        } else {
                        // If server was suspended during initialization - we do not support this and need to terminate this instance
                            $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);
                            continue;
                        }
                    }
                }

                if ($DBServer->status != SERVER_STATUS::RUNNING && $DBServer->GetRealStatus()->IsRunning()) {
                    if ($DBServer->status == SERVER_STATUS::SUSPENDED) {
                        //TODO: Depends on resume strategy need to set server status
                        // For Openstack we need to re-accociate IPs
                        $DBServer->SetProperty(\SERVER_PROPERTIES::RESUMING, 0);
                        try {
                            if ($DBServer->isOpenstack())
                                $this->openstackSetFloatingIp($DBServer);
                        } catch (Exception $e) {
                            if (!$DBServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED)) {
                                $DBServer->SetProperties([
                                    SERVER_PROPERTIES::SZR_IS_INIT_FAILED    => 1,
                                    SERVER_PROPERTIES::SZR_IS_INIT_ERROR_MSG => $e->getMessage(),
                                ]);
                            }
                        }

                        Scalr::FireEvent($DBFarm->ID, new HostUpEvent($DBServer, ""));
                        continue;

                    } elseif (!in_array($DBServer->status, array(SERVER_STATUS::TERMINATED, SERVER_STATUS::TROUBLESHOOTING))) {
                        if ($DBServer->platform == SERVER_PLATFORMS::EC2) {
                            if ($DBServer->status == SERVER_STATUS::PENDING && $DBFarm->GetSetting(DBFarm::SETTING_EC2_VPC_ID)) {
                                if ($DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_VPC_INTERNET_ACCESS) != 'outbound-only') {
                                    $ipAddress = \Scalr\Modules\Platforms\Ec2\Helpers\EipHelper::setEipForServer($DBServer);
                                    if ($ipAddress) {
                                        $DBServer->remoteIp = $ipAddress;
                                        $DBServer->Save();
                                    }
                                }
                            }
                        }

                        try {
                            if ($DBServer->isOpenstack())
                                $this->openstackSetFloatingIp($DBServer);
                        } catch (Exception $e) {
                            if (!$DBServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED)) {
                                $DBServer->SetProperties([
                                    SERVER_PROPERTIES::SZR_IS_INIT_FAILED    => 1,
                                    SERVER_PROPERTIES::SZR_IS_INIT_ERROR_MSG => $e->getMessage(),
                                ]);
                            }
                        }

                        if ($DBServer->isCloudstack()) {
                            if ($DBServer->status == SERVER_STATUS::PENDING) {
                                $jobId = $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::LAUNCH_JOB_ID);

                                try {
                                    $cs = $DBServer->GetEnvironmentObject()->cloudstack($DBServer->platform);

                                    $res = $cs->queryAsyncJobResult($jobId);

                                    if ($res->jobstatus == 1) {
                                        $DBServer->SetProperties([
                                            CLOUDSTACK_SERVER_PROPERTIES::TMP_PASSWORD => $res->virtualmachine->password,
                                            CLOUDSTACK_SERVER_PROPERTIES::SERVER_NAME => $res->virtualmachine->name,
                                        ]);
                                    }
                                    //TODO: handle failed job: $res->jobresult->jobstatus == 2
                                } catch (Exception $e) {
                                    if ($DBServer->farmId) {
                                        Logger::getLogger("CloudStack")->error(new FarmLogMessage($DBServer->farmId, $e->getMessage()));
                                    }
                                }
                            }
                        }

                        try {
                            $dtadded = strtotime($DBServer->dateAdded);
                            $DBFarmRole = $DBServer->GetFarmRoleObject();
                            $launch_timeout = $DBFarmRole->GetSetting(DBFarmRole::SETTING_SYSTEM_LAUNCH_TIMEOUT) > 0 ?
                                              $DBFarmRole->GetSetting(DBFarmRole::SETTING_SYSTEM_LAUNCH_TIMEOUT) : 900;
                        } catch (Exception $e) {
                            if (stristr($e->getMessage(), "not found")) {
                                $DBServer->terminate(DBServer::TERMINATE_REASON_ROLE_REMOVED);
                            }
                        }

                        $scripting_event = false;
                        if ($DBServer->status == SERVER_STATUS::PENDING) {
                            $event = "hostInit";
                            $scripting_event = EVENT_TYPE::HOST_INIT;
                        }
                        elseif ($DBServer->status == SERVER_STATUS::INIT) {
                            $event = "hostUp";
                            $scripting_event = EVENT_TYPE::HOST_UP;
                        }

                        if ($scripting_event && $dtadded) {
                            $scripting_timeout = (int)$this->db->GetOne("
                                SELECT sum(timeout)
                                FROM farm_role_scripts
                                WHERE event_name=? AND
                                farm_roleid=? AND issync='1'
                            ",
                                array($scripting_event, $DBServer->farmRoleId)
                            );

                            if ($scripting_timeout)
                                $launch_timeout = $launch_timeout + $scripting_timeout;


                            if ($dtadded + $launch_timeout < time() && !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
                                //Add entry to farm log
                                Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID, sprintf(
                                    "Server '%s' did not send '%s' event in %s seconds after launch (Try increasing timeouts in role settings). Considering it broken. Terminating instance.",
                                    $DBServer->serverId,
                                    $event,
                                    $launch_timeout
                                )));

                                try {
                                    $DBServer->terminate(array(DBServer::TERMINATE_REASON_SERVER_DID_NOT_SEND_EVENT, $event, $launch_timeout), false);
                                } catch (Exception $err) {
                                    $this->logger->fatal($err->getMessage());
                                }
                            } elseif ($DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
                                //DO NOT TERMINATE MONGODB INSTANCES BY TIMEOUT! IT'S NOT SAFE
                                //THINK ABOUT WORKAROUND
                            }
                        }

                        // Is IP address changed?
                        if (!$DBServer->IsRebooting())
                        {
                            $ipaddresses = PlatformFactory::NewPlatform($DBServer->platform)->GetServerIPAddresses($DBServer);

                            if (
                                ($ipaddresses['remoteIp'] && $DBServer->remoteIp && $DBServer->remoteIp != $ipaddresses['remoteIp']) ||
                                ($ipaddresses['localIp'] && $DBServer->localIp && $DBServer->localIp != $ipaddresses['localIp']))
                            {
                                Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID, sprintf("RemoteIP: %s (%s), LocalIp: %s (%s) (Poller).",
                                    $DBServer->remoteIp, $ipaddresses['remoteIp'],
                                    $DBServer->localIp, $ipaddresses['localIp']
                                )));

                                Scalr::FireEvent(
                                    $DBServer->farmId,
                                    new IPAddressChangedEvent($DBServer, $ipaddresses['remoteIp'], $ipaddresses['localIp'])
                                );
                            }

                            //TODO: Check health:
                        }
                    }
                } elseif ($DBServer->status == SERVER_STATUS::RUNNING && $DBServer->GetRealStatus()->isRunning()) {
                    // Is IP address changed?
                    if (!$DBServer->IsRebooting()) {
                        $ipaddresses = PlatformFactory::NewPlatform($DBServer->platform)->GetServerIPAddresses($DBServer);

                        if (($ipaddresses['remoteIp'] && $DBServer->remoteIp != $ipaddresses['remoteIp']) ||
                            ($ipaddresses['localIp']  && $DBServer->localIp != $ipaddresses['localIp'])) {
                            Scalr::FireEvent(
                                $DBServer->farmId,
                                new IPAddressChangedEvent($DBServer, $ipaddresses['remoteIp'], $ipaddresses['localIp'])
                            );
                        }

                        if ($payAsYouGoTime) {
                            $initTime = $DBServer->GetProperty(SERVER_PROPERTIES::INITIALIZED_TIME);
                            if ($initTime < $payAsYouGoTime)
                                $initTime = $payAsYouGoTime;

                            $runningHours = ceil((time() - $initTime) / 3600);
                            $scuUsed = $runningHours * Scalr_Billing::getSCUByInstanceType($DBServer->GetFlavor(), $DBServer->platform);

                            $this->db->Execute("UPDATE servers_history SET scu_used = ?, scu_updated = 0 WHERE server_id = ?", array($scuUsed, $DBServer->serverId));
                        }

                        if ($DBServer->platform == SERVER_PLATFORMS::EC2) {
                            $env = Scalr_Environment::init()->loadById($DBServer->envId);
                            $ec2 = $env->aws($DBServer->GetCloudLocation())->ec2;

                            //TODO:
                            $time = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED_LAST_CHECK_TIME);
                            if (!$time || time() < $time+1200) {
                                $isEnabled = $ec2->instance->describeAttribute($DBServer->GetCloudServerID(), InstanceAttributeType::disableApiTermination());
                                $DBServer->SetProperties([
                                    EC2_SERVER_PROPERTIES::IS_LOCKED                 => $isEnabled,
                                    EC2_SERVER_PROPERTIES::IS_LOCKED_LAST_CHECK_TIME => time(),
                                ]);
                            }
                        }
                    } else {
                        //TODO: Check reboot timeout
                    }
                }
            } catch (Exception $e) {
                if (stristr($e->getMessage(), "not found"))
                    print $e->getTraceAsString() . "\n";
                elseif (stristr($e->getMessage(), "Request limit exceeded")) {
                    sleep(10);
                    print "[1:SLEEP][Farm: {$farmId}] {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}\n\n";
                }
                else
                    print "[1][Farm: {$farmId}] {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}\n\n";
            }
        }
    }

    private function cleanup ()
    {
    }
}
