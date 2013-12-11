<?php
use \Scalr\Service\OpenStack\Services\Servers\Type\Personality;
use \Scalr\Service\OpenStack\Services\Servers\Type\PersonalityList;
use \Scalr\Service\OpenStack\Services\Servers\Type\ServersExtension;
use Scalr\Service\OpenStack\Services\Servers\Type\RebootType;

class Modules_Platforms_Openstack extends Modules_Platform implements IPlatformModule
{

    /** Properties **/
    const USERNAME      = 'username';
    const API_KEY       = 'api_key';
    const PASSWORD      = 'password';
    const TENANT_NAME   = 'tenant_name';
    const KEYSTONE_URL  = 'keystone_url';

    /** System Properties **/
    const AUTH_TOKEN    = 'auth_token';
    const EXT_KEYPAIRS_ENABLED = 'ext.keypairs_enabled';
    const EXT_SECURITYGROUPS_ENABLED = 'ext.securitygroups_enabled';


    private $instancesListCache = array();

    /**
     * @return \Scalr\Service\OpenStack\OpenStack
     */
    public function getOsClient(Scalr_Environment $environment, $cloudLocation)
    {
        return $environment->openstack($this->platform, $cloudLocation);
    }

    public function __construct($platform = 'openstack')
    {
        parent::__construct($platform);
    }

    public function getLocations()
    {
        try {
            $environment = Scalr_UI_Request::getInstance()->getEnvironment();
        } catch (Exception $e) {
            return array();
        }

        if (!$environment || !$environment->isPlatformEnabled($this->platform)) {
            return array();
        }

        try {
            $client = $environment->openstack($this->platform, "fakeRegion");
            foreach ($client->listZones() as $zone) {
                $retval[$zone->name] = ucfirst($this->platform) . " / {$zone->name}";
            }
        } catch (Exception $e) {
            return array();
        }

        return $retval;
    }

    public function getPropsList()
    {
        return array(
            self::USERNAME	=> 'Username',
            self::API_KEY	=> 'API KEY',
            self::PASSWORD	=> 'Password',
            self::TENANT_NAME	=> 'Tenant name',
            self::KEYSTONE_URL	=> 'KeyStone URL',
            self::AUTH_TOKEN	=> 'Auth Token'
        );
    }

    public function GetServerCloudLocation(DBServer $DBServer)
    {
        return $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
    }

