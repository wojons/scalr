<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, DateTime, DateTimeZone, Exception;
use Scalr\Service\Exception\InstanceNotFound;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr\Modules\PlatformFactory;
use \DBServer;
use \SERVER_STATUS;
use \EC2_SERVER_PROPERTIES;
use \SERVER_PROPERTIES;
use \ROLE_BEHAVIORS;
use \Logger;
use \LOG_CATEGORY;
use \FarmLogMessage;
use \HostDownEvent;
use stdClass;
use Scalr\Model\Entity\ServerTerminationError;


/**
 * ServerTerminate
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (29.10.2014)
 */
class ServerTerminate extends AbstractTask
{

    //24 hours * 2 attempt per hour = 48 times per day
    //we should try to process for 30 days so 30 * 48 attempts is the max.

    const MAX_ATTEMPTS = 1440;

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        $this->getLogger()->info("Fetching servers to remove...");

        foreach (DBServer::getTerminatingServers() as $row) {
            $obj = new stdClass();
            $obj->serverId = $row['server_id'];
            $obj->attempts = $row['attempts'];

            $queue->append($obj);
        }

        $this->getLogger()->info("Found " . count($queue) . " servers to terminate");

        return $queue;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        $dtNow = new DateTime('now');

        $dbServer = DBServer::LoadByID($request->serverId);

        if (!in_array($dbServer->status, array(
            SERVER_STATUS::PENDING_TERMINATE,
            SERVER_STATUS::PENDING_SUSPEND,
            SERVER_STATUS::TERMINATED,
            SERVER_STATUS::SUSPENDED
        ))) {
            return false;
        }

