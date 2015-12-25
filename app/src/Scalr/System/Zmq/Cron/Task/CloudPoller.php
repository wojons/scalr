<?php

namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception, stdClass;
use Scalr\Service\Azure\Services\Compute\DataType\ExtensionData;
use Scalr\Service\Azure\Services\Compute\DataType\StatusData;
use Scalr_Account;
use Scalr_Environment;
use FARM_STATUS;
use SERVER_STATUS;
use SERVER_PROPERTIES;
use CLOUDSTACK_SERVER_PROPERTIES;
use EC2_SERVER_PROPERTIES;
use ROLE_BEHAVIORS;
use SERVER_PLATFORMS;
use EVENT_TYPE;
use DBFarm;
use FarmLogMessage;
use LOG_CATEGORY;
use DBServer;
use HostDownEvent;
use IPAddressChangedEvent;
use Scalr_Billing;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr\Modules\Platforms\Cloudstack\Helpers\CloudstackHelper;
use Scalr\Modules\Platforms\Openstack\Helpers\OpenstackHelper;
use Scalr\Modules\Platforms\Azure\Helpers\AzureHelper;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\Aws\Ec2\DataType\InstanceAttributeType;
use Scalr\Modules\Platforms\Ec2\Helpers\EipHelper as Ec2EipHelper;
use Scalr\Modules\Platforms\Ec2\Helpers\Ec2Helper;
use Scalr\Model\Entity;
use Scalr\DataType\CloudPlatformSuspensionInfo;

/**
 * CloudPoller
 *
 * It is a replacement for the obsolete Poller job
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.1.0 (18.12.2014)
 */
class CloudPoller extends AbstractTask
{

    /**
     * The list of the cloud platform suspension information
     *
     * @var CloudPlatformSuspensionInfo[]
     */
    private $aSuspensionInfo;

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        $db = \Scalr::getDb();

        $replicaCloudPlatforms = $this->getReplicaTypes('type', '[\w]+');

        $replicaAccounts = $this->getReplicaAccounts();

