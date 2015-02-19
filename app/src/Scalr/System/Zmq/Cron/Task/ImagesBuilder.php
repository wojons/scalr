<?php

namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception, DateTime, DateTimeZone, stdClass;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
use \BundleTask;
use \DBServer;
use \DBRole;
use \DBFarm;
use \DBFarmRole;
use \SERVER_PROPERTIES;
use \CLOUDSTACK_SERVER_PROPERTIES;
use \OPENSTACK_SERVER_PROPERTIES;
use \SERVER_STATUS;
use \SERVER_SNAPSHOT_CREATION_STATUS;
use \SERVER_PLATFORMS;
use \SERVER_REPLACEMENT_TYPE;

/**
 * ImagesBuilder
 *
 * It is a replacement for the obsolete BundleTasksManager job
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (08.12.2014)
 */
class ImagesBuilder extends AbstractTask
{
    /**
     * @var BundleTask
     */
    private $bundleTask = null;

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        $db = \Scalr::getDb();

        $rs = $db->Execute("SELECT id FROM bundle_tasks WHERE status NOT IN (?,?,?)", array(
            SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS,
            SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
            SERVER_SNAPSHOT_CREATION_STATUS::CANCELLED
        ));

        while ($rec = $rs->FetchRow()) {
            $obj = new stdClass();
            $obj->bundleTaskId = $rec['id'];

            $queue->append($obj);
        }

        if ($cnt = count($queue)) {
            $this->getLogger()->info("%d bundle tasks found", $cnt);
        } else {
            $this->getLogger()->info("No bundle tasks.");
        }