    public function GetServerID(DBServer $DBServer)
    {
        return $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID);
    }

    public function GetServerFlavor(DBServer $DBServer)
    {
        return $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::FLAVOR_ID);
    }

    public function IsServerExists(DBServer $DBServer, $debug = false)
    {
        return in_array(
            $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID),
            array_keys($this->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION)))
        );
    }

    public function determineServerIps(\Scalr\Service\OpenStack\OpenStack $client, $server)
    {
        if (is_array($server->addresses->public)) {
            foreach ($server->addresses->public as $addr)
            if ($addr->version == 4) {
                $remoteIp = $addr->addr;
                break;
            }
        }

        if (is_array($server->addresses->private)) {
            foreach ($server->addresses->private as $addr)
            if ($addr->version == 4) {
                $localIp = $addr->addr;
                break;
            }
        }

        if (!$localIp)
            $localIp = $remoteIp;

        if (!$localIp && !$remoteIp) {

            $extendedIpsSupported = $client->servers->isExtensionSupported(ServersExtension::EXT_EXTENDED_IPS);

            $addresses = (array)$server->addresses;
            $addresses = array_shift($addresses);
            if (is_array($addresses)) {
                foreach ($addresses as $address) {
                    if ($address->version == 4) {
                        if ($extendedIpsSupported) {
                            if($address->{'OS-EXT-IPS:type'} == 'floating')
                                $remoteIp = $address->addr;
                            else {
                                if (strpos($address->addr, "10.") === 0 || strpos($address->addr, "192.168") === 0)
                                    $localIp = $address->addr;
                                else
                                    $remoteIp = $address->addr;
                            }
                        } else {
                            if (strpos($address->addr, "10.") === 0 || strpos($address->addr, "192.168") === 0) {
                                $localIp = $address->addr;
                            } else {
                                $remoteIp = $address->addr;
                            }
                        }
                    }
                }
            }
        }

        return array(
            'localIp'	=> $localIp,
            'remoteIp'	=> $remoteIp
        );
    }

    public function GetServerIPAddresses(DBServer $DBServer)
    {
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));
        $result = $client->servers->getServerDetails($DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID));

        return $this->determineServerIps($client, $result);
    }

    public function GetServersList(Scalr_Environment $environment, $cloudLocation, $skipCache = false)
    {
        if (!isset($this->instancesListCache[$environment->id]))
            $this->instancesListCache[$environment->id] = array();

        if (!isset($this->instancesListCache[$environment->id][$cloudLocation]))
            $this->instancesListCache[$environment->id][$cloudLocation] = array();

        if (!$this->instancesListCache[$environment->id] || !$this->instancesListCache[$environment->id][$cloudLocation] || $skipCache) {
            $client = $this->getOsClient($environment, $cloudLocation);
            $result = $client->servers->list();
            foreach ($result as $server) {
                $this->instancesListCache[$environment->id][$cloudLocation][$server->id] = $server->status;
            }
        }
        return $this->instancesListCache[$environment->id][$cloudLocation];
    }

    public function GetServerRealStatus(DBServer $DBServer)
    {
        $cloudLocation = $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
        $environment = $DBServer->GetEnvironmentObject();

        $iid = $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID);
        if (!$iid) {
            $status = 'not-found';
        } elseif (!$this->instancesListCache[$environment->id][$cloudLocation][$iid]) {
            $osClient = $this->getOsClient($environment, $cloudLocation);

            try {
                $result = $osClient->servers->getServerDetails($DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID));
                $status = $result->status;
            }
            catch(Exception $e)
            {
                if (stristr($e->getMessage(), "404") || stristr($e->getMessage(), "could not be found"))
                    $status = 'not-found';
                else
                    throw $e;
            }
        }
        else
        {
            $status = $this->instancesListCache[$environment->id][$cloudLocation][$DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID)];
        }

        return Modules_Platforms_Openstack_Adapters_Status::load($status);
    }

    public function TerminateServer(DBServer $DBServer)
    {
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));
        $info = $client->servers->deleteServer($DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID));
        return true;
    }

    public function RebootServer(DBServer $DBServer)
    {
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));
        $client->servers->rebootServer($DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID), RebootType::soft());
        return true;
    }

    public function RemoveServerSnapshot(DBRole $DBRole)
    {
        foreach (PlatformFactory::getOpenstackBasedPlatforms() as $platform) {
            foreach ($DBRole->getImageId($platform) as $location => $imageId) {
                try {
                    $osClient = $DBRole->getEnvironmentObject()->openstack($platform, $location);
                    $osClient->servers->images->delete($imageId);
                } catch(Exception $e) {
                    if (stristr($e->getMessage(), "Unavailable service \"compute\" or region") || stristr($e->getMessage(), "Image not found") || stristr($e->getMessage(), "Cannot destroy a destroyed snapshot") || stristr($e->getMessage(), "OpenStack error. Could not find user")) {
                      //DO NOTHING
                    } else
                        throw $e;
                }
            }
        }

        return true;
    }

    public function CheckServerSnapshotStatus(BundleTask $BundleTask)
    {
        try {
            $DBServer = DBServer::LoadByID($BundleTask->serverId);
            if ($BundleTask->bundleType != SERVER_SNAPSHOT_CREATION_TYPE::OSTACK_WINDOWS)
                return;

            $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;

            $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

            $info = $client->servers->getImage($BundleTask->snapshotId);

            switch ($info->status) {
                case 'SAVING':
                    $BundleTask->Log(sprintf(_("Creating new image. Progress: %s%%"),
                        $info->progress
                    ));
                    break;
                case "DELETED":
                    $BundleTask->SnapshotCreationFailed("Image was removed");
                    break;
                case "ERROR":
                    $BundleTask->SnapshotCreationFailed("Image is in ERROR state");
                    break;
                case "ACTIVE":
                    $BundleTask->SnapshotCreationComplete($BundleTask->snapshotId, array());
                    break;

                default:
                    var_dump($info);
                    break;
            }

        } catch (Exception $e) {
            $BundleTask->SnapshotCreationFailed($e->getMessage());
        }
    }

    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        $DBServer = DBServer::LoadByID($BundleTask->serverId);

        if ($BundleTask->osFamily == 'windows' || $DBServer->osType == 'windows') {
            if ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::PENDING) {
                $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::OSTACK_WINDOWS;
                $BundleTask->Log(sprintf(_("Selected platfrom snapshoting type: %s"), $BundleTask->bundleType));
                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::PREPARING;
                $BundleTask->Save();
                try {
                    $msg = $DBServer->SendMessage(new Scalr_Messaging_Msg_Win_PrepareBundle($BundleTask->id), false, true);
                    if ($msg) {
                        $BundleTask->Log(sprintf(
                            _("PrepareBundle message sent. MessageID: %s. Bundle task status changed to: %s"),
                            $msg->messageId, $BundleTask->status
                        ));
                    } else {
                        throw new Exception("Cannot send message");
                    }
                } catch (Exception $e) {
                    $BundleTask->SnapshotCreationFailed("Cannot send PrepareBundle message to server.");

                    return false;
                }
            } elseif ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::PREPARING) {
                $BundleTask->Log(sprintf(_("Selected platform snapshot type: %s"), $BundleTask->bundleType));
                $createImage = true;
            }
        } else {
            $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::OSTACK_LINUX;
            $createImage = false;

            $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;

            $msg = new Scalr_Messaging_Msg_Rebundle(
                $BundleTask->id,
                $BundleTask->roleName,
                array()
            );

            if (!$DBServer->SendMessage($msg)) {
                $BundleTask->SnapshotCreationFailed("Cannot send rebundle message to server. Please check event log for more details.");
                return;
            } else {
                $BundleTask->Log(sprintf(_("Snapshot creating initialized (MessageID: %s). Bundle task status changed to: %s"),
                    $msg->messageId, $BundleTask->status
                ));
            }

            $BundleTask->setDate('started');
            $BundleTask->Save();
        }

        if ($createImage) {
            try {
                $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

                $imageId = $client->servers->createImage(
                    $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID),
                    $BundleTask->roleName."-".date("YmdHi")
                );

                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
                $BundleTask->snapshotId = $imageId;

                $BundleTask->Log(sprintf(_("Snapshot creating initialized (ImageID: %s). Bundle task status changed to: %s"),
                    $BundleTask->snapshotId, $BundleTask->status
                ));

                $BundleTask->setDate('started');
                $BundleTask->Save();
            } catch (Exception $e) {
                $BundleTask->SnapshotCreationFailed($e->getMessage());
                return;
            }
        }

        return true;
    }

    protected function ApplyAccessData(Scalr_Messaging_Msg $msg)
    {


    }

    public function GetServerConsoleOutput(DBServer $DBServer)
    {
        if ($DBServer->GetRealStatus()->getName() != 'ACTIVE')
            return false;

        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));
        if ($client->servers->isExtensionSupported(ServersExtension::consoleOutput())) {
            return $client->servers->getConsoleOutput($DBServer->GetCloudServerID(), 200);
        }
        else
            throw new Exception("Not supported by Openstack");
    }

    public function GetServerExtendedInformation(DBServer $DBServer)
    {
        try
        {
            try	{
                $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));
                $iinfo = $client->servers->getServerDetails($DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID));

                $ips = $this->GetServerIPAddresses($DBServer);
            }
            catch(Exception $e){}

            if ($iinfo)
            {
                $retval =  array(
                    'Cloud Server ID'	    => $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID),
                    'Image ID'				=> $iinfo->image->id,
                    'Flavor ID'				=> $iinfo->flavor->id,
                    'Public IP'				=> $ips['remoteIp'] ? $ips['remoteIp'] : $DBServer->remoteIp,
                    'Private IP'			=> $ips['localIp'] ? $ips['localIp'] : $DBServer->localIp,
                    'Status'				=> $iinfo->status,
                    'Name'					=> $iinfo->name,
                    'Host ID'				=> $iinfo->hostId,
                    'Progress'				=> $iinfo->progress
                );

                if ($iinfo->status == 'ERROR') {
                    $retval['Status'] = "{$retval['Status']} (Fault message: {$iinfo->fault->message})";
                }

                if ($iinfo->key_name) {
                    $retval['Key name'] = $iinfo->key_name;
                }

                if ($iinfo->security_groups) {
                    $list = array();
                    foreach ($iinfo->security_groups as $sg)
                        $list[] = $sg->name;

                    $retval['Security Groups'] = implode(", ", $list);
                }

                return $retval;
            }
        }
        catch(Exception $e){}

        return false;
    }

    /**
     launchOptions: imageId
     */
    public function LaunchServer(DBServer $DBServer, Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $config = \Scalr::getContainer()->config;
        if (!$launchOptions) {
            $launchOptions = new Scalr_Server_LaunchOptions();
            $DBRole = DBRole::loadById($DBServer->roleId);

            $launchOptions->imageId = $DBRole->getImageId($this->platform, $DBServer->GetFarmRoleObject()->CloudLocation);
            $launchOptions->serverType = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_OPENSTACK_FLAVOR_ID);
            $launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;

            $launchOptions->userData = $DBServer->GetCloudUserData();
            $launchOptions->userData['region'] = $DBServer->GetFarmRoleObject()->CloudLocation;

            foreach ($launchOptions->userData as $k => $v) {
                if (!$v) {
                    unset($launchOptions->userData[$k]);
                }
            }

            $launchOptions->architecture = 'x86_64';
            $isWindows = ($DBServer->osType == 'windows' || $DBRole->osFamily == 'windows');
        } else {
            $launchOptions->userData = array();
            $isWindows = ($DBServer->osType == 'windows');
        }

        $environment = $DBServer->GetEnvironmentObject();

        $client = $this->getOsClient($environment, $launchOptions->cloudLocation);

        // Prepare user data
        $u_data = "";
        foreach ($launchOptions->userData as $k => $v) {
            $u_data .= "{$k}={$v};";
        }

        $u_data = trim($u_data, ";");

        $personality = new PersonalityList();

        if ($isWindows) {
            $personality->append(new Personality(
                'C:\\Program Files\\Scalarizr\\etc\\private.d\\.user-data',
                base64_encode($u_data)
            ));
        } else {
            if ($DBServer->platform == SERVER_PLATFORMS::ECS) {
                $personality->append(new Personality(
                    '/etc/.scalr-user-data',
                    base64_encode($u_data)
                ));
            } else {
                $personality->append(new Personality(
                    '/etc/scalr/private.d/.user-data',
                    base64_encode($u_data)
                ));
            }
        }

        //Check SecurityGroups
        $securityGroupsEnabled = $this->getConfigVariable(self::EXT_SECURITYGROUPS_ENABLED, $environment, false);
        if ($securityGroupsEnabled === null || $securityGroupsEnabled === false) {
            if ($client->servers->isExtensionSupported(ServersExtension::securityGroups()))
                $securityGroupsEnabled = 1;
            else
                $securityGroupsEnabled = 0;

            $this->setConfigVariable(array(
                self::EXT_SECURITYGROUPS_ENABLED => $securityGroupsEnabled
            ), $environment, false);
        }

        if ($securityGroupsEnabled) {
            $securityGroups = $this->GetServerSecurityGroupsList($DBServer, $client);
            foreach ($securityGroups as $sg) {
                $itm = new stdClass();
                $itm->name = $sg;
                $extProperties['security_groups'][] = $itm;
            }
        }

        //Check key-pairs
        $keyPairsEnabled = $this->getConfigVariable(self::EXT_KEYPAIRS_ENABLED, $environment, false);
        if ($keyPairsEnabled === null || $keyPairsEnabled === false) {
            if ($client->servers->isExtensionSupported(ServersExtension::keypairs()))
                $keyPairsEnabled = 1;
            else
                $keyPairsEnabled = 0;

            $this->setConfigVariable(array(
                self::EXT_KEYPAIRS_ENABLED => $keyPairsEnabled
            ), $environment, false);
        }

        if ($keyPairsEnabled) {
            $sshKey = Scalr_SshKey::init();

            if ($DBServer->status == SERVER_STATUS::TEMPORARY) {
                $keyName = "SCALR-ROLESBUILDER-".SCALR_ID;
                $farmId = 0;
            } else {
                $keyName = "FARM-{$DBServer->farmId}-".SCALR_ID;
                $farmId = $DBServer->farmId;
            }

            if ($sshKey->loadGlobalByName($keyName, $launchOptions->cloudLocation, $DBServer->envId, SERVER_PLATFORMS::OPENSTACK))
                $keyLoaded = true;


            if (!$keyLoaded && !$sshKey->loadGlobalByName($keyName, $launchOptions->cloudLocation, $DBServer->envId, $DBServer->platform)) {
                $result = $client->servers->createKeypair($keyName);

                if ($result->private_key) {
                    $sshKey->farmId = $farmId;
                    $sshKey->clientId = $DBServer->clientId;
                    $sshKey->envId = $DBServer->envId;
                    $sshKey->type = Scalr_SshKey::TYPE_GLOBAL;
                    $sshKey->cloudLocation = $launchOptions->cloudLocation;
                    $sshKey->cloudKeyName = $keyName;
                    $sshKey->platform = $DBServer->platform;

                    $sshKey->setPrivate($result->private_key);
                    $sshKey->setPublic($result->public_key);

                    $sshKey->save();
                }
            }

            $extProperties['key_name'] = $keyName;
        }

        //TODO: newtorks
        $networks = null;

        $osUserData = null;
        $osPersonality = null;
        $userDataMethod = $config->defined("scalr.{$this->platform}.user_data_method") ? $config("scalr.{$this->platform}.user_data_method") : null;
        if (!$userDataMethod || $userDataMethod == 'both' || $userDataMethod == 'personality') {
            $osPersonality = $personality;
        }

        if (!$userDataMethod || $userDataMethod == 'both' || $userDataMethod == 'meta-data') {
            $osUserData = $launchOptions->userData;
        }

        try {
            $result = $client->servers->createServer(
                $DBServer->serverId,
                $launchOptions->serverType,
                $launchOptions->imageId,
                null,
                $osUserData,
                $osPersonality,
                $networks,
                $extProperties
            );

            $DBServer->SetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID, $result->id);
            $DBServer->SetProperty(OPENSTACK_SERVER_PROPERTIES::IMAGE_ID, $launchOptions->imageId);
            $DBServer->SetProperty(OPENSTACK_SERVER_PROPERTIES::FLAVOR_ID, $launchOptions->serverType);
            $DBServer->SetProperty(OPENSTACK_SERVER_PROPERTIES::ADMIN_PASS, $result->adminPass);
            $DBServer->SetProperty(OPENSTACK_SERVER_PROPERTIES::NAME, $DBServer->serverId);
            $DBServer->SetProperty(SERVER_PROPERTIES::ARCHITECTURE, $launchOptions->architecture);
            $DBServer->SetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION, $launchOptions->cloudLocation);
            $DBServer->SetProperty(SERVER_PROPERTIES::SYSTEM_USER_DATA_METHOD, $userDataMethod);

            if ($DBServer->farmRoleId) {
                $ipPool = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_OPENSTACK_IP_POOL);
                if ($ipPool)
                    $DBServer->SetProperty(SERVER_PROPERTIES::SYSTEM_IGNORE_INBOUND_MESSAGES, 1);
            }

            $DBServer->osType = ($isWindows) ? 'windows' : 'linux';

            return $DBServer;
        } catch (Exception $e) {
            if (stripos($e->getMessage(), 'Invalid key_name provided')) {
                $sshKey->delete();
                throw new Exception(sprintf(_("Cannot launch new instance: KeyPair was removed from cloud. Re-generating it."), $e->getMessage()));
            }
            throw new Exception(sprintf(_("Cannot launch new instance. %s"), $e->getMessage()));
        }
    }

    public function GetPlatformAccessData($environment, $DBServer) {
        $accessData = new stdClass();
        $accessData->username = $this->getConfigVariable(self::USERNAME, $environment, true);
        $accessData->apiKey = $this->getConfigVariable(self::API_KEY, $environment, true);
        $accessData->keystoneUrl = $this->getConfigVariable(self::KEYSTONE_URL, $environment, true);
        $accessData->tenantName = $this->getConfigVariable(self::TENANT_NAME, $environment, true);
        $accessData->password = $this->getConfigVariable(self::PASSWORD, $environment, true);
        $accessData->cloudLocation = $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);

        return $accessData;
    }

    public function PutAccessData(DBServer $DBServer, Scalr_Messaging_Msg $message)
    {
        $put = false;
        $put |= $message instanceof Scalr_Messaging_Msg_Rebundle;
        $put |= $message instanceof Scalr_Messaging_Msg_BeforeHostUp;
        $put |= $message instanceof Scalr_Messaging_Msg_HostInitResponse;
        $put |= $message instanceof Scalr_Messaging_Msg_Mysql_PromoteToMaster;
        $put |= $message instanceof Scalr_Messaging_Msg_Mysql_CreateDataBundle;
        $put |= $message instanceof Scalr_Messaging_Msg_Mysql_CreateBackup;
        $put |= $message instanceof Scalr_Messaging_Msg_BeforeHostTerminate;

        $put |= $message instanceof Scalr_Messaging_Msg_DbMsr_PromoteToMaster;
        $put |= $message instanceof Scalr_Messaging_Msg_DbMsr_CreateDataBundle;
        $put |= $message instanceof Scalr_Messaging_Msg_DbMsr_CreateBackup;
        $put |= $message instanceof Scalr_Messaging_Msg_DbMsr_NewMasterUp;

        if ($put) {
            $environment = $DBServer->GetEnvironmentObject();
            $message->platformAccessData = $this->GetPlatformAccessData($environment, $DBServer);
        }

    }

    public function ClearCache ()
    {
        $this->instancesListCache = array();
    }

    private function GetServerSecurityGroupsList(DBServer $DBServer, $osClient)
    {
        $retval = array('default');

        try {
            //get list
            $list = $osClient->servers->securityGroups->list();

            $sgroups = array();
            foreach ($list as $sg)
                $sgroups[strtolower($sg->name)] = $sg;

            unset($list);
        }
        catch(Exception $e) {
            throw new Exception("GetServerSecurityGroupsList failed: {$e->getMessage()}");
        }

        /**** Security group for role builder ****/
        /*
        if ($DBServer->status == SERVER_STATUS::TEMPORARY) {
            if (!$sgroups['scalr-rb-system']) {
                try {
                    $osClient->servers->securityGroups->create('scalr-rb-system', _("Security group for Roles Builder"));
                }
                catch(Exception $e) {
                    throw new Exception("GetServerSecurityGroupsList failed: {$e->getMessage()}");
                }

                $IpPermissionSet = new IpPermissionSetType();

                $group_rules = array(
                    array('rule' => 'tcp:22:22'),
                    array('rule' => 'tcp:8013:8013'), // For Scalarizr
                    array('rule' => 'udp:8014:8014') // For Scalarizr
                );

                foreach (\Scalr::config('scalr.aws.ip_pool') as $ip) {
                    foreach ($group_rules as $rule) {
                        $group_rule = explode(":", $rule["rule"]);
                        $IpPermissionSet->AddItem($group_rule[0], $group_rule[1], $group_rule[2], null, array($ip));
                    }
                }

                //TODO:
            }

            array_push($retval, 'scalr-rb-system');

            return $retval;
        }
        */
        /**********************************/

        // Add Role security group
        $roleSecurityGroup = "scalr-role.{$DBServer->farmRoleId}";
        $farmSecurityGroup = "scalr-farm.{$DBServer->farmId}";


        /****
         * SCALR IP POOL SECURITY GROUP
         */
        /*
        if ($sgroups[\Scalr::config('scalr.aws.security_group_name')]) {

            $osClient->servers->securityGroups->delete($sgroups[\Scalr::config('scalr.aws.security_group_name')]->id);

            unset($sgroups[\Scalr::config('scalr.aws.security_group_name')]);
        }
        */


        if (!$sgroups[\Scalr::config('scalr.aws.security_group_name')]) {
            try {
                $group = $osClient->servers->securityGroups->create(\Scalr::config('scalr.aws.security_group_name'), _("Security group for Roles Builder"));
                $groupId = $group->id;
            }
            catch(Exception $e) {
                throw new Exception("GetServerSecurityGroupsList failed on scalr.ip-pool: {$e->getMessage()}");
            }

            /*
            $sRules = array(
                array('rule' => 'tcp:8008:8013'), // For Scalarizr
                array('rule' => 'udp:8014:8014'), // For Scalarizr
                array('rule' => 'tcp:3306:3306') // For Replication check
            );

            $rules = array();
            foreach (\Scalr::config('scalr.aws.ip_pool') as $ip) {
                foreach ($sRules as $rule) {

                    $group_rule = explode(":", $rule['rule']);

                    $rule = new stdClass();
                    $rule->security_group_rule = new stdClass();

                    $rule->security_group_rule->ip_protocol = $group_rule[0];
                    $rule->security_group_rule->from_port = $group_rule[1];
                    $rule->security_group_rule->to_port = $group_rule[2];
                    $rule->security_group_rule->cidr = $ip;
                    $rule->security_group_rule->parent_group_id = $groupId;

                    $rules[] = $rule;
                }
            }

           // foreach ($rules as $rule)
           */

           //Temporary solution because of API requests rate limit
            $rule = new stdClass();

            $rule->ip_protocol = "tcp";
            $rule->from_port = 1;
            $rule->to_port = 65535;
            $rule->cidr = "0.0.0.0/0";
            $rule->parent_group_id = $groupId;

            $res = $osClient->servers->securityGroups->addRule($rule);

            $rule = new stdClass();

            $rule->ip_protocol = "udp";
            $rule->from_port = 1;
            $rule->to_port = 65535;
            $rule->cidr = "0.0.0.0/0";
            $rule->parent_group_id = $groupId;

            $res = $osClient->servers->securityGroups->addRule($rule);
        }

        array_push($retval, \Scalr::config('scalr.aws.security_group_name'));
        /**********************************************/

        return $retval;
    }
}
