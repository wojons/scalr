<?php
use Scalr\Acl\Acl;
use Scalr\Server\Alerts;
use Scalr\Service\Aws\Ec2\DataType\InstanceAttributeType;

class Scalr_UI_Controller_Servers extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'serverId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function getList(array $status = array())
    {
        $retval = array();

        $sql = "SELECT * FROM servers WHERE env_id = ".$this->db->qstr($this->getEnvironmentId());
        if ($this->getParam('farmId'))
            $sql .= " AND farm_id = ".$this->db->qstr($this->getParam('farmId'));

        if ($this->getParam('farmRoleId'))
            $sql .= " AND farm_roleid = ".$this->db->qstr($this->getParam('farmRoleId'));

        if (!empty($status))
            $sql .= "AND status IN ('".implode("','", $status)."')";

        $s = $this->db->execute($sql);
        while ($server = $s->fetchRow()) {
            $retval[$server['server_id']] = $server;
        }

        return $retval;
    }

    public function xLockAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $this->request->defineParams(array(
            'serverId'
        ));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        if ($dbServer->platform != SERVER_PLATFORMS::EC2)
            throw new Exception("Server lock supported ONLY by EC2");

        $env = Scalr_Environment::init()->loadById($dbServer->envId);
        $ec2 = $env->aws($dbServer->GetCloudLocation())->ec2;

        $newValue = !$ec2->instance->describeAttribute($dbServer->GetCloudServerID(), InstanceAttributeType::disableApiTermination());

        $ec2->instance->modifyAttribute(
            $dbServer->GetCloudServerID(),
            InstanceAttributeType::disableApiTermination(),
            $newValue
        );

        $dbServer->SetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED, $newValue);

        $this->response->success();
    }

    public function xTroubleshootAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $this->request->defineParams(array(
            'serverId'
        ));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $dbServer->status = SERVER_STATUS::TROUBLESHOOTING;
        $dbServer->Save();

        // Send before host terminate to the server to detach all used volumes.
        $msg = new Scalr_Messaging_Msg_BeforeHostTerminate($dbServer);

        if ($dbServer->farmRoleId != 0) {
            foreach (Scalr_Role_Behavior::getListForFarmRole($dbServer->GetFarmRoleObject()) as $behavior) {
                $msg = $behavior->extendMessage($msg, $dbServer);
            }
        }
        $dbServer->SendMessage($msg);

        Scalr::FireEvent($dbServer->farmId, new HostDownEvent($dbServer));

        $this->response->success();
    }

    public function xGetWindowsPasswordAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_SECURITY_RETRIEVE_WINDOWS_PASSWORDS);

        $this->request->defineParams(array(
            'serverId'
        ));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        if ($dbServer->platform == SERVER_PLATFORMS::EC2) {
            $env = Scalr_Environment::init()->loadById($dbServer->envId);
            $ec2 = $env->aws($dbServer->GetCloudLocation())->ec2;

            $encPassword = $ec2->instance->getPasswordData($dbServer->GetCloudServerID());
            $privateKey = Scalr_SshKey::init()->loadGlobalByFarmId($dbServer->farmId, $dbServer->GetCloudLocation(), $dbServer->platform);
            $password = Scalr_Util_CryptoTool::opensslDecrypt(base64_decode($encPassword->passwordData), $privateKey->getPrivate());
        } elseif (PlatformFactory::isOpenstack($dbServer->platform)) {
            $env = Scalr_Environment::init()->loadById($dbServer->envId);
            $os = $env->openstack($dbServer->platform, $dbServer->GetCloudLocation());

            //TODO:
        } else
            throw new Exception("Requested operation supported only by EC2");
        $this->response->data(array('password' => $password));
    }

    public function xGetStorageDetailsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $client = Scalr_Net_Scalarizr_Client::getClient(
            $dbServer,
            Scalr_Net_Scalarizr_Client::NAMESPACE_SYSTEM,
            $dbServer->getPort(DBServer::PORT_API)
        );

        if ($dbServer->GetFarmRoleObject()->GetRoleObject()->osFamily == 'windows') {
            $storages = array('C' => array());
        } else {
            $storages = array('/' => array());
            $storageConfigs = $dbServer->GetFarmRoleObject()->getStorage()->getVolumes($dbServer->index);
            foreach ($storageConfigs as $config) {
                $config = $config[$dbServer->index];

                $storages[$config->config->mpoint] = array();
            }
        }

        $info = $client->statvfs(array_keys($storages));

        $this->response->data(array('data' => $info));
    }

    public function xGetHealthDetailsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $client = Scalr_Net_Scalarizr_Client::getClient(
            $dbServer,
            Scalr_Net_Scalarizr_Client::NAMESPACE_SYSTEM,
            $dbServer->getPort(DBServer::PORT_API)
        );

        $data = array();

        try {
            $la = $client->loadAverage();
            $data['la'] = number_format($la[0], 2);
        } catch (Exception $e) {}

        try {
            $mem = $client->memInfo();
            $data['memory'] = array('total' => round($mem->total_real / 1024 / 1024, 1), 'free' => round(($mem->total_free+$mem->cached) / 1024 / 1024, 1));
        } catch (Exception $e) {}

        try {
            $cpu1 = $client->cpuStat();
            sleep(1);
            $cpu2 = $client->cpuStat();

            $dif['user'] = $cpu2->user - $cpu1->user;
            $dif['nice'] = $cpu2->nice - $cpu1->nice;
            $dif['sys'] =  $cpu2->system - $cpu1->system;
            $dif['idle'] = $cpu2->idle - $cpu1->idle;
            $total = array_sum($dif);
            foreach($dif as $x=>$y) $cpu[$x] = round($y / $total * 100, 1);
            $data['cpu'] = $cpu;
        } catch (Exception $e) {}

        $this->response->data(array('data' => $data));
    }

    public function xResendMessageAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $message = $this->db->GetRow("SELECT * FROM messages WHERE server_id=? AND messageid=? LIMIT 1",array(
            $this->getParam('serverId'), $this->getParam('messageId')
        ));

        if ($message) {
            if ($message['message_format'] == 'json') {
                $serializer = new Scalr_Messaging_JsonSerializer();
            } else {
                $serializer = new Scalr_Messaging_XmlSerializer();
            }

            $msg = $serializer->unserialize($message['message']);

            $dbServer = DBServer::LoadByID($this->getParam('serverId'));
            $this->user->getPermissions()->validate($dbServer);

            if (in_array($dbServer->status, array(SERVER_STATUS::RUNNING, SERVER_STATUS::INIT))) {
                $this->db->Execute("UPDATE messages SET status=?, handle_attempts='0' WHERE id=?", array(MESSAGE_STATUS::PENDING, $message['id']));
                $dbServer->SendMessage($msg);
            }
            else
                throw new Exception("Scalr unable to re-send message. Server should be in running state.");

            $this->response->success('Message successfully re-sent to the server');
        } else {
            throw new Exception("Message not found");
        }
    }

    public function xListMessagesAction()
    {
        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS) && !$this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->request->defineParams(array(
            'serverId',
            'sort' => array('type' => 'string', 'default' => 'id'),
            'dir' => array('type' => 'string', 'default' => 'DESC')
        ));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $sql = "SELECT *, message_name as message_type FROM messages WHERE server_id='{$dbServer->serverId}'";
        $response = $this->buildResponseFromSql($sql, array("server_id", "message", "messageid"));

        foreach ($response["data"] as &$row) {

            if (!$row['message_type']) {
                preg_match("/^<\?xml [^>]+>[^<]*<message(.*?)name=\"([A-Za-z0-9_]+)\"/si", $row['message'], $matches);
                $row['message_type'] = $matches[2];
            }

            $row['message'] = '';
            $row['dtlasthandleattempt'] = Scalr_Util_DateTime::convertTz($row['dtlasthandleattempt']);
        }

        $this->response->data($response);
    }

    public function messagesAction()
    {
        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS) && !$this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        $this->response->page('ui/servers/messages.js', array('serverId' => $this->getParam('serverId')));
    }

    public function viewAction()
    {
        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS) && !$this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        $this->response->page('ui/servers/view.js', array(
            'mindtermEnabled' => \Scalr::config('scalr.ui.mindterm_enabled')
        ), array('ui/servers/actionsmenu.js'), array('ui/servers/view.css'));
    }

    public function sshConsoleAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        if (\Scalr::config('scalr.instances_connection_policy') == 'local')
            $ipAddress = $dbServer->localIp;
        elseif (\Scalr::config('scalr.instances_connection_policy') == 'public')
            $ipAddress = $dbServer->remoteIp;
        elseif (\Scalr::config('scalr.instances_connection_policy') == 'auto') {
            if ($this->remoteIp)
                $ipAddress = $dbServer->remoteIp;
            else
                $ipAddress = $dbServer->localIp;
        }

        if ($ipAddress) {
            $dBFarm = $dbServer->GetFarmObject();
            $dbRole = DBRole::loadById($dbServer->roleId);

            $sshPort = $dbRole->getProperty(DBRole::PROPERTY_SSH_PORT);
            if (!$sshPort)
                $sshPort = 22;

            $cSshPort = $dbServer->GetProperty(SERVER_PROPERTIES::CUSTOM_SSH_PORT);
            if ($cSshPort)
                $sshPort = $cSshPort;

            $sshKey = Scalr_SshKey::init()->loadGlobalByFarmId(
                $dbServer->farmId,
                $dbServer->GetFarmRoleObject()->CloudLocation,
                $dbServer->platform
            );

            $this->response->page('ui/servers/sshconsole.js', array(
                'serverId' => $dbServer->serverId,
                'serverIndex' => $dbServer->index,
                'remoteIp' => $ipAddress,
                'localIp' => $dbServer->localIp,
                'farmName' => $dBFarm->Name,
                'farmId' => $dbServer->farmId,
                'roleName' => $dbRole->name,
                'port' => $sshPort,
                'username' => $dbServer->platform == SERVER_PLATFORMS::GCE ? 'scalr' : 'root',
                "key" => base64_encode($sshKey->getPrivate())
            ));
        }
        else
            throw new Exception(_("Server not initialized yet"));
    }

    public function xServerCancelOperationAction()
    {
        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS) && !$this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->request->defineParams(array(
            'serverId'
        ));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $bt_id = $this->db->GetOne("
            SELECT id FROM bundle_tasks WHERE server_id=? AND prototype_role_id='0' AND status NOT IN (?,?,?) LIMIT 1
        ", array(
            $dbServer->serverId,
            SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
            SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS,
            SERVER_SNAPSHOT_CREATION_STATUS::CANCELLED
        ));

        if ($bt_id) {
            $BundleTask = BundleTask::LoadById($bt_id);
            $BundleTask->SnapshotCreationFailed("Server was terminated before snapshot was created.");
        }

        if ($dbServer->status == SERVER_STATUS::IMPORTING) {
            $dbServer->Remove();
        } else {
            $dbServer->terminate('SNAPSHOT_CANCELLATION', true, $this->user);
        }

        $this->response->success("Server was successfully canceled and removed from database");
    }

    public function xUpdateUpdateClientAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $this->request->defineParams(array(
            'serverId'
        ));

        if (!$this->db->GetOne("SELECT id FROM scripts WHERE id='3803' AND clientid='0' LIMIT 1"))
            throw new Exception("Automatical scalarizr update doesn't supported by this scalr version");

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $scriptSettings = array(
            'version' => $this->db->GetOne("SELECT MAX(revision) FROM script_revisions WHERE scriptid='3803'"),
            'scriptid' => 3803,
            'timeout' => 300,
            'issync' => 0,
            'params' => serialize(array())
        );

        $message = new Scalr_Messaging_Msg_ExecScript("Manual");
        $message->setServerMetaData($dbServer);

        $script = Scalr_Scripting_Manager::prepareScript($scriptSettings, $dbServer);

        $itm = new stdClass();
        // Script
        $itm->asynchronous = ($script['issync'] == 1) ? '0' : '1';
        $itm->timeout = $script['timeout'];
        if ($script['body']) {
            $itm->name = $script['name'];
            $itm->body = $script['body'];
        } else {
            $itm->path = $script['path'];
        }
        $itm->executionId = $script['execution_id'];

        $message->scripts = array($itm);

        $dbServer->SendMessage($message);

        $this->response->success('Scalarizr update-client update successfully initiated');
    }

    public function xUpdateAgentAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $this->request->defineParams(array(
            'serverId'
        ));

        if (!$this->db->GetOne("SELECT id FROM scripts WHERE id='2102' AND clientid='0' LIMIT 1"))
            throw new Exception("Automatical scalarizr update doesn't supported by this scalr version");

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $scriptSettings = array(
            'version' => $this->db->GetOne("SELECT MAX(revision) FROM script_revisions WHERE scriptid='2102'"),
            'scriptid' => 2102,
            'timeout' => 300,
            'issync' => 0,
            'params' => serialize(array())
        );

        $message = new Scalr_Messaging_Msg_ExecScript("Manual");
        $message->setServerMetaData($dbServer);

        $script = Scalr_Scripting_Manager::prepareScript($scriptSettings, $dbServer);

        $itm = new stdClass();
        // Script
        $itm->asynchronous = ($script['issync'] == 1) ? '0' : '1';
        $itm->timeout = $script['timeout'];
        if ($script['body']) {
            $itm->name = $script['name'];
            $itm->body = $script['body'];
        } else {
            $itm->path = $script['path'];
        }
        $itm->executionId = $script['execution_id'];

        $message->scripts = array($itm);

        $dbServer->SendMessage($message);

        $this->response->success('Scalarizr update successfully initiated. Please wait a few minutes and then refresh the page');
    }

    public function xListServersAction()
    {

        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS) && !$this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->request->defineParams(array(
            'roleId' => array('type' => 'int'),
            'farmId' => array('type' => 'int'),
            'farmRoleId' => array('type' => 'int'),
            'serverId',
            'hideTerminated' => array('type' => 'bool'),
            'sort' => array('type' => 'json')
        ));

        $sql = 'SELECT servers.*, farms.name AS farm_name, roles.name AS role_name, farm_roles.alias AS role_alias
                FROM servers
                LEFT JOIN farms ON servers.farm_id = farms.id
                LEFT JOIN roles ON roles.id = servers.role_id
                LEFT JOIN farm_roles ON farm_roles.id = servers.farm_roleid
                WHERE servers.env_id = ? AND :FILTER:';
        $args = array($this->getEnvironmentId());

        if ($this->getParam('cloudServerId')) {
            $sql = str_replace('WHERE', 'LEFT JOIN server_properties ON servers.server_id = server_properties.server_id WHERE', $sql);
            $sql .= ' AND (';

            $sql .= 'server_properties.name = ? AND server_properties.value = ?';
            $args[] = CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID;
            $args[] = $this->getParam('cloudServerId');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = EC2_SERVER_PROPERTIES::INSTANCE_ID;
            $args[] = $this->getParam('cloudServerId');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = EUCA_SERVER_PROPERTIES::INSTANCE_ID;
            $args[] = $this->getParam('cloudServerId');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = GCE_SERVER_PROPERTIES::SERVER_ID;
            $args[] = $this->getParam('cloudServerId');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = OPENSTACK_SERVER_PROPERTIES::SERVER_ID;
            $args[] = $this->getParam('cloudServerId');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = RACKSPACE_SERVER_PROPERTIES::SERVER_ID;
            $args[] = $this->getParam('cloudServerId');

            $sql .= ')';
        }

        if ($this->getParam('cloudServerLocation')) {
            if (!strstr($sql, 'LEFT JOIN server_properties ON servers.server_id = server_properties.server_id'))
                $sql = str_replace('WHERE', 'LEFT JOIN server_properties ON servers.server_id = server_properties.server_id WHERE', $sql);
            $sql .= ' AND (';

            $sql .= 'server_properties.name = ? AND server_properties.value = ?';
            $args[] = CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION;
            $args[] = $this->getParam('cloudServerLocation');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = EC2_SERVER_PROPERTIES::REGION;
            $args[] = $this->getParam('cloudServerLocation');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = EUCA_SERVER_PROPERTIES::REGION;
            $args[] = $this->getParam('cloudServerLocation');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = GCE_SERVER_PROPERTIES::CLOUD_LOCATION;
            $args[] = $this->getParam('cloudServerLocation');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION;
            $args[] = $this->getParam('cloudServerLocation');

            $sql .= ' OR server_properties.name = ? AND server_properties.value = ?';
            $args[] = RACKSPACE_SERVER_PROPERTIES::DATACENTER;
            $args[] = $this->getParam('cloudServerLocation');

            $sql .= ')';
        }

        if ($this->getParam('farmId')) {
            $sql .= " AND farm_id=?";
            $args[] = $this->getParam('farmId');
        }

        if ($this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS)) {
            if (!$this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_NOT_OWNED_FARMS)) {
                $sql .= " AND (farms.created_by_id = ? OR servers.status IN (?, ?) AND farms.id IS NULL)";
                $args[] = $this->user->getId();
                $args[] = SERVER_STATUS::IMPORTING;
                $args[] = SERVER_STATUS::TEMPORARY;
            }
        } else {
            //show servers related to role creation process only
            $sql .= ' AND servers.status IN (?, ?)';
            $args[] = SERVER_STATUS::IMPORTING;
            $args[] = SERVER_STATUS::TEMPORARY;
        }

        if ($this->getParam('farmRoleId')) {
            $sql .= " AND farm_roleid=?";
            $args[] = $this->getParam('farmRoleId');
        }

        if ($this->getParam('roleId')) {
            $sql .= " AND role_id=?";
            $args[] = $this->getParam('roleId');
        }

        if ($this->getParam('serverId')) {
            $sql .= " AND server_id=?";
            $args[] = $this->getParam('serverId');
        }

        if ($this->getParam('hideTerminated')) {
            $sql .= ' AND servers.status != ?';
            $args[] = SERVER_STATUS::TERMINATED;
        }

        $response = $this->buildResponseFromSql2($sql, array('platform', 'farm_name', 'role_name', 'role_alias', 'index', 'server_id', 'remote_ip', 'local_ip', 'uptime', 'status'),
            array('servers.server_id', 'farm_id', 'farms.name', 'remote_ip', 'local_ip', 'servers.status', 'farm_roles.alias'), $args);

        foreach ($response["data"] as &$row) {
            try {
                $dbServer = DBServer::LoadByID($row['server_id']);

                $row['cloud_server_id'] = $dbServer->GetCloudServerID();

                if (in_array($dbServer->status, array(SERVER_STATUS::RUNNING, SERVER_STATUS::INIT))) {
                    $row['cluster_role'] = "";
                    if ($dbServer->GetFarmRoleObject()->GetRoleObject()->getDbMsrBehavior() || $dbServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {

                        $isMaster = ($dbServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER) || $dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER));
                        $row['cluster_role'] = ($isMaster) ? 'Master' : 'Slave';

                        if ($isMaster && $dbServer->GetFarmRoleObject()->GetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER) || $dbServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_MYSQL_SLAVE_TO_MASTER)) {
                            $row['cluster_role'] = 'Promoting';
                        }
                    }
                }

                $row['cloud_location'] = $dbServer->GetCloudLocation();
                if ($dbServer->platform == SERVER_PLATFORMS::EC2) {
                    $loc = $dbServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE);
                    if ($loc && $loc != 'x-scalr-diff')
                        $row['cloud_location'] .= "/".substr($loc, -1, 1);
                }

                if ($dbServer->platform == SERVER_PLATFORMS::EC2) {
                    $row['has_eip'] = $this->db->GetOne("SELECT id FROM elastic_ips WHERE server_id = ?", array($dbServer->serverId));
                }

                if ($dbServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
                    $shardIndex = $dbServer->GetProperty(Scalr_Role_Behavior_MongoDB::SERVER_SHARD_INDEX);
                    $replicaSetIndex = $dbServer->GetProperty(Scalr_Role_Behavior_MongoDB::SERVER_REPLICA_SET_INDEX);
                    $row['cluster_position'] = "{$shardIndex}-{$replicaSetIndex}";
                }
            }
            catch(Exception $e){  }

            $rebooting = $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=? LIMIT 1", array(
                $row['server_id'], SERVER_PROPERTIES::REBOOTING
            ));
            if ($dbServer->status == SERVER_STATUS::RUNNING) {
                if ($rebooting)
                    $row['status'] = "Rebooting";

                $subStatus = $dbServer->GetProperty(SERVER_PROPERTIES::SUB_STATUS);
                if ($subStatus) {
                    $row['status'] = ucfirst($subStatus);
                }
            }

            $row['is_locked'] = $dbServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED) ? 1 : 0;
            $row['is_szr'] = $dbServer->IsSupported("0.5");
            $row['initDetailsSupported'] = $dbServer->IsSupported("0.7.181");

            if ($dbServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED) && in_array($dbServer->status, array(SERVER_STATUS::INIT, SERVER_STATUS::PENDING)))
                $row['isInitFailed'] = 1;

            $launchError = $dbServer->GetProperty(SERVER_PROPERTIES::LAUNCH_ERROR);
            if ($launchError)
                $row['launch_error'] = "1";

            $serverAlerts = new Alerts($dbServer);

            $row['agent_version'] = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_VESION);
            $row['agent_update_needed'] = $dbServer->IsSupported("0.7") && !$dbServer->IsSupported("0.7.189");
            $row['agent_update_manual'] = !$dbServer->IsSupported("0.5");
            $row['os_family'] = $dbServer->GetOsFamily();
            $row['flavor'] = $dbServer->GetFlavor();
            $row['alerts'] = $serverAlerts->getActiveAlertsCount();
            if (!$row['flavor'])
                $row['flavor'] = '';

            if ($dbServer->status == SERVER_STATUS::RUNNING) {
                $tm = (int)$dbServer->GetProperty(SERVER_PROPERTIES::INITIALIZED_TIME);

                if (!$tm)
                    $tm = (int)strtotime($row['dtadded']);

                if ($tm > 0) {
                    $row['uptime'] = Scalr_Util_DateTime::getHumanReadableTimeout(time() - $tm, false);
                }
            }
            else
                $row['uptime'] = '';

            $r_dns = $this->db->GetOne("SELECT value FROM farm_role_settings WHERE farm_roleid=? AND `name`=? LIMIT 1", array(
                $row['farm_roleid'], DBFarmRole::SETTING_EXCLUDE_FROM_DNS
            ));

            $row['excluded_from_dns'] = (!$dbServer->GetProperty(SERVER_PROPERTIES::EXCLUDE_FROM_DNS) && !$r_dns) ? false : true;
        }

        $this->response->data($response);
    }

    public function xListServersUpdateAction()
    {
        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS) && !$this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->request->defineParams(array(
            'servers' => array('type' => 'json')
        ));

        $retval = array();
        $sql = array();


        $servers = $this->getParam('servers');
        if (!empty($servers)) {
            foreach ($servers as $serverId) {
                $sql[] = $this->db->qstr($serverId);
            }
        }

        $stmt = "
            SELECT s.server_id, s.status, s.remote_ip, s.local_ip
            FROM servers s
            LEFT JOIN farms f ON f.id = s.farm_id
            WHERE s.server_id IN (" . join($sql, ',') . ")
            AND s.env_id = ?
        ";

        $args = array($this->getEnvironmentId());

        if ($this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS)) {
            if (!$this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_NOT_OWNED_FARMS)) {
                $stmt .= " AND (f.created_by_id = ? OR s.status IN (?, ?) AND f.id IS NULL)";
                $args[] = $this->user->getId();
                $args[] = SERVER_STATUS::IMPORTING;
                $args[] = SERVER_STATUS::TEMPORARY;
            }
        } else {
            $sql .= ' AND s.status IN (?, ?)';
            $args[] = SERVER_STATUS::IMPORTING;
            $args[] = SERVER_STATUS::TEMPORARY;
        }

        if (count($sql)) {
            $servers = $this->db->Execute($stmt, $args);
            while ($server = $servers->FetchRow()) {
                $rebooting = $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=? LIMIT 1", array(
                    $server['server_id'], SERVER_PROPERTIES::REBOOTING
                ));
                if ($rebooting) {
                    $server['status'] = "Rebooting";
                }

                $subStatus =  $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=? LIMIT 1", array(
                    $server['server_id'], SERVER_PROPERTIES::SUB_STATUS
                ));
                if ($subStatus) {
                    $server['status'] = ucfirst($subStatus);
                }

                $szrInitFailed = $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=? LIMIT 1", array(
                    $server['server_id'], SERVER_PROPERTIES::SZR_IS_INIT_FAILED
                ));

                if ($szrInitFailed && in_array($server['status'], array(SERVER_STATUS::INIT, SERVER_STATUS::PENDING)))
                    $server['isInitFailed'] = 1;

                $launchError = $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=? LIMIT 1", array(
                    $server['server_id'], SERVER_PROPERTIES::LAUNCH_ERROR
                ));

                if ($launchError)
                    $server['launch_error'] = "1";

                $retval[$server['server_id']] = $server;
            }
        }

        $this->response->data(array(
            'servers' => $retval
        ));
    }

    public function xSzrUpdateAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        if (! $this->getParam('serverId'))
            throw new Exception(_('Server not found'));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_UPDC_PORT);
        if (!$port)
            $port = 8008;

        $updateClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer, $port, 30);
        $status = $updateClient->updateScalarizr();

        $this->response->success('Scalarizr successfully updated to the latest version');
    }

    public function xSzrRestartAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        if (! $this->getParam('serverId'))
            throw new Exception(_('Server not found'));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_UPDC_PORT);
        if (!$port)
            $port = 8008;

        $updateClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer, $port, 30);
        $status = $updateClient->restartScalarizr();

        $this->response->success('Scalarizr successfully restarted');
    }

    public function dashboardAction()
    {
        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS) && !$this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        if (! $this->getParam('serverId')) {
            throw new Exception(_('Server not found'));
        }

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $data = array();

        $info = PlatformFactory::NewPlatform($dbServer->platform)->GetServerExtendedInformation($dbServer);
        if (is_array($info) && count($info)) {
            $data['cloudProperties'] = $info;
        }

        try {
            $dbRole = $dbServer->GetFarmRoleObject()->GetRoleObject();
        } catch (Exception $e) {}


        $r_dns = $this->db->GetOne("SELECT value FROM farm_role_settings WHERE farm_roleid=? AND `name`=? LIMIT 1", array(
            $dbServer->farmRoleId, DBFarmRole::SETTING_EXCLUDE_FROM_DNS
        ));

        $data['general'] = array(
            'server_id'         => $dbServer->serverId,
            'farm_id'           => $dbServer->farmId,
            'farm_role_id'      => $dbServer->farmRoleId,
            'role_id'           => isset($dbRole) ? $dbRole->id : null,
            'platform'          => $dbServer->platform,
            'cloud_location'    => $dbServer->GetCloudLocation(),
            'role'              => array (
                                    'name'      => isset($dbRole) ? $dbRole->name : 'unknown',
                                    'platform'  => $dbServer->platform
                                ),
            'os'                => array(
                                    'title'  => isset($dbRole) ? $dbRole->os : 'unknown',
                                    'family' => isset($dbRole) ? $dbRole->osFamily : 'unknown'
                                ),
            'behaviors'         => isset($dbRole) ? $dbRole->getBehaviors() : array(),
            'status'            => $dbServer->status,
            'index'             => $dbServer->index,
            'local_ip'          => $dbServer->localIp,
            'remote_ip'         => $dbServer->remoteIp,
            'instType'          => PlatformFactory::NewPlatform($dbServer->platform)->GetServerFlavor($dbServer),
            'addedDate'         => Scalr_Util_DateTime::convertTz($dbServer->dateAdded),
            'excluded_from_dns' => (!$dbServer->GetProperty(SERVER_PROPERTIES::EXCLUDE_FROM_DNS) && !$r_dns) ? false : true,
            'is_locked'         => $dbServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED) ? 1 : 0,
            'cloud_server_id'   => $dbServer->GetCloudServerID()
        );

        if ($dbServer->status == SERVER_STATUS::RUNNING) {
            $rebooting = $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=? LIMIT 1", array(
                $dbServer->serverId, SERVER_PROPERTIES::REBOOTING
            ));
            if ($rebooting) {
                $data['general']['status'] = "Rebooting";
            }

            $subStatus = $dbServer->GetProperty(SERVER_PROPERTIES::SUB_STATUS);
            if ($subStatus) {
                $data['general']['status'] = ucfirst($subStatus);
            }
        }

        if ($dbServer->status == SERVER_STATUS::RUNNING && $dbServer->GetProperty(SERVER_PROPERTIES::SUB_STATUS) != 'stopped' &&
            (($dbServer->IsSupported('0.8') && $dbServer->osType == 'linux') || ($dbServer->IsSupported('0.19') && $dbServer->osType == 'windows'))) {
            try {
                $port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_UPDC_PORT);
                if (!$port) {
                    $port = 8008;
                }
                $updateClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer, $port, \Scalr::config('scalr.system.instances_connection_timeout'));
                $scalarizr = $updateClient->getStatus();
            } catch (Exception $e) {
                $oldUpdClient = stristr($e->getMessage(), "Method not found");
                $error = $e->getMessage();
            }


            if ($scalarizr) {
                $data['scalarizr'] = array(
                    'status'      => $scalarizr->service_status,
                    'version'     => $scalarizr->installed,
                    'candidate'   => $scalarizr->candidate,
                    'repository' => ucfirst($scalarizr->repository),
                    'lastUpdate'  => array(
                                        'date'   => ($scalarizr->executed_at) ? Scalr_Util_DateTime::convertTz($scalarizr->executed_at) : "",
                                        'error' => nl2br($scalarizr->error)
                                     ),
                    'nextUpdate'  => ($scalarizr->installed != $scalarizr->candidate) ? "Update to <b>{$scalarizr->candidate}</b> scheduled on <b>".Scalr_Util_DateTime::convertTz($scalarizr->scheduled_on)."</b>" : "Scalarizr is up to date",
                    'fullInfo'    => $scalarizr
                );
            } else {
                if ($oldUpdClient) {
                    $data['scalarizr'] = array('status' => 'upgradeUpdClient');
                } else {
                    $data['scalarizr'] = array(
                        'status' => 'statusNotAvailable',
                        'error' => "<span style='color:red;'>Scalarizr status is not available: {$error}</span>"
                    );
                }
            }
        }

        $internalProperties = $dbServer->GetAllProperties();
        if (!empty($internalProperties)) {
            $data['internalProperties'] = $internalProperties;
        }

        if (!$dbServer->IsSupported('0.5'))
        {
            $baseurl = $this->getContainer()->config('scalr.endpoint.scheme') . "://" .
                       $this->getContainer()->config('scalr.endpoint.host');

            $authKey = $dbServer->GetKey();
            if (!$authKey) {
                $authKey = Scalr::GenerateRandomKey(40);
                $dbServer->SetProperty(SERVER_PROPERTIES::SZR_KEY, $authKey);
            }

            $dbServer->SetProperty(SERVER_PROPERTIES::SZR_KEY_TYPE, SZR_KEY_TYPE::PERMANENT);
            $data['updateAmiToScalarizr'] = sprintf("wget " . $baseurl . "/storage/scripts/amiscripts-to-scalarizr.py && python amiscripts-to-scalarizr.py -s %s -k %s -o queryenv-url=%s -o messaging_p2p.producer_url=%s",
                $dbServer->serverId,
                $authKey,
                $baseurl . "/query-env",
                $baseurl . "/messaging"
            );
        }

        $this->response->page('ui/servers/dashboard.js', $data, array('ui/servers/actionsmenu.js', 'ui/monitoring/window.js'));
    }

    public function consoleOutputAction()
    {
        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS) && !$this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        if (! $this->getParam('serverId')) {
            throw new Exception(_('Server not found'));
        }

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $output = PlatformFactory::NewPlatform($dbServer->platform)->GetServerConsoleOutput($dbServer);

        if ($output) {
            $output = trim(base64_decode($output));
            $output = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $output);
            $output = nl2br($output);

            $output = str_replace("\033[74G", "</span>", $output);
            $output = str_replace("\033[39;49m", "</span>", $output);
            $output = str_replace("\033[80G <br />", "<span style='padding-left:20px;'></span>", $output);
            $output = str_replace("\033[80G", "<span style='padding-left:20px;'>&nbsp;</span>", $output);
            $output = str_replace("\033[31m", "<span style='color:red;'>", $output);
            $output = str_replace("\033[33m", "<span style='color:brown;'>", $output);
        } else
            $output = 'Console output not available yet';

        $this->response->page('ui/servers/consoleoutput.js', array(
            'name' => $dbServer->serverId,
            'content' => $output
        ));
    }

    public function xServerExcludeFromDnsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        if (! $this->getParam('serverId'))
            throw new Exception(_('Server not found'));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $dbServer->SetProperty(SERVER_PROPERTIES::EXCLUDE_FROM_DNS, 1);

        $zones = DBDNSZone::loadByFarmId($dbServer->farmId);
        foreach ($zones as $DBDNSZone)
        {
            $DBDNSZone->updateSystemRecords($dbServer->serverId);
            $DBDNSZone->save();
        }

        $this->response->success("Server successfully removed from DNS");
    }

    public function xServerIncludeInDnsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        if (! $this->getParam('serverId'))
            throw new Exception(_('Server not found'));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $dbServer->SetProperty(SERVER_PROPERTIES::EXCLUDE_FROM_DNS, 0);

        $zones = DBDNSZone::loadByFarmId($dbServer->farmId);
        foreach ($zones as $DBDNSZone)
        {
            $DBDNSZone->updateSystemRecords($dbServer->serverId);
            $DBDNSZone->save();
        }

        $this->response->success("Server successfully added to DNS");
    }

    public function xServerCancelAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        if (! $this->getParam('serverId'))
            throw new Exception(_('Server not found'));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $bt_id = $this->db->GetOne("
            SELECT id FROM bundle_tasks
            WHERE server_id=? AND prototype_role_id='0' AND status NOT IN (?,?,?)
            LIMIT 1
        ", array(
            $dbServer->serverId,
            SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
            SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS,
            SERVER_SNAPSHOT_CREATION_STATUS::CANCELLED
        ));

        if ($bt_id) {
            $BundleTask = BundleTask::LoadById($bt_id);
            $BundleTask->SnapshotCreationFailed("Server was cancelled before snapshot was created.");
        }

        if ($dbServer->status == SERVER_STATUS::IMPORTING) {
            $dbServer->Remove();
        } else {
            $dbServer->terminate('OPERATION_CANCELLATION', true, $this->user);
        }

        $this->response->success("Server successfully cancelled and removed from database.");
    }

    public function xServerRebootServersAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $this->request->defineParams(array(
            'servers' => array('type' => 'json')
        ));

        foreach ($this->getParam('servers') as $serverId) {
            try {
                $dbServer = DBServer::LoadByID($serverId);
                $this->user->getPermissions()->validate($dbServer);

                PlatformFactory::NewPlatform($dbServer->platform)->RebootServer($dbServer);
            }
            catch (Exception $e) {}
        }

        $this->response->success();
    }

    public function xServerTerminateServersAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $this->request->defineParams(array(
            'servers' => array('type' => 'json'),
            'descreaseMinInstancesSetting' => array('type' => 'bool'),
            'forceTerminate' => array('type' => 'bool')
        ));

        foreach ($this->getParam('servers') as $serverId) {
            $dbServer = DBServer::LoadByID($serverId);
            $this->user->getPermissions()->validate($dbServer);

            $forceTerminate = !$dbServer->isOpenstack() && !$dbServer->isCloudstack() && $this->getParam('forceTerminate');

            if ($dbServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED))
                continue;

            if (!$forceTerminate) {
                Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($dbServer->farmId,
                    sprintf("Scheduled termination for server %s (%s). It will be terminated in 3 minutes.",
                        $dbServer->serverId,
                        $dbServer->remoteIp ? $dbServer->remoteIp : $dbServer->localIp
                    )
                ));
            }

            $dbServer->terminate(array('MANUALLY', $this->user->fullname), (bool)$forceTerminate, $this->user);
        }

        if ($this->getParam('descreaseMinInstancesSetting')) {
            $servers = $this->getParam('servers');
            $dbServer = DBServer::LoadByID($servers[0]);
            $dbFarmRole = $dbServer->GetFarmRoleObject();

            $minInstances = $dbFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_MIN_INSTANCES);
            if ($minInstances > count($servers)) {
                $dbFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_MIN_INSTANCES,
                    $minInstances - count($servers),
                    DBFarmRole::TYPE_LCL
                );
            } else {
                $dbFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_MIN_INSTANCES, 1, DBFarmRole::TYPE_CFG);
            }
        }

        $this->response->success();
    }

    public function xServerGetLaAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        if (!$dbServer->IsSupported('0.13.0')) {
            $la = "Unknown";
        } else {
            if ($dbServer->osType == 'linux') {
                try {
                    $szrClient = Scalr_Net_Scalarizr_Client::getClient(
                        $dbServer,
                        Scalr_Net_Scalarizr_Client::NAMESPACE_SYSTEM,
                        $dbServer->getPort(DBServer::PORT_API)
                    );

                    $la = $szrClient->loadAverage();
                    if ($la[0] !== null && $la[0] !== false)
                        $la = number_format($la[0], 2);
                    else
                        $la = "Unknown";
                } catch (Exception $e) {
                    $la = "Unknown";
                }
            } else
                $la = "Not available";
        }

        $this->response->data(array('la' => $la));
    }

    public function createSnapshotAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        if (!$this->getParam('serverId'))
            throw new Exception(_('Server not found'));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $dbFarmRole = $dbServer->GetFarmRoleObject();

        if ($dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
            $this->response->warning("You are about to synchronize MySQL instance. The bundle will not include DB data. <a href='#/dbmsr/status?farmId={$dbServer->farmId}&type=mysql'>Click here if you wish to bundle and save DB data</a>.");

            if (!$dbServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER)) {
                $dbSlave = true;
            }
        }

        $dbMsrBehavior = $dbFarmRole->GetRoleObject()->getDbMsrBehavior();
        if ($dbMsrBehavior) {
            $this->response->warning("You are about to synchronize DB instance. The bundle will not include DB data. <a href='#/dbmsr/status?farmId={$dbServer->farmId}&type={$dbMsrBehavior}'>Click here if you wish to bundle and save DB data</a>.");

            if (!$dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER)) {
                $dbSlave = true;
            }
        }

        //Check for already running bundle on selected instance
        $chk = $this->db->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND status NOT IN ('success', 'failed') LIMIT 1",
            array($dbServer->serverId)
        );

        if ($chk)
            throw new Exception(sprintf(_("This server is already synchonizing. <a href='#/bundletasks/%s/logs'>Check status</a>."), $chk));

        if (!$dbServer->IsSupported("0.2-112"))
            throw new Exception(sprintf(_("You cannot create snapshot from selected server because scalr-ami-scripts package on it is too old.")));

        //Check is role already synchronizing...
        $chk = $this->db->GetOne("SELECT server_id FROM bundle_tasks WHERE prototype_role_id=? AND status NOT IN ('success', 'failed') LIMIT 1", array(
            $dbServer->roleId
        ));

        if ($chk && $chk != $dbServer->serverId) {
            try {
                $bDBServer = DBServer::LoadByID($chk);
            }
            catch(Exception $e) {}

            if ($bDBServer->farmId == $dbServer->farmId)
                throw new Exception(sprintf(_("This role is already synchonizing. <a href='#/bundletasks/%s/logs'>Check status</a>."), $chk));
        }

        $roleName = $dbServer->GetFarmRoleObject()->GetRoleObject()->name;
        $this->response->page('ui/servers/createsnapshot.js', array(
            'serverId' 	=> $dbServer->serverId,
            'platform'	=> $dbServer->platform,
            'dbSlave'	=> $dbSlave,
            'isVolumeSizeSupported'=> (int)$dbServer->IsSupported('0.7'),
            'farmId' => $dbServer->farmId,
            'farmName' => $dbServer->GetFarmObject()->Name,
            'roleName' => $roleName,
            'replaceNoReplace' => "<b>DO NOT REPLACE</b> any roles on any farms, just create new one.</td>",
            'replaceFarmReplace' => "Replace role '{$roleName}' with new one <b>ONLY</b> on current farm '{$dbServer->GetFarmObject()->Name}'</td>",
            'replaceAll' => "Replace role '{$roleName}' with new one on <b>ALL MY FARMS</b> <span style=\"font-style:italic;font-size:11px;\">(You will be able to bundle role with the same name. Old role will be renamed.)</span></td>"
        ));
    }

    public function xServerCreateSnapshotAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $this->request->defineParams(array(
            'rootVolumeSize' => array('type' => 'int')
        ));

        if (! $this->getParam('serverId'))
            throw new Exception(_('Server not found'));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $err = array();

        if (strlen($this->getParam('roleName')) < 3)
            $err[] = _("Role name should be greater than 3 chars");

        if (! preg_match("/^[A-Za-z0-9-]+$/si", $this->getParam('roleName')))
            $err[] = _("Role name is incorrect");

        $roleinfo = $this->db->GetRow("SELECT * FROM roles WHERE name=? AND (env_id=? OR env_id='0') LIMIT 1", array($this->getParam('roleName'), $dbServer->envId, $dbServer->roleId));
        if ($this->getParam('replaceType') != SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
            if ($roleinfo)
                $err[] = _("Specified role name is already used by another role. You can use this role name only if you will replace old on on ALL your farms.");
        } else {
            if ($roleinfo && $roleinfo['env_id'] == 0)
                $err[] = _("Selected role name is reserved and cannot be used for custom role");
        }

        //Check for already running bundle on selected instance
        $chk = $this->db->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND status NOT IN ('success', 'failed') LIMIT 1",
            array($dbServer->serverId)
        );

        if ($chk)
            $err[] = sprintf(_("Server '%s' is already synchonizing."), $dbServer->serverId);

        //Check is role already synchronizing...
        $chk = $this->db->GetOne("SELECT server_id FROM bundle_tasks WHERE prototype_role_id=? AND status NOT IN ('success', 'failed') LIMIT 1", array(
            $dbServer->roleId
        ));

        if ($chk && $chk != $dbServer->serverId) {
            try	{
                $bDBServer = DBServer::LoadByID($chk);
                if ($bDBServer->farmId == $DBServer->farmId)
                    $err[] = sprintf(_("Role '%s' is already synchonizing."), $dbServer->GetFarmRoleObject()->GetRoleObject()->name);
            } catch(Exception $e) {}
        }

        if ($dbServer->GetFarmRoleObject()->NewRoleID)
            $err[] = sprintf(_("Role '%s' is already synchonizing."), $dbServer->GetFarmRoleObject()->GetRoleObject()->name);

        if (count($err))
            throw new Exception(nl2br(implode('\n', $err)));

        $ServerSnapshotCreateInfo = new ServerSnapshotCreateInfo(
            $dbServer,
            $this->getParam('roleName'),
            $this->getParam('replaceType'),
            false,
            $this->getParam('roleDescription'),
            $this->getParam('rootVolumeSize'),
            $this->getParam('noServersReplace') == 'on' ? true : false
        );
        $BundleTask = BundleTask::Create($ServerSnapshotCreateInfo);

        $BundleTask->createdById = $this->user->id;
        $BundleTask->createdByEmail = $this->user->getEmail();

        $protoRole = DBRole::loadById($dbServer->roleId);
        if (in_array($protoRole->osFamily, array('redhat', 'oel', 'scientific')) &&
        $dbServer->platform == SERVER_PLATFORMS::EC2) {
            $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
        }

        $BundleTask->save();


        $this->response->success("Bundle task successfully created. <a href='#/bundletasks/{$BundleTask->id}/logs'>Click here to check status.</a>");
    }
}
