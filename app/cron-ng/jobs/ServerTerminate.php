<?php

use Scalr\Modules\PlatformFactory;

class Scalr_Cronjob_ServerTerminate extends Scalr_System_Cronjob_MultiProcess_DefaultWorker
{
    static function getConfig () {
        return array(
            "description" => "Server termination manager.",
            "processPool" => array(
                "daemonize"         => false,
                "workerMemoryLimit" => 40000,
                "size"              => 14,
                "startupTimeout"    => 10000
            ),
            "waitPrevComplete"      => true,
            "fileName"              => __FILE__,
            "memoryLimit"           => 500000
        );
    }

    private $logger;

    /**
     * @var \ADODB_mysqli
     */
    private $db;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
        $this->timeLogger = Logger::getLogger('time');
        $this->db = $this->getContainer()->adodb;
    }

    /**
     * {@inheritdoc}
     * @see Scalr_System_Cronjob_MultiProcess_DefaultWorker::startForking()
     */
    function startForking ($workQueue)
    {
    }

    /**
     * {@inheritdoc}
     * @see Scalr_System_Cronjob_MultiProcess_DefaultWorker::startChild()
     */
    function startChild ()
    {
    }

    /**
     * {@inheritdoc}
     * @see Scalr_System_Cronjob_MultiProcess_DefaultWorker::enqueueWork()
     */
    function enqueueWork($workQueue)
    {
        $this->logger->info("Fetching servers to remove...");

        $qty = 0;
        foreach (DBServer::getTerminatingServers() as $row) {
            $workQueue->put($row['server_id']);
            $qty++;
        }

        $this->logger->info("Found " . $qty . " terminating servers");
    }

    /**
     * {@inheritdoc}
     * @see Scalr_System_Cronjob_MultiProcess_DefaultWorker::handleWork()
     */
    function handleWork($serverId)
    {
        $dtNow = new DateTime('now');

        $dbServer = DBServer::LoadByID($serverId);

        if (!in_array($dbServer->status, array(
            SERVER_STATUS::PENDING_TERMINATE,
            SERVER_STATUS::PENDING_SUSPEND,
            SERVER_STATUS::TERMINATED,
            SERVER_STATUS::SUSPENDED
        ))) {
            return;
        }


        // Skip Locked instances
        if ($dbServer->status == SERVER_STATUS::PENDING_TERMINATE &&
            $dbServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED) == 1)
            return;

        if (in_array($dbServer->status, array(SERVER_STATUS::TERMINATED)) || $dbServer->dateShutdownScheduled <= $dtNow->format('Y-m-d H:i:s')) {
            try {
                if ($dbServer->GetCloudServerID()) {
                    $serverHistory = $dbServer->getServerHistory();

                    $isTermination = in_array($dbServer->status, array(SERVER_STATUS::TERMINATED, SERVER_STATUS::PENDING_TERMINATE));
                    $isSuspension = in_array($dbServer->status, array(SERVER_STATUS::SUSPENDED, SERVER_STATUS::PENDING_SUSPEND));

                    if (
                        ($isTermination && !$dbServer->GetRealStatus()->isTerminated()) ||
                        ($isSuspension && !$dbServer->GetRealStatus()->isSuspended())
                    ) {
                        try {
                            if ($dbServer->farmId != 0) {
                            	try {
	                                if ($dbServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
	                                    $serversCount = count($dbServer->GetFarmRoleObject()->GetServersByFilter(array(), array(
	                                        'status' => array(SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED)
	                                    )));
	                                    if ($dbServer->index == 1 && $serversCount > 1) {
	                                        Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($dbServer->GetFarmObject()->ID, sprintf(
	                                            "RabbitMQ role. Main DISK node should be terminated after all other nodes. "
	                                          . "Waiting... (Platform: %s) (ServerTerminate).",
	                                            $dbServer->serverId, $dbServer->platform
	                                        )));
	                                        return;
	                                    }
	                                }
                            	} catch (Exception $e) {}

                                Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($dbServer->GetFarmObject()->ID, sprintf(
                                    "Terminating server '%s' (Platform: %s) (ServerTerminate).",
                                    $dbServer->serverId, $dbServer->platform
                                )));
                            }
                        } catch (Exception $e) {
                            Logger::getLogger(LOG_CATEGORY::FARM)->warn($serverId . ": {$e->getMessage()}");
                        }

                        if ($isTermination)
                            PlatformFactory::NewPlatform($dbServer->platform)->TerminateServer($dbServer);
                        else
                            PlatformFactory::NewPlatform($dbServer->platform)->SuspendServer($dbServer);

                        if ($dbServer->farmId) {
                            $wasHostDownFired = $this->db->GetOne("SELECT id FROM events WHERE event_server_id = ? AND type = ?", array(
                                $serverId, 'HostDown'
                            ));

                            if (!$wasHostDownFired)
                                Scalr::FireEvent($dbServer->farmId, new HostDownEvent($dbServer));
                        }
                    } else {

                        if ($dbServer->status == SERVER_STATUS::TERMINATED) {
                            if (!$dbServer->dateShutdownScheduled || time() - strtotime($dbServer->dateShutdownScheduled) > 600) {
                                $serverHistory->setTerminated();
                                $dbServer->Remove();
                            }
                        } else if ($dbServer->status == SERVER_STATUS::PENDING_TERMINATE) {
                            $dbServer->status = SERVER_STATUS::TERMINATED;
                            $dbServer->Save();
                        } else if ($dbServer->status == SERVER_STATUS::PENDING_SUSPEND) {
                            $dbServer->status = SERVER_STATUS::SUSPENDED;
                            $dbServer->remoteIp = '';
                            $dbServer->localIp = '';
                            $dbServer->Save();
                        }
                    }
                } else {
                    //$serverHistory->setTerminated(); If there is no cloudserverID we don't need to add this server into server history.
                    $dbServer->Remove();
                }
            } catch (Exception $e) {
                if (stristr($e->getMessage(), "not found") ||
                    stristr($e->getMessage(), "could not be found") ||

                    // Cloudstack
                    stristr($e->getMessage(), "or entity does not exist or due to incorrect parameter annotation for the field in api cmd class")) {
                    if ($serverHistory)
                        $serverHistory->setTerminated();

                    $dbServer->Remove();
                } else {
                    throw $e;
                }
            }
        }
    }
}