        $rs = $db->Execute("
            SELECT f.id, f.clientid AS account_id, s.platform
            FROM farms f
            JOIN clients c ON c.id = f.clientid
            JOIN client_environments ce ON ce.id = f.env_id
            JOIN servers s ON s.farm_id = f.id
            LEFT JOIN client_environment_properties cep ON cep.env_id = f.env_id AND cep.name = CONCAT_WS('.', s.platform, ?)
            WHERE c.status = ? AND ce.status = ? AND (cep.value IS NULL OR cep.value = 0)
            GROUP BY f.id, s.platform
        ", [
            CloudPlatformSuspensionInfo::NAME_SUSPENDED,
            Scalr_Account::STATUS_ACTIVE,
            Scalr_Environment::STATUS_ACTIVE
        ]);

        while ($row = $rs->FetchRow()) {
            $obj = new stdClass();
            $obj->farmId = $row['id'];
            $obj->platform = $row['platform'];

            //Adjusts object with custom routing address.
            //It determines which of the workers pool should handle the task.
            $obj->address = $this->name
            . '.' . (!empty($replicaCloudPlatforms) ? (in_array($row['platform'], $replicaCloudPlatforms) ? $row['platform'] : 'all') : 'all')
            . '.' . (!empty($replicaAccounts) ? (in_array($row['account_id'], $replicaAccounts) ? $row['account_id'] : 'all') : 'all');

            $queue->append($obj);
        }

        if ($cnt = count($queue)) {
            $this->getLogger()->info("%d farm%s found.", $cnt, ($cnt == 1 ? '' : 's'));
        }

        return $queue;
    }

    /**
     * Gets suspension info for the current server
     *
     * @param   \DBServer $DBServer  The server
     * @return  CloudPlatformSuspensionInfo Returns cloud platform suspension information for the specified server
     */
    private function getSuspensionInfo($platform, $envId)
    {
        if (empty($this->aSuspensionInfo[$envId][$platform])) {
            $this->aSuspensionInfo[$envId][$platform] = new CloudPlatformSuspensionInfo($envId, $platform);
        }

        return $this->aSuspensionInfo[$envId][$platform];
    }


    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        $db = \Scalr::getDb();

        //The list of the suspension information about cloud platforms
        $this->aSuspensionInfo = [];

        //Speed up poller
        if ($this->config()->daemon) {
            //Warming up static DI cache
            \Scalr::getContainer()->warmup();
        }

        // Reconfigure observers
        \Scalr::ReconfigureObservers();

        $DBFarm = DBFarm::LoadByID($request->farmId);

        $account = Scalr_Account::init()->loadById($DBFarm->ClientID);
        $payAsYouGoTime = $account->getSetting(Scalr_Account::SETTING_BILLING_PAY_AS_YOU_GO_DATE);

        $transactionId = abs(crc32(posix_getpid() . $request->farmId));
        $this->getLogger()->info(
            "[%s] Begin polling farm (ID: %d, Name: %s, Status: %s, Platform:%s)",
            $transactionId,
            $DBFarm->ID,
            $DBFarm->Name,
            $DBFarm->Status,
            $request->platform
        );
        $jobStartTime = microtime(true);

        //Retrieves the number of either terminated or suspended servers for the farm
        $servers_count = $db->GetOne("
            SELECT COUNT(*) AS cnt FROM servers
            WHERE farm_id = ? AND platform = ? AND status NOT IN (?,?)
        ", [$DBFarm->ID, $request->platform, SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED]);

        if ($DBFarm->Status == FARM_STATUS::TERMINATED && $servers_count == 0) {
            //There are no servers for this farm and platform
            return;
        }

        $this->getLogger()->info(
            "%d server%s for the farm: %d and platform: %s",
            $servers_count, ($servers_count == 1 ? '' : 's'), $DBFarm->ID, $request->platform
        );

        $config = \Scalr::getContainer()->config;

        /*
        if ($request->platform) {
            $p = PlatformFactory::NewPlatform($request->platform);
            $p->ClearCache();
        }
        */
        $p = PlatformFactory::NewPlatform($request->platform);

        foreach ($DBFarm->GetServersByFilter(['platform' => $request->platform], ['status' => SERVER_STATUS::PENDING_LAUNCH]) as $DBServer) {
            /* @var $DBServer \DBServer */

            //Get platform suspension info
            $suspensionInfo = $this->getSuspensionInfo($DBServer->platform, $DBServer->envId);

            //If the cloud platform is suspended we should not process it
            if ($suspensionInfo->isSuspended()) {
                continue;
            }

            try {
                //1. We need to check that server is exists in cloud and not missed.
                //   (On Openstack server can be missed and should not be terminated)

                $cacheKey = sprintf('%s:%s', $DBServer->envId, $DBServer->cloudLocation);
                if ($DBServer->cloudLocation && count($p->instancesListCache[$cacheKey]) == 0) {
                    try {
                        $this->getLogger()->info(
                            "Retrieving the list of the instances for %s, server: %s, platform: %s",
                            $DBServer->cloudLocation,
                            $DBServer->serverId,
                            $request->platform
                        );

                        if ($DBServer->platform == \SERVER_PLATFORMS::AZURE) {
                            //For Azure we need to pass resource group instead of cloudLocation
                            $p->GetServersList(
                                $DBServer->GetEnvironmentObject(),
                                $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP)
                            );
                        } else {
                            $p->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->cloudLocation);
                        }

                        //We successfully polled cloud so can resume suspension status for the cloud platform
                        if ($suspensionInfo->isPendingSuspend()) {
                            $suspensionInfo->resume();
                        }
                    } catch (Exception $e) {
                        if (CloudPlatformSuspensionInfo::isSuspensionException($e)) {
                            $suspensionInfo->registerError($e->getMessage());
                        }

                        $this->getLogger()->error(
                            "[Server: %s] Could not retrieve the list of the instances: %s",
                            $DBServer->serverId, $e->getMessage()
                        );

                        continue;
                    }
                }

                if ($DBServer->status != SERVER_STATUS::PENDING && $DBServer->status != SERVER_STATUS::PENDING_TERMINATE) {
                    if (!$p->IsServerExists($DBServer)) {
                        try {
                            $serverInfo = $p->GetServerExtendedInformation($DBServer);
                        } catch (Exception $e) {
                            $this->getLogger()->error(
                                "[CRASH][FarmID: %d] Crash check for server '%s' failed: %s",
                                $DBFarm->ID,
                                $DBServer->serverId,
                                $e->getMessage()
                            );
                            continue;
                        }

                        if (!$serverInfo) {
                            if (!in_array($DBServer->status, [SERVER_STATUS::PENDING_TERMINATE, SERVER_STATUS::TERMINATED])) {
                                if ($DBServer->isOpenstack() && $DBServer->status == SERVER_STATUS::SUSPENDED) {
                                    continue;
                                } elseif ($DBServer->platform == \SERVER_PLATFORMS::GCE && $DBServer->status == SERVER_STATUS::SUSPENDED) {
                                    $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);
                                    \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                                        $DBFarm->ID,
                                        sprintf(_("Server '%s' was terminated"),
                                            $DBServer->serverId
                                        ),
                                        $DBServer->serverId
                                    ));
                                    continue;
                                }

                                $action = 'terminate';
                                if ($config->defined("scalr.{$DBServer->platform}.action_on_missing_server"))
                                    $action = $config->get("scalr.{$DBServer->platform}.action_on_missing_server");

                            	if ($action == 'flag' && !$DBServer->GetProperty(SERVER_PROPERTIES::MISSING)) {
                            	    \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                                        $DBFarm->ID,
                            	        sprintf("Server '%s' found in Scalr but not found in the cloud (%s). Marking as Missing.",
                            	           $DBServer->serverId,
                            	           $DBServer->platform
                            	        ),
                                        $DBServer->serverId
                                    ));

                            		$DBServer->SetProperties([
                            		    SERVER_PROPERTIES::REBOOTING => 0,
                            		    SERVER_PROPERTIES::MISSING   => 1
                            		]);
                            	} else {
                            	    \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                                        $DBFarm->ID,
                            	        sprintf("Server '%s' found in Scalr but not found in the cloud (%s). Terminating.",
                                            $DBServer->serverId,
                                	        $DBServer->platform
                                        ),
                            	        $DBServer->serverId
                        	        ));

                                    $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);
                            	}

                                continue;
                            }
                        } else {
                            //http.persistent.handles.limit must be set to 0 for pecl-http version 1
                            $this->getLogger()->error(
                                "[CRASH][FarmID: %d] False-positive crash check: %s (EnvID: %d). Please verify current scalr install with app/www/testenvironment.php",
                                $DBFarm->ID,
                                $DBServer->serverId,
                                $DBServer->envId
                            );
                        }
                    } else {
                        $DBServer->SetProperties([SERVER_PROPERTIES::MISSING => 0]);
                    }
                }
            } catch (Exception $e) {
                if (CloudPlatformSuspensionInfo::isSuspensionException($e)) {
                    $suspensionInfo->registerError($e->getMessage());
                }

                $this->getLogger()->warn(
                    "Exception for Farm: %d, Platform: %s with the message: %s, in the %s:%s",
                    $request->farmId, $request->platform, $e->getMessage(), $e->getFile(), $e->getLine()
                );

                continue;
            }

            try {
                if (!in_array($DBServer->status, [
                    SERVER_STATUS::SUSPENDED,
                    SERVER_STATUS::TERMINATED,
                    SERVER_STATUS::PENDING_TERMINATE,
                    SERVER_STATUS::PENDING_SUSPEND
                ])) {
                    $openstackErrorState = false;
                    if (PlatformFactory::isOpenstack($DBServer->platform) && $DBServer->GetRealStatus()->getName() === 'ERROR') {
                        $openstackErrorState = true;
                    }

                    if ($DBServer->GetRealStatus()->isTerminated() || $openstackErrorState) {

                        // If openstack server is in ERROR state we need more details
                        if ($openstackErrorState) {
                            try {
                                $info = $p->GetServerExtendedInformation($DBServer);
                                $status = empty($info['Status']) ? false : $info['Status'];
                            } catch (Exception $e) {}
                        }

                        if (empty($status)) {
                            $status = $DBServer->GetRealStatus()->getName();
                        }

                        \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                            $DBFarm->ID,
                            sprintf("Server '%s' (Platform: %s) was terminated in cloud or from within an OS. Status: %s.",
                                $DBServer->serverId,
                                $DBServer->platform,
                                $status
                            ),
                            $DBServer->serverId
                        ));

                        $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);

                        continue;
                    } elseif ($DBServer->GetRealStatus()->isSuspended()) {
                        //In case the server was suspended when it was running
                        if ($DBServer->status == SERVER_STATUS::RUNNING) {
                            \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                                $DBFarm->ID,
                                sprintf("Server '%s' (Platform: %s) is not running (Status in cloud: %s, Status in Scalr: %s).",
                                    $DBServer->serverId,
                                    $DBServer->platform,
                                    $DBServer->GetRealStatus()->getName(),
                                    $DBServer->status
                                ),
                                $DBServer->serverId
                            ));

                            $event = new HostDownEvent($DBServer);
                            $event->isSuspended = true;

                            \Scalr::FireEvent($DBFarm->ID, $event);

                            continue;
                        } else {
                            if ($DBServer->status != \SERVER_STATUS::RESUMING) {
                                //If the server was suspended during initialization
                                //we do not support this and need to terminate this instance

                                if ($DBServer->platform == \SERVER_PLATFORMS::EC2) {
                                    try {
                                        $info = $p->GetServerExtendedInformation($DBServer);
                                        $realStatus = !empty($info['Instance state']) ? $info['Instance state'] : '';
                                    } catch (\Exception $e) {
                                        // no need to do anything here;
                                    }

                                    $this->getLogger()->error(
                                        "[SUSPEND_RESUME_ISSUE][ServerID: %s][2] Cached Cloud Status: %s (Cache age: %d seconds), Status: %s, Real status: %s",
                                        $DBServer->serverId,
                                        $DBServer->GetRealStatus()->getName(),
                                        time() - $p->instancesListCache[$cacheKey][$DBServer->GetCloudServerID()]['_timestamp'],
                                        $DBServer->status,
                                        $realStatus
                                    );
                                }

                                $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);
                                continue;
                            } else {
                                // Need to clear cache, because this situation happens only when cache is stale.
                                $p->ClearCache();
                            }
                        }
                    }
                }

                if ($DBServer->status != SERVER_STATUS::RUNNING && $DBServer->GetRealStatus()->IsRunning()) {
                    if ($DBServer->status == SERVER_STATUS::SUSPENDED) {
                        if ($DBServer->platform == \SERVER_PLATFORMS::GCE) {
                            if ($p->GetServerRealStatus($DBServer)->getName() == 'STOPPING') {
                                continue;
                            }
                        }

                        $update = [];

                        // For Openstack we need to re-accociate IPs
                        try {
                            if ($DBServer->isOpenstack()) {
                                OpenstackHelper::setServerFloatingIp($DBServer);
                            }
                        } catch (Exception $e) {
                            if (!$DBServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED)) {
                                $DBServer->SetProperties([
                                    \SERVER_PROPERTIES::SZR_IS_INIT_FAILED    => 1,
                                    \SERVER_PROPERTIES::SZR_IS_INIT_ERROR_MSG => "Scalr is unable to allocate/associate floating IP with server: ". $e->getMessage(),
                                ]);
                            }
                        }

                        if ($DBServer->platform == \SERVER_PLATFORMS::CLOUDSTACK) {
                            if (!$DBServer->remoteIp) {
                                $update['remoteIp'] = CloudstackHelper::getSharedIP($DBServer);
                            }
                        }

                        if ($DBServer->platform == \SERVER_PLATFORMS::EC2) {
                            try {
                                $info = $p->GetServerExtendedInformation($DBServer);
                                $realStatus = !empty($info['Instance state']) ? $info['Instance state'] : '';
                            } catch (\Exception $e) {
                                // no need to do anything here;
                            }

                            $this->getLogger()->error(
                                "[SUSPEND_RESUME_ISSUE][ServerID: %s][1] Cached Cloud Status: %s (Cache age: %d seconds), Status: %s, Real status: %s",
                                $DBServer->serverId,
                                $DBServer->GetRealStatus()->getName(),
                                time() - $p->instancesListCache[$cacheKey][$DBServer->GetCloudServerID()]['_timestamp'],
                                $DBServer->status,
                                $realStatus
                            );
                        }

                        $update['status'] = \SERVER_STATUS::RESUMING;
                        $update['dateAdded'] = date("Y-m-d H:i:s");

                        $DBServer->update($update);
                        unset($update);

                        continue;
                    } elseif (!in_array($DBServer->status, array(SERVER_STATUS::TERMINATED))) {

                        $elasticIpAssigned = false;

                        if ($DBServer->platform == SERVER_PLATFORMS::EC2) {
                            if ($DBServer->status == SERVER_STATUS::PENDING) {
                                if (!$DBServer->remoteIp && !$DBServer->localIp) {
                                    $ipaddresses = $p->GetServerIPAddresses($DBServer);

                                    $elasticIpAddress = Ec2EipHelper::setEipForServer($DBServer);
                                    if ($elasticIpAddress) {
                                        $ipaddresses['remoteIp'] = $elasticIpAddress;
                                        $DBServer->remoteIp = $elasticIpAddress;
                                        $elasticIpAssigned = true;
                                    }

                                    if (($ipaddresses['remoteIp'] && !$DBServer->remoteIp) || ($ipaddresses['localIp'] && !$DBServer->localIp) || $elasticIpAssigned) {
                                        $DBServer->update([
                                            'remoteIp' => $ipaddresses['remoteIp'],
                                            'localIp' => $ipaddresses['localIp']
                                        ]);
                                    }

                                    //Add tags
                                    Ec2Helper::createObjectTags($DBServer);
                                }
                            }
                        }

                        if ($DBServer->platform == \SERVER_PLATFORMS::AZURE) {
                            if ($DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::SZR_EXTENSION_DEPLOYED)) {
                                if (!$DBServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED)) {
                                    // Check scalarizr deployment status
                                    $env = $DBServer->GetEnvironmentObject();
                                    $azure = $env->azure();

                                    $info = $azure->compute->virtualMachine->getInstanceViewInfo(
                                        $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                                        $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP),
                                        $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::SERVER_NAME)
                                    );

                                    $extensions = !empty($info->extensions) ? $info->extensions : [];

                                    foreach ($extensions as $extension) {
                                        /* @var $extension ExtensionData */
                                        if ($extension->name == 'scalarizr') {
                                            $extStatus = $extension->statuses[0];
                                            /* @var $extStatus StatusData */
                                            if ($extStatus->level == 'Error') {
                                                $DBServer->SetProperties([
                                                    \SERVER_PROPERTIES::SZR_IS_INIT_FAILED    => 1,
                                                    \SERVER_PROPERTIES::SZR_IS_INIT_ERROR_MSG => "Azure resource extension failed to provision scalr agent. Status: {$extStatus->code} ({$extStatus->message})",
                                                ]);
                                            }
                                        }
                                    }
                                }
                            } else {
                                AzureHelper::setupScalrAgent($DBServer);
                            }
                        }

                        try {
                            if ($DBServer->isOpenstack()) {
                                OpenstackHelper::setServerFloatingIp($DBServer);
                            }
                        } catch (Exception $e) {
                            if (!$DBServer->GetProperty(\SERVER_PROPERTIES::SZR_IS_INIT_FAILED)) {
                                $DBServer->SetProperties([
                                    \SERVER_PROPERTIES::SZR_IS_INIT_FAILED    => 1,
                                    \SERVER_PROPERTIES::SZR_IS_INIT_ERROR_MSG => "Scalr is unable to allocate/associate floating IP with server:" . $e->getMessage(),
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
                                    //TODO handle failed job: $res->jobresult->jobstatus == 2
                                } catch (Exception $e) {
                                    if ($DBServer->farmId) {
                                        \Scalr::getContainer()->logger("CloudStack")->error(new FarmLogMessage(
                                            $DBServer->farmId,
                                            $e->getMessage(),
                                            $DBServer->serverId
                                        ));
                                    }
                                }
                            }
                        }

                        try {
                            $dtadded = strtotime($DBServer->dateAdded);
                            $DBFarmRole = $DBServer->GetFarmRoleObject();
                            $launchTimeout = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SYSTEM_LAUNCH_TIMEOUT) > 0 ?
                                              $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SYSTEM_LAUNCH_TIMEOUT) : 900;
                        } catch (Exception $e) {
                            if (stristr($e->getMessage(), "not found")) {
                                $DBServer->terminate(DBServer::TERMINATE_REASON_ROLE_REMOVED);
                            }
                        }

                        $scriptingEvent = false;
                        $eventName = null;

                        if ($DBServer->status == SERVER_STATUS::PENDING) {
                            $eventName = "hostInit";
                            $scriptingEvent = EVENT_TYPE::HOST_INIT;
                        } elseif ($DBServer->status == SERVER_STATUS::INIT) {
                            $eventName = "hostUp";
                            $scriptingEvent = EVENT_TYPE::HOST_UP;
                        }

                        if ($scriptingEvent && $dtadded) {
                            $hasPendingMessages = !!$db->GetOne("
                                SELECT EXISTS(SELECT 1 FROM messages WHERE type='in' AND status='0' AND server_id = ?)
                            ", [$DBServer->serverId]);

                            $scriptingTimeout = (int) $db->GetOne("
                                SELECT SUM(timeout)
                                FROM farm_role_scripts
                                WHERE event_name = ? AND farm_roleid = ? AND issync = '1'
                            ", [$scriptingEvent, $DBServer->farmRoleId]);

                            if ($scriptingTimeout)
                                $launchTimeout = $launchTimeout + $scriptingTimeout;

                            if (!$hasPendingMessages && $dtadded + $launchTimeout < time() && !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
                                //Add entry to farm log
                                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                                    $DBFarm->ID,
                                    sprintf( "Server '%s' did not send '%s' event in %s seconds after launch (Try increasing timeouts in role settings). Considering it broken. Terminating instance.",
                                        $DBServer->serverId,
                                        $eventName,
                                        $launchTimeout
                                    ),
                                    $DBServer->serverId
                                ));

                                try {
                                    $DBServer->terminate(array(DBServer::TERMINATE_REASON_SERVER_DID_NOT_SEND_EVENT, $eventName, $launchTimeout), false);
                                } catch (Exception $err) {
                                    $this->getLogger()->fatal($err->getMessage());
                                }
                            } elseif ($DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
                                //DO NOT TERMINATE MONGODB INSTANCES BY TIMEOUT! IT'S NOT SAFE
                                //THINK ABOUT WORKAROUND
                            }
                        }

                        //Whether IP address is changed
                        if (!$DBServer->IsRebooting() && !$elasticIpAssigned) {
                            $ipaddresses = $p->GetServerIPAddresses($DBServer);

                            if (($ipaddresses['remoteIp'] && $DBServer->remoteIp && $DBServer->remoteIp != $ipaddresses['remoteIp']) ||
                                ($ipaddresses['localIp'] && $DBServer->localIp && $DBServer->localIp != $ipaddresses['localIp'])) {
                                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                                    $DBFarm->ID, sprintf("RemoteIP: %s (%s), LocalIp: %s (%s) (Poller).",
                                        $DBServer->remoteIp,
                                        $ipaddresses['remoteIp'],
                                        $DBServer->localIp,
                                        $ipaddresses['localIp']
                                    ),
                                    $DBServer->serverId
                                ));

                                \Scalr::FireEvent(
                                    $DBServer->farmId,
                                    new IPAddressChangedEvent($DBServer, $ipaddresses['remoteIp'], $ipaddresses['localIp'])
                                );
                            }

                            //TODO Check health
                        }
                    }
                } elseif ($DBServer->status == SERVER_STATUS::SUSPENDED && $DBServer->GetRealStatus()->isTerminated()) {
                    //TODO: Terminated outside scalr while in SUSPENDED state
                    $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);

                } elseif ($DBServer->status == SERVER_STATUS::RUNNING && $DBServer->GetRealStatus()->isRunning()) {
                    // Is IP address changed?
                    if (!$DBServer->IsRebooting()) {
                        $ipaddresses = $p->GetServerIPAddresses($DBServer);

                        // Private IP cannot be removed (only changed).
                        if (($DBServer->remoteIp != $ipaddresses['remoteIp']) || ($ipaddresses['localIp'] && $DBServer->localIp != $ipaddresses['localIp'])) {
                            \Scalr::FireEvent(
                                $DBServer->farmId,
                                new IPAddressChangedEvent($DBServer, $ipaddresses['remoteIp'], $ipaddresses['localIp'])
                            );
                        }

                        if ($payAsYouGoTime) {
                            $initTime = $DBServer->dateInitialized ? strtotime($DBServer->dateInitialized) : null;
                            if ($initTime < $payAsYouGoTime) {
                                $initTime = $payAsYouGoTime;
                            }

                            $runningHours = ceil((time() - $initTime) / 3600);
                            $scuUsed = $runningHours * Scalr_Billing::getSCUByInstanceType($DBServer->getType(), $DBServer->platform);

                            $db->Execute("UPDATE servers_history SET scu_used = ?, scu_updated = 0 WHERE server_id = ?", [$scuUsed, $DBServer->serverId]);
                        }

                        if ($DBServer->platform == SERVER_PLATFORMS::EC2) {
                            $env = Scalr_Environment::init()->loadById($DBServer->envId);
                            $ec2 = $env->aws($DBServer->GetCloudLocation())->ec2;

                            $time = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED_LAST_CHECK_TIME);
                            if (!$time || time() > $time + 1200) {
                                $isEnabled = $ec2->instance->describeAttribute($DBServer->GetCloudServerID(), InstanceAttributeType::disableApiTermination());
                                $DBServer->SetProperties([
                                    EC2_SERVER_PROPERTIES::IS_LOCKED                 => $isEnabled,
                                    EC2_SERVER_PROPERTIES::IS_LOCKED_LAST_CHECK_TIME => time(),
                                ]);
                            }
                        }
                    } else {
                        //TODO Check reboot timeout
                    }
                }
            } catch (Exception $e) {
                if (CloudPlatformSuspensionInfo::isSuspensionException($e)) {
                    $suspensionInfo->registerError($e->getMessage());
                }

                if (stristr($e->getMessage(), "not found")) {
                    $this->getLogger()->fatal($e->getMessage());
                } elseif (stristr($e->getMessage(), "Request limit exceeded")) {
                    sleep(5);
                    $this->getLogger()->error("[Farm: %d] sleep due to exception: %s", $request->farmId, $e->getMessage());
                } else {
                    $this->getLogger()->error("[Farm: %d] Exception: %s", $request->farmId, $e->getMessage());
                }
            }
        }

        $this->getLogger()->info(
            "[%s] Finished farm polling (ID: %d, Name: %s, Status: %s, Platform:%s). Time: %s",
            $transactionId,
            $DBFarm->ID,
            $DBFarm->Name,
            $DBFarm->Status,
            $request->platform,
            microtime(true) - $jobStartTime
        );

        return $request;
    }
}
