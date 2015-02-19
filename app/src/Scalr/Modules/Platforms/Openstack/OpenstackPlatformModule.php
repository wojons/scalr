<?php

namespace Scalr\Modules\Platforms\Openstack;

use \DBServer;
use \BundleTask;
use Exception;
use Scalr\Service\OpenStack\Exception\InstanceNotFoundException;
use Scalr\Service\OpenStack\Exception\NotFoundException;
use Scalr\Service\OpenStack\Services\Servers\Type\Personality;
use Scalr\Service\OpenStack\Services\Servers\Type\PersonalityList;
use Scalr\Service\OpenStack\Services\Servers\Type\ServersExtension;
use Scalr\Service\OpenStack\Services\Servers\Type\RebootType;
use Scalr\Service\OpenStack\Services\Servers\Type\NetworkList;
use Scalr\Service\OpenStack\Services\Servers\Type\Network;
use Scalr\Service\OpenStack\Type\PaginationInterface;
use Scalr\Modules\Platforms\Openstack\Adapters\StatusAdapter;
use Scalr\Modules\Platforms\AbstractOpenstackPlatformModule;
use Scalr\Service\OpenStack\Client\AuthToken;
use Scalr\Service\OpenStack\Services\Network\Type\CreateSecurityGroupRule;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\CloudLocation;

class OpenstackPlatformModule extends AbstractOpenstackPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{

    /** Properties **/
    const USERNAME      = 'username';
    const API_KEY       = 'api_key';
    const PASSWORD      = 'password';
    const TENANT_NAME   = 'tenant_name';
    const KEYSTONE_URL  = 'keystone_url';
    const SSL_VERIFYPEER = 'ssl_verifypeer';

    /** System Properties **/
    const AUTH_TOKEN    = 'auth_token';
    const EXT_KEYPAIRS_ENABLED = 'ext.keypairs_enabled';
    const EXT_SECURITYGROUPS_ENABLED = 'ext.securitygroups_enabled';
    const EXT_SWIFT_ENABLED = 'ext.swift_enabled';
    const EXT_CINDER_ENABLED = 'ext.cinder_enabled';
    const EXT_FLOATING_IPS_ENABLED = 'ext.floating_ips_enabled';
    const EXT_LBAAS_ENABLED = 'ext.lbaas_enabled';

    public $instancesListCache = array();
    public $instancesDetailsCache = array();

    public $debugLog;

    protected $resumeStrategy = \Scalr_Role_Behavior::RESUME_STRATEGY_REBOOT;

    /**
     * @return \Scalr\Service\OpenStack\OpenStack
     */
    public function getOsClient(\Scalr_Environment $environment, $cloudLocation)
    {
        $client =  $environment->openstack($this->platform, $cloudLocation);
        if (defined("OPENSTACK_DEBUG") && OPENSTACK_DEBUG == 1)
            $client->setDebug(true);

        return $client;
    }