        //Skip Locked instances
        if ($dbServer->status == SERVER_STATUS::PENDING_TERMINATE && $dbServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED) == 1) {
            return false;
        }

        //Warming up static DI cache
        \Scalr::getContainer()->warmup();

        // Reconfigure observers
        \Scalr::ReconfigureObservers();

        if (in_array($dbServer->status, array(SERVER_STATUS::TERMINATED)) || $dbServer->dateShutdownScheduled <= $dtNow->format('Y-m-d H:i:s')) {
            try {
                if ($dbServer->GetCloudServerID()) {
                    $serverHistory = $dbServer->getServerHistory();

                    $isTermination = in_array(
                        $dbServer->status, array(SERVER_STATUS::TERMINATED, SERVER_STATUS::PENDING_TERMINATE)
                    );
                    $isSuspension = in_array(
                        $dbServer->status, array(SERVER_STATUS::SUSPENDED, SERVER_STATUS::PENDING_SUSPEND)
                    );

                    if (($isTermination && !$dbServer->GetRealStatus()->isTerminated()) || ($isSuspension && !$dbServer->GetRealStatus()->isSuspended())) {
                        try {
                            if ($dbServer->farmId != 0) {
                                try {
                                    if ($dbServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
                                        $serversCount = count($dbServer->GetFarmRoleObject()->GetServersByFilter([], ['status' => [SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED]]));
                                        if ($dbServer->index == 1 && $serversCount > 1) {
                                            Logger::getLogger(LOG_CATEGORY::FARM)->warn(
                                                new FarmLogMessage(
                                                    $dbServer->GetFarmObject()->ID, sprintf(
                                                        "RabbitMQ role. Main DISK node should be terminated after all other nodes. "
                                                        . "Waiting... (Platform: %s) (ServerTerminate).",
                                                        $dbServer->serverId, $dbServer->platform
                                                    )
                                                )
                                            );

                                            return false;
                                        }
                                    }
                                } catch (Exception $e) {
                                }

                                Logger::getLogger(LOG_CATEGORY::FARM)->warn(
                                    new FarmLogMessage(
                                        $dbServer->GetFarmObject()->ID, sprintf(
                                            "Terminating server '%s' (Platform: %s) (ServerTerminate).",
                                            $dbServer->serverId, $dbServer->platform
                                        )
                                    )
                                );
                            }
                        } catch (Exception $e) {
                            $this->getLogger()->warn("Server: %s caused exception: %s", $request->serverId, $e->getMessage());
                        }

                        $terminationTime = $dbServer->GetProperty(SERVER_PROPERTIES::TERMINATION_REQUEST_UNIXTIME);

                        if (!$terminationTime || (time() - $terminationTime) > 180) {
                            if ($isTermination) {
                                PlatformFactory::NewPlatform($dbServer->platform)->TerminateServer($dbServer);
                            } else {
                                PlatformFactory::NewPlatform($dbServer->platform)->SuspendServer($dbServer);
                            }

                            $dbServer->SetProperty(SERVER_PROPERTIES::TERMINATION_REQUEST_UNIXTIME, time());

                            if ($dbServer->farmId) {
                                $wasHostDownFired = \Scalr::getDb()->GetOne("
                                    SELECT id FROM events WHERE event_server_id = ? AND type = ? AND is_suspend = '0'", array(
                                    $request->serverId, 'HostDown'
                                ));

                                if (!$wasHostDownFired) {
                                    $event = new HostDownEvent($dbServer);
                                    $event->isSuspended = !$isTermination;
                                    
                                    \Scalr::FireEvent($dbServer->farmId, $event);
                                }
                            }
                        }
                    } else {
                        if ($dbServer->status == SERVER_STATUS::TERMINATED) {
                            if (!$dbServer->dateShutdownScheduled || time() - strtotime($dbServer->dateShutdownScheduled) > 600) {
                                $errorResolution = true;
                                $serverHistory->setTerminated();
                                $dbServer->Remove();
                            }
                        } else if ($dbServer->status == SERVER_STATUS::PENDING_TERMINATE) {
                            $dbServer->status = SERVER_STATUS::TERMINATED;
                            $dbServer->Save();
                            $errorResolution = true;
                        } else if ($dbServer->status == SERVER_STATUS::PENDING_SUSPEND) {
                            $dbServer->status = SERVER_STATUS::SUSPENDED;
                            $dbServer->remoteIp = '';
                            $dbServer->localIp = '';
                            $dbServer->Save();
                            $errorResolution = true;
                        }

                        if (!empty($errorResolution) && $request->attempts > 0 && $ste = ServerTerminationError::findPk($request->serverId)) {
                            //Automatic error resolution
                            $ste->delete();
                        }
                    }
                } else {
                    //If there is no cloudserverID we don't need to add this server into server history.
                    //$serverHistory->setTerminated();
                    $dbServer->Remove();
                }
            } catch (InstanceNotFound $e) {
                if ($serverHistory) {
                    $serverHistory->setTerminated();
                }
                $dbServer->Remove();
            } catch (Exception $e) {
                if ($request->serverId &&
                          (stristr($e->getMessage(), "tenant is disabled") ||
                          stristr($e->getMessage(), "was not able to validate the provided access credentials") ||
                          stristr($e->getMessage(), "modify its 'disableApiTermination' instance attribute and try again") ||
                          stristr($e->getMessage(), "neither api key nor password was provided for the openstack config"))) {
                    //Postpones unsuccessful task for 30 minutes.
                    $ste = new ServerTerminationError(
                        $request->serverId, (isset($request->attempts) ? $request->attempts + 1 : 1), $e->getMessage()
                    );

                    $minutes = rand(30, 40);
                    $ste->retryAfter = new \DateTime('+' . $minutes . ' minutes');

                    if ($ste->attempts > self::MAX_ATTEMPTS && in_array($dbServer->status, [SERVER_STATUS::PENDING_TERMINATE, SERVER_STATUS::TERMINATED])) {
                        //We are going to remove those with Pending terminate status after 1 month of unsuccessful attempts
                        //$dbServer->Remove();
                    }

                    $ste->save();
                } else {
                    $this->log('ERROR', "Server:%s, failed - Exeption:%s %s", $request->serverId, get_class($e), $e->getMessage());

                    throw $e;
                }
            }
        }

        return $request;
    }
}