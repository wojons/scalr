<?php

use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;

/**
 * @deprecated It has been deprecated since 08.12.2014 because of implementing a new Scalr service
 * @see        \Scalr\System\Zmq\Cron\Task\ImagesBuilder
 */
class BundleTasksManagerProcess implements \Scalr\System\Pcntl\ProcessInterface
{

    public $ThreadArgs;

    public $ProcessDescription = "Bundle tasks manager";

    public $Logger;

    public $IsDaemon;

    public function __construct()
    {
        // Get Logger instance
        $this->Logger = Logger::getLogger(__CLASS__);
    }

    public function OnStartForking()
    {
        $db = \Scalr::getDb();
        $this->ThreadArgs = $db->GetAll("SELECT id FROM bundle_tasks WHERE status NOT IN (?,?,?)", array(
            SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS,
            SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
            SERVER_SNAPSHOT_CREATION_STATUS::CANCELLED
        ));
        $this->Logger->info("Found " . count($this->ThreadArgs) . " bundle tasks.");
        $this->crypto = new Scalr_Util_CryptoTool(MCRYPT_TRIPLEDES, MCRYPT_MODE_CFB, 24, 8);
        $this->cryptoKey = @file_get_contents(APPPATH . "/etc/.cryptokey");
    }

    public function OnEndForking()
    {
    }

