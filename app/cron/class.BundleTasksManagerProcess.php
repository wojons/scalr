<?php

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
                $ctrlPort = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_CTRL_PORT);
                if (!$ctrlPort) {
                    $ctrlPort = 8013;
                }
                if (\Scalr::config('scalr.instances_connection_policy') == 'local') {
                    $requestHost = $DBServer->localIp;
                } elseif (\Scalr::config('scalr.instances_connection_policy') == 'public') {
                    $requestHost = $DBServer->remoteIp;
                } elseif (\Scalr::config('scalr.instances_connection_policy') == 'auto') {
                    if ($DBServer->remoteIp) {
                        $requestHost = $DBServer->remoteIp;
                    } else {
                        $requestHost = $DBServer->localIp;
                    }
                }

                $conn = @fsockopen($requestHost, $ctrlPort, $errno, $errstr, 10);

                if ($conn) {
                    $DBServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OUT_CONNECTION, 1);
                    $BundleTask->Log("Outbound connection successfully established. Awaiting user action: prebuild automation selection");
                    $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::AWAITING_USER_ACTION;
                    $BundleTask->Log(sprintf(_("Bundle task status: %s"), $BundleTask->status));
                    $BundleTask->Save();
                } else {
                    $errstr = sprintf("Unable to establish outbound (Scalr -> Scalarizr) communication (%s:%s): %s.", $requestHost, $ctrlPort, $errstr);
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
                        $sharedIpId = $platform->getConfigVariable(Modules_Platforms_Cloudstack::SHARED_IP_ID.".{$cloudLocation}", $environment, false);
                        $sharedIp = $platform->getConfigVariable(Modules_Platforms_Cloudstack::SHARED_IP.".{$cloudLocation}", $environment, false);

                        $BundleTask->Log("Shared IP: {$sharedIp}");

                        $cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
                            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $environment),
                            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $environment),
                            $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $environment),
                            $DBServer->platform
                        );

                        // Create port forwarding rules for scalarizr
                        $port = $platform->getConfigVariable(Modules_Platforms_Cloudstack::SZR_PORT_COUNTER.".{$cloudLocation}.{$sharedIpId}", $environment, false);
                        if (!$port) {
                            $port1 = 30000;
                            $port2 = 30001;
                            $port3 = 30002;
                            $port4 = 30003;
                        }
                        else {
                            $port1 = $port+1;
                            $port2 = $port1+1;
                            $port3 = $port2+1;
                            $port4 = $port3+1;
                        }

                        $result2 = $cs->createPortForwardingRule($sharedIpId, 8014, "udp", $port1, $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));
                        $result1 = $cs->createPortForwardingRule($sharedIpId, 8013, "tcp", $port1, $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));
                        $result3 = $cs->createPortForwardingRule($sharedIpId, 8010, "tcp", $port3, $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));
                        $result4 = $cs->createPortForwardingRule($sharedIpId, 8008, "tcp", $port2, $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));

                        $result5 = $cs->createPortForwardingRule($sharedIpId, 22, "tcp", $port4, $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));

                        $DBServer->SetProperties(array(
                            SERVER_PROPERTIES::SZR_CTRL_PORT => $port1,
                            SERVER_PROPERTIES::SZR_SNMP_PORT => $port1,

                            SERVER_PROPERTIES::SZR_API_PORT => $port3,
                            SERVER_PROPERTIES::SZR_UPDC_PORT => $port2,
                            SERVER_PROPERTIES::CUSTOM_SSH_PORT => $port4
                        ));

                        $DBServer->remoteIp = $sharedIp;
                        $DBServer->Save();

                        $platform->setConfigVariable(array(Modules_Platforms_Cloudstack::SZR_PORT_COUNTER.".{$cloudLocation}.{$sharedIpId}" => $port4), $environment, false);
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

                //Prepare script
                $BundleTask->Log(sprintf(_("Uploading builder scripts...")));
                $behaviors = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR);
                try {
                    if ($DBServer->isOpenstack()) {
                        $platform = SERVER_PLATFORMS::OPENSTACK;
                    } else {
                        $platform = $DBServer->platform;
                    }
                    $baseUrl = \Scalr::config('scalr.endpoint.scheme') . "://" . \Scalr::config('scalr.endpoint.host');
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

                    fwrite($shell, "sudo su\n");
                    fwrite($shell, "touch /var/log/role-builder-output.log 2>&1\n");
                    fwrite($shell, "chmod 0666 /var/log/role-builder-output.log 2>&1\n");
                    fwrite($shell, "setsid /tmp/scalr-builder.sh > /var/log/role-builder-output.log 2>&1 &\n");

                    // Get output
                    $iterations = 0;
                    while($l = @fgets($shell, 4096) && $iterations < 10)
                    {
                        $meta = stream_get_meta_data($shell);
                        if ($meta["timed_out"])
                            break;
                        $retval .= $l;
                        $iterations ++;
                    }

                    @fclose($shell);

                    $BundleTask->Log("Exec result: {$retval}");

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

            case SERVER_SNAPSHOT_CREATION_STATUS::REPLACING_SERVERS:
                $r_farm_roles = array();
                $BundleTask->Log(sprintf("Bundle task replacement type: %s", $BundleTask->replaceType));

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
                    } catch (Exception $e) {
                    }
                } elseif ($BundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
                    $farm_roles = $db->GetAll("
                        SELECT id FROM farm_roles
                        WHERE role_id=? AND new_role_id=?
                        AND farmid IN (SELECT id FROM farms WHERE env_id=?)
                    ", array(
                        $BundleTask->prototypeRoleId,
                        $BundleTask->roleId,
                        $BundleTask->envId
                    ));

                    foreach ($farm_roles as $farm_role) {
                        try {
                            $r_farm_roles[] = DBFarmRole::LoadByID($farm_role['id']);
                        } catch (Exception $e) {
                        }
                    }
                }

                $update_farm_dns_zones = array();
                $completed_roles = 0;
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
                        $completed_roles++;
                    } else {
                        $servers = $db->GetAll("SELECT server_id FROM servers WHERE farm_roleid = ? AND role_id=? AND status NOT IN (?,?)", array(
                            $DBFarmRole->ID,
                            $DBFarmRole->RoleID,
                            SERVER_STATUS::TERMINATED,
                            SERVER_STATUS::PENDING_TERMINATE
                        ));

                        $BundleTask->Log(sprintf(
                            "Found %s servers that need to be replaced with new ones. "
                          . "Role '%s' (ID: %s), farm '%s' (ID: %s)",
                            count($servers),
                            $DBFarmRole->GetRoleObject()->name,
                            $DBFarmRole->ID,
                            $DBFarmRole->GetFarmObject()->Name,
                            $DBFarmRole->FarmID
                        ));

                        if (count($servers) == 0) {
                            $DBFarmRole->RoleID = $DBFarmRole->NewRoleID;
                            $DBFarmRole->NewRoleID = null;
                            $DBFarmRole->Save();
                            $update_farm_dns_zones[$DBFarmRole->FarmID] = 1;
                            $completed_roles++;
                        } else {
                            $metaData = $BundleTask->getSnapshotDetails();
                            foreach ($servers as $server) {
                                try {
                                    $DBServer = DBServer::LoadByID($server['server_id']);
                                } catch (Exception $e) {
                                    //TODO:
                                    continue;
                                }

                                if ($DBServer->serverId == $BundleTask->serverId || $metaData['noServersReplace']) {
                                    $DBServer->roleId = $BundleTask->roleId;
                                    $DBServer->Save();
                                    if ($metaData['noServersReplace']) {
                                        $BundleTask->Log(sprintf(
                                            "'Do not replace servers' option was checked. "
                                          . "Server '%s' won't be replaced to new image.",
                                            $DBServer->serverId
                                        ));
                                    } else {
                                        $BundleTask->Log(sprintf(
                                            "Server '%s', on which snapshot has been taken, "
                                          . "already has all modifications. No need to replace it.",
                                            $DBServer->serverId
                                        ));
                                    }
                                    if ($DBServer->GetFarmObject()->Status == FARM_STATUS::SYNCHRONIZING) {
                                        $DBServer->terminate(array('BUNDLE_TASK_FINISHED', 'Synchronizing', $BundleTask->id));
                                    }
                                } else {
                                    if (!$db->GetOne("SELECT server_id FROM servers WHERE replace_server_id=? AND status NOT IN (?,?,?) LIMIT 1", array(
                                        $DBServer->serverId,
                                        SERVER_STATUS::TERMINATED,
                                        SERVER_STATUS::PENDING_TERMINATE,
                                        SERVER_STATUS::TROUBLESHOOTING
                                    ))) {
                                        $ServerCreateInfo = new ServerCreateInfo($DBFarmRole->Platform, $DBFarmRole, $DBServer->index, $DBFarmRole->NewRoleID);
                                        $nDBServer = Scalr::LaunchServer(
                                            $ServerCreateInfo, null, false, "Server replacement after snapshotting",
                                            (!empty($BundleTask->createdById) ? $BundleTask->createdById : null)
                                        );
                                        $nDBServer->replaceServerID = $DBServer->serverId;
                                        $nDBServer->Save();
                                        $BundleTask->Log(sprintf(_("Started new server %s to replace server %s"), $nDBServer->serverId, $DBServer->serverId));
                                    }
                                }
                            }
                        }
                    }
                }

                if ($completed_roles == count($r_farm_roles)) {
                    $BundleTask->Log(sprintf(_(
                        "No servers with old role. Replacement complete. Bundle task complete."),
                        SERVER_REPLACEMENT_TYPE::NO_REPLACE,
                        SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS
                    ));

                    try {
                        if ($DBServer->status == SERVER_STATUS::IMPORTING) {
                            $DBServer->Remove();
                        } elseif ($DBServer->status == SERVER_STATUS::TEMPORARY) {
                            $BundleTask->Log("Terminating temporary server");
                            $DBServer->terminate('TEMPORARY_SERVER_ROLE_BUILDER');
                            $BundleTask->Log("Termination request has been sent");
                        }
                    } catch (Exception $e) {
                        $BundleTask->Log("Warning: {$e->getMessage()}");
                    }

                    $BundleTask->setDate('finished');
                    $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;
                    $BundleTask->Save();
                }

                try {
                    if (count($update_farm_dns_zones) != 0) {
                        foreach ($update_farm_dns_zones as $farm_id => $v) {
                            $dnsZones = DBDNSZone::loadByFarmId($farm_id);
                            foreach ($dnsZones as $dnsZone) {
                                if ($dnsZone->status != DNS_ZONE_STATUS::INACTIVE && $dnsZone->status != DNS_ZONE_STATUS::PENDING_DELETE) {
                                    $dnsZone->updateSystemRecords();
                                    $dnsZone->save();
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->Logger->fatal("DNS ZONE: {$e->getMessage()}");
                }
                break;

            case SERVER_SNAPSHOT_CREATION_STATUS::CREATING_ROLE:
                try {
                    if ($BundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
                        $saveOldRole = false;
                        try {
                            $dbRole = DBRole::loadById($DBServer->roleId);
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

                        try {
                            $DBServer = DBServer::LoadByID($BundleTask->serverId);
                            if ($DBServer->status == SERVER_STATUS::IMPORTING) {
                                if ($DBServer->farmId) {
                                    // Create DBFarmRole object
                                    // TODO: create DBFarm role
                                }
                            }
                        } catch (Exception $e) {
                        }
                    } else {
                        try {
                            $BundleTask->Log(sprintf(_("Replacement type: %s. Bundle task status: %s"), $BundleTask->replaceType, SERVER_SNAPSHOT_CREATION_STATUS::REPLACING_SERVERS));
                            if ($BundleTask->replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_FARM) {
                                $DBFarm = DBFarm::LoadByID($BundleTask->farmId);
                                $DBFarmRole = $DBFarm->GetFarmRoleByRoleID($BundleTask->prototypeRoleId);
                                $DBFarmRole->NewRoleID = $BundleTask->roleId;
                                $DBFarmRole->Save();
                            } else {
                                $farm_roles = $db->GetAll("SELECT id FROM farm_roles WHERE role_id=? AND farmid IN (SELECT id FROM farms WHERE env_id=?)", array(
                                    $BundleTask->prototypeRoleId,
                                    $BundleTask->envId
                                ));

                                foreach ($farm_roles as $farm_role) {
                                    $DBFarmRole = DBFarmRole::LoadByID($farm_role['id']);
                                    $DBFarmRole->NewRoleID = $BundleTask->roleId;
                                    $DBFarmRole->Save();
                                }
                            }
                            $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::REPLACING_SERVERS;
                        } catch (Exception $e) {
                            $this->Logger->error($e->getMessage());
                            $BundleTask->Log(sprintf(_("Server replacement failed: %s"), $e->getMessage()));
                            $BundleTask->setDate('finished');
                            $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS;
                        }
                    }

                    if ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS) {
                        try {
                            if ($DBServer->status == SERVER_STATUS::IMPORTING) {
                                $DBServer->Remove();
                            } elseif ($DBServer->status == SERVER_STATUS::TEMPORARY) {
                                $BundleTask->Log("Terminating temporary server");
                                $DBServer->terminate('TEMPORARY_SERVER_ROLE_BUILDER');
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
