<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception, DateTime, DateTimeZone, stdClass;
use \Scalr_Account;
use \Scalr_Environment;
use \FARM_STATUS;
use \SERVER_STATUS;
use \SERVER_PROPERTIES;
use \CLOUDSTACK_SERVER_PROPERTIES;
use \OPENSTACK_SERVER_PROPERTIES;
use \EC2_SERVER_PROPERTIES;
use \ROLE_BEHAVIORS;
use \SERVER_PLATFORMS;
use \EVENT_TYPE;
use \DBFarm;
use \DBFarmRole;
use \Logger;
use \FarmLogMessage;
use \LOG_CATEGORY;
use \DBServer;
use \HostCrashEvent;
use \HostDownEvent;
use \HostUpEvent;
use \IPAddressChangedEvent;
use \Scalr_Billing;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\Aws\Ec2\DataType\InstanceAttributeType;
use Scalr\Modules\Platforms\Ec2\Helpers\EipHelper as Ec2EipHelper;



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
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        $db = \Scalr::getDb();

        $rs = $db->Execute("
            SELECT
                f.id, f.status,
                (EXISTS(SELECT 1 FROM servers s WHERE s.farm_id = f.id)) AS `has_servers`
            FROM farms f
            JOIN clients c ON c.id = f.clientid
            JOIN client_environments ce ON ce.id = f.env_id
            WHERE c.status = ? AND ce.status = ?
        ",[
            Scalr_Account::STATUS_ACTIVE,
            Scalr_Environment::STATUS_ACTIVE
        ]);

        while ($row = $rs->FetchRow()) {
            if ($row['has_servers']) {
                $obj = new stdClass();
                $obj->farmId = $row['id'];

                $queue->append($obj);
            } else if ($row['status'] == FARM_STATUS::SYNCHRONIZING) {
                $db->Execute("UPDATE farms SET status = ? WHERE id = ?", [FARM_STATUS::TERMINATED, $row['id']]);
            }
        }

        if ($cnt = count($queue)) {
            $this->getLogger()->info("%d farm%s found.", $cnt, ($cnt == 1 ? '' : 's'));
        }

        return $queue;
    }


    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        $db = \Scalr::getDb();

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

        $GLOBALS["SUB_TRANSACTIONID"] = abs(crc32(posix_getpid() . $request->farmId));
        $GLOBALS["LOGGER_FARMID"] = $request->farmId;

        $this->getLogger()->info(
            "[%s] Begin polling farm (ID: %d, Name: %s, Status: %s)",
            $GLOBALS["SUB_TRANSACTIONID"],
            $DBFarm->ID,
            $DBFarm->Name,
            $DBFarm->Status
        );

        //Retrieves the number of either terminated or suspended servers for the farm
        $servers_count = $db->GetOne("
            SELECT COUNT(*) AS cnt FROM servers WHERE farm_id = ? AND status NOT IN (?,?)
        ", [$DBFarm->ID, SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED]);

        if ($DBFarm->Status == FARM_STATUS::TERMINATED && $servers_count == 0) {
            //There are no servers for this farm
            return;
        }

        $this->getLogger()->info("%d server%s for the farm: %d", $servers_count, ($servers_count == 1 ? '' : 's'), $DBFarm->ID);

        foreach ($DBFarm->GetServersByFilter(array(), array('status' => SERVER_STATUS::PENDING_LAUNCH)) as $DBServer) {
            /* @var $DBServer \DBServer */
            try {
                if ($DBServer->cloudLocation) {
                    try {
                        $this->getLogger()->info(
                            "Retrieving the list of the instances for %s, server: %s",
                            $DBServer->cloudLocation,
                            $DBServer->serverId
                        );

                        $p = PlatformFactory::NewPlatform($DBServer->platform);

                        $list = $p->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->cloudLocation);
                    } catch (Exception $e) {
                        $this->getLogger()->error("Could not retrieve the list of the instances: %s", $e->getMessage());
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
                        }

                        if (!$serverInfo) {
                            if (!in_array($DBServer->status, [SERVER_STATUS::PENDING_TERMINATE, SERVER_STATUS::TERMINATED])) {
                                if ($DBServer->isOpenstack() && $DBServer->status == SERVER_STATUS::SUSPENDED) {
                                    continue;
                                }

                                if ($DBServer->GetProperty(SERVER_PROPERTIES::CRASHED) == 1) {
                                	if (PlatformFactory::isOpenstack($DBServer->platform)) {
                                		$DBServer->SetProperty(SERVER_PROPERTIES::MISSING, 1);
                                	} else {
	                                    $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);
	                                    \Scalr::FireEvent($DBFarm->ID, new HostCrashEvent($DBServer));
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
                            //http.persistent.handles.limit must be set to 0 for pecl-http version 1
                            $this->getLogger()->error(
                                "[CRASH][FarmID: %d] False-positive crash check: %s (EnvID: %d). Please verify current scalr install with app/www/testenvironment.php",
                                $DBFarm->ID,
                                $DBServer->serverId,
                                $DBServer->envId
                            );
                        }
                    } else {
                        $DBServer->SetProperty(SERVER_PROPERTIES::CRASHED, "0");
                        $DBServer->SetProperty(SERVER_PROPERTIES::MISSING, "0");
                    }
                }
            } catch (Exception $e) {
                if (stristr($e->getMessage(), "AWS was not able to validate the provided access credentials") ||
                    stristr($e->getMessage(), "Unable to sign AWS API request. Please, check your X.509")) {
                    /* @var $env \Scalr_Environment */
                    $env = Scalr_Environment::init()->LoadById($DBFarm->EnvID);
                    $env->status = Scalr_Environment::STATUS_INACTIVE;
                    $env->save();

                    //Saving the reason why this environment is disabled
                    $env->setPlatformConfig(['system.auto-disable-reason' => $e->getMessage()]);

                    return;
                } elseif (stristr($e->getMessage(), "Could not connect to host")) {
                    continue;
                }

                $this->getLogger()->warn(
                    "Exception for farm: %d with the message: %s, in the %s:%s",
                    $request->farmId, $e->getMessage(), $e->getFile(), $e->getLine()
                );

                continue;
            }

            try {
                if (!in_array($DBServer->status, [
                        SERVER_STATUS::SUSPENDED,
                        SERVER_STATUS::TERMINATED,
                        SERVER_STATUS::PENDING_TERMINATE,
                        SERVER_STATUS::PENDING_SUSPEND ])) {
                    $openstackErrorState = false;

                    if (PlatformFactory::isOpenstack($DBServer->platform) && $DBServer->GetRealStatus()->getName() === 'ERROR') {
                        $openstackErrorState = true;
                    }

                    if ($DBServer->GetRealStatus()->isTerminated() || $openstackErrorState) {
                        Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID,
                            sprintf("Server '%s' (Platform: %s) is not running (Real state: %s, Scalr status: %s).",
                                $DBServer->serverId,
                                $DBServer->platform,
                                $DBServer->GetRealStatus()->getName(),
                                $DBServer->status
                            )
                        ));

                        $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);

                        $DBServer->SetProperties([
                            SERVER_PROPERTIES::REBOOTING => 0,
                            SERVER_PROPERTIES::RESUMING  => 0,
                        ]);

                        \Scalr::FireEvent($DBFarm->ID, new HostDownEvent($DBServer));

                        continue;
                    } elseif ($DBServer->GetRealStatus()->isSuspended()) {
                        //In case the server was suspended when it was running
                        if ($DBServer->status == SERVER_STATUS::RUNNING) {
                            Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID,
                                sprintf("Server '%s' (Platform: %s) is not running (Real state: %s, Scalr status: %s).",
                                    $DBServer->serverId,
                                    $DBServer->platform,
                                    $DBServer->GetRealStatus()->getName(),
                                    $DBServer->status
                                )
                            ));

                            $DBServer->SetProperties([
                                SERVER_PROPERTIES::REBOOTING  => 0,
                                SERVER_PROPERTIES::RESUMING   => 0
                            ]);

                            $DBServer->remoteIp = "";
                            $DBServer->localIp = "";

                            $DBServer->status = SERVER_STATUS::SUSPENDED;
                            $DBServer->Save();
                            
                            $event = new HostDownEvent($DBServer);
                            $event->isSuspended = true;

                            \Scalr::FireEvent($DBFarm->ID, $event);

                            continue;
                        } else {
                            //If the server was suspended during initialization
                            //we do not support this and need to terminate this instance
                            $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);
                            continue;
                        }
                    }
                }

                if ($DBServer->status != SERVER_STATUS::RUNNING && $DBServer->GetRealStatus()->IsRunning()) {
                    if ($DBServer->status == SERVER_STATUS::SUSPENDED) {
                        // For Openstack we need to re-accociate IPs
                        try {
                            if ($DBServer->isOpenstack()) {
                                $this->openstackSetFloatingIp($DBServer);
                            }
                        } catch (Exception $e) {
                            if (!$DBServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED)) {
                                $DBServer->SetProperties([
                                    SERVER_PROPERTIES::SZR_IS_INIT_FAILED    => 1,
                                    SERVER_PROPERTIES::SZR_IS_INIT_ERROR_MSG => $e->getMessage(),
                                ]);
                            }
                        }

                        $platform = PlatformFactory::NewPlatform($DBServer->platform);

                        if ($platform->getResumeStrategy() == \Scalr_Role_Behavior::RESUME_STRATEGY_INIT) {
                            $DBServer->status = \SERVER_STATUS::PENDING;
                            $DBServer->SetProperty(\SERVER_PROPERTIES::RESUMING, 1);
                            $DBServer->dateAdded = date("Y-m-d H:i:s");
                            $DBServer->Save();
                        } else {
                            $DBServer->SetProperty(\SERVER_PROPERTIES::RESUMING, 0);
                            \Scalr::FireEvent($DBFarm->ID, new HostUpEvent($DBServer, ""));
                        }

                        continue;
                    } elseif (!in_array($DBServer->status, array(SERVER_STATUS::TERMINATED, SERVER_STATUS::TROUBLESHOOTING))) {
                        if ($DBServer->platform == SERVER_PLATFORMS::EC2) {
                            if ($DBServer->status == SERVER_STATUS::PENDING && $DBFarm->GetSetting(DBFarm::SETTING_EC2_VPC_ID)) {
                                if ($DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_VPC_INTERNET_ACCESS) != 'outbound-only') {
                                    $ipAddress = Ec2EipHelper::setEipForServer($DBServer);
                                    if ($ipAddress) {
                                        $DBServer->remoteIp = $ipAddress;
                                        $DBServer->Save();
                                    }
                                }
                            }
                        }

                        try {
                            if ($DBServer->isOpenstack()) {
                                $this->openstackSetFloatingIp($DBServer);
                            }
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
                                    //TODO handle failed job: $res->jobresult->jobstatus == 2
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
                        } elseif ($DBServer->status == SERVER_STATUS::INIT) {
                            $event = "hostUp";
                            $scripting_event = EVENT_TYPE::HOST_UP;
                        }

                        if ($scripting_event && $dtadded) {
                            $scripting_timeout = (int) $db->GetOne("
                                SELECT SUM(timeout)
                                FROM farm_role_scripts
                                WHERE event_name = ? AND farm_roleid = ? AND issync = '1'
                            ", [$scripting_event, $DBServer->farmRoleId]);

                            if ($scripting_timeout) {
                                $launch_timeout = $launch_timeout + $scripting_timeout;
                            }

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
                                    $this->getLogger()->fatal($err->getMessage());
                                }
                            } elseif ($DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
                                //DO NOT TERMINATE MONGODB INSTANCES BY TIMEOUT! IT'S NOT SAFE
                                //THINK ABOUT WORKAROUND
                            }
                        }

                        //Whether IP address is changed
                        if (!$DBServer->IsRebooting()) {
                            $ipaddresses = PlatformFactory::NewPlatform($DBServer->platform)->GetServerIPAddresses($DBServer);

                            if (($ipaddresses['remoteIp'] && $DBServer->remoteIp && $DBServer->remoteIp != $ipaddresses['remoteIp']) ||
                                ($ipaddresses['localIp'] && $DBServer->localIp && $DBServer->localIp != $ipaddresses['localIp'])) {
                                Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($DBFarm->ID, sprintf(
                                    "RemoteIP: %s (%s), LocalIp: %s (%s) (Poller).",
                                    $DBServer->remoteIp,
                                    $ipaddresses['remoteIp'],
                                    $DBServer->localIp,
                                    $ipaddresses['localIp']
                                )));

                                \Scalr::FireEvent(
                                    $DBServer->farmId,
                                    new IPAddressChangedEvent($DBServer, $ipaddresses['remoteIp'], $ipaddresses['localIp'])
                                );
                            }

                            //TODO Check health
                        }
                    }
                } elseif ($DBServer->status == SERVER_STATUS::SUSPENDED && $DBServer->GetRealStatus()->isTerminated()) {
                    if ($DBServer->platform == SERVER_PLATFORMS::EC2) {
                        $DBServer->terminate(DBServer::TERMINATE_REASON_CRASHED);
                        \Scalr::FireEvent($DBFarm->ID, new HostCrashEvent($DBServer));
                    }
                } elseif ($DBServer->status == SERVER_STATUS::RUNNING && $DBServer->GetRealStatus()->isRunning()) {
                    // Is IP address changed?
                    if (!$DBServer->IsRebooting()) {
                        $ipaddresses = PlatformFactory::NewPlatform($DBServer->platform)->GetServerIPAddresses($DBServer);

                        if (($ipaddresses['remoteIp'] && $DBServer->remoteIp != $ipaddresses['remoteIp']) ||
                            ($ipaddresses['localIp']  && $DBServer->localIp != $ipaddresses['localIp'])) {
                            \Scalr::FireEvent(
                                $DBServer->farmId,
                                new IPAddressChangedEvent($DBServer, $ipaddresses['remoteIp'], $ipaddresses['localIp'])
                            );
                        }

                        if ($payAsYouGoTime) {
                            $initTime = $DBServer->GetProperty(SERVER_PROPERTIES::INITIALIZED_TIME);
                            if ($initTime < $payAsYouGoTime) {
                                $initTime = $payAsYouGoTime;
                            }

                            $runningHours = ceil((time() - $initTime) / 3600);
                            $scuUsed = $runningHours * Scalr_Billing::getSCUByInstanceType($DBServer->GetFlavor(), $DBServer->platform);

                            $db->Execute("UPDATE servers_history SET scu_used = ?, scu_updated = 0 WHERE server_id = ?", [$scuUsed, $DBServer->serverId]);
                        }

                        if ($DBServer->platform == SERVER_PLATFORMS::EC2) {
                            $env = Scalr_Environment::init()->loadById($DBServer->envId);
                            $ec2 = $env->aws($DBServer->GetCloudLocation())->ec2;

                            $time = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED_LAST_CHECK_TIME);
                            if (!$time || time() < $time + 1200) {
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

        return $request;
    }

    /**
     * Sets floating IP for openstack server
     *
     * @param   \DBServer    $DBServer  The server object
     */
    private function openstackSetFloatingIp(DBServer $DBServer)
    {
        $ipPool = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_OPENSTACK_IP_POOL);

        if (!$DBServer->remoteIp && (in_array($DBServer->status, array(SERVER_STATUS::PENDING, SERVER_STATUS::INIT, SERVER_STATUS::SUSPENDED))) && $ipPool) {
            //$ipAddress = \Scalr\Modules\Platforms\Openstack\Helpers\OpenstackHelper::setFloatingIpForServer($DBServer);

        	$osClient = $DBServer->GetEnvironmentObject()->openstack(
				$DBServer->platform, $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION)
            );

            if ($osClient->hasService('network')) {
                $platform = PlatformFactory::NewPlatform($DBServer->platform);
                $serverIps = $platform->GetServerIPAddresses($DBServer);

                // USE Quantum (Neuron) NETWORK
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

                    if (!$ip->fixed_ip_address && $ip->floating_ip_address && !$ip->port_id) {
                        $ipInfo = $ip;
                    }
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
                        if ($port->device_id == $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID)) {
                            $serverNetworkPort[] = $port;
                        }
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
                                    OPENSTACK_SERVER_PROPERTIES::FLOATING_IP    => $ipInfo->floating_ip_address,
                                    OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => $ipInfo->id,
                                ));

                                $ipAddress = $ipInfo->floating_ip_address;

                                break;
                            } catch (Exception $e) {
                            	$this->getLogger()->error("Clould not allocate new floating IP from pool: %s", $e->getMessage());
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
                //USE NOVA NETWORK
                //Check free existing IP
                $ipAssigned = false;
                $ipAddress = false;

                $ips = $osClient->servers->floatingIps->list($ipPool);
                foreach ($ips as $ip) {
                    if (!$ip->instance_id) {
                    	$ipAddress = $ip->ip;
                    	$ipAddressId = $ip->id;
                    }


                    if ($ip->instance_id == $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID)) {
                        $ipAddress = $ip->ip;
                        $ipAssigned = true;
                    }
                }

                //If no free IP allocate new from pool
                if (!$ipAddress) {
                    $ip = $osClient->servers->floatingIps->create($ipPool);
                    $ipAddress = $ip->ip;
                    $ipAddressId = $ip->id;
                }

                if (!$ipAssigned) {
                    //Associate floating IP with Instance
                    $osClient->servers->addFloatingIp($DBServer->GetCloudServerID(), $ipAddress);
                    
                    $DBServer->SetProperties(array(
                        OPENSTACK_SERVER_PROPERTIES::FLOATING_IP => $ipAddress,
                        OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => $ipAddressId
                    ));
                }
            }

            if ($ipAddress) {
                $DBServer->remoteIp = $ipAddress;
                $DBServer->Save();
                $DBServer->SetProperty(SERVER_PROPERTIES::SYSTEM_IGNORE_INBOUND_MESSAGES, null);
            }
        }
    }
}