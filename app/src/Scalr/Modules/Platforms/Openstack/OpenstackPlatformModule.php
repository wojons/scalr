<?php

namespace Scalr\Modules\Platforms\Openstack;

use \DBServer;
use \BundleTask;
use Exception;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity\CloudInstanceType;
use Scalr\Service\OpenStack\Client\RestClient;
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
use Scalr\Service\OpenStack\Services\Network\Type\CreateSecurityGroupRule;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\SshKey;
use Scalr\Model\Entity\CloudLocation;
use \OPENSTACK_SERVER_PROPERTIES;
use Scalr\Service\OpenStack\OpenStack;
use Scalr\Model\Entity;

class OpenstackPlatformModule extends AbstractOpenstackPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{

    /** Properties **/
    const USERNAME      = 'username';
    const API_KEY       = 'api_key';
    const PASSWORD      = 'password';
    const TENANT_NAME   = 'tenant_name';
    const DOMAIN_NAME   = 'domain_name';
    const KEYSTONE_URL  = 'keystone_url';
    const SSL_VERIFYPEER = 'ssl_verifypeer';
    const IDENTITY_VERSION   = 'identity_version';

    /** System Properties **/
    const AUTH_TOKEN    = 'auth_token';
    const EXT_KEYPAIRS_ENABLED = 'ext.keypairs_enabled';
    const EXT_CONFIG_DRIVE_ENABLED = 'ext.configdrive_enabled';
    const EXT_SECURITYGROUPS_ENABLED = 'ext.securitygroups_enabled';
    const EXT_SWIFT_ENABLED = 'ext.swift_enabled';
    const EXT_CINDER_ENABLED = 'ext.cinder_enabled';
    const EXT_FLOATING_IPS_ENABLED = 'ext.floating_ips_enabled';
    const EXT_LBAAS_ENABLED = 'ext.lbaas_enabled';

    public $instancesListCache = array();
    public $instancesDetailsCache = array();

