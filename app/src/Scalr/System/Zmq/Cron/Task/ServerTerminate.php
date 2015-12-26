<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, DateTime, DateTimeZone, Exception;
use HttpRequest;
use Scalr\Exception\ServerNotFoundException;
use Scalr\Model\Entity\Server\TerminationData;
use Scalr\Modules\PlatformModuleInterface;
use Scalr\Service\Exception\InstanceNotFound;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr\Modules\PlatformFactory;
use \DBServer;
use Scalr\Util\CallbackInterface;
use \SERVER_STATUS;
use \EC2_SERVER_PROPERTIES;
use \SERVER_PROPERTIES;
use \ROLE_BEHAVIORS;
use \LOG_CATEGORY;
use \FarmLogMessage;
use \HostDownEvent;
use stdClass;
use Scalr\Model\Entity\ServerTerminationError;
use Scalr\Exception\InvalidCloudCredentialsException;
use Scalr\DataType\CloudPlatformSuspensionInfo;
use Scalr\Service\Aws\Ec2\DataType\InstanceAttributeType;


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

        try {
            $dbServer = DBServer::LoadByID($request->serverId);
        } catch (ServerNotFoundException $e) {
            $this->log('INFO', "Server:%s does not exist:%s", $request->serverId, $e->getMessage());

            return false;
        }


        if (!in_array($dbServer->status, [
            SERVER_STATUS::PENDING_TERMINATE,
            SERVER_STATUS::PENDING_SUSPEND,
            SERVER_STATUS::TERMINATED,
            SERVER_STATUS::SUSPENDED
        ])) {
            return false;
        }

        // Check and skip locked instances
        if ($dbServer->status == SERVER_STATUS::PENDING_TERMINATE && $dbServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED) == 1) {
            if (($checkDateTime = $dbServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED_LAST_CHECK_TIME)) <= time()) {
                if (! $dbServer->GetRealStatus(true)->isTerminated()) {
                    $isLocked = $dbServer->GetEnvironmentObject()->aws($dbServer->GetCloudLocation())->ec2->instance->describeAttribute($dbServer->GetCloudServerID(), InstanceAttributeType::disableApiTermination());
                    if ($isLocked) {
                        \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                            $dbServer->GetFarmObject()->ID,
                            sprintf("Server '%s' has disableAPITermination flag and can't be terminated (Platform: %s) (ServerTerminate).",
                                $dbServer->serverId, $dbServer->platform
                            ),
                            $dbServer->serverId
                        ));

                        $startTime = strtotime($dbServer->dateShutdownScheduled);

                        // 1, 2, 3, 4, 5, 6, 9, 14, ... 60
                        $diff = round((($checkDateTime < $startTime ? $startTime : $checkDateTime) - $startTime) / 60 * 0.5) * 60;
                        $diff = $diff == 0 ? 60 : ($diff > 3600 ? 3600 : $diff);

                        $dbServer->SetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED_LAST_CHECK_TIME, time() + $diff);
                        return false;
                    } else {
                        $dbServer->SetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED, $isLocked);
                    }
                }
            } else {
                return false;
            }
        }

        //Warming up static DI cache
        \Scalr::getContainer()->warmup();

        // Reconfigure observers
        \Scalr::ReconfigureObservers();

        if ($dbServer->status == SERVER_STATUS::TERMINATED || $dbServer->dateShutdownScheduled <= $dtNow->format('Y-m-d H:i:s')) {
            try {
                $p = PlatformFactory::NewPlatform($dbServer->platform);

                $environment = $dbServer->GetEnvironmentObject();

                if (!$environment->isPlatformEnabled($dbServer->platform)) {
                    throw new Exception(sprintf(
                        "%s platform is not enabled in the '%s' (%d) environment.",
                        $dbServer->platform, $environment->name, $environment->id
                    ));
                }

                if ($dbServer->GetCloudServerID()) {
                    $serverHistory = $dbServer->getServerHistory();

                    $isTermination = in_array(
                        $dbServer->status, [SERVER_STATUS::TERMINATED, SERVER_STATUS::PENDING_TERMINATE]
                    );
                    $isSuspension = in_array(
                        $dbServer->status, [SERVER_STATUS::SUSPENDED, SERVER_STATUS::PENDING_SUSPEND]
                    );

                    /* @var $terminationData TerminationData */
                    $terminationData = null;

                    //NOTE: in any case, after call, be sure to set callback to null
                    $this->setupClientCallback($p, function ($request, $response) use ($dbServer, &$terminationData) {
                        $terminationData = new TerminationData();
                        $terminationData->serverId = $dbServer->serverId;

                        if ($request instanceof \http\Client\Request) {
                            $terminationData->requestUrl = $request->getRequestUrl();
                            $terminationData->requestQuery = $request->getQuery();
                            $terminationData->request = $request->toString();
                        }

                        if ($response instanceof \http\Client\Response) {
                            $terminationData->response = $response->toString();
                            $terminationData->responseCode = $response->getResponseCode();
                            $terminationData->responseStatus = $response->getResponseStatus();
                        }
                    }, $dbServer);

                    try {
                        $status = $dbServer->GetRealStatus();
                    } catch (Exception $e) {
                        //eliminate callback
                        $this->setupClientCallback($p, null, $dbServer);

                        throw $e;
                    }

                    //eliminate callback
                    $this->setupClientCallback($p, null, $dbServer);

                    if ($dbServer->isCloudstack()) {
                        //Workaround for when expunge flag not working and servers stuck in Destroyed state.
                        $isTerminated = $status->isTerminated() && $status->getName() != 'Destroyed';
                    } else {
                        $isTerminated = $status->isTerminated();
                    }

                    if (($isTermination && !$isTerminated) || ($isSuspension && !$dbServer->GetRealStatus()->isSuspended())) {
                        try {
                            if ($dbServer->farmId != 0) {
                                try {
                                    if ($dbServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
                                        $serversCount = count($dbServer->GetFarmRoleObject()->GetServersByFilter([], ['status' => [SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED]]));
                                        if ($dbServer->index == 1 && $serversCount > 1) {
                                            \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(
                                                new FarmLogMessage(
                                                    $dbServer->GetFarmObject()->ID,
                                                    sprintf("RabbitMQ role. Main DISK node should be terminated after all other nodes. Waiting... (Platform: %s) (ServerTerminate).",
                                                        $dbServer->serverId, $dbServer->platform
                                                    ),
                                                    $dbServer->serverId
                                                )
                                            );

                                            return false;
                                        }
                                    }
                                } catch (Exception $e) {
                                }

                                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(
                                    new FarmLogMessage(
                                        $dbServer->GetFarmObject()->ID,
                                        sprintf("Terminating server '%s' (Platform: %s) (ServerTerminate).",
                                            $dbServer->serverId, $dbServer->platform
                                        ),
                                        $dbServer->serverId
                                    )
                                );
                            }
                        } catch (Exception $e) {
                            $this->getLogger()->warn("Server: %s caused exception: %s", $request->serverId, $e->getMessage());
                        }

                        $terminationTime = $dbServer->GetProperty(SERVER_PROPERTIES::TERMINATION_REQUEST_UNIXTIME);

                        if (!$terminationTime || (time() - $terminationTime) > 180) {
                            if ($isTermination) {
                                $p->TerminateServer($dbServer);
                            } else {
                                $p->SuspendServer($dbServer);
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

                                if (isset($terminationData)) {
                                    $terminationData->save();
                                }
                            }
                        } else if ($dbServer->status == SERVER_STATUS::PENDING_TERMINATE) {
                            $dbServer->updateStatus(SERVER_STATUS::TERMINATED);

                            $errorResolution = true;
                        } else if ($dbServer->status == SERVER_STATUS::PENDING_SUSPEND) {
                            $dbServer->update([
                                'status'   => SERVER_STATUS::SUSPENDED,
                                'remoteIp' => '',
                                'localIp'  => ''
                            ]);

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

                if (isset($terminationData)) {
                    $terminationData->save();
                }
            } catch (Exception $e) {
                if ($request->serverId &&
                    (stripos($e->getMessage(), "modify its 'disableApiTermination' instance attribute and try again") !== false) &&
                    $dbServer &&
                    $dbServer->platform == \SERVER_PLATFORMS::EC2)
                {
                    // server has disableApiTermination flag on cloud, update server properties
                    $dbServer->SetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED, 1);

                } else if ($request->serverId && ($e instanceof InvalidCloudCredentialsException ||
                    CloudPlatformSuspensionInfo::isSuspensionException($e) ||
                    stripos($e->getMessage(), "tenant is disabled") !== false ||
                    stripos($e->getMessage(), "was not able to validate the provided access credentials") !== false ||
                    stripos($e->getMessage(), "platform is not enabled") !== false ||
                    stripos($e->getMessage(), "neither api key nor password was provided for the openstack config") !== false ||
                    stripos($e->getMessage(), "refreshing the OAuth2 token") !== false ||
                    strpos($e->getMessage(), "Cannot obtain endpoint url. Unavailable service") !== false)) {
                    //Postpones unsuccessful task for 30 minutes.
                    $ste = new ServerTerminationError(
                        $request->serverId, (isset($request->attempts) ? $request->attempts + 1 : 1), $e->getMessage()
                    );

                    $minutes = rand(30, 40);
                    $ste->retryAfter = new \DateTime('+' . $minutes . ' minutes');

                    if ($ste->attempts > self::MAX_ATTEMPTS && in_array($dbServer->status, [SERVER_STATUS::PENDING_TERMINATE, SERVER_STATUS::TERMINATED])) {
                        //We are going to remove those with Pending terminate status from Scalr after 1 month of unsuccessful attempts
                        $dbServer->Remove();

                        if (isset($terminationData)) {
                            $terminationData->save();
                        }
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

    /**
     * Setups callback to API HTTP client configured for specified server
     *
     * @param   PlatformModuleInterface $platformModule          Platform module for the server
     * @param   callable                $callback       optional Settable callback
     * @param   DBServer                $dbServer       optional Server to configure client
     */
    private function setupClientCallback(PlatformModuleInterface $platformModule, callable $callback = null, DBServer $dbServer = null)
    {
        $client = $platformModule->getHttpClient($dbServer);

        if ($client instanceof CallbackInterface) {
            $client->setCallback($callback);
        }
    }
}