        return $queue;
    }

    /**
     * Add log record to bundle task
     *
     * @param   string     $message
     */
    public function bundleTaskLog($message)
    {
        $this->bundleTask->Log($message);
        $this->getLogger()->info("Task: %s id:%d, server: %s says: %s", $this->bundleTask->roleName, $this->bundleTask->id, $this->bundleTask->serverId, $message);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        $db = \Scalr::getDb();

        //Warming up static DI cache
        \Scalr::getContainer()->warmup();

        // Reconfigure observers
        \Scalr::ReconfigureObservers();

        $bundleTask = BundleTask::LoadById($request->bundleTaskId);

        if (!($bundleTask instanceof BundleTask)) {
            $this->getLogger()->fatal("Could not load bundle task id: %s", $request->bundleTaskId);
            return false;
        } else {
            $this->bundleTask = $bundleTask;
            $this->getLogger()->info("Processing bundle task id: %d status: %s serverid: %s", $bundleTask->id, $bundleTask->status, $bundleTask->serverId);
        }

        try {
            $dbServer = DBServer::LoadByID($bundleTask->serverId);
        } catch (\Scalr\Exception\ServerNotFoundException $e) {
            if (!$bundleTask->snapshotId) {
                $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::FAILED;
                $bundleTask->setDate('finished');
                $bundleTask->failureReason = sprintf(_("Server '%s' was terminated during snapshot creation process"), $bundleTask->serverId);
                $bundleTask->Save();
                return;
            }
            $this->getLogger()->warn("Could not load server: %s. %s says: %s", $bundleTask->serverId, get_class($e), $e->getMessage());
        } catch (Exception $e) {
            $this->getLogger()->warn("Could not load server: %s. %s says: %s", $bundleTask->serverId, get_class($e), $e->getMessage());
        }

        switch ($bundleTask->status) {
            case SERVER_SNAPSHOT_CREATION_STATUS::ESTABLISHING_COMMUNICATION:
                $conn = @fsockopen(
                    $dbServer->getSzrHost(),
                    $dbServer->getPort(DBServer::PORT_CTRL),
                    $errno,
                    $errstr,
                    10
                );

                if ($conn) {
                    $dbServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OUT_CONNECTION, 1);
                    $this->bundleTaskLog("Outbound connection successfully established. Awaiting user action: prebuild automation selection");
                    $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::AWAITING_USER_ACTION;
                    $this->bundleTaskLog(sprintf(_("Bundle task status: %s"), $bundleTask->status));
                    $bundleTask->Save();
                } else {
                    $errstr = sprintf("Unable to establish outbound (Scalr -> Scalarizr) communication (%s:%s): %s.", $dbServer->getSzrHost(), $dbServer->getPort(DBServer::PORT_CTRL), $errstr);
                    $errMsg = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OUT_CONNECTION_ERROR);
                    if (!$errMsg || $errstr != $errMsg) {
                        $dbServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OUT_CONNECTION_ERROR, $errstr);
                        $this->bundleTaskLog("{$errstr} Will try again in a few minutes.");
                    }
                }
                return false;

            case SERVER_SNAPSHOT_CREATION_STATUS::AWAITING_USER_ACTION:
                //nothing to do;
                return false;

            case SERVER_SNAPSHOT_CREATION_STATUS::STARING_SERVER:
                $bundleTask->setDate('started');

            case SERVER_SNAPSHOT_CREATION_STATUS::PREPARING_ENV:
            case SERVER_SNAPSHOT_CREATION_STATUS::INTALLING_SOFTWARE:
                if (!PlatformFactory::NewPlatform($dbServer->platform)->GetServerID($dbServer)) {
                    $this->bundleTaskLog(sprintf(_("Waiting for temporary server")));
                    return false;
                }

                $status = PlatformFactory::NewPlatform($dbServer->platform)->GetServerRealStatus($dbServer);
                if ($status->isPending()) {
                    //Server is in pensing state
                    $this->bundleTaskLog(sprintf(_("Server status: %s"), $status->getName()));
                    $this->bundleTaskLog(sprintf(_("Waiting for running state."), $status->getName()));
                    return false;
                } elseif ($status->isTerminated()) {
                    $this->bundleTaskLog(sprintf(_("Server status: %s"), $status->getName()));
                    $dbServer->status = SERVER_STATUS::TERMINATED;
                    $dbServer->save();

                    $bundleTask->SnapshotCreationFailed("Server was terminated and no longer available in cloud.");
                    return false;
                }

                break;
        }

        $this->getLogger()->info("Continue bundle task id:%d status:%s", $bundleTask->id, $bundleTask->status);

        switch ($bundleTask->status) {
            case SERVER_SNAPSHOT_CREATION_STATUS::STARING_SERVER:
                $ips = PlatformFactory::NewPlatform($dbServer->platform)->GetServerIPAddresses($dbServer);
                $dbServer->remoteIp = $ips['remoteIp'];
                $dbServer->localIp = $ips['localIp'];
                $dbServer->save();

                $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::PREPARING_ENV;
                $bundleTask->save();

                $this->bundleTaskLog(sprintf(_("Bundle task status: %s"), $bundleTask->status));
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::PREPARING_ENV:
                $this->bundleTaskLog(sprintf(_("Initializing SSH2 session to the server")));
                if ($dbServer->platform == SERVER_PLATFORMS::IDCF && !$dbServer->remoteIp) {
                    try {
                        $this->bundleTaskLog("Creating port forwarding rules to be able to connect to the server by SSH");

                        $environment = $dbServer->GetEnvironmentObject();
                        $cloudLocation = $dbServer->GetCloudLocation();

                        $platform = PlatformFactory::NewPlatform($dbServer->platform);

                        $sharedIpId = $platform->getConfigVariable(CloudstackPlatformModule::SHARED_IP_ID.".{$cloudLocation}", $environment, false);
                        $sharedIp = $platform->getConfigVariable(CloudstackPlatformModule::SHARED_IP.".{$cloudLocation}", $environment, false);

                        $this->bundleTaskLog("Shared IP: {$sharedIp}");

                        $cs = $environment->cloudstack($dbServer->platform);

                        // Create port forwarding rules for scalarizr
                        $port = $platform->getConfigVariable(CloudstackPlatformModule::SZR_PORT_COUNTER.".{$cloudLocation}.{$sharedIpId}", $environment, false);

                        if (!$port) {
                            $port1 = 30000;
                            $port2 = 30001;
                            $port3 = 30002;
                            $port4 = 30003;
                        } else {
                            $port1 = $port+1;
                            $port2 = $port1+1;
                            $port3 = $port2+1;
                            $port4 = $port3+1;
                        }

                        $virtualmachineid = $dbServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID);

                        $result2 = $cs->firewall->createPortForwardingRule(array(
                            'ipaddressid'      => $sharedIpId,
                            'privateport'      => 8014,
                            'protocol'         => "udp",
                            'publicport'       => $port1,
                            'virtualmachineid' => $virtualmachineid
                        ));

                        $result1 = $cs->firewall->createPortForwardingRule(array(
                            'ipaddressid'      => $sharedIpId,
                            'privateport'      => 8013,
                            'protocol'         => "tcp",
                            'publicport'       => $port1,
                            'virtualmachineid' => $virtualmachineid
                        ));

                        $result3 = $cs->firewall->createPortForwardingRule(array(
                            'ipaddressid'      => $sharedIpId,
                            'privateport'      => 8010,
                            'protocol'         => "tcp",
                            'publicport'       => $port3,
                            'virtualmachineid' => $virtualmachineid
                        ));

                        $result4 = $cs->firewall->createPortForwardingRule(array(
                            'ipaddressid'      => $sharedIpId,
                            'privateport'      => 8008,
                            'protocol'         => "tcp",
                            'publicport'       => $port2,
                            'virtualmachineid' => $virtualmachineid
                        ));

                        $result5 = $cs->firewall->createPortForwardingRule(array(
                            'ipaddressid'      => $sharedIpId,
                            'privateport'      => 22,
                            'protocol'         => "tcp",
                            'publicport'       => $port4,
                            'virtualmachineid' => $virtualmachineid
                        ));

                        $dbServer->SetProperties(array(
                            SERVER_PROPERTIES::SZR_CTRL_PORT   => $port1,
                            SERVER_PROPERTIES::SZR_SNMP_PORT   => $port1,

                            SERVER_PROPERTIES::SZR_API_PORT    => $port3,
                            SERVER_PROPERTIES::SZR_UPDC_PORT   => $port2,
                            SERVER_PROPERTIES::CUSTOM_SSH_PORT => $port4
                        ));

                        $dbServer->remoteIp = $sharedIp;
                        $dbServer->Save();

                        $platform->setConfigVariable(array(CloudstackPlatformModule::SZR_PORT_COUNTER.".{$cloudLocation}.{$sharedIpId}" => $port4), $environment, false);
                    } catch (Exception $e) {
                        $this->bundleTaskLog("Unable to create port-forwarding rules: {$e->getMessage()}");
                    }

                    return false;
                }

                if ($dbServer->platform == SERVER_PLATFORMS::ECS && !$dbServer->remoteIp) {
                    $this->bundleTaskLog(sprintf(_("Server doesn't have public IP. Assigning...")));
                    $osClient = $dbServer->GetEnvironmentObject()->openstack(
                        $dbServer->platform, $dbServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION)
                    );

                    $ports = $osClient->network->ports->list();
                    foreach ($ports as $port) {
                        if ($port->device_id == $dbServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID)) {
                            $serverNetworkPort = $port->id;
                            break;
                        }
                    }

                    $ips = $osClient->network->floatingIps->list();
                    //Check free existing IP
                    $ipAssigned = false;
                    $ipAddress = false;
                    $ipId = false;
                    $ipInfo = false;
                    foreach ($ips as $ip) {
                        if ($ip->port_id && $ip->port_id == $serverNetworkPort) {
                            $ipAddress = $ip->floating_ip_address;
                            $ipId = $ip->id;
                            $ipAssigned = true;
                            $ipInfo = $ip;
                            break;
                        }

                        if (!$ip->fixed_ip_address && !$ipAddress) {
                            $ipAddress = $ip->floating_ip_address;
                            $ipId = $ip->id;
                            $ipInfo = $ip;
                        }
                    }

                    if (!$ipAssigned) {
                        if (!$serverNetworkPort) {
                            $this->bundleTaskLog("Unable to identify network port of instance");
                            return false;
                        } else {
                            if (!$ipAddress) {
                                $networks = $osClient->network->listNetworks();
                                foreach ($networks as $network) {
                                    if ($network->{"router:external"} == true) {
                                        $publicNetworkId = $network->id;
                                    }
                                }

                                if (!$publicNetworkId) {
                                    $this->bundleTaskLog("Unable to identify public network to allocate");
                                    return false;
                                } else {
                                    $ip = $osClient->network->floatingIps->create($publicNetworkId, $serverNetworkPort);
                                    $ipAddress = $ip->floating_ip_address;

                                    $dbServer->SetProperties(array(
                                        OPENSTACK_SERVER_PROPERTIES::FLOATING_IP => $ip->floating_ip_address,
                                        OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => $ip->id,
                                    ));

                                    $this->bundleTaskLog("Allocated new IP {$ipAddress} for port: {$serverNetworkPort}");
                                }
                            } else {
                                $this->bundleTaskLog("Found free floating IP: {$ipAddress} for use (". json_encode($ipInfo) .")");
                                $osClient->network->floatingIps->update($ipId, $serverNetworkPort);
                            }
                        }
                    } else {
                        $this->bundleTaskLog("IP: {$ipAddress} already assigned");
                    }

                    if ($ipAddress) {
                        $dbServer->remoteIp = $ipAddress;
                        $dbServer->Save();
                    }

                    return false;
                }

                try {
                    $ssh2Client = $dbServer->GetSsh2Client();
                    $ssh2Client->connect($dbServer->remoteIp, $dbServer->getPort(DBServer::PORT_SSH));
                } catch (Exception $e) {
                    $this->bundleTaskLog(sprintf(_("Scalr unable to establish SSH connection with server on %:%. Error: %s"), $dbServer->remoteIp, $dbServer->getPort(DBServer::PORT_SSH), $e->getMessage()));
                    //TODO: Set status of bundle log to failed
                    return false;
                }

                $this->bundleTaskLog(sprintf(_("Created SSH session. Username: %s"), $ssh2Client->getLogin()));

                //Prepare script
                $this->bundleTaskLog(sprintf(_("Uploading builder scripts...")));
                $behaviors = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR);
                try {
                    if ($dbServer->isOpenstack()) {
                        $platform = SERVER_PLATFORMS::OPENSTACK;
                    } else {
                        $platform = $dbServer->platform;
                    }

                    $baseUrl = rtrim(\Scalr::config('scalr.endpoint.scheme') . "://" . \Scalr::config('scalr.endpoint.host'), '/');

                    $options = array(
                        'server-id'                  => $dbServer->serverId,
                        'role-name'                  => $bundleTask->roleName,
                        'crypto-key'                 => $dbServer->GetProperty(SERVER_PROPERTIES::SZR_KEY),
                        'platform'                   => $platform,
                        'queryenv-url'               => $baseUrl . "/query-env",
                        'messaging-p2p.producer-url' => $baseUrl . "/messaging",
                        'behaviour'                  => trim(trim(str_replace("base", "", $behaviors), ",")),
                        'env-id'                     => $dbServer->envId,
                        'region'                     => $dbServer->GetCloudLocation(),
                        'scalr-id'                   => SCALR_ID
                    );

                    $command = 'scalarizr --import -y';
                    foreach ($options as $k => $v) {
                        $command .= sprintf(' -o %s=%s', $k, $v);
                    }

                    if ($dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_MYSQL_SERVER_TYPE) == 'percona') {
                        $recipes = 'mysql=percona';
                    } else {
                        $recipes = '';
                    }

                    $scalarizrBranch = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_DEV_SCALARIZR_BRANCH);
                    $scriptContents = @file_get_contents(APPPATH . "/templates/services/role_builder/chef_import.tpl");

                    /*
                     %CHEF_SERVER_URL%
                     %CHEF_VALIDATOR_NAME%
                     %CHEF_VALIDATOR_KEY%
                     %CHEF_ENVIRONMENT%
                     %CHEF_ROLE_NAME%
                     */
                    $chefServerId = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_CHEF_SERVER_ID);
                    if ($chefServerId) {
                        $chefServerInfo = $db->GetRow("SELECT * FROM services_chef_servers WHERE id=?", array($chefServerId));
                        $chefServerInfo['v_auth_key'] = \Scalr::getContainer()->crypto->decrypt($chefServerInfo['v_auth_key']);
                    }

                    $scriptContents = str_replace(
                        array(
                            "%PLATFORM%",
                            "%BEHAVIOURS%",
                            "%SZR_IMPORT_STRING%",
                            "%DEV%",
                            "%SCALARIZR_BRANCH%",
                            "%RECIPES%",
                            "%BUILD_ONLY%",
                            "%CHEF_SERVER_URL%",
                            "%CHEF_VALIDATOR_NAME%",
                            "%CHEF_VALIDATOR_KEY%",
                            "%CHEF_ENVIRONMENT%",
                            "%CHEF_ROLE%",
                            "%CHEF_ROLE_NAME%",
                            "%CHEF_NODE_NAME%",
                            "\r\n"
                        ),
                        array(
                            $platform,
                            trim(str_replace("base", "", str_replace(",", " ", $behaviors))),
                            $command,
                            ($scalarizrBranch ? '1' : '0'),
                            $scalarizrBranch,
                            $recipes,
                            '0',
                            $chefServerInfo['url'],
                            $chefServerInfo['v_username'],
                            $chefServerInfo['v_auth_key'],
                            $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_CHEF_ENVIRONMENT),
                            $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_CHEF_ROLE_NAME),
                            $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_CHEF_ROLE_NAME),
                            '',
                            "\n"
                        ),
                        $scriptContents
                    );

                    if (!$ssh2Client->sendFile('/tmp/scalr-builder.sh', $scriptContents, "w+", false)) {
                        throw new Exception("Cannot upload script");
                    }

                    /*
                    $this->bundleTaskLog(sprintf(_("Uploading chef recipes...")));
                    if (!$ssh2Client->sendFile('/tmp/recipes.tar.gz', APPPATH . '/www/storage/chef/recipes.tar.gz')) {
                        throw new Exception("Cannot upload chef recipes");
                    }
                    */
                } catch (Exception $e) {
                    $this->bundleTaskLog(sprintf(_("Scripts upload failed: %s"), $e->getMessage()));
                    //TODO: Set status of bundle log to failed
                    return false;
                }

                $this->bundleTaskLog("Launching role builder routines on server");

                $ssh2Client->exec("chmod 0777 /tmp/scalr-builder.sh");

                // For CGE we need to use sudo
                if ($bundleTask->platform == SERVER_PLATFORMS::GCE || $bundleTask->osFamily == 'amazon') {
                    $shell = $ssh2Client->getShell();

                    @stream_set_blocking($shell, true);
                    @stream_set_timeout($shell, 5);

                    @fwrite($shell, "sudo touch /var/log/role-builder-output.log 2>&1" . PHP_EOL);
                    $output = @fgets($shell, 4096);
                    $this->bundleTaskLog("Verbose 1: {$output}");

                    @fwrite($shell, "sudo chmod 0666 /var/log/role-builder-output.log 2>&1" . PHP_EOL);
                    $output2 = @fgets($shell, 4096);
                    $this->bundleTaskLog("Verbose 2: {$output2}");


                    @fwrite($shell, "sudo setsid /tmp/scalr-builder.sh > /var/log/role-builder-output.log 2>&1 &" . PHP_EOL);
                    $output3 = @fgets($shell, 4096);
                    $this->bundleTaskLog("Verbose 3: {$output3}");

                    sleep(5);

                    $meta = stream_get_meta_data($shell);
                    $this->bundleTaskLog(sprintf("Verbose (Meta): %s", json_encode($meta)));
                    $i = 4;
                    if ($meta['eof'] == false && $meta['unread_bytes'] != 0) {
                        $output4 = @fread($shell, $meta['unread_bytes']);
                        $this->bundleTaskLog("Verbose {$i}: {$output4}");

                        $meta = stream_get_meta_data($shell);
                        $this->bundleTaskLog(sprintf("Verbose (Meta): %s", json_encode($meta)));
                    }

                    @fclose($shell);

                    /*
                    $r1 = $ssh2Client->exec("sudo touch /var/log/role-builder-output.log");
                    $this->bundleTaskLog("1: {$r1} ({$ssh2Client->stdErr})");
                    $r2 = $ssh2Client->exec("sudo chmod 0666 /var/log/role-builder-output.log");
                    $this->bundleTaskLog("2: {$r2} ({$ssh2Client->stdErr})");
                    $r3 = $ssh2Client->exec("sudo setsid /tmp/scalr-builder.sh > /var/log/role-builder-output.log 2>&1 &");
                    $this->bundleTaskLog("3: {$r3} ({$ssh2Client->stdErr})");
                    */
                } else {
                    $ssh2Client->exec("setsid /tmp/scalr-builder.sh > /var/log/role-builder-output.log 2>&1 &");
                }

                $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::INTALLING_SOFTWARE;
                $bundleTask->save();
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::INTALLING_SOFTWARE:
                try {
                    $ssh2Client = $dbServer->GetSsh2Client();
                    $ssh2Client->connect($dbServer->remoteIp, $dbServer->getPort(DBServer::PORT_SSH));
                } catch (Exception $e) {
                    $this->bundleTaskLog(sprintf(_("Scalr unable to establish SSH connection with server on %:%. Error: %s"), $dbServer->remoteIp, $dbServer->getPort(DBServer::PORT_SSH), $e->getMessage()));
                    //TODO: Set status of bundle log to failed
                    return false;
                }

                $log = $ssh2Client->getFile('/var/log/role-builder-output.log');
                $log_lines = explode("\r\n", $log);
                $last_msg = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_LAST_LOG_MESSAGE);
                while ($msg = trim(array_shift($log_lines))) {
                    if (substr($msg, -1, 1) != ']') {
                        continue;
                    }
                    if ($last_msg) {
                        if ($msg != $last_msg) {
                            continue;
                        } elseif ($msg == $last_msg) {
                            $last_msg = null;
                            continue;
                        }
                    }
                    if (stristr($msg, '[ Failed ]')) {
                        $stepLog = $ssh2Client->getFile('/var/log/role-builder-step.log');
                        $this->bundleTaskLog(sprintf("role-builder-step.log: %s", $stepLog));
                        $bundleTask->SnapshotCreationFailed($msg);
                    } else {
                        $this->bundleTaskLog($msg);
                        $dbServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_LAST_LOG_MESSAGE, $msg);
                    }
                }
                //Read /var/log/role-builder-output.log
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::PENDING:
                try {
                    $platformModule = PlatformFactory::NewPlatform($bundleTask->platform);
                    $platformModule->CreateServerSnapshot($bundleTask);
                } catch (Exception $e) {
                    $this->getLogger()->error($e->getMessage());
                    $bundleTask->SnapshotCreationFailed($e->getMessage());
                }
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::PREPARING:
                $addedTime = strtotime($bundleTask->dateAdded);
                if ($addedTime + 3600 < time()) {
                    $bundleTask->SnapshotCreationFailed("Server didn't send PrepareBundleResult message in time.");
                }
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS:
                PlatformFactory::NewPlatform($bundleTask->platform)->CheckServerSnapshotStatus($bundleTask);
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::CREATING_ROLE:
                try {
                    if ($bundleTask->object == BundleTask::BUNDLETASK_OBJECT_IMAGE) {
                        if ($bundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
                            $dbRole = $dbServer->GetFarmRoleObject()->GetRoleObject();
                            $dbRole->__getNewRoleObject()->setImage(
                                $bundleTask->platform,
                                $bundleTask->cloudLocation,
                                $bundleTask->snapshotId,
                                $bundleTask->createdById,
                                $bundleTask->createdByEmail
                            );

                            $this->bundleTaskLog(sprintf(_("Image replacement completed.")));
                        }

                        $this->bundleTaskLog(sprintf(_("Bundle task completed.")));

                        $bundleTask->setDate('finished');
                        $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;
                        $bundleTask->Save();
                    } elseif ($bundleTask->object == BundleTask::BUNDLETASK_OBJECT_ROLE) {
                        if ($bundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
                            $saveOldRole = false;
                            try {
                                $dbRole = $dbServer->GetFarmRoleObject()->GetRoleObject();
                                if ($dbRole->name == $bundleTask->roleName && $dbRole->envId == $bundleTask->envId) {
                                    $saveOldRole = true;
                                }
                            } catch (Exception $e) {
                                //NO OLD ROLE
                            }
                            if ($dbRole && $saveOldRole) {
                                if ($dbServer) {
                                    $new_role_name = BundleTask::GenerateRoleName($dbServer->GetFarmRoleObject(), $dbServer);
                                } else {
                                    $new_role_name = $bundleTask->roleName . "-" . rand(1000, 9999);
                                }
                                $dbRole->name = $new_role_name;
                                $this->bundleTaskLog(sprintf(_("Old role '%s' (ID: %s) renamed to '%s'"), $bundleTask->roleName, $dbRole->id, $new_role_name));
                                $dbRole->save();
                            }
                        }

                        try {
                            $DBRole = DBRole::createFromBundleTask($bundleTask);
                        } catch (Exception $e) {
                            $bundleTask->SnapshotCreationFailed("Role creation failed due to internal error ({$e->getMessage()}). Please try again.");
                            return;
                        }

                        if ($bundleTask->replaceType == SERVER_REPLACEMENT_TYPE::NO_REPLACE) {
                            $bundleTask->setDate('finished');
                            $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;

                            $this->bundleTaskLog(sprintf(_(
                                "Replacement type: %s. Bundle task status: %s"),
                                SERVER_REPLACEMENT_TYPE::NO_REPLACE, SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS
                            ));
                        } else {
                            try {
                                $this->bundleTaskLog(sprintf(_("Replacement type: %s"), $bundleTask->replaceType));

                                $r_farm_roles = array();

                                try {
                                    $DBFarm = DBFarm::LoadByID($bundleTask->farmId);
                                } catch (Exception $e) {
                                    if (stristr($e->getMessage(), "not found in database")) {
                                        $bundleTask->SnapshotCreationFailed("Farm was removed before task was finished");
                                    }
                                    return;
                                }

                                if ($bundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_FARM) {
                                    try {
                                        $r_farm_roles[] = $DBFarm->GetFarmRoleByRoleID($bundleTask->prototypeRoleId);
                                    } catch (Exception $e) {}
                                } elseif ($bundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
                                    $farm_roles = $db->GetAll("
                                        SELECT id FROM farm_roles
                                        WHERE role_id=?
                                        AND farmid IN (SELECT id FROM farms WHERE env_id=?)
                                    ", array(
                                        $bundleTask->prototypeRoleId,
                                        $bundleTask->envId
                                    ));

                                    foreach ($farm_roles as $farm_role) {
                                        try {
                                            $r_farm_roles[] = DBFarmRole::LoadByID($farm_role['id']);
                                        } catch (Exception $e) {}
                                    }
                                }

                                foreach ($r_farm_roles as $DBFarmRole) {
                                    if ($DBFarmRole->CloudLocation != $bundleTask->cloudLocation) {
                                        $this->bundleTaskLog(sprintf(
                                            "Role '%s' (ID: %s), farm '%s' (ID: %s) using the same role "
                                            . "but in abother cloud location. Skiping it.",
                                            $DBFarmRole->GetRoleObject()->name,
                                            $DBFarmRole->ID,
                                            $DBFarmRole->GetFarmObject()->Name,
                                            $DBFarmRole->FarmID
                                        ));
                                    } else {
                                        $DBFarmRole->RoleID = $bundleTask->roleId;
                                        $DBFarmRole->Save();
                                    }
                                }

                                $this->bundleTaskLog(sprintf(_("Replacement completed. Bundle task completed.")));

                                try {
                                    if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                                        $dbServer->Remove();
                                    } elseif ($dbServer->status == SERVER_STATUS::TEMPORARY) {
                                        $this->bundleTaskLog("Terminating temporary server");
                                        $dbServer->terminate(DBServer::TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER);
                                        $this->bundleTaskLog("Termination request has been sent");
                                    }
                                } catch (Exception $e) {
                                    $this->bundleTaskLog("Warning: {$e->getMessage()}");
                                }

                                $bundleTask->setDate('finished');
                                $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;
                                $bundleTask->Save();
                            } catch (Exception $e) {
                                $this->getLogger()->error($e->getMessage());
                                $this->bundleTaskLog(sprintf(_("Server replacement failed: %s"), $e->getMessage()));
                                $bundleTask->setDate('finished');
                                $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;
                            }
                        }
                    }

                    if ($bundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS) {
                        try {
                            if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                                $dbServer->Remove();
                            } elseif ($dbServer->status == SERVER_STATUS::TEMPORARY) {
                                $this->bundleTaskLog("Terminating temporary server");
                                $dbServer->terminate(DBServer::TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER);
                                $this->bundleTaskLog("Termination request has been sent");
                            }
                        } catch (Exception $e) {
                            $this->bundleTaskLog("Warning: {$e->getMessage()}");
                        }
                    }

                    $bundleTask->Save();
                } catch (Exception $e) {
                    $this->getLogger()->error($e->getMessage());
                }
                break;
        }

        return $request;
    }
}