    /**
     * @return OpenStack
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

        $url = $env->cloudCredentials($this->platform)->properties[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL];

        if (empty($url)) return false;

        return $this->container->analytics->prices->hasPriceForUrl($platform, $url) ?: $url;
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
     * @see \Scalr\Modules\PlatformModuleInterface::IsServerExists()
     */
    public function IsServerExists(DBServer $DBServer, $debug = false)
    {
        return in_array(
            $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID),
            array_keys($this->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION)))
        );
    }

    public function determineServerIps(OpenStack $client, $server)
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
        $iid = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID);

        if (!$iid) {
            $status = 'not-found';
        } elseif (!$this->instancesListCache[$DBServer->envId][$cloudLocation][$iid]) {
            $osClient = $this->getOsClient($DBServer->GetEnvironmentObject(), $cloudLocation);

            try {
                $result = $osClient->servers->getServerDetails($iid);
                $status = $result->status;
            } catch (NotFoundException $e) {
                $status = 'not-found';
            }
        } else {
            $status = $this->instancesListCache[$DBServer->envId][$cloudLocation][$iid];
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
            $BundleTask->Log(sprintf(_("Selected platform snapshot type: %s"), $BundleTask->bundleType));

            //prepare bundle
            $BundleTask->Log(sprintf(_("Sending 'prepare' command to scalarizr")));
            $prepare = $DBServer->scalarizr->image->prepare($BundleTask->roleName);
            $BundleTask->Log(sprintf(_("Prepare result: %s"), json_encode($prepare)));

            $createImage = true;

            /*
            if ($BundleTask->status == \SERVER_SNAPSHOT_CREATION_STATUS::PENDING) {
                $BundleTask->bundleType = \SERVER_SNAPSHOT_CREATION_TYPE::OSTACK_WINDOWS;
                $BundleTask->Log(sprintf(_("Selected platform snapshotting type: %s"), $BundleTask->bundleType));
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

            }
            */
        } else {
            $BundleTask->bundleType = \SERVER_SNAPSHOT_CREATION_TYPE::OSTACK_LINUX;
            $BundleTask->Log(sprintf(_("Selected platform snapshot type: %s"), $BundleTask->bundleType));

            //prepare bundle
            $BundleTask->Log(sprintf(_("Sending 'prepare' command to scalarizr")));
            $prepare = $DBServer->scalarizr->image->prepare($BundleTask->roleName);
            $BundleTask->Log(sprintf(_("Prepare result: %s"), json_encode($prepare)));

            $createImage = true;

            /*
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
            */
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
            $cloudLocation = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
            $serverId = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID);
            $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $cloudLocation);
            $iinfo = $client->servers->getServerDetails($serverId);

            if (!$this->instancesDetailsCache[$iinfo->id]) {
                $this->instancesDetailsCache[$iinfo->id] = new \stdClass();
                $this->instancesDetailsCache[$iinfo->id]->addresses = $iinfo->addresses;
            }

            $ips = $this->GetServerIPAddresses($DBServer);

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

                if ($iinfo->availability_zone || $iinfo->{"OS-EXT-AZ:availability_zone"}) {
                    $retval['Availability zone'] = $iinfo->availability_zone ? $iinfo->availability_zone : $iinfo->{"OS-EXT-AZ:availability_zone"};
                }

                if ($iinfo->security_groups) {
                    $list = array();
                    foreach ($iinfo->security_groups as $sg)
                        $list[] = $sg->name;

                    $retval['Security Groups'] = implode(", ", $list);
                }

                return $retval;
            }
        } catch (NotFoundException $e) {
            return false;
        } catch (\Exception $e) {
            throw $e;
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
            $launchOptions->serverType = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::OPENSTACK_FLAVOR_ID);
            $launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;

            $launchOptions->userData = $DBServer->GetCloudUserData();
            $launchOptions->userData['region'] = $launchOptions->cloudLocation;
            $launchOptions->userData['platform'] = 'openstack';

            $launchOptions->networks = @json_decode($DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::OPENSTACK_NETWORKS));
            $gevernanceNetworks = $governance->getValue($this->platform, 'openstack.networks');
            if (count($launchOptions->networks) == 0 && $gevernanceNetworks) {
                $launchOptions->networks = $gevernanceNetworks[$launchOptions->cloudLocation];
            }

            foreach ($launchOptions->userData as $k => $v) {
                if (!$v)
                    unset($launchOptions->userData[$k]);
            }

            $launchOptions->architecture = 'x86_64';
            $isWindows = ($DBServer->osType == 'windows' || $DBRole->getOs()->family == 'windows');

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

            // Availability zone
            $launchOptions->availZone = $this->GetServerAvailZone(
                $DBServer,
                $this->getOsClient($environment, $launchOptions->cloudLocation),
                $launchOptions
            );
        } else {
            $launchOptions->availZone = null;
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
            $personality->append(new Personality(
                '/etc/scalr/private.d/.user-data',
                base64_encode($u_data)
            ));
        }

        /* @var $ccProps SettingsCollection */
        $ccProps = $environment->cloudCredentials($this->platform)->properties;

        //Check SecurityGroups
        $securityGroupsEnabled = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED];
        $extProperties['security_groups'] = array();
        if ($securityGroupsEnabled) {
            $securityGroups = $this->GetServerSecurityGroupsList($DBServer, $client, $governance);
            foreach ($securityGroups as $sg) {
                $itm = new \stdClass();
                $itm->name = $sg;
                $extProperties['security_groups'][] = $itm;
            }
        }

        if ($launchOptions->availZone)
            $extProperties['availability_zone'] = $launchOptions->availZone;

        if ($deviceMapping)
            $extProperties['block_device_mapping_v2'][] = $deviceMapping;

        //Check key-pairs
        $keyPairsEnabled = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED];
        if ($keyPairsEnabled === null || $keyPairsEnabled === false) {
            if ($client->servers->isExtensionSupported(ServersExtension::EXT_KEYPAIRS))
                $keyPairsEnabled = 1;
            else
                $keyPairsEnabled = 0;

            $ccProps->saveSettings([
                Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED => $keyPairsEnabled
            ]);
        }

        //Check config-drive

        $configDriveEnabled = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED];
        if ($configDriveEnabled === null || $configDriveEnabled === false) {
            if ($client->servers->isExtensionSupported(ServersExtension::EXT_CONFIG_DRIVE))
                $configDriveEnabled = 1;
            else
                $configDriveEnabled = 0;

            $ccProps->saveSettings([
                Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED => $configDriveEnabled
            ]);
        }
        $extProperties['config_drive'] = $configDriveEnabled;

        if ($keyPairsEnabled) {
            if ($DBServer->status == \SERVER_STATUS::TEMPORARY) {
                $keyName = "SCALR-ROLESBUILDER-".SCALR_ID;
                $farmId = NULL;
            } else {
                $keyName = "FARM-{$DBServer->farmId}-".SCALR_ID;
                $farmId = $DBServer->farmId;
            }

            $sshKey = (new SshKey())->loadGlobalByName($DBServer->envId, \SERVER_PLATFORMS::OPENSTACK, $launchOptions->cloudLocation, $keyName);

            if (!$sshKey && !($sshKey = (new SshKey())->loadGlobalByName($DBServer->envId, $DBServer->platform, $launchOptions->cloudLocation, $keyName))) {
                $result = $client->servers->createKeypair($keyName);

                if ($result->private_key) {
                    $sshKey = new SshKey();
                    $sshKey->farmId = $farmId;
                    $sshKey->envId = $DBServer->envId;
                    $sshKey->type = SshKey::TYPE_GLOBAL;
                    $sshKey->platform = $DBServer->platform;
                    $sshKey->cloudLocation = $launchOptions->cloudLocation;
                    $sshKey->cloudKeyName = $keyName;

                    $sshKey->privateKey = $result->private_key;
                    $sshKey->publicKey = $result->public_key;

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

            $instanceTypeInfo = $this->getInstanceType(
                $launchOptions->serverType,
                $environment,
                $launchOptions->cloudLocation
            );
            /* @var $instanceTypeInfo CloudInstanceType */
            $DBServer->SetProperties([
                \OPENSTACK_SERVER_PROPERTIES::SERVER_ID      => $result->id,
                \OPENSTACK_SERVER_PROPERTIES::IMAGE_ID       => $launchOptions->imageId,
                \OPENSTACK_SERVER_PROPERTIES::ADMIN_PASS     => ($launchOptions->userData['vzc.adminpassword']) ? $launchOptions->userData['vzc.adminpassword'] : $result->adminPass,
                \OPENSTACK_SERVER_PROPERTIES::NAME           => $DBServer->serverId,
                \SERVER_PROPERTIES::ARCHITECTURE             => $launchOptions->architecture,
                \OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $launchOptions->cloudLocation,
                \OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION_ZONE => $launchOptions->availZone,
                \SERVER_PROPERTIES::SYSTEM_USER_DATA_METHOD  => $userDataMethod,
                \SERVER_PROPERTIES::INFO_INSTANCE_VCPUS      => $instanceTypeInfo ? $instanceTypeInfo->vcpus : null,
            ]);

            if ($DBServer->farmRoleId) {
                $ipPool = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::OPENSTACK_IP_POOL);

                if ($ipPool) {
                    $DBServer->SetProperty(\SERVER_PROPERTIES::SYSTEM_IGNORE_INBOUND_MESSAGES, 1);
                }
            }

            $params = ['type' => $launchOptions->serverType];

            if ($instanceTypeInfo) {
                $params['instanceTypeName'] = $instanceTypeInfo->name;
            }

            $DBServer->setOsType($isWindows ? 'windows' : 'linux');
            $DBServer->cloudLocation = $launchOptions->cloudLocation;
            $DBServer->cloudLocationZone = $launchOptions->availZone;
            $DBServer->update($params);
            $DBServer->imageId = $launchOptions->imageId;
            // we set server history here
            $DBServer->getServerHistory();

            return $DBServer;
        } catch (\Exception $e) {
            if (stripos($e->getMessage(), 'Invalid key_name provided')) {
                $sshKey->delete();
                throw new \Exception(sprintf(_("Cannot launch new instance: KeyPair was removed from cloud. Re-generating it."), $e->getMessage()));
            }
            throw new \Exception(sprintf(_("Cannot launch new instance. %s"), $e->getMessage()));
        }
    }

    /**
     * Gets Avail zone for the specified DB server
     *
     * @param   DBServer                   $DBServer
     * @param   OpenStack $client Openstack client
     * @param   \Scalr_Server_LaunchOptions $launchOptions
     */
    private function GetServerAvailZone(DBServer $DBServer, OpenStack $client, \Scalr_Server_LaunchOptions $launchOptions)
    {
        if ($DBServer->status == \SERVER_STATUS::TEMPORARY)
            return null;

        $serverAvailZone = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION_ZONE);

        if ($serverAvailZone && $serverAvailZone != 'x-scalr-diff' && !stristr($serverAvailZone, "x-scalr-custom"))
            return $serverAvailZone;

        $roleAvailZone = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::OPENSTACK_AVAIL_ZONE);

        if (!$roleAvailZone)
            return null;

        if ($roleAvailZone == "x-scalr-diff" || stristr($roleAvailZone, "x-scalr-custom")) {
            $availZones = array();
            if (stristr($roleAvailZone, "x-scalr-custom")) {
                $zones = explode("=", $roleAvailZone);
                foreach (explode(":", $zones[1]) as $zoneName) {
                    if ($zoneName != "") {
                        $isUnavailable = $DBServer->GetEnvironmentObject()->getPlatformConfigValue(
                            "openstack.{$launchOptions->cloudLocation}.{$zoneName}.unavailable",
                            false
                        );
                        if ($isUnavailable && $isUnavailable + 3600 < time()) {
                            $DBServer->GetEnvironmentObject()->setPlatformConfig(
                                array(
                                    "openstack.{$launchOptions->cloudLocation}.{$zoneName}.unavailable" => false
                                ),
                                false
                            );
                            $isUnavailable = false;
                        }

                        if (!$isUnavailable)
                            array_push($availZones, $zoneName);
                    }
                }

            } else {
                // Get list of all available zones
                $availZonesResp = $client->servers->listAvailabilityZones();
                foreach ($availZonesResp as $zone) {
                    $zoneName = $zone->zoneName;

                    if ($zone->zoneState->available == true) {
                        $isUnavailable = $DBServer->GetEnvironmentObject()->getPlatformConfigValue(
                            "openstack.{$launchOptions->cloudLocation}.{$zoneName}.unavailable",
                            false
                        );
                        if ($isUnavailable && $isUnavailable + 3600 < time()) {
                            $DBServer->GetEnvironmentObject()->setPlatformConfig(
                                array(
                                    "openstack.{$launchOptions->cloudLocation}.{$zoneName}.unavailable" => false
                                ),
                                false
                            );
                            $isUnavailable = false;
                        }

                        if (!$isUnavailable)
                            array_push($availZones, $zoneName);
                    }
                }
            }

            rsort($availZones);

            $servers = $DBServer->GetFarmRoleObject()->GetServersByFilter(array("status" => array(
                \SERVER_STATUS::RUNNING,
                \SERVER_STATUS::INIT,
                \SERVER_STATUS::PENDING
            )));
            $availZoneDistribution = array();
            foreach ($servers as $cDbServer) {
                if ($cDbServer->serverId != $DBServer->serverId) {
                    $availZoneDistribution[$cDbServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION_ZONE)]++;
                }
            }

            $sCount = PHP_INT_MAX;
            foreach ($availZones as $zone) {
                if ((int)$availZoneDistribution[$zone] <= $sCount) {
                    $sCount = (int)$availZoneDistribution[$zone];
                    $availZone = $zone;
                }
            }

            return $availZone;
        } else {
            return $roleAvailZone;
        }
    }

    public function GetPlatformAccessData(\Scalr_Environment $environment, DBServer $DBServer)
    {
        $ccProps = $environment->cloudCredentials($this->platform)->properties;

        $accessData = new \stdClass();
        $accessData->username = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME];
        $accessData->apiKey = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY];

        // We need regional keystone //
        $os = $environment->openstack($this->platform, $DBServer->cloudLocation);
        $os->listZones();
        $url = $os->getConfig()->getIdentityEndpoint();
        $accessData->keystoneUrl = $url;

        $accessData->tenantName = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME];
        $accessData->password = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD];
        $accessData->cloudLocation = $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
        $accessData->sslVerifyPeer = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER];

        $config = \Scalr::getContainer()->config;

        if ($config->defined("scalr.{$this->platform}.use_proxy") &&
            $config("scalr.{$this->platform}.use_proxy") &&
            in_array($config('scalr.connections.proxy.use_on'), ['both', 'instance'])) {
            $proxySettings = $config('scalr.connections.proxy');
            $accessData->proxy = new \stdClass();
            $accessData->proxy->host = $proxySettings['host'];
            $accessData->proxy->port = $proxySettings['port'];
            $accessData->proxy->user = $proxySettings['user'];
            $accessData->proxy->pass = $proxySettings['pass'];
            $accessData->proxy->type = $proxySettings['type'];
        }

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

    private function GetServerSecurityGroupsList(DBServer $DBServer, OpenStack $osClient, \Scalr_Governance $governance = null)
    {
        $retval = $sgroups = $sgroupIds = $checkGroups = [];
        $sgGovernance = false;
        $allowAdditionalSgs = true;

        if ($governance) {
            $sgs = $governance->getValue($DBServer->platform, \Scalr_Governance::OPENSTACK_SECURITY_GROUPS);

            if ($sgs !== null) {
                $governanceSecurityGroups = @explode(",", $sgs);

                if (!empty($governanceSecurityGroups)) {
                    foreach ($governanceSecurityGroups as $sg) {
                        if ($sg != '') {
                            array_push($checkGroups, trim($sg));
                        }
                    }
                }

                if (!empty($checkGroups)) {
                    $sgGovernance = true;
                }

                $allowAdditionalSgs = $governance->getValue($DBServer->platform, \Scalr_Governance::OPENSTACK_SECURITY_GROUPS, 'allow_additional_sec_groups');
            }
        }

        if (!$sgGovernance || $allowAdditionalSgs) {
            if ($DBServer->farmRoleId != 0) {
                $dbFarmRole = $DBServer->GetFarmRoleObject();

                if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::OPENSTACK_SECURITY_GROUPS_LIST) !== null) {
                    // New SG management
                    $sgs = @json_decode($dbFarmRole->GetSetting(Entity\FarmRoleSetting::OPENSTACK_SECURITY_GROUPS_LIST));

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
            } else {
                array_push($checkGroups, 'scalr-rb-system');
            }
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
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $groupName)) {
                if (isset($sgroupIds[strtolower($groupName)])) {
                    $groupName = $sgroupIds[$groupName]->name;
                } else {
                    throw new \Exception(sprintf(_("Security group '%s' is not found (1)"), $groupName));
                }
            } elseif (preg_match('/^\d+$/', $groupName)) {
                // In openstack IceHouse, SG ID is integer and not UUID
                if (isset($sgroupIds[strtolower($groupName)])) {
                    $groupName = $sgroupIds[$groupName]->name;
                } else {
                    throw new \Exception(sprintf(_("Security group '%s' is not found (1)"), $groupName));
                }
            }

            if ($groupName == 'default') {
                // Check default SG
                array_push($retval, $groupName);
            } elseif ($groupName == 'scalr-rb-system' || $groupName == \Scalr::config('scalr.aws.security_group_name')) {
                // Check Roles builder SG
                if (!isset($sgroups[strtolower($groupName)])) {
                    try {
                        $group = $osClient->createSecurityGroup($groupName, _("Scalr system security group"));
                        $groupId = $group->id;
                    } catch (\Exception $e) {
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
                } else {
                    array_push($retval, $groupName);
                }
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
        $url = $env->cloudCredentials($this->platform)->properties[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL];
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
        return $env->cloudCredentials($this->platform)->properties[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceIdPropertyName()
     */
    public function getInstanceIdPropertyName()
    {
        return OPENSTACK_SERVER_PROPERTIES::SERVER_ID;
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getgetClientByDbServer()
     *
     * @return RestClient
     */
    public function getHttpClient(DBServer $dbServer)
    {
        return $this->getOsClient($dbServer->GetEnvironmentObject(), $dbServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION))
                    ->getClient();
    }
}