    public function StartThread($bundle_task_info)
    {
        $db = \Scalr::getDb();

        // Reconfigure observers;
        Scalr::ReconfigureObservers();

        $BundleTask = BundleTask::LoadById($bundle_task_info['id']);

        try {
            $DBServer = DBServer::LoadByID($BundleTask->serverId);
        } catch (\Scalr\Exception\ServerNotFoundException $e) {
            if (!$BundleTask->snapshotId) {
                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::FAILED;
                $BundleTask->setDate('finished');
                $BundleTask->failureReason = sprintf(_("Server '%s' was terminated during snapshot creation process"), $BundleTask->serverId);
                $BundleTask->Save();
                return;
            }
        } catch (Exception $e) {
            //$this->Logger->error($e->getMessage());
        }

        switch ($BundleTask->status) {
            case SERVER_SNAPSHOT_CREATION_STATUS::ESTABLISHING_COMMUNICATION:
                $conn = @fsockopen(
                    $DBServer->getSzrHost(),
                    $DBServer->getPort(DBServer::PORT_CTRL),
                    $errno,
                    $errstr,
                    10
                );

                if ($conn) {
                    $DBServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OUT_CONNECTION, 1);
                    $BundleTask->Log("Outbound connection successfully established. Awaiting user action: prebuild automation selection");
                    $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::AWAITING_USER_ACTION;
                    $BundleTask->Log(sprintf(_("Bundle task status: %s"), $BundleTask->status));
                    $BundleTask->Save();
                } else {
                    $errstr = sprintf("Unable to establish outbound (Scalr -> Scalarizr) communication (%s:%s): %s.", $DBServer->getSzrHost(), $DBServer->getPort(DBServer::PORT_CTRL), $errstr);
                    $errMsg = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OUT_CONNECTION_ERROR);
                    if (!$errMsg || $errstr != $errMsg) {
                        $DBServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OUT_CONNECTION_ERROR, $errstr);
                        $BundleTask->Log("{$errstr} Will try again in a few minutes.");
                    }
                }

                exit();
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::AWAITING_USER_ACTION:
                //NOTHING TO DO;
                exit();
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::STARING_SERVER:
                $BundleTask->setDate('started');

            case SERVER_SNAPSHOT_CREATION_STATUS::PREPARING_ENV:
            case SERVER_SNAPSHOT_CREATION_STATUS::INTALLING_SOFTWARE:
                if (!PlatformFactory::NewPlatform($DBServer->platform)->GetServerID($DBServer)) {
                    $BundleTask->Log(sprintf(_("Waiting for temporary server")));
                    exit();
                }

                if (!PlatformFactory::NewPlatform($DBServer->platform)->IsServerExists($DBServer)) {
                    $DBServer->status = SERVER_STATUS::TERMINATED;
                    $DBServer->save();
                    $BundleTask->SnapshotCreationFailed("Server was terminated and no longer available in cloud.");
                    exit();
                }

                // IF server is in pensing state
                $status = PlatformFactory::NewPlatform($DBServer->platform)->GetServerRealStatus($DBServer);
                if ($status->isPending()) {
                    $BundleTask->Log(sprintf(_("Server status: %s"), $status->getName()));
                    $BundleTask->Log(sprintf(_("Waiting for running state."), $status->getName()));
                    exit();
                } elseif ($status->isTerminated()) {
                    $BundleTask->Log(sprintf(_("Server status: %s"), $status->getName()));
                    $DBServer->status = SERVER_STATUS::TERMINATED;
                    $DBServer->save();
                    $BundleTask->SnapshotCreationFailed("Server was terminated and no longer available in cloud.");
                    exit();
                }
                break;
        }

        switch ($BundleTask->status) {
            case SERVER_SNAPSHOT_CREATION_STATUS::STARING_SERVER:
                $ips = PlatformFactory::NewPlatform($DBServer->platform)->GetServerIPAddresses($DBServer);
                $DBServer->remoteIp = $ips['remoteIp'];
                $DBServer->localIp = $ips['localIp'];
                $DBServer->save();
                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::PREPARING_ENV;
                $BundleTask->save();
                $BundleTask->Log(sprintf(_("Bundle task status: %s"), $BundleTask->status));
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::PREPARING_ENV:
                $BundleTask->Log(sprintf(_("Initializing SSH2 session to the server")));
                if ($DBServer->platform == SERVER_PLATFORMS::IDCF && !$DBServer->remoteIp) {
                    try {
                        $BundleTask->Log("Creating port forwarding rules to be able to connect to the server by SSH");

                        $environment = $DBServer->GetEnvironmentObject();
                        $cloudLocation = $DBServer->GetCloudLocation();
                        $platform = PlatformFactory::NewPlatform($DBServer->platform);
                        $sharedIpId = $platform->getConfigVariable(CloudstackPlatformModule::SHARED_IP_ID.".{$cloudLocation}", $environment, false);
                        $sharedIp = $platform->getConfigVariable(CloudstackPlatformModule::SHARED_IP.".{$cloudLocation}", $environment, false);

                        $BundleTask->Log("Shared IP: {$sharedIp}");

                        $cs = $environment->cloudstack($DBServer->platform);

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

                        $virtualmachineid = $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID);

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

                        $DBServer->SetProperties(array(
                            SERVER_PROPERTIES::SZR_CTRL_PORT   => $port1,
                            SERVER_PROPERTIES::SZR_SNMP_PORT   => $port1,

                            SERVER_PROPERTIES::SZR_API_PORT    => $port3,
                            SERVER_PROPERTIES::SZR_UPDC_PORT   => $port2,
                            SERVER_PROPERTIES::CUSTOM_SSH_PORT => $port4
                        ));

                        $DBServer->remoteIp = $sharedIp;
                        $DBServer->Save();

                        $platform->setConfigVariable(array(CloudstackPlatformModule::SZR_PORT_COUNTER.".{$cloudLocation}.{$sharedIpId}" => $port4), $environment, false);
                    } catch (Exception $e) {
                        $BundleTask->Log("Unable to create port-forwarding rules: {$e->getMessage()}");
                    }

                    exit();
                }

                if ($DBServer->platform == SERVER_PLATFORMS::ECS && !$DBServer->remoteIp) {
                    $BundleTask->Log(sprintf(_("Server doesn't have public IP. Assigning...")));
                    $osClient = $DBServer->GetEnvironmentObject()->openstack(
                        $DBServer->platform, $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION)
                    );

                    $ports = $osClient->network->ports->list();
                    foreach ($ports as $port) {
                        if ($port->device_id == $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID)) {
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
                            $BundleTask->Log("Unable to identify network port of instance");
                            exit();
                        } else {
                            if (!$ipAddress) {
                                $networks = $osClient->network->listNetworks();
                                foreach ($networks as $network) {
                                    if ($network->{"router:external"} == true) {
                                        $publicNetworkId = $network->id;
                                    }
                                }

                                if (!$publicNetworkId) {
                                    $BundleTask->Log("Unable to identify public network to allocate");
                                    exit();
                                } else {
                                    $ip = $osClient->network->floatingIps->create($publicNetworkId, $serverNetworkPort);
                                    $ipAddress = $ip->floating_ip_address;

                                    $DBServer->SetProperties(array(
                                        OPENSTACK_SERVER_PROPERTIES::FLOATING_IP => $ip->floating_ip_address,
                                        OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => $ip->id,
                                    ));

                                    $BundleTask->Log("Allocated new IP {$ipAddress} for port: {$serverNetworkPort}");
                                }
                            } else {
                                $BundleTask->Log("Found free floating IP: {$ipAddress} for use (". json_encode($ipInfo) .")");
                                $osClient->network->floatingIps->update($ipId, $serverNetworkPort);
                            }
                        }
                    } else {
                        $BundleTask->Log("IP: {$ipAddress} already assigned");
                    }

                    if ($ipAddress) {
                        $DBServer->remoteIp = $ipAddress;
                        $DBServer->Save();
                    }

                    exit();
                }

                try {
                    $ssh2Client = $DBServer->GetSsh2Client();
                    $ssh2Client->connect($DBServer->remoteIp, $DBServer->getPort(DBServer::PORT_SSH));
                } catch (Exception $e) {
                    $BundleTask->Log(sprintf(_("Scalr unable to establish SSH connection with server on %:%. Error: %s"), $DBServer->remoteIp, $DBServer->getPort(DBServer::PORT_SSH), $e->getMessage()));
                    //TODO: Set status of bundle log to failed
                    exit();
                }

                $BundleTask->Log(sprintf(_("Created SSH session. Username: %s"), $ssh2Client->getLogin()));

                //Prepare script
                $BundleTask->Log(sprintf(_("Uploading builder scripts...")));
                $behaviors = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR);
                try {
                    if ($DBServer->isOpenstack()) {
                        $platform = SERVER_PLATFORMS::OPENSTACK;
                    } else {
                        $platform = $DBServer->platform;
                    }
                    $baseUrl = rtrim(\Scalr::config('scalr.endpoint.scheme') . "://" . \Scalr::config('scalr.endpoint.host'), '/');
                    $options = array(
                        'server-id'                  => $DBServer->serverId,
                        'role-name'                  => $BundleTask->roleName,
                        'crypto-key'                 => $DBServer->GetProperty(SERVER_PROPERTIES::SZR_KEY),
                        'platform'                   => $platform,
                        'queryenv-url'               => $baseUrl . "/query-env",
                        'messaging-p2p.producer-url' => $baseUrl . "/messaging",
                        'behaviour'                  => trim(trim(str_replace("base", "", $behaviors), ",")),
                        'env-id'                     => $DBServer->envId,
                        'region'                     => $DBServer->GetCloudLocation(),
                        'scalr-id'                   => SCALR_ID
                    );

                    $command = 'scalarizr --import -y';
                    foreach ($options as $k => $v) {
                        $command .= sprintf(' -o %s=%s', $k, $v);
                    }

                    if ($DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_MYSQL_SERVER_TYPE) == 'percona') {
                        $recipes = 'mysql=percona';
                    } else {
                        $recipes = '';
                    }

                    $scalarizrBranch = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_DEV_SCALARIZR_BRANCH);
                    $scriptContents = @file_get_contents(APPPATH . "/templates/services/role_builder/chef_import.tpl");

                    /*
                     %CHEF_SERVER_URL%
                     %CHEF_VALIDATOR_NAME%
                     %CHEF_VALIDATOR_KEY%
                     %CHEF_ENVIRONMENT%
                     %CHEF_ROLE_NAME%
                     */
                    $chefServerId = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_CHEF_SERVER_ID);
                    if ($chefServerId) {
                        $chefServerInfo = $db->GetRow("SELECT * FROM services_chef_servers WHERE id=?", array($chefServerId));
                        $chefServerInfo['v_auth_key'] = $this->crypto->decrypt($chefServerInfo['v_auth_key'], $this->cryptoKey);
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
                            $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_CHEF_ENVIRONMENT),
                            $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_CHEF_ROLE_NAME),
                            $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_CHEF_ROLE_NAME),
                            '',
                            "\n"
                        ),
                        $scriptContents
                    );

                    if (!$ssh2Client->sendFile('/tmp/scalr-builder.sh', $scriptContents, "w+", false)) {
                        throw new Exception("Cannot upload script");
                    }

                    /*
                    $BundleTask->Log(sprintf(_("Uploading chef recipes...")));
                    if (!$ssh2Client->sendFile('/tmp/recipes.tar.gz', APPPATH . '/www/storage/chef/recipes.tar.gz')) {
                        throw new Exception("Cannot upload chef recipes");
                    }
                    */
                } catch (Exception $e) {
                    $BundleTask->Log(sprintf(_("Scripts upload failed: %s"), $e->getMessage()));
                    //TODO: Set status of bundle log to failed
                    exit();
                }

                $BundleTask->Log("Launching role builder routines on server");

                $ssh2Client->exec("chmod 0777 /tmp/scalr-builder.sh");

                // For CGE we need to use sudo
                if ($BundleTask->platform == SERVER_PLATFORMS::GCE || $BundleTask->osFamily == 'amazon') {

                    $shell = $ssh2Client->getShell();

                    @stream_set_blocking($shell, true);
                    @stream_set_timeout($shell, 5);

                    @fwrite($shell, "sudo touch /var/log/role-builder-output.log 2>&1" . PHP_EOL);
                    $output = @fgets($shell, 4096);
                    $BundleTask->Log("Verbose 1: {$output}");

                    @fwrite($shell, "sudo chmod 0666 /var/log/role-builder-output.log 2>&1" . PHP_EOL);
                    $output2 = @fgets($shell, 4096);
                    $BundleTask->Log("Verbose 2: {$output2}");


                    @fwrite($shell, "sudo setsid /tmp/scalr-builder.sh > /var/log/role-builder-output.log 2>&1 &" . PHP_EOL);
                    $output3 = @fgets($shell, 4096);
                    $BundleTask->Log("Verbose 3: {$output3}");

                    sleep(5);

                    $meta = stream_get_meta_data($shell);
                    $BundleTask->Log(sprintf("Verbose (Meta): %s", json_encode($meta)));
                    $i = 4;
                    if ($meta['eof'] == false && $meta['unread_bytes'] != 0) {
                        $output4 = @fread($shell, $meta['unread_bytes']);
                        $BundleTask->Log("Verbose {$i}: {$output4}");

                        $meta = stream_get_meta_data($shell);
                        $BundleTask->Log(sprintf("Verbose (Meta): %s", json_encode($meta)));
                    }

                    @fclose($shell);

                    /*
                    $r1 = $ssh2Client->exec("sudo touch /var/log/role-builder-output.log");
                    $BundleTask->Log("1: {$r1} ({$ssh2Client->stdErr})");
                    $r2 = $ssh2Client->exec("sudo chmod 0666 /var/log/role-builder-output.log");
                    $BundleTask->Log("2: {$r2} ({$ssh2Client->stdErr})");
                    $r3 = $ssh2Client->exec("sudo setsid /tmp/scalr-builder.sh > /var/log/role-builder-output.log 2>&1 &");
                    $BundleTask->Log("3: {$r3} ({$ssh2Client->stdErr})");
                    */
                } else {
                    $ssh2Client->exec("setsid /tmp/scalr-builder.sh > /var/log/role-builder-output.log 2>&1 &");
                }

                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::INTALLING_SOFTWARE;
                $BundleTask->save();
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::INTALLING_SOFTWARE:
                try {
                    $ssh2Client = $DBServer->GetSsh2Client();
                    $ssh2Client->connect($DBServer->remoteIp, $DBServer->getPort(DBServer::PORT_SSH));
                } catch (Exception $e) {
                    $BundleTask->Log(sprintf(_("Scalr unable to establish SSH connection with server on %:%. Error: %s"), $DBServer->remoteIp, $DBServer->getPort(DBServer::PORT_SSH), $e->getMessage()));
                    //TODO: Set status of bundle log to failed
                    exit();
                }

                $log = $ssh2Client->getFile('/var/log/role-builder-output.log');
                $log_lines = explode("\r\n", $log);
                $last_msg = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_LAST_LOG_MESSAGE);
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
                        $BundleTask->Log(sprintf("role-builder-step.log: %s", $stepLog));
                        $BundleTask->SnapshotCreationFailed($msg);
                    } else {
                        $BundleTask->Log($msg);
                        $DBServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_LAST_LOG_MESSAGE, $msg);
                    }
                }
                //Read /var/log/role-builder-output.log
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::PENDING:
                try {
                    $platformModule = PlatformFactory::NewPlatform($BundleTask->platform);
                    $platformModule->CreateServerSnapshot($BundleTask);
                } catch (Exception $e) {
                    Logger::getLogger(LOG_CATEGORY::BUNDLE)->error($e->getMessage());
                    $BundleTask->SnapshotCreationFailed($e->getMessage());
                }
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::PREPARING:
                $addedTime = strtotime($BundleTask->dateAdded);
                if ($addedTime + 3600 < time()) {
                    $BundleTask->SnapshotCreationFailed("Server didn't send PrepareBundleResult message in time.");
                }
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS:
                PlatformFactory::NewPlatform($BundleTask->platform)->CheckServerSnapshotStatus($BundleTask);
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::CREATING_ROLE:
                try {
                    if ($BundleTask->object == BundleTask::BUNDLETASK_OBJECT_IMAGE) {
                        if ($BundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
                            $dbRole = $DBServer->GetFarmRoleObject()->GetRoleObject();
                            $dbRole->__getNewRoleObject()->setImage(
                                $BundleTask->platform,
                                $BundleTask->cloudLocation,
                                $BundleTask->snapshotId,
                                $BundleTask->createdById,
                                $BundleTask->createdByEmail
                            );

                            $BundleTask->Log(sprintf(_("Image replacement completed.")));
                        }

                        $BundleTask->Log(sprintf(_("Bundle task completed.")));

                        $BundleTask->setDate('finished');
                        $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;
                        $BundleTask->Save();

                    } elseif ($BundleTask->object == BundleTask::BUNDLETASK_OBJECT_ROLE) {

                        if ($BundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
                            $saveOldRole = false;
                            try {
                                $dbRole = $DBServer->GetFarmRoleObject()->GetRoleObject();
                                if ($dbRole->name == $BundleTask->roleName && $dbRole->envId == $BundleTask->envId) {
                                    $saveOldRole = true;
                                }
                            } catch (Exception $e) {
                                //NO OLD ROLE
                            }
                            if ($dbRole && $saveOldRole) {
                                if ($DBServer) {
                                    $new_role_name = BundleTask::GenerateRoleName($DBServer->GetFarmRoleObject(), $DBServer);
                                } else {
                                    $new_role_name = $BundleTask->roleName . "-" . rand(1000, 9999);
                                }
                                $dbRole->name = $new_role_name;
                                $BundleTask->Log(sprintf(_("Old role '%s' (ID: %s) renamed to '%s'"), $BundleTask->roleName, $dbRole->id, $new_role_name));
                                $dbRole->save();
                            } else {
                                //TODO:
                                //$this->Logger->error("dbRole->replace->fail({$BundleTask->roleName}, {$BundleTask->envId})");
                            }
                        }

                        try {
                            $DBRole = DBRole::createFromBundleTask($BundleTask);
                        } catch (Exception $e) {
                            $BundleTask->SnapshotCreationFailed("Role creation failed due to internal error ({$e->getMessage()}). Please try again.");
                            return;
                        }

                        if ($BundleTask->replaceType == SERVER_REPLACEMENT_TYPE::NO_REPLACE) {
                            $BundleTask->setDate('finished');
                            $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;

                            $BundleTask->Log(sprintf(_(
                                "Replacement type: %s. Bundle task status: %s"),
                                SERVER_REPLACEMENT_TYPE::NO_REPLACE, SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS
                            ));
                        } else {
                            try {
                                $BundleTask->Log(sprintf(_("Replacement type: %s"), $BundleTask->replaceType));

                                $r_farm_roles = array();

                                try {
                                    $DBFarm = DBFarm::LoadByID($BundleTask->farmId);
                                } catch (Exception $e) {
                                    if (stristr($e->getMessage(), "not found in database")) {
                                        $BundleTask->SnapshotCreationFailed("Farm was removed before task was finished");
                                    }
                                    return;
                                }

                                if ($BundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_FARM) {
                                    try {
                                        $r_farm_roles[] = $DBFarm->GetFarmRoleByRoleID($BundleTask->prototypeRoleId);
                                    } catch (Exception $e) {}
                                } elseif ($BundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
                                    $farm_roles = $db->GetAll("
                                        SELECT id FROM farm_roles
                                        WHERE role_id=?
                                        AND farmid IN (SELECT id FROM farms WHERE env_id=?)
                                    ", array(
                                        $BundleTask->prototypeRoleId,
                                        $BundleTask->envId
                                    ));

                                    foreach ($farm_roles as $farm_role) {
                                        try {
                                            $r_farm_roles[] = DBFarmRole::LoadByID($farm_role['id']);
                                        } catch (Exception $e) {}
                                    }
                                }

                                foreach ($r_farm_roles as $DBFarmRole) {
                                    if ($DBFarmRole->CloudLocation != $BundleTask->cloudLocation) {
                                        $BundleTask->Log(sprintf(
                                            "Role '%s' (ID: %s), farm '%s' (ID: %s) using the same role "
                                            . "but in abother cloud location. Skiping it.",
                                            $DBFarmRole->GetRoleObject()->name,
                                            $DBFarmRole->ID,
                                            $DBFarmRole->GetFarmObject()->Name,
                                            $DBFarmRole->FarmID
                                        ));
                                    } else {
                                        $DBFarmRole->RoleID = $BundleTask->roleId;
                                        $DBFarmRole->Save();
                                    }
                                }

                                $BundleTask->Log(sprintf(_("Replacement completed. Bundle task completed.")));

                                try {
                                    if ($DBServer->status == SERVER_STATUS::IMPORTING) {
                                        $DBServer->Remove();
                                    } elseif ($DBServer->status == SERVER_STATUS::TEMPORARY) {
                                        $BundleTask->Log("Terminating temporary server");
                                        $DBServer->terminate(DBServer::TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER);
                                        $BundleTask->Log("Termination request has been sent");
                                    }
                                } catch (Exception $e) {
                                    $BundleTask->Log("Warning: {$e->getMessage()}");
                                }

                                $BundleTask->setDate('finished');
                                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;
                                $BundleTask->Save();

                            } catch (Exception $e) {
                                $this->Logger->error($e->getMessage());
                                $BundleTask->Log(sprintf(_("Server replacement failed: %s"), $e->getMessage()));
                                $BundleTask->setDate('finished');
                                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;
                            }
                        }
                    }

                    if ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS) {
                        try {
                            if ($DBServer->status == SERVER_STATUS::IMPORTING) {
                                $DBServer->Remove();
                            } elseif ($DBServer->status == SERVER_STATUS::TEMPORARY) {
                                $BundleTask->Log("Terminating temporary server");
                                $DBServer->terminate(DBServer::TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER);
                                $BundleTask->Log("Termination request has been sent");
                            }
                        } catch (Exception $e) {
                            $BundleTask->Log("Warning: {$e->getMessage()}");
                        }
                    }
                    $BundleTask->Save();

                } catch (Exception $e) {
                    $this->Logger->error($e->getMessage());
                }
                break;
        }
    }
}