    /**
     * Constuctor
     *
     * @param   string    $platform The name of the openstack based platform
     */
    public function __construct($platform = \SERVER_PLATFORMS::OPENSTACK)
    {
        parent::__construct($platform);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getLocations()
     */
    public function getLocations(\Scalr_Environment $environment = null)
    {
        if ($environment === null || !$environment->isPlatformEnabled($this->platform)) {
            return array();
        }

        try {
            $client = $environment->openstack($this->platform, "fakeRegion");
            foreach ($client->listZones() as $zone) {
                $retval[$zone->name] = ucfirst($this->platform) . " / {$zone->name}";
            }
        } catch (\Exception $e) {
            return array();
        }

        return $retval;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::hasCloudPrices()
     */
    public function hasCloudPrices(\Scalr_Environment $env)
    {
        if (!$this->container->analytics->enabled) return false;

        $platform = $this->platform ?: \SERVER_PLATFORMS::OPENSTACK;

        $url = $this->getConfigVariable($this::KEYSTONE_URL, $env);

        if (empty($url)) return false;

        return $this->container->analytics->prices->hasPriceForUrl($platform, $url) ?: $url;
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

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerCloudLocation()
     */
    public function GetServerCloudLocation(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerID()
     */
    public function GetServerID(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerFlavor()
     */
    public function GetServerFlavor(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::FLAVOR_ID);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::IsServerExists()
     */
    public function IsServerExists(DBServer $DBServer, $debug = false)
    {
        return in_array(
            $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID),
            array_keys($this->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION)))
        );
    }

    public function determineServerIps(\Scalr\Service\OpenStack\OpenStack $client, $server)
    {
        $config = \Scalr::getContainer()->config;

        $publicNetworkName = 'public';
        $privateNetworkName = 'private';

        if (is_array($server->addresses->{$publicNetworkName})) {
            foreach ($server->addresses->{$publicNetworkName} as $addr)
            if ($addr->version == 4) {
                $remoteIp = $addr->addr;
                break;
            }
        }

        if (is_array($server->addresses->{$privateNetworkName})) {
            foreach ($server->addresses->{$privateNetworkName} as $addr)
            if ($addr->version == 4) {
                $localIp = $addr->addr;
                break;
            }
        }

        if (!$localIp)
            $localIp = $remoteIp;

        if (!$localIp && !$remoteIp) {
            $addresses = (array)$server->addresses;
            $addresses = array_shift($addresses);
            if (is_array($addresses)) {
                foreach ($addresses as $address) {
                    if ($address->version == 4) {
                        if(isset($address->{'OS-EXT-IPS:type'}) && $address->{'OS-EXT-IPS:type'}  == 'floating')
                            $remoteIp = $address->addr;
                        else {
                            if (strpos($address->addr, "10.") === 0 || strpos($address->addr, "192.168") === 0)
                                $localIp = $address->addr;
                            else
                                $remoteIp = $address->addr;
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

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerIPAddresses()
     */
    public function GetServerIPAddresses(DBServer $DBServer)
    {
        $id = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID);
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

        if (!$this->instancesDetailsCache[$id]) {
            $result = $client->servers->getServerDetails($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID));
            $this->instancesDetailsCache[$id] = new \stdClass();
            $this->instancesDetailsCache[$id]->addresses = $result->addresses;
        }

        return $this->determineServerIps($client, $this->instancesDetailsCache[$id]);
    }

    public function GetServersList(\Scalr_Environment $environment, $cloudLocation, $skipCache = false)
    {
        if (!isset($this->instancesListCache[$environment->id]))
            $this->instancesListCache[$environment->id] = array();

        if (!isset($this->instancesListCache[$environment->id][$cloudLocation]))
            $this->instancesListCache[$environment->id][$cloudLocation] = array();

        if (!$this->instancesListCache[$environment->id] || !$this->instancesListCache[$environment->id][$cloudLocation] || $skipCache) {
            $client = $this->getOsClient($environment, $cloudLocation);
            $result = $client->servers->list();
            do {
                foreach ($result as $server) {
                    $this->instancesListCache[$environment->id][$cloudLocation][$server->id] = $server->status;
                    if (!$this->instancesDetailsCache[$server->id]) {
                        $this->instancesDetailsCache[$server->id] = new \stdClass();
                        $this->instancesDetailsCache[$server->id]->addresses = $server->addresses;
                    }
                }
            } while (false !== ($result = $result->getNextPage()));
        }
        return $this->instancesListCache[$environment->id][$cloudLocation];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerRealStatus()
     */
    public function GetServerRealStatus(DBServer $DBServer)
    {
        $cloudLocation = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
        $environment = $DBServer->GetEnvironmentObject();

        $iid = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID);
        if (!$iid) {
            $status = 'not-found';
        } elseif (!$this->instancesListCache[$environment->id][$cloudLocation][$iid]) {
            $osClient = $this->getOsClient($environment, $cloudLocation);

            try {
                $result = $osClient->servers->getServerDetails($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID));
                $status = $result->status;
            } catch(NotFoundException $e) {
                $status = 'not-found';
            }
        }
        else
        {
            $status = $this->instancesListCache[$environment->id][$cloudLocation][$DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID)];
        }

        return StatusAdapter::load($status);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::ResumeServer()
     */
    public function ResumeServer(DBServer $DBServer)
    {
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

        if ($DBServer->GetRealStatus()->getName() == 'SHUTOFF')
            $info = $client->servers->osStart($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID));
        else
            $info = $client->servers->resume($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID));

        parent::ResumeServer($DBServer);

        return true;
    }


    public function SuspendServer(DBServer $DBServer)
    {
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

        try {
            $info = $client->servers->suspend($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID));
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::TerminateServer()
     */
    public function TerminateServer(DBServer $DBServer)
    {
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

        try {
            $info = $client->servers->deleteServer($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID));
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RebootServer()
     */
    public function RebootServer(DBServer $DBServer, $soft = true)
    {
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

        try {
            if ($soft)
                $client->servers->rebootServer($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID), RebootType::soft());
            else
                $client->servers->rebootServer($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID), RebootType::hard());
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RemoveServerSnapshot()
     */
    public function RemoveServerSnapshot(Image $image)
    {
        if (! $image->getEnvironment())
            return true;

        try {
            $cloudLocation = $image->cloudLocation;
            if ($image->cloudLocation == '') {
                $locations = $this->getLocations($image->getEnvironment());
                $cloudLocation = array_keys($locations)[0];
            }

            $osClient = $image->getEnvironment()->openstack($image->platform, $cloudLocation);
            $osClient->servers->images->delete($image->id);
        } catch(\Exception $e) {
            if (stristr($e->getMessage(), "Image not found") || stristr($e->getMessage(), "Cannot destroy a destroyed snapshot")) {
                //DO NOTHING
            } else
                throw $e;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CheckServerSnapshotStatus()
     */
    public function CheckServerSnapshotStatus(BundleTask $BundleTask)
    {
        try {
            $DBServer = DBServer::LoadByID($BundleTask->serverId);

            if ($BundleTask->bundleType != \SERVER_SNAPSHOT_CREATION_TYPE::OSTACK_WINDOWS)
                return;

            $BundleTask->status = \SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;

            $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

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
                    trigger_error(sprintf("Unexpected server snapshot status %s", serialize($info)), E_USER_WARNING);
                    break;
            }
        } catch (\Exception $e) {
            $BundleTask->SnapshotCreationFailed($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CreateServerSnapshot()
     */
    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        $DBServer = DBServer::LoadByID($BundleTask->serverId);

        if ($BundleTask->osFamily == 'windows' || $DBServer->osType == 'windows') {
        	$BundleTask->bundleType = \SERVER_SNAPSHOT_CREATION_TYPE::OSTACK_WINDOWS;
        	/*
            if ($BundleTask->status == \SERVER_SNAPSHOT_CREATION_STATUS::PENDING) {
                $BundleTask->bundleType = \SERVER_SNAPSHOT_CREATION_TYPE::OSTACK_WINDOWS;
                $BundleTask->Log(sprintf(_("Selected platfrom snapshoting type: %s"), $BundleTask->bundleType));
                $BundleTask->status = \SERVER_SNAPSHOT_CREATION_STATUS::PREPARING;
                $BundleTask->Save();
                try {
                    $msg = $DBServer->SendMessage(new \Scalr_Messaging_Msg_Win_PrepareBundle($BundleTask->id), false, true);
                    if ($msg) {
                        $BundleTask->Log(sprintf(
                            _("PrepareBundle message sent. MessageID: %s. Bundle task status changed to: %s"),
                            $msg->messageId, $BundleTask->status
                        ));
                    } else {
                        throw new \Exception("Cannot send message");
                    }
                } catch (\Exception $e) {
                    $BundleTask->SnapshotCreationFailed("Cannot send PrepareBundle message to server.");

                    return false;
                }
            } elseif ($BundleTask->status == \SERVER_SNAPSHOT_CREATION_STATUS::PREPARING) {
            */
            $BundleTask->Log(sprintf(_("Selected platform snapshot type: %s"), $BundleTask->bundleType));
            $createImage = true;
            /*}*/
        } else {
            $BundleTask->bundleType = \SERVER_SNAPSHOT_CREATION_TYPE::OSTACK_LINUX;
            $createImage = false;

            $BundleTask->status = \SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;

            $msg = new \Scalr_Messaging_Msg_Rebundle(
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
                $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));

                $imageId = $client->servers->createImage(
                    $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID),
                    $BundleTask->roleName."-".date("YmdHi")
                );

                $BundleTask->status = \SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
                $BundleTask->snapshotId = $imageId;

                $BundleTask->Log(sprintf(_("Snapshot creating initialized (ImageID: %s). Bundle task status changed to: %s"),
                    $BundleTask->snapshotId, $BundleTask->status
                ));

                $BundleTask->setDate('started');
                $BundleTask->Save();
            } catch (\Exception $e) {
                $BundleTask->SnapshotCreationFailed($e->getMessage());
                return;
            }
        }

        return true;
    }

    protected function ApplyAccessData(\Scalr_Messaging_Msg $msg)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerConsoleOutput()
     */
    public function GetServerConsoleOutput(DBServer $DBServer)
    {
        if ($DBServer->GetRealStatus()->getName() != 'ACTIVE')
            return false;

        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));
        if ($client->servers->isExtensionSupported(ServersExtension::consoleOutput())) {
            return $client->servers->getConsoleOutput($DBServer->GetCloudServerID(), 200);
        }
        else
            throw new \Exception("Not supported by Openstack");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerExtendedInformation()
     */
    public function GetServerExtendedInformation(DBServer $DBServer, $extended = false)
    {
        try {
            try	{
                $cloudLocation = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
                $serverId = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID);
                $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $cloudLocation);
                $iinfo = $client->servers->getServerDetails($serverId);
                $ips = $this->GetServerIPAddresses($DBServer);
            } catch (\Exception $e) {}

            //TODO: Remove as soon as we will confirm that there is no longer issues with API
            if ($client->getConfig()->getAuthToken() instanceof AuthToken) {
                $log = array(
                    'authTokenTenantId' => $client->getConfig()->getAuthToken()->getTenantId(),
                    'authTokenTenantName' => $client->getConfig()->getAuthToken()->getTenantName(),
                       'scalrTenantName' => $client->getConfig()->getTenantName(),
                       'scalrUsername' => $client->getConfig()->getUsername()
                );

                $this->debugLog = sprintf("[OPENSTACK_DEBUG] Checking ServerID %s: %s,%s,%s,%s",
                    $serverId,
                    $log['authTokenTenantId'],
                       $log['authTokenTenantName'],
                       $log['scalrTenantName'],
                       $log['scalrUsername']
                );
            }

            if ($iinfo) {
                $retval =  array(
                    'Cloud Server ID' => $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID),
                    'Image ID'        => $iinfo->image->id,
                    'Flavor ID'       => $iinfo->flavor->id,
                    'Public IP'       => $ips['remoteIp'] ? $ips['remoteIp'] : $DBServer->remoteIp,
                    'Private IP'      => $ips['localIp'] ? $ips['localIp'] : $DBServer->localIp,
                    'Status'          => $iinfo->status,
                    'Name'            => $iinfo->name,
                    'Host ID'         => $iinfo->hostId,
                    'Progress'        => $iinfo->progress
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
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::LaunchServer()
     */
    public function LaunchServer(DBServer $DBServer, \Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $config = \Scalr::getContainer()->config;
        $environment = $DBServer->GetEnvironmentObject();
        $governance = new \Scalr_Governance($environment->id);

        if (!$launchOptions) {
            $launchOptions = new \Scalr_Server_LaunchOptions();
            $DBRole = $DBServer->GetFarmRoleObject()->GetRoleObject();

            $launchOptions->imageId = $DBRole->__getNewRoleObject()->getImage($this->platform, $DBServer->GetCloudLocation())->imageId;
            $launchOptions->serverType = $DBServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_OPENSTACK_FLAVOR_ID);
            $launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;

            $launchOptions->userData = $DBServer->GetCloudUserData();
            $launchOptions->userData['platform'] = 'openstack';
            $launchOptions->userData['region'] = $launchOptions->cloudLocation;

            $launchOptions->networks = @json_decode($DBServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_OPENSTACK_NETWORKS));
            $gevernanceNetworks = $governance->getValue($this->platform, 'openstack.networks');
            if (count($launchOptions->networks) == 0 && $gevernanceNetworks) {
                $launchOptions->networks = $gevernanceNetworks[$launchOptions->cloudLocation];
            }

            foreach ($launchOptions->userData as $k => $v) {
                if (!$v)
                    unset($launchOptions->userData[$k]);
            }

            $launchOptions->architecture = 'x86_64';
            $isWindows = ($DBServer->osType == 'windows' || $DBRole->osFamily == 'windows');

            if ($DBServer->GetFarmRoleObject()->GetSetting('openstack.boot_from_volume') == 1) {
                $deviceMapping = new \stdClass();
                $deviceMapping->device_name = 'vda';
                $deviceMapping->source_type = 'image';
                $deviceMapping->destination_type = 'volume';
                $deviceMapping->delete_on_termination = true;
                $deviceMapping->guest_format = null;
                $deviceMapping->volume_size = 10;
                $deviceMapping->uuid = $launchOptions->imageId;
                $deviceMapping->boot_index = 0;
            }

            $customUserData = $DBServer->GetFarmRoleObject()->GetSetting('base.custom_user_data');
            
            $serverNameFormat = $governance->getValue($DBServer->platform, \Scalr_Governance::OPENSTACK_INSTANCE_NAME_FORMAT);
            if (!$serverNameFormat)
                $serverNameFormat = $DBServer->GetFarmRoleObject()->GetSetting(\Scalr_Role_Behavior::ROLE_INSTANCE_NAME_FORMAT);
            
        } else {
            $launchOptions->userData = array();
            $customUserData = false;

            if (!$launchOptions->networks) {
                $launchOptions->networks = array();
            }
            $isWindows = ($DBServer->osType == 'windows');
        }

        $client = $this->getOsClient($environment, $launchOptions->cloudLocation);

        // Prepare user data
        $u_data = "";
        foreach ($launchOptions->userData as $k => $v)
            $u_data .= "{$k}={$v};";

        $u_data = trim($u_data, ";");
        if ($customUserData) {
            $repos = $DBServer->getScalarizrRepository();

            $extProperties["user_data"] = base64_encode(str_replace(array(
                '{SCALR_USER_DATA}',
                '{RPM_REPO_URL}',
                '{DEB_REPO_URL}'
            ), array(
                $u_data,
                $repos['rpm_repo_url'],
                $repos['deb_repo_url']
            ), $customUserData));
        }

        $personality = new PersonalityList();

        if ($isWindows) {
            $personality->append(new Personality(
                'C:\\Program Files\\Scalarizr\\etc\\private.d\\.user-data',
                base64_encode($u_data)
            ));
        } else {
            if ($DBServer->platform == \SERVER_PLATFORMS::ECS) {
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
        $extProperties['security_groups'] = array();
        if ($securityGroupsEnabled) {
            $securityGroups = $this->GetServerSecurityGroupsList($DBServer, $client, $governance);
            foreach ($securityGroups as $sg) {
                $itm = new \stdClass();
                $itm->name = $sg;
                $extProperties['security_groups'][] = $itm;
            }
        }

        if ($deviceMapping)
            $extProperties['block_device_mapping_v2'][] = $deviceMapping;

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
            $sshKey = \Scalr_SshKey::init();

            if ($DBServer->status == \SERVER_STATUS::TEMPORARY) {
                $keyName = "SCALR-ROLESBUILDER-".SCALR_ID;
                $farmId = NULL;
            } else {
                $keyName = "FARM-{$DBServer->farmId}-".SCALR_ID;
                $farmId = $DBServer->farmId;
            }

            if ($sshKey->loadGlobalByName($keyName, $launchOptions->cloudLocation, $DBServer->envId, \SERVER_PLATFORMS::OPENSTACK))
                $keyLoaded = true;


            if (!$keyLoaded && !$sshKey->loadGlobalByName($keyName, $launchOptions->cloudLocation, $DBServer->envId, $DBServer->platform)) {
                $result = $client->servers->createKeypair($keyName);

                if ($result->private_key) {
                    $sshKey->farmId = $farmId;
                    $sshKey->envId = $DBServer->envId;
                    $sshKey->type = \Scalr_SshKey::TYPE_GLOBAL;
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
        $networks = new NetworkList();
        foreach ((array)$launchOptions->networks as $network) {
            if ($network)
                $networks->append(new Network($network));
        }

        $osUserData = null;
        $osPersonality = null;
        $userDataMethod = $config->defined("scalr.{$this->platform}.user_data_method") ? $config("scalr.{$this->platform}.user_data_method") : null;
        if (!$userDataMethod || $userDataMethod == 'both' || $userDataMethod == 'personality') {
            $osPersonality = $personality;
        }

        if (!$userDataMethod || $userDataMethod == 'both' || $userDataMethod == 'meta-data' || $isWindows) {
            $osUserData = $launchOptions->userData;
        }

        
        $serverName = ($serverNameFormat) ? $DBServer->applyGlobalVarsToValue($serverNameFormat) : $DBServer->serverId;
        
        try {
            $result = $client->servers->createServer(
                $serverName,
                $launchOptions->serverType,
                $launchOptions->imageId,
                null,
                $osUserData,
                $osPersonality,
                $networks,
                $extProperties
            );

            $DBServer->SetProperties([
                \OPENSTACK_SERVER_PROPERTIES::SERVER_ID      => $result->id,
                \OPENSTACK_SERVER_PROPERTIES::IMAGE_ID       => $launchOptions->imageId,
                \OPENSTACK_SERVER_PROPERTIES::FLAVOR_ID      => $launchOptions->serverType,
                \OPENSTACK_SERVER_PROPERTIES::ADMIN_PASS     => $result->adminPass,
                \OPENSTACK_SERVER_PROPERTIES::NAME           => $DBServer->serverId,
                \SERVER_PROPERTIES::ARCHITECTURE             => $launchOptions->architecture,
                \OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $launchOptions->cloudLocation,
                \SERVER_PROPERTIES::SYSTEM_USER_DATA_METHOD  => $userDataMethod,
            ]);

            if ($DBServer->farmRoleId) {
                $ipPool = $DBServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_OPENSTACK_IP_POOL);
                if ($ipPool)
                    $DBServer->SetProperty(\SERVER_PROPERTIES::SYSTEM_IGNORE_INBOUND_MESSAGES, 1);
                }

            $DBServer->setOsType($isWindows ? 'windows' : 'linux');
            $DBServer->cloudLocation = $launchOptions->cloudLocation;
            $DBServer->cloudLocationZone = ""; // Not supported by openstack
            $DBServer->imageId = $launchOptions->imageId;

            return $DBServer;
        } catch (\Exception $e) {
            if (stripos($e->getMessage(), 'Invalid key_name provided')) {
                $sshKey->delete();
                throw new \Exception(sprintf(_("Cannot launch new instance: KeyPair was removed from cloud. Re-generating it."), $e->getMessage()));
            }
            throw new \Exception(sprintf(_("Cannot launch new instance. %s"), $e->getMessage()));
        }
    }

    public function GetPlatformAccessData(\Scalr_Environment $environment, DBServer $DBServer)
    {
        $accessData = new \stdClass();
        $accessData->username = $this->getConfigVariable(self::USERNAME, $environment, true);
        $accessData->apiKey = $this->getConfigVariable(self::API_KEY, $environment, true);

        // We need regional keystone //
        $os = $environment->openstack($this->platform, $DBServer->cloudLocation);
        $os->listZones();
        $url = $os->getConfig()->getIdentityEndpoint();
        $accessData->keystoneUrl = $url;
        //////////////////////////////

        $accessData->tenantName = $this->getConfigVariable(self::TENANT_NAME, $environment, true);
        $accessData->password = $this->getConfigVariable(self::PASSWORD, $environment, true);
        $accessData->cloudLocation = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
        $accessData->sslVerifyPeer = $this->getConfigVariable(self::SSL_VERIFYPEER, $environment, true);

        return $accessData;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::PutAccessData()
     */
    public function PutAccessData(DBServer $DBServer, \Scalr_Messaging_Msg $message)
    {
        $put = false;
        $put |= $message instanceof \Scalr_Messaging_Msg_Rebundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_BeforeHostUp;
        $put |= $message instanceof \Scalr_Messaging_Msg_HostInitResponse;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_PromoteToMaster;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_CreateDataBundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_CreateBackup;
        $put |= $message instanceof \Scalr_Messaging_Msg_BeforeHostTerminate;

        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_PromoteToMaster;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_CreateDataBundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_CreateBackup;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_NewMasterUp;

        if ($put) {
            $environment = $DBServer->GetEnvironmentObject();
            $message->platformAccessData = $this->GetPlatformAccessData($environment, $DBServer);
        }

    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::ClearCache()
     */
    public function ClearCache()
    {
        $this->instancesListCache = [];
        $this->instancesDetailsCache = [];
    }

    private function GetServerSecurityGroupsList(DBServer $DBServer, \Scalr\Service\OpenStack\OpenStack $osClient, \Scalr_Governance $governance = null)
    {
        $retval = array();
        $checkGroups = array();
        $sgGovernance = true;
        $allowAdditionalSgs = true;

        if ($governance) {
            $sgs = $governance->getValue($DBServer->platform, \Scalr_Governance::OPENSTACK_SECURITY_GROUPS);
            if ($sgs !== null) {
                $governanceSecurityGroups = @explode(",", $sgs);
                if (!empty($governanceSecurityGroups)) {
                    foreach ($governanceSecurityGroups as $sg) {
                        if ($sg != '')
                            array_push($checkGroups, trim($sg));
                    }
                }

                $sgGovernance = false;
                $allowAdditionalSgs = $governance->getValue($DBServer->platform, \Scalr_Governance::OPENSTACK_SECURITY_GROUPS, 'allow_additional_sec_groups');
            }
        }

        if (!$sgGovernance || $allowAdditionalSgs) {
            if ($DBServer->farmRoleId != 0) {
                $dbFarmRole = $DBServer->GetFarmRoleObject();
                if ($dbFarmRole->GetSetting(\DBFarmRole::SETTING_OPENSTACK_SECURITY_GROUPS_LIST) !== null) {
                    // New SG management
                    $sgs = @json_decode($dbFarmRole->GetSetting(\DBFarmRole::SETTING_OPENSTACK_SECURITY_GROUPS_LIST));
                    if (!empty($sgs)) {
                        foreach ($sgs as $sg) {
                            array_push($checkGroups, $sg);
                        }
                    }
                } else {
                    // Old SG management
                    array_push($checkGroups, 'default');
                    array_push($checkGroups, \Scalr::config('scalr.aws.security_group_name'));
                }
            } else
                array_push($checkGroups, 'scalr-rb-system');
        }

        try {
        	$list = $osClient->listSecurityGroups();
            do {
                foreach ($list as $sg) {
                    $sgroups[strtolower($sg->name)] = $sg;
                    $sgroupIds[strtolower($sg->id)] = $sg;
                }
                if ($list instanceof PaginationInterface) {
                    $list = $list->getNextPage();
                } else {
                    $list = false;
                }
            } while ($list !== false);
            unset($list);
        } catch (\Exception $e) {
            throw new \Exception("GetServerSecurityGroupsList failed: {$e->getMessage()}");
        }

        foreach ($checkGroups as $groupName) {
            // || !in_array($groupName, array('scalr-rb-system', 'default', \Scalr::config('scalr.aws.security_group_name')))
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/si', $groupName)) {
                if (isset($sgroupIds[strtolower($groupName)]))
                    $groupName = $sgroupIds[$groupName]->name;
                else
                   throw new \Exception(sprintf(_("Security group '%s' is not found (1)"), $groupName));
            }

            // Check default SG
            if ($groupName == 'default') {
                array_push($retval, $groupName);

                // Check Roles builder SG
            } elseif ($groupName == 'scalr-rb-system' || $groupName == \Scalr::config('scalr.aws.security_group_name')) {
                if (!isset($sgroups[strtolower($groupName)])) {
                    try {
                        $group = $osClient->createSecurityGroup($groupName, _("Scalr system security group"));
                        $groupId = $group->id;
                    }
                    catch (\Exception $e) {
                            throw new \Exception("GetServerSecurityGroupsList failed on scalr.ip-pool: {$e->getMessage()}");
                    }

                    $r = new CreateSecurityGroupRule($groupId);
                    $r->direction = 'ingress';
                    $r->protocol = 'tcp';
                    $r->port_range_min = 1;
                    $r->port_range_max = 65535;
                    $r->remote_ip_prefix = "0.0.0.0/0";

                    $res = $osClient->createSecurityGroupRule($r);

                    $r = new CreateSecurityGroupRule($groupId);
                    $r->direction = 'ingress';
                    $r->protocol = 'udp';
                    $r->port_range_min = 1;
                    $r->port_range_max = 65535;
                    $r->remote_ip_prefix = "0.0.0.0/0";

                    $res = $osClient->createSecurityGroupRule($r);
                }
                array_push($retval, $groupName);
            } else {
                if (!isset($sgroups[strtolower($groupName)])) {
                    throw new \Exception(sprintf(_("Security group '%s' is not found (2)"), $groupName));
                } else
                    array_push($retval, $groupName);
            }
        }

        return $retval;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceTypes()
     */
    public function getInstanceTypes(\Scalr_Environment $env = null, $cloudLocation = null, $details = false)
    {
        if (!($env instanceof \Scalr_Environment) || empty($cloudLocation)) {
            throw new \InvalidArgumentException(sprintf(
                "Method %s requires both environment object and cloudLocation to be specified.", __METHOD__
            ));
        }

        $ret = [];
        $detailed = [];

        //Trying to retrieve instance types from the cache
        $url = $this->getConfigVariable($this::KEYSTONE_URL, $env);
        $collection = $this->getCachedInstanceTypes($this->platform, $url, $cloudLocation);

        if ($collection === false || $collection->count() == 0) {
            //No cache. Fetching data from the cloud
            $client = $env->openstack($this->platform, $cloudLocation);

            foreach ($client->servers->listFlavors() as $flavor) {
                $detailed[(string)$flavor->id] = array(
                    'name'  => (string) $flavor->name,
                    'ram'   => (string) $flavor->ram,
                    'vcpus' => (string) $flavor->vcpus,
                    'disk'  => (string) $flavor->disk,
                    'type'  => 'HDD'
                );

                if (!$details) {
                    $ret[(string)$flavor->id] = (string) $flavor->name;
                } else {
                    $ret[(string)$flavor->id] = $detailed[(string)$flavor->id];
                }
            }

            //Refreshes/creates a cache
            CloudLocation::updateInstanceTypes($this->platform, $url, $cloudLocation, $detailed);
        } else {
            //Takes data from cache
            foreach ($collection as $cloudInstanceType) {
                /* @var $cloudInstanceType \Scalr\Model\Entity\CloudInstanceType */
                if (!$details) {
                    $ret[$cloudInstanceType->instanceTypeId] = $cloudInstanceType->name;
                } else {
                    $ret[$cloudInstanceType->instanceTypeId] = $cloudInstanceType->getProperties();
                }
            }
        }

        return $ret;
    }

    /**
     * Gets endpoint url for private clouds
     *
     * @param \Scalr_Environment $env       The scalr environment object
     * @param string             $group     optional The group name for eucaliptus
     * @return string Returns endpoint url for private clouds.
     */
    public function getEndpointUrl(\Scalr_Environment $env, $group = null)
    {
        return $env->getPlatformConfigValue($this->platform . "." . self::KEYSTONE_URL);
    }

}
