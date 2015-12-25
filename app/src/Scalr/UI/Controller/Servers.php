<?php

use Scalr\Acl\Acl;
use Scalr\Exception\Http\NotFoundException;
use Scalr\Model\Entity;
use Scalr\Model\Entity\SshKey;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Openstack\Helpers\OpenstackHelper;
use Scalr\Service\Aws\Ec2\DataType\InstanceAttributeType;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;
use Scalr\Util\CryptoTool;
use Scalr\UI\Request\JsonData;
use Scalr\Model\Entity\Server;
use Scalr\Model\Entity\Image;
use Scalr\DataType\ScopeInterface;

class Scalr_UI_Controller_Servers extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'serverId';

    /**
     * Check if at least one of given behaviors is database one
     *
     * @param   array   $behaviors  List of behaviors
     * @return  bool    Return true if at least one of given behaviors is database one
     */
    private function hasDatabaseBehavior($behaviors)
    {
        $dbBehaviors = [
            \ROLE_BEHAVIORS::REDIS,
            \ROLE_BEHAVIORS::POSTGRESQL,
            \ROLE_BEHAVIORS::MYSQL,
            \ROLE_BEHAVIORS::MYSQL2,
            \ROLE_BEHAVIORS::PERCONA,
            \ROLE_BEHAVIORS::MARIADB,
            \ROLE_BEHAVIORS::MONGODB
        ];

        return !empty(array_intersect($dbBehaviors, $behaviors));
    }

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

    public function downloadScalarizrDebugLogAction()
    {
        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $fileName = "scalarizr_debug-{$dbServer->serverId}.log";
        $retval = base64_decode($dbServer->scalarizr->system->getDebugLog());

        $this->response->setHeader('Pragma', 'private');
        $this->response->setHeader('Cache-control', 'private, must-revalidate');
        $this->response->setHeader('Content-type', 'plain/text');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="'.$fileName.'"');
        $this->response->setHeader('Content-Length', strlen($retval));

        $this->response->setResponse($retval);
    }

    public function xLockAction()
    {
        $this->request->defineParams(array(
            'serverId'
        ));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        if ($dbServer->platform != SERVER_PLATFORMS::EC2)
            throw new Exception("Server lock supported ONLY by EC2");

        $env = Scalr_Environment::init()->loadById($dbServer->envId);
        $ec2 = $env->aws($dbServer->GetCloudLocation())->ec2;

        if ($dbServer->GetRealStatus(true)->isTerminated()) {
            $dbServer->SetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED, 0);
            $this->response->warning('Server was terminated. Clearing disableAPITermination flag.');
        } else {
            $newValue = !$ec2->instance->describeAttribute($dbServer->GetCloudServerID(), InstanceAttributeType::disableApiTermination());

            $ec2->instance->modifyAttribute(
                $dbServer->GetCloudServerID(),
                InstanceAttributeType::disableApiTermination(),
                $newValue
            );

            $dbServer->SetProperties([
                EC2_SERVER_PROPERTIES::IS_LOCKED_LAST_CHECK_TIME => 0,
                EC2_SERVER_PROPERTIES::IS_LOCKED => $newValue
            ]);

            $this->response->success();
        }
    }

    /**
     * Retrieve password for a Windows machine
     *
     * @param  string $serverId
     * @throws Exception
     */
    public function xGetWindowsPasswordAction($serverId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SECURITY_RETRIEVE_WINDOWS_PASSWORDS);

        $password = $encPassword = null;

        $dbServer = DBServer::LoadByID($serverId);
        $this->user->getPermissions()->validate($dbServer);

        if ($dbServer->platform == SERVER_PLATFORMS::EC2) {
            $env = Scalr_Environment::init()->loadById($dbServer->envId);
            $ec2 = $env->aws($dbServer->GetCloudLocation())->ec2;

            $encPassword = $ec2->instance->getPasswordData($dbServer->GetCloudServerID());
            $encPassword = str_replace('\/', '/', trim($encPassword->passwordData));
        } elseif ($dbServer->platform == SERVER_PLATFORMS::AZURE) {
            $password = $dbServer->GetProperty(\AZURE_SERVER_PROPERTIES::ADMIN_PASSWORD);
        } elseif ($dbServer->platform == SERVER_PLATFORMS::GCE) {
            $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
            /* @var $client Google_Service_Compute */
            $client = $platform->getClient($this->environment, $this->getParam('cloudLocation'));
            $ccProps = $this->environment->cloudCredentials(SERVER_PLATFORMS::GCE)->properties;

            /* @var $info Google_Service_Compute_Instance */
            $info = $client->instances->get(
                $ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $dbServer->cloudLocation,
                $dbServer->serverId
            );

            // More info about following code is available here:
            // https://cloud.google.com/compute/docs/instances/windows-old-auth
            //
            // Check GCE agent version
            $serialPort = $client->instances->getSerialPortOutput(
                $ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $dbServer->cloudLocation,
                $dbServer->serverId
            );
            $serialPortContents = $serialPort->getContents();

            preg_match("/GCE Agent started( \(version ([0-9\.]+)\))?\./", $serialPortContents, $matches);
            $agentVersion = (count($matches) > 1) ? (int)str_replace('.', '', $matches[2]) : 0;

            // New stuff is supported from version 3.0.0.0
            if ($agentVersion > 3000) {
                // NEW GCE AGENT

                // Get SSH key
                $config = array(
                    "digest_alg" => "sha512",
                    "private_key_bits" => 2048,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                );
                $key = openssl_pkey_new($config);
                $details = openssl_pkey_get_details($key);

                $userObject = [
                    'userName' => 'scalr',
                    'modulus' => base64_encode($details['rsa']['n']),
                    'exponent' => base64_encode($details['rsa']['e']),
                    'email' => $ccProps[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME],
                    'expireOn' => date("c", strtotime("+10 minute"))
                ];

                /* @var $meta Google_Service_Compute_Metadata */
                $meta = $info->getMetadata();
                $found = false;

                /* @var $item \Google_Service_Compute_MetadataItems */
                foreach ($meta as $item) {
                    if ($item->getKey() === "windows-keys") {
                        $item->setValue(json_encode($userObject, JSON_FORCE_OBJECT));

                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $item = new \Google_Service_Compute_MetadataItems();
                    $item->setKey("windows-keys");
                    $item->setValue(json_encode($userObject, JSON_FORCE_OBJECT));

                    $meta[count($meta)] = $item;
                }

                $client->instances->setMetadata(
                    $ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                    $dbServer->cloudLocation,
                    $dbServer->serverId,
                    $meta
                );

                //Monitor serial port #4
                for ($i = 0; $i < 10; $i++) {

                    $serialPortInfo = $client->instances->getSerialPortOutput(
                        $ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                        $dbServer->cloudLocation,
                        $dbServer->serverId,
                        ['port' => 4]
                    );

                    $lines = explode("\n", $serialPortInfo->getContents());
                    foreach ($lines as $line) {
                        $obj = json_decode(trim($line));

                        if (isset($obj->modulus) && $obj->modulus == $userObject['modulus']) {
                            $encPassword = base64_decode($obj->encryptedPassword);
                            break;
                        }
                    }

                    if ($encPassword)
                        break;

                    sleep(2);
                }

                if ($encPassword) {
                    openssl_private_decrypt($encPassword, $password, $key, OPENSSL_PKCS1_OAEP_PADDING);
                    $encPassword = null;
                } else {
                    throw new Exception("Windows password is not available yet. Please try again in couple minutes.");
                }

            } else {

                // OLD GCE AGENT
                foreach ($info->getMetadata() as $meta) {
                    /* @var $meta Google_Service_Compute_MetadataItems */
                    if ($meta->getKey() == 'gce-initial-windows-password') {
                        $password = $meta->getValue();
                        break;
                    }
                }
            }

        } elseif (PlatformFactory::isOpenstack($dbServer->platform)) {
            if (in_array($dbServer->platform, array(SERVER_PLATFORMS::RACKSPACENG_UK, SERVER_PLATFORMS::RACKSPACENG_US))) {
                $password = $dbServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::ADMIN_PASS);
            } else {
                $env = Scalr_Environment::init()->loadById($dbServer->envId);
                $os = $env->openstack($dbServer->platform, $dbServer->GetCloudLocation());

                //TODO: Check is extension supported
                $encPassword = trim($os->servers->getEncryptedAdminPassword($dbServer->GetCloudServerID()));
            }
        } else
            throw new Exception("Requested operation is supported by '{$dbServer->platform}' cloud");

        if ($encPassword) {
            try {
                $sshKey = (new SshKey())->loadGlobalByFarmId($dbServer->envId, $dbServer->platform, $dbServer->GetCloudLocation(), $dbServer->farmId);
                $password = CryptoTool::opensslDecrypt(base64_decode($encPassword), $sshKey->privateKey);
            } catch (Exception $e) {
                //Do nothing. Error already handled in UI (If no password returned)
            }
        }

        $this->response->data(array('password' => $password, 'encodedPassword' => $encPassword));
    }

    public function xGetStorageDetailsAction()
    {
        $dbServer = DBServer::LoadByID($this->getParam('serverId'));

        if (!$this->user->getPermissions()->hasReadOnlyAccessServer($dbServer)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        try {
            if ($dbServer->IsSupported('2.5.4')) {
                $info = $dbServer->scalarizr->system->mounts();
            } else {
                if ($dbServer->GetFarmRoleObject()->GetRoleObject()->getOs()->family == 'windows') {
                    $storages = array('C' => array());
                } else {
                    $storages = array('/' => array());
                    $storageConfigs = $dbServer->GetFarmRoleObject()->getStorage()->getVolumes($dbServer->index);
                    foreach ($storageConfigs as $config) {
                        $config = $config[$dbServer->index];

                        $storages[$config->config->mpoint] = array();
                    }
                }

                $info = $dbServer->scalarizr->system->statvfs(array_keys($storages));
            }

            $this->response->data([
                'data' => $info,
                'status' => 'available'
            ]);
        } catch (Exception $e) {
            $this->response->data([
                'status' => 'notAvailable',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function xGetHealthDetailsAction()
    {
        $dbServer = DBServer::LoadByID($this->getParam('serverId'));

        if (!$this->user->getPermissions()->hasReadOnlyAccessServer($dbServer)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $data = array();

        try {
            if ($dbServer->osType == 'linux') {
                $la = $dbServer->scalarizr->system->loadAverage();
                $data['la'] = number_format($la[0], 2);
            }
        } catch (Exception $e) {}

        try {
            $mem = $dbServer->scalarizr->system->memInfo();
            $data['memory'] = array('total' => round($mem->total_real / 1024 / 1024, 1), 'free' => round(($mem->total_free+$mem->cached) / 1024 / 1024, 1));
        } catch (Exception $e) {}

        try {
            if ($dbServer->osType == 'windows') {
                $cpu = $dbServer->scalarizr->system->cpuStat();
            } else {
                $cpu1 = $dbServer->scalarizr->system->cpuStat();
                sleep(1);
                $cpu2 = $dbServer->scalarizr->system->cpuStat();

                $dif['user'] = $cpu2->user - $cpu1->user;
                $dif['nice'] = $cpu2->nice - $cpu1->nice;
                $dif['sys'] =  $cpu2->system - $cpu1->system;
                $dif['idle'] = $cpu2->idle - $cpu1->idle;
                $total = array_sum($dif);
                foreach($dif as $x=>$y)
                    $cpu[$x] = $total != 0 ? round($y / $total * 100, 1) : 0;
            }

            $data['cpu'] = $cpu;
        } catch (Exception $e) {}

        $this->response->data(array('data' => $data));
    }

    public function xResendMessageAction()
    {
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
                $this->db->Execute(
                    "UPDATE messages SET status=?, handle_attempts='0' WHERE messageid = ? AND server_id = ?",
                    [MESSAGE_STATUS::PENDING, $message['messageid'], $message['server_id']]
                );
                $dbServer->SendMessage($msg);
            }
            else
                throw new Exception("Scalr unable to re-send message. Server should be in running state.");

            $this->response->success('Message successfully re-sent to the server');
        } else {
            throw new Exception("Message not found");
        }
    }

    /**
     * @param string $serverId
     * @throws Scalr_UI_Exception_NotFound
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xListMessagesAction($serverId)
    {
        $dbServer = DBServer::LoadByID($serverId);
        $this->user->getPermissions()->validate($dbServer);

        $sql = "SELECT *, message_name as message_type FROM messages WHERE server_id = ? AND :FILTER:";
        $args = [$dbServer->serverId];

        $response = $this->buildResponseFromSql2($sql, ['dtadded', 'messageid', 'event_server_id', 'handle_attempts', 'handle_attempts', 'dtlasthandleattempt'], ['server_id', 'message', 'messageid'], $args);

        foreach ($response["data"] as &$row) {

            if (!$row['message_type']) {
                preg_match("/^<\?xml [^>]+>[^<]*<message(.*?)name=\"([A-Za-z0-9_]+)\"/si", $row['message'], $matches);
                $row['message_type'] = $matches[2];
            }

            $row['message'] = '';
            $row['dtlasthandleattempt'] = Scalr_Util_DateTime::convertTz($row['dtlasthandleattempt']);
            if ($row['handle_attempts'] == 0 && $row['status'] == 1)
                $row['handle_attempts'] = 1;
        }

        $this->response->data($response);
    }

    public function messagesAction()
    {
        if (!$this->request->isFarmAllowed(null, Acl::PERM_FARMS_SERVERS) && !$this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        $this->response->page('ui/servers/messages.js', array('serverId' => $this->getParam('serverId')));
    }

    public function viewAction()
    {
        if (!$this->request->isFarmAllowed() && !$this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        $this->response->page('ui/servers/view.js', array(
            'mindtermEnabled' => \Scalr::config('scalr.ui.mindterm_enabled')
        ), array('ui/servers/actionsmenu.js'), array('ui/servers/view.css'));
    }

    /**
     * @param   string  $serverId
     */
    public function sshConsoleAction($serverId)
    {
        $dbServer = DBServer::LoadByID($serverId);
        $this->user->getPermissions()->validate($dbServer);

        $this->response->page('ui/servers/sshconsole.js', $this->getSshConsoleSettings($dbServer));
    }

    /**
     * @param   string  $serverId
     */
    public function getSshConsoleSettingsAction($serverId)
    {
        $dbServer = DBServer::LoadByID($serverId);
        $this->user->getPermissions()->validate($dbServer);

        $this->response->data(['settings' => $this->getSshConsoleSettings($dbServer)]);
    }

    public function xServerCancelOperationAction()
    {
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
            $dbServer->terminate(DBServer::TERMINATE_REASON_SNAPSHOT_CANCELLATION, true, $this->user);
            if (PlatformFactory::isOpenstack($dbServer->platform)) {
                OpenstackHelper::removeServerFloatingIp($dbServer);
            }
        }

        $this->response->success("Server was successfully canceled");
    }

    /**
     * @internal Filter an array by values
     * @param array  $array Input array to filter
     * @param string $value Filter
     * @return array
     */
    private function propertyFilter($array, $value)
    {
        return array_filter($array, function($a) use ($value) { return $a === $value; });
    }

    /**
     * Helper for the server lister
     *
     * @param array $response Reference to a response array
     */
    private function listServersResponseHelper(&$response)
    {
        if (empty($response["data"])) {
            return;
        }

        $serverIds = [];
        $farmRoles = [];
        $userBelongsToTeam = [];

        foreach ($response["data"] as $idx => $row) {
            $serverIds[$row["server_id"]][$idx] = [];
            $farmRoles[$row["farm_roleid"]][$idx] = [];
        }

        $neededServerProperties = [
            // cloud_server_id
            \GCE_SERVER_PROPERTIES::SERVER_NAME      => "cloud_server_id",
            \OPENSTACK_SERVER_PROPERTIES::SERVER_ID  => "cloud_server_id",
            \CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID => "cloud_server_id",
            \EC2_SERVER_PROPERTIES::INSTANCE_ID      => "cloud_server_id",
            \RACKSPACE_SERVER_PROPERTIES::SERVER_ID  => "cloud_server_id",
            \AZURE_SERVER_PROPERTIES::SERVER_NAME    => "cloud_server_id",
            // hostname
            \Scalr_Role_Behavior::SERVER_BASE_HOSTNAME => "hostname",
            // cluster_role
            Entity\Server::DB_MYSQL_MASTER      => "cluster_role",
            \Scalr_Db_Msr::REPLICATION_MASTER   => "cluster_role",

            \EC2_SERVER_PROPERTIES::AVAIL_ZONE                => "avail_zone",
            // cloud_location
            \EC2_SERVER_PROPERTIES::REGION                => "cloud_location",
            \OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION  => "cloud_location",
            \CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => "cloud_location",
            \GCE_SERVER_PROPERTIES::CLOUD_LOCATION        => "cloud_location",
            \RACKSPACE_SERVER_PROPERTIES::DATACENTER      => "cloud_location",
            \AZURE_SERVER_PROPERTIES::CLOUD_LOCATION      => "cloud_location",
            // cluster_position
            \Scalr_Role_Behavior_MongoDB::SERVER_SHARD_INDEX       => "cluster_position",
            \Scalr_Role_Behavior_MongoDB::SERVER_REPLICA_SET_INDEX => "cluster_position",
            // status
            Entity\Server::REBOOTING => "status",
            Entity\Server::MISSING   => "status",
            // is_locked
            \EC2_SERVER_PROPERTIES::IS_LOCKED => "is_locked",
            // szr_version (is_szr)
            Entity\Server::SZR_VESION => "szr_version",
            // isInitFailed
            Entity\Server::SZR_IS_INIT_FAILED => "isInitFailed",
            // launch_error
            Entity\Server::LAUNCH_ERROR => "launch_error",
            // excluded_from_dns
            Entity\Server::EXCLUDE_FROM_DNS => "excluded_from_dns"
        ];

        $neededFarmRoleSettings = [
            \Scalr_Db_Msr::SLAVE_TO_MASTER,
            Entity\FarmRoleSetting::MYSQL_SLAVE_TO_MASTER,
            Entity\FarmRoleSetting::EXCLUDE_FROM_DNS,
            Entity\FarmRoleSetting::SCALING_ENABLED,
        ];

        // get necessary properties
        foreach (Entity\Server\Property::fetch(array_keys($serverIds), array_keys($neededServerProperties)) as $prop) {
            foreach ($serverIds[$prop->serverId] as $idx => &$upd) {
                $upd[$prop->name] = $prop->value;
            }
        }

        // get farm role settings
        foreach (Entity\FarmRoleSetting::fetch(array_keys($farmRoles), $neededFarmRoleSettings) as $prop) {
            foreach ($farmRoles[$prop->farmRoleId] as $idx => &$upd) {
                $upd[$prop->name] = $prop->value;
            }
        }

        // check elastic IP existence
        foreach (Entity\Server\ElasticIp::checkPresenceOfPublicIP(array_keys($serverIds)) as $resRow) {
            foreach ($serverIds[$resRow["server_id"]] as $idx => &$upd) {
                $upd["extIp"] = $resRow["ipc"];
            }
        }

        // check alerts
        foreach (Entity\Server\Alert::checkPresenceOfAlerts(array_keys($serverIds)) as $resRow) {
            foreach ($serverIds[$resRow["server_id"]] as $idx => &$upd) {
                $upd["alerts"] = $resRow["alerts"];
            }
        }

        foreach ($response["data"] as $idx => &$row) {
            $status = $row["status"];
            $behaviors = explode(",", $row["behaviors"]);
            $row["hostname"] = $row['cluster_role'] = $row["alerts"] = "";

            $cloudServerIds = [];
            foreach ($this->propertyFilter($neededServerProperties, "cloud_server_id") as $prop => $key) {
                if (array_key_exists($prop, $serverIds[$row["server_id"]][$idx])) {
                    $cloudServerIds[] = $serverIds[$row["server_id"]][$idx][$prop];
                }
            }
            $row['cloud_server_id'] = empty($cloudServerIds) ? "" : $cloudServerIds[0];

            if (array_key_exists(\Scalr_Role_Behavior::SERVER_BASE_HOSTNAME, $serverIds[$row["server_id"]][$idx])) {
                $row["hostname"] = $serverIds[$row["server_id"]][$idx][\Scalr_Role_Behavior::SERVER_BASE_HOSTNAME];
            }

            $row['flavor'] = $row['type'];

            if (in_array($status, [Entity\Server::STATUS_RUNNING, Entity\Server::STATUS_INIT])) {
                $hasDbBehavior = array_intersect([
                    \ROLE_BEHAVIORS::REDIS,
                    \ROLE_BEHAVIORS::POSTGRESQL,
                    \ROLE_BEHAVIORS::MYSQL,
                    \ROLE_BEHAVIORS::MYSQL2,
                    \ROLE_BEHAVIORS::PERCONA,
                    \ROLE_BEHAVIORS::MARIADB,
                ], $behaviors);
                if (! empty($hasDbBehavior)) {
                    $isMaster = false;
                    foreach ($this->propertyFilter($neededServerProperties, "cluster_role") as $prop => $key) {
                        if (array_key_exists($prop, $serverIds[$row["server_id"]][$idx]) && $serverIds[$row["server_id"]][$idx][$prop] != 0) {
                            $isMaster = true;
                            break;
                        }
                    }
                    $row['cluster_role'] = $isMaster ? 'Master' : 'Slave';

                    if ($isMaster &&
                        (array_key_exists(\Scalr_Db_Msr::SLAVE_TO_MASTER, $farmRoles[$row["farm_roleid"]][$idx]) &&
                        $farmRoles[$row["farm_roleid"]][$idx][\Scalr_Db_Msr::SLAVE_TO_MASTER] == 1) ||
                        (array_key_exists(Entity\FarmRoleSetting::MYSQL_SLAVE_TO_MASTER, $farmRoles[$row["farm_roleid"]][$idx]) &&
                        $farmRoles[$row["farm_roleid"]][$idx][Entity\FarmRoleSetting::MYSQL_SLAVE_TO_MASTER] == 1)) {
                            $row['cluster_role'] = 'Promoting';
                    }
                }

                $row['suspendHidden'] = $this->hasDatabaseBehavior($behaviors);

                /* @var $image Image */
                $row['suspendEc2Locked'] = ($row['platform'] == SERVER_PLATFORMS::EC2) &&
                    ($image = Image::findOne([
                        ['platform'      => $row['platform']],
                        ['cloudLocation' => $row['cloud_location']],
                        ['id'            => $row['image_id']],
                        ['$or'           => [['accountId' => null], ['accountId' => $row['client_id']]]],
                        ['$or'           => [['envId' => null], ['envId' => $row['env_id']]]]
                    ])) &&
                    $image->isEc2InstanceStoreImage();
            }

            $cloudLocations = [];
            foreach ($this->propertyFilter($neededServerProperties, "cloud_location") as $prop => $key) {
                if (array_key_exists($prop, $serverIds[$row["server_id"]][$idx])) {
                    $cloudLocations[] = $serverIds[$row["server_id"]][$idx][$prop];
                }
            }
            $row['cloud_location'] = empty($cloudLocations) ? null : $cloudLocations[0];

            if ($row["platform"] === \SERVER_PLATFORMS::EC2 && array_key_exists(\EC2_SERVER_PROPERTIES::AVAIL_ZONE, $serverIds[$row["server_id"]][$idx])) {
                $loc = $serverIds[$row["server_id"]][$idx][\EC2_SERVER_PROPERTIES::AVAIL_ZONE];

                if ($loc && $loc != 'x-scalr-diff') {
                    $row['cloud_location'] .= "/".substr($loc, -1, 1);
                }

                $row['has_eip'] = array_key_exists("extIp", $serverIds[$row["server_id"]][$idx]) &&
                                  $serverIds[$row["server_id"]][$idx]["extIp"] > 0;
            }

            if (in_array(\ROLE_BEHAVIORS::MONGODB, $behaviors)) {
                $shardIndex = $serverIds[$row["server_id"]][$idx][\Scalr_Role_Behavior_MongoDB::SERVER_SHARD_INDEX];
                $replicaSetIndex = $serverIds[$row["server_id"]][$idx][\Scalr_Role_Behavior_MongoDB::SERVER_REPLICA_SET_INDEX];
                $row['cluster_position'] = $shardIndex . "-" . $replicaSetIndex;
            }

            if (in_array($status, [Entity\Server::STATUS_RUNNING, Entity\Server::STATUS_SUSPENDED])) {
                if (array_key_exists(Entity\Server::REBOOTING, $serverIds[$row["server_id"]][$idx]) && $serverIds[$row["server_id"]][$idx][Entity\Server::REBOOTING] != 0) {
                     $row["status"] = "Rebooting";
                }
                if (array_key_exists(Entity\Server::MISSING, $serverIds[$row["server_id"]][$idx]) && $serverIds[$row["server_id"]][$idx][Entity\Server::MISSING] != 0) {
                     $row["status"] = "Missing";
                }
            }

            $row['agent_version'] = $serverIds[$row["server_id"]][$idx][Entity\Server::SZR_VESION];
            $agentVersion = Entity\Server::versionInfo($row['agent_version']);

            $row['is_locked'] = array_key_exists(\EC2_SERVER_PROPERTIES::IS_LOCKED, $serverIds[$row["server_id"]][$idx]) &&
                                $serverIds[$row["server_id"]][$idx][\EC2_SERVER_PROPERTIES::IS_LOCKED] != 0 ? 1 : 0;

            $row['is_szr'] = $agentVersion >= Entity\Server::versionInfo("0.5");
            $row['initDetailsSupported'] = $agentVersion >= Entity\Server::versionInfo("0.7.181");

            if (array_key_exists(Entity\Server::SZR_IS_INIT_FAILED, $serverIds[$row["server_id"]][$idx]) &&
                $serverIds[$row["server_id"]][$idx][Entity\Server::SZR_IS_INIT_FAILED] == 1 &&
                in_array($status, [Entity\Server::STATUS_INIT, Entity\Server::STATUS_PENDING])) {
                    $row['isInitFailed'] = 1;
            }

            if (array_key_exists(Entity\Server::LAUNCH_ERROR, $serverIds[$row["server_id"]][$idx]) && strlen($serverIds[$row["server_id"]][$idx][Entity\Server::LAUNCH_ERROR]) > 0) {
                $row['launch_error'] = "1";
            }

            $row['isScalarized'] = $row["is_scalarized"];

            $row['agent_update_needed'] = $agentVersion >= Entity\Server::versionInfo("0.7") &&
                                          $agentVersion  < Entity\Server::versionInfo("0.7.189");
            $row['agent_update_manual'] = $agentVersion  < Entity\Server::versionInfo("0.5");

            $row['os_family'] = $row["os_type"];

            $flavors = [];

            foreach ($this->propertyFilter($neededServerProperties, "flavor") as $prop => $key) {
                if (array_key_exists($prop, $serverIds[$row["server_id"]][$idx])) {
                    $flavors[] = $serverIds[$row["server_id"]][$idx][$prop];
                }
            }

            $row["flavor"] = empty($flavors) ? "" : $flavors[0];

            if (array_key_exists("alerts", $serverIds[$row["server_id"]][$idx])) {
                $row["alerts"] = $serverIds[$row["server_id"]][$idx]["alerts"];
            }

            if ($status === Entity\Server::STATUS_RUNNING) {
                $row['uptime'] = \Scalr_Util_DateTime::getHumanReadableTimeout(time() - strtotime($row['uptime']), false);
            } else {
                $row['uptime'] = '';
            }

            $row['excluded_from_dns'] = !
                (!(array_key_exists(Entity\Server::EXCLUDE_FROM_DNS, $serverIds[$row["server_id"]][$idx]) &&
                $serverIds[$row["server_id"]][$idx][Entity\Server::EXCLUDE_FROM_DNS] == 1) &&
                !(array_key_exists(Entity\FarmRoleSetting::EXCLUDE_FROM_DNS, $farmRoles[$row["farm_roleid"]][$idx]) &&
                $farmRoles[$row["farm_roleid"]][$idx][Entity\FarmRoleSetting::EXCLUDE_FROM_DNS] == 1));

            $row['scalingEnabled'] =
                (array_key_exists(Entity\FarmRoleSetting::SCALING_ENABLED, $farmRoles[$row["farm_roleid"]][$idx]) &&
                $farmRoles[$row["farm_roleid"]][$idx][Entity\FarmRoleSetting::SCALING_ENABLED] == 1);

            if ($row['farmTeamId']) {
                if (! isset($userBelongsToTeam[$row['farmTeamId']])) {
                    $userBelongsToTeam[$row['farmTeamId']] = $this->user->isInTeam($row['farmTeamId']);
                }
                $row['farmTeamIdPerm'] = $userBelongsToTeam[$row['farmTeamId']];
            }

            $row['farmOwnerIdPerm'] = $row['farmOwnerId'] && $this->user->getId() == $row['farmOwnerId'];
        }
    }

    /**
     * Get list of servers
     *
     * @param string  $cloudServerId       optional Cloud server ID
     * @param string  $cloudServerLocation optional Cloud server location
     * @param string  $hostname            optional Hostname
     * @param int     $farmId              optional Farm ID
     * @param int     $farmRoleId          optional Farm role ID
     * @param int     $roleId              optional Role ID
     * @param string  $serverId            optional Server ID
     * @param string  $imageId             optional Image ID
     * @param boolean $showTerminated      optional Whether to show terminated servers as well
     * @param string  $uptime              optional Uptime
     * @throws \Scalr_Exception_InsufficientPermissions
     */
    public function xListServersAction(
        $cloudServerId       = null,
        $cloudServerLocation = null,
        $hostname            = null,
        $farmId              = null,
        $farmRoleId          = null,
        $roleId              = null,
        $serverId            = null,
        $imageId             = null,
        $showTerminated      = null,
        $uptime              = null
    ) {
        if (!$this->request->isFarmAllowed() && !$this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
            throw new \Scalr_Exception_InsufficientPermissions();
        }

        $sortParamsLoad = '';
        foreach ($this->getSortOrder() as $param) {
            if ($param['property'] == 'remote_ip_int' || $param['property'] == 'local_ip_int') {
                $sortParamsLoad .= ", INET_ATON(servers." . substr($param['property'], 0, -4) . ") AS " . $param['property'];
            }
        }

        $stmFrom = "SELECT servers.*,
                       farms.name AS farm_name,
                       roles.name AS role_name,
                       roles.behaviors AS behaviors,
                       farm_roles.alias AS role_alias,
                       farms.created_by_id AS farmOwnerId,
                       farms.team_id AS farmTeamId,
                       servers.dtinitialized AS uptime,
                       ste.last_error AS termination_error{$sortParamsLoad}
                FROM servers
                LEFT JOIN farms ON servers.farm_id = farms.id
                LEFT JOIN farm_roles ON farm_roles.id = servers.farm_roleid
                LEFT JOIN roles ON roles.id = farm_roles.role_id
                LEFT JOIN server_termination_errors ste ON servers.server_id = ste.server_id";

        $stmWhere = ["servers.env_id = ?"];

        $args = [$this->getEnvironmentId()];
        $sqlFlt = [];

        if (!empty($cloudServerId)) {
            $cloudServerProps = [
                \CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID,
                \AZURE_SERVER_PROPERTIES::SERVER_NAME,
                \EC2_SERVER_PROPERTIES::INSTANCE_ID,
                \GCE_SERVER_PROPERTIES::SERVER_NAME,
                \OPENSTACK_SERVER_PROPERTIES::SERVER_ID,
                \RACKSPACE_SERVER_PROPERTIES::SERVER_ID
            ];
            $sqlFlt["sp1"] = "sp1.`name` IN (". implode(", ", array_fill(0, count($cloudServerProps), "?")) . ") AND sp1.`value` = ?";
            foreach ($cloudServerProps as $spn) {
                $args[] = $spn;
            }
            $args[] = $cloudServerId;
        }

        if (!empty($cloudServerLocation)) {
            $cloudServerLocationProps = [
                \CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION,
                \AZURE_SERVER_PROPERTIES::CLOUD_LOCATION,
                \EC2_SERVER_PROPERTIES::REGION,
                \GCE_SERVER_PROPERTIES::CLOUD_LOCATION,
                \OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION,
                \RACKSPACE_SERVER_PROPERTIES::DATACENTER
            ];
            $sqlFlt["sp2"] = "sp2.`name` IN (". implode(", ", array_fill(0, count($cloudServerLocationProps), "?")) . ") AND sp2.`value` = ?";
            foreach ($cloudServerLocationProps as $spn) {
                $args[] = $spn;
            }
            $args[] = $cloudServerLocation;
        }

        if (!empty($hostname)) {
            $sqlFlt["sp3"] = "sp3.`name` = ? AND sp3.`value` LIKE ?";
            $args[] = \Scalr_Role_Behavior::SERVER_BASE_HOSTNAME;
            $args[] = "%" . $hostname . "%";
        }

        if (!empty($sqlFlt)) {
            foreach ($sqlFlt as $alias => $where) {
                $stmFrom .= " INNER JOIN server_properties AS " . $alias . " ON servers.server_id = " . $alias . ".server_id";
                $stmWhere[] = $where;
            }
        }

        if (!empty($farmId)) {
            $stmWhere[] = "farm_id = ?";
            $args[] = $farmId;
        }

        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS)) {
            $q = [];
            if ($this->request->isAllowed(Acl::RESOURCE_TEAM_FARMS)) {
                $t = array_map(function($t) { return $t['id']; }, $this->user->getTeams());
                if (! empty($t)) {
                    $q[] = "farms.team_id IN (" . join(',', $t) . ")";
                }
            }

            if ($this->request->isAllowed(Acl::RESOURCE_OWN_FARMS)) {
                $q[] = "farms.created_by_id = ?";
                $args[] =  $this->request->getUser()->getId();
            }

            if (!empty($q)) {
                if ($this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
                    $q[] = "servers.status IN (?, ?) AND farms.id IS NULL";
                    $args[] = Entity\Server::STATUS_IMPORTING;
                    $args[] = Entity\Server::STATUS_TEMPORARY;
                }

                $stmWhere[] = "(" . join(" OR ", $q) . ")";
            } else {
                //show servers related to role creation process only
                $stmWhere[] = "servers.status IN (?, ?) AND farms.id IS NULL";
                $args[] = Entity\Server::STATUS_IMPORTING;
                $args[] = Entity\Server::STATUS_TEMPORARY;
            }
        }

        if (!empty($farmRoleId)) {
            $stmWhere[] = "farm_roleid = ?";
            $args[] = $farmRoleId;
        }

        if (!empty($roleId)) {
            $stmWhere[] = "farm_roles.role_id = ?";
            $args[] = $roleId;
        }

        if (!empty($serverId)) {
            $stmWhere[] = "servers.server_id = ?";
            $args[] = $serverId;
        }

        if (!empty($imageId)) {
            $stmWhere[] = "image_id = ?";
            $args[] = $imageId;
        }

        if (empty($showTerminated)) {
            $stmWhere[] = "servers.status != ?";
            $args[] = Entity\Server::STATUS_TERMINATED;
        }

        if (!empty($uptime) && (preg_match('/^(m|l)([0-9]+)(d|h)$/', $uptime, $matches))) {
            if ($matches[1] == 'm') {
                $stmWhere[] = "servers.dtinitialized < ?";
            } else {
                $stmWhere[] = "servers.dtinitialized > ?";
            }

            $args[] = date("Y-m-d H:i:s", strtotime("-" . $matches[2] . ($matches[3] == 'd' ? 'day' : 'hour')));
        }

        $stmWhere[] = ":FILTER:";

        $response = $this->buildResponseFromSql2(
            $stmFrom . " WHERE " . implode(" AND ", $stmWhere), [
                "platform",
                "farm_name",
                "role_name",
                "role_alias",
                "index",
                "servers.server_id",
                "remote_ip_int",
                "local_ip_int",
                "uptime",
                "status"
            ], [
                "servers.server_id",
                "farm_id",
                "farms.name",
                "remote_ip",
                "local_ip",
                "servers.status",
                "farm_roles.alias"
            ], $args
        );

        $this->listServersResponseHelper($response);

        $this->response->data($response);
    }

    /**
     * Update server's information
     *
     * @param jsonData $servers optional List of servers to check against
     * @throws \Scalr_Exception_InsufficientPermissions
     */
    public function xListServersUpdateAction(JsonData $servers = null)
    {
        if (!$this->request->isFarmAllowed() && !$this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
            throw new \Scalr_Exception_InsufficientPermissions();
        }

        $props = $retval = [];
        $args = $servers = (array) $servers;

        $stmt = "
            SELECT s.server_id, s.status, s.remote_ip, s.local_ip, s.dtadded, s.dtinitialized AS uptime
            FROM servers s
            LEFT JOIN farms f ON f.id = s.farm_id
            WHERE s.server_id IN (" . implode(",", array_fill(0, count($servers), "?")) . ")
            AND s.env_id = ?
        ";

        $args[] = $this->getEnvironmentId();

        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS)) {
            $q = [];
            if ($this->request->isAllowed(Acl::RESOURCE_TEAM_FARMS)) {
                $t = array_map(function($t) { return $t["id"]; }, $this->user->getTeams());
                if (!empty($t)) {
                    $q[] = "f.team_id IN(" . join(",", $t) . ")";
                }
            }

            if ($this->request->isAllowed(Acl::RESOURCE_OWN_FARMS)) {
                $q[] = "f.created_by_id = ?";
                $args[] = $this->request->getUser()->getId();
            }

            if (!empty($q)) {
                if ($this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
                    $q[] = "s.status IN (?, ?) AND f.id IS NULL";
                    $args[] = Entity\Server::STATUS_IMPORTING;
                    $args[] = Entity\Server::STATUS_TEMPORARY;
                }

                $stmt .= " AND (" . join(" OR ", $q) . ")";
            } else {
                //show servers related to role creation process only
                $stmt .= " AND s.status IN (?, ?) AND f.id IS NULL";
                $args[] = Entity\Server::STATUS_IMPORTING;
                $args[] = Entity\Server::STATUS_TEMPORARY;
            }
        }

        if (!empty($servers)) {
            $srs = $this->db->Execute($stmt, $args);
            $neededProps = [
                Entity\Server::REBOOTING,
                Entity\Server::MISSING,
                Entity\Server::SZR_IS_INIT_FAILED,
                Entity\Server::LAUNCH_ERROR,
                Entity\Server::SZR_VESION
            ];

            foreach (Entity\Server\Property::fetch($servers, $neededProps) as $resRow) {
                if (!array_key_exists($resRow->serverId, $props)) {
                    $props[$resRow->serverId] = [];
                }
                $props[$resRow->serverId][$resRow->name] = $resRow->value;
            }

            while ($server = $srs->FetchRow()) {
                if (!array_key_exists($server["server_id"], $props)) {
                    $props[$server["server_id"]] = [];
                }

                $status = $server["status"];

                if (in_array($status, [Entity\Server::STATUS_RUNNING, Entity\Server::STATUS_SUSPENDED])) {
                    if (array_key_exists(Entity\Server::REBOOTING, $props[$server["server_id"]]) && $props[$server["server_id"]][Entity\Server::REBOOTING] != 0) {
                        $server["status"] = "Rebooting";
                    }

                    if (array_key_exists(Entity\Server::MISSING, $props[$server["server_id"]]) && $props[$server["server_id"]][Entity\Server::MISSING] != 0) {
                        $server["status"] = "Missing";
                    }
                }

                if (array_key_exists(Entity\Server::SZR_IS_INIT_FAILED, $props[$server["server_id"]]) &&
                    $props[$server["server_id"]][Entity\Server::SZR_IS_INIT_FAILED] == 1 &&
                    in_array($server["status"], [Entity\Server::STATUS_INIT, Entity\Server::STATUS_PENDING])) {
                        $server["isInitFailed"] = 1;
                }

                if (array_key_exists(Entity\Server::LAUNCH_ERROR, $props[$server["server_id"]]) && $props[$server["server_id"]][Entity\Server::LAUNCH_ERROR] == 1) {
                    $server["launch_error"] = "1";
                }

                $server["agent_version"] = $props[$server["server_id"]][Entity\Server::SZR_VESION];

                if ($status === Entity\Server::STATUS_RUNNING) {
                    $server['uptime'] = \Scalr_Util_DateTime::getHumanReadableTimeout(time() - strtotime($server['uptime']), false);
                } else {
                    $server['uptime'] = '';
                }

                $retval[$server["server_id"]] = $server;
            }
        }

        $this->response->data(["servers" => $retval]);
    }

    public function xSzrUpdateAction()
    {
        if (! $this->getParam('serverId'))
            throw new Exception(_('Server not found'));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_UPDC_PORT);
        if (!$port)
            $port = 8008;

        $updateClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer, $port, 100);
        $status = $updateClient->updateScalarizr();

        $this->response->success('Scalarizr successfully updated to the latest version');
    }

    public function xSzrRestartAction()
    {
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

    /**
     * @param DBServer $dbServer
     * @param bool $cached check only cached information
     * @param int $timeout
     * @return array|NULL
     */
    public function getServerStatus(DBServer $dbServer, $cached = true, $timeout = 0)
    {
        if ($dbServer->status == SERVER_STATUS::RUNNING &&
            (($dbServer->IsSupported('0.8') && $dbServer->osType == 'linux') || ($dbServer->IsSupported('0.19') && $dbServer->osType == 'windows'))) {
            if ($cached && !$dbServer->IsSupported('2.7.7')) {
                return [
                    'status' => 'statusNoCache',
                    'error' => "<span style='color:gray;'>Scalarizr is checking actual status</span>"
                ];
            }

            try {
                $port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_UPDC_PORT);
                if (!$port) {
                    $port = 8008;
                }
                if (! $timeout) {
                    $timeout = \Scalr::config('scalr.system.instances_connection_timeout');
                }

                $updateClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer, $port, $timeout);
                $scalarizr = $updateClient->getStatus($cached);

                try {
                    if ($dbServer->farmRoleId != 0) {
                        $scheduledOn = $dbServer->GetFarmRoleObject()->GetSetting('scheduled_on');
                    }
                } catch (Exception $e) {}

                $nextUpdate = null;
                if ($scalarizr->candidate && $scalarizr->installed != $scalarizr->candidate) {
                    $nextUpdate = [
                        'candidate'   => htmlspecialchars($scalarizr->candidate),
                        'scheduledOn' => $scheduledOn ? Scalr_Util_DateTime::convertTzFromUTC($scheduledOn) : null
                    ];
                }
                return [
                    'status'      => htmlspecialchars($scalarizr->service_status),
                    'version'     => htmlspecialchars($scalarizr->installed),
                    'candidate'   => htmlspecialchars($scalarizr->candidate),
                    'repository'  => ucfirst(htmlspecialchars($scalarizr->repository)),
                    'lastUpdate'  => [
                        'date'        => ($scalarizr->executed_at) ? Scalr_Util_DateTime::convertTzFromUTC($scalarizr->executed_at) : "",
                        'error'       => nl2br(htmlspecialchars($scalarizr->error))
                    ],
                    'nextUpdate'  => $nextUpdate,
                    'fullInfo'    => $scalarizr
                ];
            } catch (Exception $e) {
                if (stristr($e->getMessage(), "Method not found")) {
                    return [
                        'status' => 'statusNotAvailable',
                        'error'  => "<span style='color:red;'>Scalarizr status is not available, because scalr-upd-client installed on this server is too old.</span>"
                    ];
                } else {
                    return [
                        'status' => 'statusNotAvailable',
                        'error'  => "<span style='color:red;'>Scalarizr status is not available: {$e->getMessage()}</span>"
                    ];
                }
            }
        }
    }

    /**
     * @param string $serverId
     * @param int $timeout
     * @throws Exception
     */
    public function xGetServerRealStatusAction($serverId, $timeout = 30)
    {
        if (! $serverId) {
            throw new Exception(_('Server not found'));
        }

        $dbServer = DBServer::LoadByID($serverId);
        if (!$this->user->getPermissions()->hasReadOnlyAccessServer($dbServer)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->response->data([
            'scalarizr' => $this->getServerStatus($dbServer, false, $timeout)
        ]);
    }

    public function dashboardAction()
    {
        if (! $this->getParam('serverId')) {
            throw new Exception(_('Server not found'));
        }

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        if (!$this->user->getPermissions()->hasAccessServer($dbServer)) {
            $readOnlyAccess = $this->user->getPermissions()->hasReadOnlyAccessServer($dbServer);
            if (!$readOnlyAccess) {
                throw new Scalr_Exception_InsufficientPermissions();
            }
        }

        $data = array();
        if (!$readOnlyAccess) {
            $p = PlatformFactory::NewPlatform($dbServer->platform);
            try {
                $info = $p->GetServerExtendedInformation($dbServer, true);
            } catch (Exception $e) {
                // ignoring
            }
            if (is_array($info) && count($info)) {
                $data['cloudProperties'] = $info;

                if ($dbServer->platform == SERVER_PLATFORMS::OPENSTACK) {
                    $client = $p->getOsClient($this->environment, $dbServer->GetCloudLocation());
                    $iinfo = $client->servers->getServerDetails($dbServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID));
                    $data['raw_server_info'] = $iinfo;
                }
            }
        }

        $imageIdDifferent = false;
        try {
            $dbRole = $dbServer->GetFarmRoleObject()->GetRoleObject();
            // GCE didn't have imageID before we implement this feature
            $roleImageEntity = $dbRole->__getNewRoleObject()->getImage($dbServer->platform, $dbServer->cloudLocation);
            $imageIdDifferent = ($roleImageEntity->imageId != $dbServer->imageId) && $dbServer->imageId;
            $os = [
                'family' => $dbRole->getOs()->family,
                'name' => $dbRole->getOs()->name
            ];
            $image = $roleImageEntity->getImage();
            $imageHash = $image->hash;
            $imageType = $image->type;
            $imageArchitecture = $image->architecture;
        } catch (Exception $e) {}

        /* @var $image Image */
        $suspendEc2Locked = ($dbServer->platform == SERVER_PLATFORMS::EC2) &&
            ($image = Image::findOne([
                ['platform'      => $dbServer->platform],
                ['cloudLocation' => $dbServer->cloudLocation],
                ['id'  => $dbServer->imageId],
                ['$or' => [['accountId' => null], ['accountId' => $dbServer->clientId]]],
                ['$or' => [['envId' => null], ['envId' => $dbServer->envId]]]
            ])) &&
            $image->isEc2InstanceStoreImage();

        $r_dns = $this->db->GetOne("SELECT value FROM farm_role_settings WHERE farm_roleid=? AND `name`=? LIMIT 1", array(
            $dbServer->farmRoleId, Entity\FarmRoleSetting::EXCLUDE_FROM_DNS
        ));

        $conf = $this->getContainer()->config->get('scalr.load_statistics.connections.plotter');

        try {
            if ($dbServer->farmRoleId != 0) {
                $hostNameFormat = $dbServer->GetFarmRoleObject()->GetSetting(Scalr_Role_Behavior::ROLE_BASE_HOSTNAME_FORMAT);
                $hostnameDebug = (!empty($hostNameFormat)) ? $dbServer->applyGlobalVarsToValue($hostNameFormat) : '';
                $scalingEnabled = $dbServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::SCALING_ENABLED) == 1;
            }
        } catch (Exception $e) {}

        if ($dbServer->farmId != 0) {
            $hash = $dbServer->GetFarmObject()->Hash;
        }

        $serverHistory = Server\History::findPk($dbServer->serverId);
        /* @var $serverHistory Entity\Server\History */
        $instType = $serverHistory->instanceTypeName;

        if (empty($instType)) {
            $instType = $dbServer->getType();
        }

        $data['general'] = [
            'server_id'         => $dbServer->serverId,
            'isScalarized'      => $dbServer->isScalarized,
            'hostname_debug'    => urlencode($hostnameDebug),
            'hostname'          => $dbServer->GetProperty(Scalr_Role_Behavior::SERVER_BASE_HOSTNAME),
            'farm_id'           => $dbServer->farmId,
            'farm_name'         => $dbServer->farmId ? $dbServer->GetFarmObject()->Name : "",
            'farm_roleid'       => $dbServer->farmRoleId,
            'imageId'           => $dbServer->imageId,
            'imageHash'         => $imageHash,
            'imageType'         => $imageType,
            'imageArchitecture' => $imageArchitecture,
            'imageIdDifferent'  => $imageIdDifferent,
            'farm_hash'         => $hash,
            'role_id'           => isset($dbRole) ? $dbRole->id : null,
            'platform'          => $dbServer->platform,
            'cloud_location'    => $dbServer->GetCloudLocation(),
            'role'              => [
                                    'name'      => isset($dbRole) ? $dbRole->name : 'unknown',
                                    'id'        => isset($dbRole) ? $dbRole->id : 0,
                                    'platform'  => $dbServer->platform
                                ],
            'os'                => $os,
            'behaviors'         => isset($dbRole) ? $dbRole->getBehaviors() : [],
            'status'            => $dbServer->status,
            'initDetailsSupported' => $dbServer->IsSupported("0.7.181"),
            'index'             => $dbServer->index,
            'local_ip'          => $dbServer->localIp,
            'remote_ip'         => $dbServer->remoteIp,
            'instType'          => $instType,
            'addedDate'         => Scalr_Util_DateTime::convertTz($dbServer->dateAdded),
            'excluded_from_dns' => (!$dbServer->GetProperty(SERVER_PROPERTIES::EXCLUDE_FROM_DNS) && !$r_dns) ? false : true,
            'is_locked'         => $dbServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED) ? 1 : 0,
            'cloud_server_id'   => $dbServer->GetCloudServerID(),
            'monitoring_host_url' => "{$conf['scheme']}://{$conf['host']}:{$conf['port']}",
            'os_family'         => $dbServer->GetOsType(),
            'scalingEnabled'    => $scalingEnabled,
            'suspendEc2Locked'  => $suspendEc2Locked,
            'suspendHidden'     => $dbServer->farmRoleId ? $this->hasDatabaseBehavior($dbServer->GetFarmRoleObject()->GetRoleObject()->getBehaviors()) : false,
            'farmOwnerIdPerm'   => $dbServer->farmId != 0 ? $dbServer->GetFarmObject()->createdByUserId == $this->user->getId() : false,
            'farmTeamIdPerm'    => $dbServer->farmId != 0 && $dbServer->GetFarmObject()->teamId ? $this->user->isInTeam($dbServer->GetFarmObject()->teamId) : false
        ];

        $szrInitFailed = $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=? LIMIT 1", array(
            $dbServer->serverId, SERVER_PROPERTIES::SZR_IS_INIT_FAILED
        ));

        if ($szrInitFailed && in_array($dbServer->status, array(SERVER_STATUS::INIT, SERVER_STATUS::PENDING))) {
            $data['general']['isInitFailed'] = 1;
        }

        if ($dbServer->GetProperty(SERVER_PROPERTIES::LAUNCH_ERROR)) {
            $data['general']['launch_error'] = 1;
        }

        if ($dbServer->status == SERVER_STATUS::RUNNING) {
            $rebooting = $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=? LIMIT 1", array(
                $dbServer->serverId, SERVER_PROPERTIES::REBOOTING
            ));
            if ($rebooting) {
                $data['general']['status'] = "Rebooting";
            }

            /*
            $subStatus = $dbServer->GetProperty(SERVER_PROPERTIES::SUB_STATUS);
            if ($subStatus) {
                $data['general']['status'] = ucfirst($subStatus);
            }
            */
        }

        $status = $this->getServerStatus($dbServer, true);
        if ($status) {
            $data['scalarizr'] = $status;
        }

        if (!$readOnlyAccess) {
            $internalProperties = $dbServer->GetAllProperties();
            if (!empty($internalProperties)) {
                $data['internalProperties'] = $internalProperties;
            }
        }

        if ($dbServer->platform === SERVER_PLATFORMS::EC2 && $dbServer->status === SERVER_STATUS::SUSPENDED) {
            $farmRole = DBFarmRole::LoadByID($dbServer->farmRoleId);
            $data['general']['farmRoleInstanceType'] = $farmRole->getInstanceType();
        }

        $data['mindtermEnabled'] = \Scalr::config('scalr.ui.mindterm_enabled');
        $this->response->page('ui/servers/dashboard.js', $data, array('ui/servers/actionsmenu.js', 'ui/monitoring/window.js'));
    }

    public function consoleOutputAction()
    {
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
            $dbServer->terminate(DBServer::TERMINATE_REASON_OPERATION_CANCELLATION, true, $this->user);
        }

        $this->response->success("Server successfully cancelled and removed from database.");
    }

    public function xResumeServersAction()
    {
        $this->request->defineParams(array(
            'servers' => array('type' => 'json')
        ));

        $errors = array();

        foreach ($this->getParam('servers') as $serverId) {
            try {
                $dbServer = DBServer::LoadByID($serverId);
                $this->user->getPermissions()->validate($dbServer);

                if ($dbServer->platform == SERVER_PLATFORMS::AZURE || $dbServer->platform == SERVER_PLATFORMS::CLOUDSTACK || $dbServer->platform == SERVER_PLATFORMS::GCE || $dbServer->platform == SERVER_PLATFORMS::EC2 || PlatformFactory::isOpenstack($dbServer->platform)) {
                    PlatformFactory::NewPlatform($dbServer->platform)->ResumeServer($dbServer);
                } else {
                    //NOT SUPPORTED
                }
            }
            catch (Exception $e) {
                $errors[$serverId] = $e->getMessage();
            }
        }
        if (!empty($errors)) {
            $this->response->warning(implode("\n", $errors));
        } else {
            $this->response->success();
        }
    }

    public function xSuspendServersAction()
    {
        $this->request->defineParams(array(
            'servers' => array('type' => 'json')
        ));

        $errorServers = array();

        foreach ($this->getParam('servers') as $serverId) {
            try {
                $dbServer = DBServer::LoadByID($serverId);
                $this->user->getPermissions()->validate($dbServer);

                if ($dbServer->platform == SERVER_PLATFORMS::AZURE || $dbServer->platform == SERVER_PLATFORMS::CLOUDSTACK || $dbServer->platform == SERVER_PLATFORMS::GCE || $dbServer->platform == SERVER_PLATFORMS::EC2 || PlatformFactory::isOpenstack($dbServer->platform)) {
                    /* @var $image Image */
                    if (($dbServer->platform == SERVER_PLATFORMS::EC2) &&
                        ($image = Image::findOne([
                            ['platform'      => $dbServer->platform],
                            ['cloudLocation' => $dbServer->cloudLocation],
                            ['id'  => $dbServer->imageId],
                            ['$or' => [['accountId' => null], ['accountId' => $dbServer->clientId]]],
                            ['$or' => [['envId' => null], ['envId' => $dbServer->envId]]]
                        ])) &&
                        $image->isEc2InstanceStoreImage()
                    ) {
                        $errorServers[] = "The instance does not have an 'ebs' root device type and cannot be stopped";
                        continue;
                    }

                    if ($dbServer->farmRoleId) {
                        if ($this->hasDatabaseBehavior($dbServer->GetFarmRoleObject()->GetRoleObject()->getBehaviors())) {
                            $errors[] = "Database instance cannot be stopped";
                            continue;
                        }
                    }

                    $dbServer->suspend('', false, $this->user);
                } else {
                    //NOT SUPPORTED
                }
            }
            catch (Exception $e) {}
        }

        $this->response->data(array('data' => $errorServers));
    }

    public function xServerRebootServersAction()
    {
        $this->request->defineParams(array(
            'servers' => array('type' => 'json'),
            'type'
        ));

        $type = $this->getParam('type') ? $this->getParam('type') : 'hard';

        foreach ($this->getParam('servers') as $serverId) {
            try {
                $dbServer = DBServer::LoadByID($serverId);
                $this->user->getPermissions()->validate($dbServer);

                if ($type == 'hard'/* || PlatformFactory::isOpenstack($dbServer->platform)*/) {
                    $isSoft = $type == 'hard' ? false : true;
                    PlatformFactory::NewPlatform($dbServer->platform)->RebootServer($dbServer, $isSoft);
                } else {
                    try {
                        $dbServer->scalarizr->system->reboot();
                    } catch (Exception $e) {
                        $errorServers[] = $dbServer->serverId;
                        $errorMessages[] = $e->getMessage();
                    }

                    $debug = $dbServer->scalarizr->system->debug;
                }
            }
            catch (Exception $e) {}
        }

        $this->response->data(array('data' => $errorServers, 'errorMessage' => $errorMessages, 'debug' => $debug));
    }

    public function xServerTerminateServersAction()
    {
        $this->request->defineParams(array(
            'servers' => array('type' => 'json'),
            'descreaseMinInstancesSetting' => array('type' => 'bool'),
            'forceTerminate' => array('type' => 'bool')
        ));

        $processed = 0;
        $skippedEc2Flag = 0;

        foreach ($this->getParam('servers') as $serverId) {
            $processed++;
            $dbServer = DBServer::LoadByID($serverId);
            $this->user->getPermissions()->validate($dbServer);

            $forceTerminate = !$dbServer->isOpenstack() && !$dbServer->isCloudstack() && $this->getParam('forceTerminate');

            if ($dbServer->GetProperty(EC2_SERVER_PROPERTIES::IS_LOCKED)) {
                $skippedEc2Flag++;
                continue;
            }

            if (!$forceTerminate) {
                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                    $dbServer->farmId,
                    sprintf("Scheduled termination for server %s (%s). It will be terminated in 3 minutes.",
                        $dbServer->serverId,
                        $dbServer->remoteIp ? $dbServer->remoteIp : $dbServer->localIp
                    ),
                    $dbServer->serverId
                ));
            }

            $dbServer->terminate(array(DBServer::TERMINATE_REASON_MANUALLY, $this->user->fullname), (bool)$forceTerminate, $this->user);
        }

        if ($this->getParam('descreaseMinInstancesSetting')) {
            try {
                $servers = $this->getParam('servers');
                $dbServer = DBServer::LoadByID($servers[0]);
                $dbFarmRole = $dbServer->GetFarmRoleObject();
            } catch (Exception $e) {}

            if ($dbFarmRole && $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_ENABLED) == 1) {
                $minInstances = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES);
                if ($minInstances > count($servers)) {
                    $dbFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES,
                        $minInstances - count($servers),
                        Entity\FarmRoleSetting::TYPE_LCL
                    );
                } else {
                    if ($minInstances != 0)
                        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES, 1, Entity\FarmRoleSetting::TYPE_CFG);
                }
            }
        }

        if ($skippedEc2Flag) {
            $this->response->warning(sprintf("Termination was requested for %d server(s). %d server(s) has disableAPITermination flag and won’t be terminated", $processed, $skippedEc2Flag));
        } else {
            $this->response->success();
        }
    }

    public function xServerGetLaAction()
    {
        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        if (!$dbServer->IsSupported('0.13.0')) {
            $la = "Unknown";
        } else {
            if ($dbServer->osType == 'linux') {
                try {
                    $la = $dbServer->scalarizr->system->loadAverage();
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
        $this->request->restrictAccess(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE);

        if (!$this->getParam('serverId'))
            throw new Exception(_('Server not found'));

        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        $dbFarmRole = $dbServer->GetFarmRoleObject();

        if ($dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
            $this->response->warning("You are about to synchronize MySQL instance. The bundle will not include DB data. <a href='#/db/dashboard?farmId={$dbServer->farmId}&type=mysql'>Click here if you wish to bundle and save DB data</a>.", true);

            if (!$dbServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER)) {
                $dbSlave = true;
            }
        }

        $dbMsrBehavior = $dbFarmRole->GetRoleObject()->getDbMsrBehavior();
        if ($dbMsrBehavior) {
            $this->response->warning("You are about to synchronize DB instance. The bundle will not include DB data. <a href='#/db/manager/dashboard?farmId={$dbServer->farmId}&type={$dbMsrBehavior}'>Click here if you wish to bundle and save DB data</a>.", true);

            if (!$dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER)) {
                $dbSlave = true;
            }
        }

        //Check for already running bundle on selected instance
        $chk = $this->db->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND status NOT IN ('success', 'failed') LIMIT 1",
            array($dbServer->serverId)
        );

        if ($chk) {
            $message = "This server is already synchronizing.";
            if ($this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_BUNDLETASKS)) {
                $message .= " <a href='#/bundletasks?id={$chk}'>Check status</a>.";
            }

            $this->response->failure($message, true);
            return;
        }

        if (!$dbServer->IsSupported("0.2-112"))
            throw new Exception(sprintf(_("You cannot create snapshot from selected server because scalr-ami-scripts package on it is too old.")));

        //Check is role already synchronizing...
        $chk = $this->db->GetRow("SELECT id, server_id FROM bundle_tasks WHERE prototype_role_id=? AND status NOT IN ('success', 'failed') LIMIT 1", array(
            $dbServer->GetFarmRoleObject()->RoleID
        ));

        if ($chk && ($chk['server_id'] != $dbServer->serverId)) {
            try {
                $bDBServer = DBServer::LoadByID($chk['server_id']);
            }
            catch(Exception $e) {}

            if ($bDBServer->farmId == $dbServer->farmId) {
                $this->response->failure(sprintf(_("This role is already synchonizing. <a href='#/bundletasks?id=%d'>Check status</a>."), $chk['id']), true);
                return;
            }
        }

        $roleImage = $dbFarmRole->GetRoleObject()->__getNewRoleObject()->getImage($dbServer->platform, $dbServer->GetCloudLocation());
        $image = $roleImage->getImage();
        $roleName = $dbServer->GetFarmRoleObject()->GetRoleObject()->name;
        $roleScope = $dbServer->GetFarmRoleObject()->GetRoleObject()->__getNewRoleObject()->getScope();

        $this->response->page('ui/servers/createsnapshot.js', array(
            'serverId' 	=> $dbServer->serverId,
            'platform'	=> $dbServer->platform,
            'dbSlave'	=> $dbSlave,
            'isVolumeSizeSupported' => $dbServer->platform == SERVER_PLATFORMS::EC2 && (
                $dbServer->IsSupported('0.7') && $dbServer->osType == 'linux' ||
                $image->isEc2HvmImage()
            ),
            'isVolumeTypeSupported' => $dbServer->platform == SERVER_PLATFORMS::EC2 && (
                $dbServer->IsSupported('2.11.4') && $dbServer->osType == 'linux' ||
                $image->isEc2HvmImage()
            ),
            'farmId' => $dbServer->farmId,
            'farmName' => $dbServer->GetFarmObject()->Name,
            'roleId'   => $dbServer->GetFarmRoleObject()->GetRoleObject()->id,
            'serverIndex' => $dbServer->index,
            'roleName' => $roleName,
            'imageId' => $dbServer->imageId,
            'roleImageId' => $roleImage->imageId,
            'imageName' => $image->name,
            'isManageableRole' => $roleScope == ScopeInterface::SCOPE_ENVIRONMENT && $this->request->isAllowed(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE) ||
                                  $roleScope == ScopeInterface::SCOPE_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_ROLES_ACCOUNT, Acl::PERM_ROLES_ACCOUNT_MANAGE),
            'cloudLocation' => $dbServer->GetCloudLocation(),
            'replaceNoReplace' => "<b>DO NOT REPLACE</b> any roles on any farms, just create a new one.</td>",
            'replaceFarmReplace' => "Replace the role '{$roleName}' with this new one <b>ONLY</b> on the current farm '{$dbServer->GetFarmObject()->Name}'</td>",
            'replaceAll' => "Replace the role '{$roleName}' with this new one on <b>ALL MY FARMS</b> <br/><span style=\"font-style:italic;font-size:11px;\">(You will be able to bundle this role with the same name. The old role will be renamed.)</span></td>",
            'isReplaceFarmRolePermission' => $this->request->isFarmAllowed($dbServer->GetFarmObject(), Acl::PERM_FARMS_MANAGE),
            'showWarningMessage' =>
                $dbServer->osType == 'windows' ?
                    'You are snapshotting a Windows Server. Before proceeding, make sure that you have sysprepped the instance' . ($dbServer->platform == SERVER_PLATFORMS::GCE ? 'by running gcesysprep' : '') . '. ' .
                    '<br/>Note that the instance <b>will be ' . ($dbServer->platform == SERVER_PLATFORMS::GCE ? 'terminated' : 'rebooted') . '</b> in order to complete the server snapshotting process. Do not proceed if this is a problem for you.'
                : null
        ));
    }

    /**
     * @param   string  $serverId
     * @param   string  $name
     * @param   string  $description
     * @param   bool    $createRole
     * @param   string  $replaceRole
     * @param   bool    $replaceImage
     * @param   int     $rootVolumeSize
     * @param   string  $rootVolumeType
     * @param   int     $rootVolumeIops
     * @throws Exception
     */
    public function xServerCreateSnapshotAction($serverId, $name = '', $description = '', $createRole = false, $replaceRole = '', $replaceImage = false, $rootVolumeSize = 0, $rootVolumeType = '', $rootVolumeIops = 0)
    {
        $this->request->restrictAccess(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE);

        if (! $serverId)
            throw new Exception('Server not found');

        $dbServer = DBServer::LoadByID($serverId);
        $this->user->getPermissions()->validate($dbServer);

        $errorMsg = [];
        //Check for already running bundle on selected instance
        $chk = $this->db->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND status NOT IN ('success', 'failed') LIMIT 1",
            array($dbServer->serverId)
        );

        if ($chk)
            $errorMsg[] = sprintf(_("Server '%s' is already synchonizing."), $dbServer->serverId);

        //Check is role already synchronizing...
        $chk = $this->db->GetOne("SELECT server_id FROM bundle_tasks WHERE prototype_role_id=? AND status NOT IN ('success', 'failed') LIMIT 1", array(
            $dbServer->GetFarmRoleObject()->RoleID
        ));

        if ($chk && $chk != $dbServer->serverId) {
            try {
                $bDBServer = DBServer::LoadByID($chk);
                if ($bDBServer->farmId == $dbServer->farmId)
                    $errorMsg[] = sprintf(_("Role '%s' is already synchronizing."), $dbServer->GetFarmRoleObject()->GetRoleObject()->name);
            } catch(Exception $e) {}
        }

        if (! empty($errorMsg))
            throw new Exception(implode('\n', $errorMsg));

        $validator = new \Scalr\UI\Request\Validator();
        $validator->addErrorIf(strlen($name) < 3, 'name', _("Role name should be greater than 3 chars"));
        $validator->addErrorIf(! preg_match("/^[A-Za-z0-9-]+$/si", $name), 'name', _("Role name is incorrect"));
        $validator->addErrorIf(! in_array($replaceRole, ['farm', 'all', '']), 'replaceRole', 'Invalid value');

        $object = $createRole ? BundleTask::BUNDLETASK_OBJECT_ROLE : BundleTask::BUNDLETASK_OBJECT_IMAGE;
        $replaceType = SERVER_REPLACEMENT_TYPE::NO_REPLACE;

        if ($createRole) {
            $this->request->restrictAccess(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);
            if ($replaceRole == 'farm') {
                $this->request->restrictFarmAccess($dbServer->GetFarmObject(), Acl::PERM_FARMS_MANAGE);
                $replaceType = SERVER_REPLACEMENT_TYPE::REPLACE_FARM;
            } else if ($replaceRole == 'all') {
                $this->request->restrictFarmAccess(null, Acl::PERM_FARMS_MANAGE);
                $replaceType = SERVER_REPLACEMENT_TYPE::REPLACE_ALL;
            }
        } else {
            $scope = $dbServer->GetFarmRoleObject()->GetRoleObject()->__getNewRoleObject()->getScope();
            if ($replaceImage && ($scope == ScopeInterface::SCOPE_ENVIRONMENT && $this->request->isAllowed(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE) ||
                                  $scope == ScopeInterface::SCOPE_ACCOUNT && $this->request->isAllowed(Acl::RESOURCE_ROLES_ACCOUNT, Acl::PERM_ROLES_ACCOUNT_MANAGE))
            ) {
                $replaceType = SERVER_REPLACEMENT_TYPE::REPLACE_ALL;
            }
        }

        if ($createRole) {
            $roleInfo = $this->db->GetRow("SELECT * FROM roles WHERE name=? AND (client_id IS NULL OR client_id = ? AND env_id IS NULL OR env_id=?) LIMIT 1", array($name, $dbServer->clientId, $dbServer->envId, $dbServer->GetFarmRoleObject()->RoleID));
            if ($roleInfo) {
                if (empty($roleInfo['client_id'])) {
                    $validator->addError('name', _("Selected role name is reserved and cannot be used for custom role"));
                } else if ($replaceType != SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
                    $validator->addError('name', _("Specified role name is already used by another role. You can use this role name only if you will replace old one on ALL your farms."));
                } else if ($replaceType == SERVER_REPLACEMENT_TYPE::REPLACE_ALL && $roleInfo['id'] != $dbServer->GetFarmRoleObject()->RoleID) {
                    $validator->addError('name', _("This Role name is already in use. You cannot replace a Role different from the one you are currently snapshotting."));
                }
            }
        }

        $roleImage = $dbServer->GetFarmRoleObject()->GetRoleObject()->__getNewRoleObject()->getImage($dbServer->platform, $dbServer->GetCloudLocation());
        $rootBlockDevice = [];
        if ($dbServer->platform == SERVER_PLATFORMS::EC2 && ($dbServer->IsSupported('0.7') && $dbServer->osType == 'linux' || $roleImage->getImage()->isEc2HvmImage())) {
            if ($rootVolumeSize > 0) {
                $rootBlockDevice['size'] = $rootVolumeSize;
            }

            if (in_array($rootVolumeType, ['standard', 'gp2', 'io1'])) {
                $rootBlockDevice['volume_type'] = $rootVolumeType;
                if ($rootVolumeType == 'io1' && $rootVolumeIops > 0) {
                    $rootBlockDevice['iops'] = $rootVolumeIops;
                }
            }
        }

        if (! $validator->isValid($this->response))
            return;

        $ServerSnapshotCreateInfo = new ServerSnapshotCreateInfo(
            $dbServer,
            $name,
            $replaceType,
            $object,
            $description,
            $rootBlockDevice
        );
        $BundleTask = BundleTask::Create($ServerSnapshotCreateInfo);

        $BundleTask->createdById = $this->user->id;
        $BundleTask->createdByEmail = $this->user->getEmail();

        if ($dbServer->GetFarmRoleObject()->GetSetting('user-data.scm_branch') == 'feature/image-api') {
            $BundleTask->generation = 2;
        }

        $protoRole = DBRole::loadById($dbServer->GetFarmRoleObject()->RoleID);
        $BundleTask->osId = $protoRole->osId;

        if ($protoRole->getOs()->family == 'windows') {
            $BundleTask->osFamily = $protoRole->getOs()->family;
            $BundleTask->osVersion = $protoRole->getOs()->generation;
            $BundleTask->osName = '';
        } else {
            $BundleTask->osFamily = $protoRole->getOs()->family;
            $BundleTask->osVersion = $protoRole->getOs()->version;
            $BundleTask->osName = $protoRole->getOs()->name;
        }

        if (in_array($protoRole->getOs()->family, array('redhat', 'oel', 'scientific')) &&
            $dbServer->platform == SERVER_PLATFORMS::EC2) {
            $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
        }

        $BundleTask->save();

        $this->response->data(['bundleTaskId' => $BundleTask->id]);
        $this->response->success("Bundle task successfully created.");
    }

    public function xServerDeleteAction($serverId)
    {
        $dbServer = DBServer::LoadByID($serverId);
        $this->user->getPermissions()->validate($dbServer);

        if ($dbServer->status == SERVER_STATUS::PENDING_TERMINATE || $dbServer->status == SERVER_STATUS::TERMINATED) {
            $serverHistory = $dbServer->getServerHistory();
            if ($serverHistory) {
                $serverHistory->setTerminated();
            }
            $dbServer->Remove();
        }

        $this->response->success('Server record successfully removed.');
    }

    private function getSshConsoleSettings($dbServer)
    {
        $userSshSettings = $this->user->getSshConsoleSettings(false, true, $dbServer->serverId);
        $ipType = $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_IP] ? $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_IP] : 'auto';
        switch ($ipType) {
            case 'auto':
                $ipAddress = $dbServer->getSzrHost();
                break;
            case 'public':
                $ipAddress = $dbServer->remoteIp;
                break;
            case 'private':
                $ipAddress = $dbServer->localIp;
                break;
        }

        if ($ipAddress) {
            $dBFarm = $dbServer->GetFarmObject();
            $dbFarmRole = $dbServer->GetFarmRoleObject();
            $dbRole = $dbFarmRole->GetRoleObject();

            $sshPort = $dbRole->getProperty(DBRole::PROPERTY_SSH_PORT);
            if (!$sshPort)
                $sshPort = 22;

            $cSshPort = $dbServer->GetProperty(SERVER_PROPERTIES::CUSTOM_SSH_PORT);
            if ($cSshPort)
                $sshPort = $cSshPort;

            $sshSettings = array(
                'serverId' => $dbServer->serverId,
                'serverIndex' => $dbServer->index,
                'ip' => $ipAddress,
                'farmName' => $dBFarm->Name,
                'farmId' => $dbServer->farmId,
                'roleName' => $dbRole->name,
                'farmRoleAlias' => $dbFarmRole->Alias,
                'farmRoleId' => $dbFarmRole->ID,
                Scalr_Account_User::VAR_SSH_CONSOLE_IP => $ipAddress == $dbServer->remoteIp ? 'public' : 'private',
                Scalr_Account_User::VAR_SSH_CONSOLE_PORT => $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_PORT] ? $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_PORT] : $sshPort,
                Scalr_Account_User::VAR_SSH_CONSOLE_USERNAME => $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_USERNAME] ? $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_USERNAME] : ($dbServer->platform == SERVER_PLATFORMS::GCE ? 'scalr' : 'root'),
                Scalr_Account_User::VAR_SSH_CONSOLE_LOG_LEVEL => $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_LOG_LEVEL] ? $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_LOG_LEVEL] : 'CONFIG',
                Scalr_Account_User::VAR_SSH_CONSOLE_PREFERRED_PROVIDER => $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_PREFERRED_PROVIDER] ? $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_PREFERRED_PROVIDER] : '',
                Scalr_Account_User::VAR_SSH_CONSOLE_ENABLE_AGENT_FORWARDING => $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_ENABLE_AGENT_FORWARDING] ? $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_ENABLE_AGENT_FORWARDING] : '0',
            );

            if ($this->request->isAllowed(Acl::RESOURCE_SECURITY_SSH_KEYS)) {
                $sshKey = (new SshKey())->loadGlobalByFarmId(
                    $dbServer->envId,
                    $dbServer->platform,
                    $dbServer->GetFarmRoleObject()->CloudLocation,
                    $dbServer->farmId
                );

                if (!$sshKey) {
                    throw new NotFoundException(sprintf(
                        "Cannot find ssh key corresponding to environment:'%d', farm:'%d', platform:'%s', cloud location:'%s'.",
                        $dbServer->envId,
                        $dbServer->farmId,
                        strip_tags($dbServer->platform),
                        strip_tags($dbServer->GetFarmRoleObject()->CloudLocation)
                    ));
                }

                $cloudKeyName = $sshKey->cloudKeyName;
                if (substr_count($cloudKeyName, '-') == 2) {
                    $cloudKeyName = str_replace('-'.SCALR_ID, '-'.$sshKey->cloudLocation.'-'.SCALR_ID, $cloudKeyName);
                }

                $sshSettings['ssh.console.key'] = base64_encode($sshKey->privateKey);
                $sshSettings['ssh.console.putty_key'] = base64_encode($sshKey->getPuttyPrivateKey());
                $sshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_KEY_NAME] = $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_KEY_NAME] ? $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_KEY_NAME] : $cloudKeyName;
                $sshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_DISABLE_KEY_AUTH] = $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_DISABLE_KEY_AUTH] ? $userSshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_DISABLE_KEY_AUTH] : '0';
            } else {
                $sshSettings['ssh.console.key'] = '';
                $sshSettings['ssh.console.putty_key'] = '';
                $sshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_KEY_NAME] = '';
                $sshSettings[Scalr_Account_User::VAR_SSH_CONSOLE_DISABLE_KEY_AUTH] = '1';
            }

            return $sshSettings;
        }
        else
            throw new Exception(_("SSH console not available for this server or server is not yet initialized"));
    }

    /**
     * xChangeInstanceType
     * Resizes Ec2 instance
     *
     * @param string $serverId
     * @param string $instanceType
     * @throws \Scalr\Exception\ModelException
     * @throws InvalidArgumentException
     */
    public function xChangeInstanceTypeAction($serverId, $instanceType)
    {
        $dbServer = DBServer::LoadByID($serverId);

        $this->user->getPermissions()->validate($dbServer);

        if (empty($instanceType)) {
            throw new InvalidArgumentException('Instance type cannot be empty.');
        }

        if ($dbServer->getType() == $instanceType) {
            throw new InvalidArgumentException(sprintf("The server is already of %s type.", $instanceType));
        }

        $dbServer->GetEnvironmentObject()->aws($dbServer->GetCloudLocation())->ec2->instance->modifyAttribute(
            $dbServer->GetCloudServerID(),
            InstanceAttributeType::TYPE_INSTANCE_TYPE,
            $instanceType
        );

        // NOTE: instance type name equals to instance type id for ec2 platform
        $dbServer->update(['type' => $instanceType, 'instanceTypeName' => $instanceType]);

        $serverHistory = Entity\Server\History::findPk($serverId);
        /* @var $serverHistory Entity\Server\History */
        $serverHistory->instanceTypeName = $instanceType;
        $serverHistory->type = $instanceType;
        $serverHistory->save();

        $this->response->success("Server's instance type has been successfully modified.");
    }

}
