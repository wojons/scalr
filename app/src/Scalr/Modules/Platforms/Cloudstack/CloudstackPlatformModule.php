<?php

namespace Scalr\Modules\Platforms\Cloudstack;

use Scalr\Model\Entity\CloudInstanceType;
use Scalr\Modules\Platforms\AbstractCloudstackPlatformModule;
use Scalr\Modules\Platforms\Cloudstack\Adapters\StatusAdapter;
use Scalr\Modules\Platforms\Cloudstack\Helpers\CloudstackHelper;
use Scalr\Service\CloudStack\Client\QueryClient;
use Scalr\Service\CloudStack\DataType\ListIpAddressesData;
use Scalr\Service\CloudStack\DataType\AssociateIpAddressData;
use Scalr\Service\CloudStack\DataType\SecurityGroupData;
use Scalr\Service\CloudStack\Exception\InstanceNotFoundException;
use Scalr\Service\CloudStack\Exception\NotFoundException;
use Scalr\Service\CloudStack\Services\SecurityGroup\DataType\CreateSecurityGroupData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\CreateSnapshotData;
use Scalr\Service\CloudStack\Services\Volume\DataType\VolumeResponseData;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity;
use Scalr\Model\Entity\SshKey;
use DBServer;
use Exception;
use Scalr_Environment;
use SERVER_PLATFORMS;
use SERVER_PROPERTIES;
use CLOUDSTACK_SERVER_PROPERTIES;
use SERVER_SNAPSHOT_CREATION_STATUS;
use SERVER_SNAPSHOT_CREATION_TYPE;
use BundleTask;
use DBFarmRole;
use Scalr_Server_LaunchOptions;
use SERVER_STATUS;
use FarmLogMessage;
use Scalr\Model\Entity\CloudLocation;
use Scalr_Messaging_Msg_Rebundle;
use Scalr_Governance;
use stdClass;
use Scalr_Messaging_Msg;
use InvalidArgumentException;

class CloudstackPlatformModule extends AbstractCloudstackPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{
    /** Properties **/
    const API_KEY = 'api_key';
    const SECRET_KEY = 'secret_key';
    const API_URL = 'api_url';

    const ACCOUNT_NAME = 'account_name';
    const DOMAIN_NAME = 'domain_name';
    const DOMAIN_ID = 'domain_id';
    const SHARED_IP = 'shared_ip';
    const SHARED_IP_ID = 'shared_ip_id';
    const SHARED_IP_INFO = 'shared_ip_info';
    const SZR_PORT_COUNTER = 'szr_port_counter';

    public $instancesListCache;

    /**
     * Constructor
     *
     * @param   string    $platform  The name of the cloudstack based platform
     */
    public function __construct($platform = SERVER_PLATFORMS::CLOUDSTACK)
    {
        parent::__construct($platform);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getLocations()
     */
    public function getLocations(Scalr_Environment $environment = null)
    {
        $retval = [];

        if ($environment && $environment->isPlatformEnabled($this->platform)) {
            try {
                $cs = $environment->cloudstack($this->platform);

                foreach ($cs->zone->describe() as $zone) {
                    $retval[$zone->name] = ucfirst($this->platform) . " / {$zone->name}";
                }

            } catch (Exception $e) {}
        }

        return $retval;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::hasCloudPrices()
     */
    public function hasCloudPrices(Scalr_Environment $env)
    {
        if (!$this->container->analytics->enabled) return false;

        $platform = $this->platform ?: SERVER_PLATFORMS::CLOUDSTACK;

        $url = $env->keychain($platform)->properties[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL];

        if (empty($url)) return false;

        return $this->container->analytics->prices->hasPriceForUrl($platform, $url) ?: $url;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerCloudLocation()
     */
    public function GetServerCloudLocation(DBServer $DBServer)
    {
        return $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerID()
     */
    public function GetServerID(DBServer $DBServer)
    {
        return $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::IsServerExists()
     */
    public function IsServerExists(DBServer $DBServer)
    {
        $list = $this->GetServersList($DBServer->GetEnvironmentObject(), $this->GetServerCloudLocation($DBServer));
        return !empty($list) && in_array(
            $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID),
            array_keys($list)
        );
    }

    public function determineServerIps($client, $server)
    {
        $localIp = null;
        $remoteIp = null;

        $addr = $server->nic[0]->ipaddress;
        if (strpos($addr, "10.") === 0 || strpos($addr, "192.168") === 0) {
            $localIp = $addr;
        }
        else {
            $remoteIp = $addr;
        }
        if (!empty($server->publicip)) {
            $remoteIp = $server->publicip;
        }
        return array(
            'localIp'   => $localIp,
            'remoteIp'  => $remoteIp
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerIPAddresses()
     */
    public function GetServerIPAddresses(DBServer $DBServer)
    {
        $cloudLocation = $this->GetServerCloudLocation($DBServer);
        $env = $DBServer->GetEnvironmentObject();
          $cs = $env->cloudstack($this->platform);
        try {
            $info = $cs->instance->describe(array('id' => $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)));
            $info = !empty($info[0]) ? $info[0] : null;
        } catch (Exception $e) {}

        if (!empty($info) && property_exists($info, 'id') && $info->id == $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID))
            return $this->determineServerIps($cs, $info);

        return array(
            'localIp'   => null,
            'remoteIp'  => null
        );
    }

    public function GetServersList(Scalr_Environment $environment, $region, $skipCache = false)
    {
        if (!$region) {
            return array();
        }
        if (!$this->instancesListCache[$environment->id][$region] || $skipCache) {
            $cs = $environment->cloudstack($this->platform);

            try {
                $results = $cs->instance->describe(array('zoneid' => $region));
            } catch (Exception $e) {
                throw new Exception(sprintf("Cannot get list of servers for platform {$this->platform}: %s", $e->getMessage()));
            }


            if (count($results) > 0) {
                foreach ($results as $item)
                    $this->instancesListCache[$environment->id][$region][$item->id] = $item->state;
            }
        }

        return $this->instancesListCache[$environment->id][$region];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerRealStatus()
     */
    public function GetServerRealStatus(DBServer $DBServer)
    {
        $region = $this->GetServerCloudLocation($DBServer);
        $iid = $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID);

        if (!$iid || !$region) {
            $status = 'not-found';
        } elseif (!$this->instancesListCache[$DBServer->GetEnvironmentObject()->id][$region][$iid]) {
            $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);

            try {
                $iinfo = $cs->instance->describe(array('id' => $iid));
                $iinfo = (!empty($iinfo)) ? $iinfo[0] : null;

                if (!empty($iinfo)) {
                    $status = $iinfo->state;
                } else {
                    $status = 'not-found';
                }
            } catch (NotFoundException $e) {
                $status = 'not-found';
            }
        } else {
            $status = $this->instancesListCache[$DBServer->GetEnvironmentObject()->id][$region][$DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)];
        }

        return StatusAdapter::load($status);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::TerminateServer()
     */
    public function TerminateServer(DBServer $DBServer)
    {
        $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);

        try {
            $cs->instance->destroy($DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID), true);

            try {
                if ($DBServer->GetRealStatus()->getName() == 'Destroyed') {
                    $cs->instance->expunge($DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));
                }
            } catch (Exception $e) {
            }
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::ResumeServer()
     */
    public function ResumeServer(DBServer $DBServer)
    {
        $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);
        try {
            $cs->instance->start($DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }

        $remoteIp = CloudstackHelper::getSharedIP($DBServer);
        if ($remoteIp)
            $DBServer->remoteIp = $remoteIp;

        parent::ResumeServer($DBServer);

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::SuspendServer()
     */
    public function SuspendServer(DBServer $DBServer)
    {
        $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);
        try {
            $cs->instance->stop($DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID), true);
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
        if ($soft) {
            throw new Exception("Soft reboot not supported by cloud");
        }

        $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);

        try {
            $cs->instance->reboot($DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));
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

        $cs = $image->getEnvironment()->cloudstack($this->platform);
        try {
            $cs->template->delete($image->id, $image->cloudLocation);
        } catch (Exception $e) {
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

            if ($BundleTask->bundleType != SERVER_SNAPSHOT_CREATION_TYPE::CSTACK_WINDOWS)
                return;

            $meta = $BundleTask->getSnapshotDetails();
            $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);

            if (!$BundleTask->snapshotId) {
                $cloudStackSnapshotId = $meta['cloudStackSnapshotId'];
                $snapshotsList = $cs->snapshot->describe(array('id' => $cloudStackSnapshotId));
                $snapshot = $snapshotsList[0];
                if ($snapshot->state == 'BackedUp') {

                    $BundleTask->Log(sprintf(_("Snapshot created. Registering template.")));

                    try {
                        $vmList = $cs->instance->describe(array('id' => $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)));
                        $vmInfo = $vmList[0];

                        if ($vmInfo->templateid)
                            $originalTemplate = $cs->template->describe(array('templatefilter' => 'all', 'id' => $vmInfo->templateid))[0];

                        $request = array(
                            'ostypeid' => $vmInfo->guestosid,
                            'name' => $BundleTask->roleName . "-" . date("YmdHi"),
                            'displaytext' => $BundleTask->roleName,
                            'snapshotid' => $cloudStackSnapshotId,
                            'passwordenabled' => $vmInfo->passwordenabled
                        );
                        if ($originalTemplate)
                            $request['details'] = $originalTemplate->details->toArray();

                        $template = $cs->template->create($request);

                        //TODO: Check job and update Image size.

                    } catch (Exception $e) {
                        $BundleTask->SnapshotCreationFailed(sprintf("Unable to register new template: %s", $e->getMessage()));
                        return false;
                    }

                    $BundleTask->Log(sprintf(_("Template successfully registered. (ID: %s)"), $template->id));
                    $BundleTask->SnapshotCreationComplete($template->id);

                } else {
                    $BundleTask->Log(sprintf(_("Snapshot is in progress. Current status: %s"),
                        $snapshot->state
                    ));
                }
            }

        } catch (Exception $e) {
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

            $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::CSTACK_WINDOWS;
            $BundleTask->Log(sprintf(_("Selected platform snapshot type: %s"), $BundleTask->bundleType));
            $createImage = true;

        } else {
            $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
            $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::CSTACK_DEF;

            $msg = new Scalr_Messaging_Msg_Rebundle(
                $BundleTask->id,
                $BundleTask->roleName,
                array()
            );

            if (!$DBServer->SendMessage($msg)) {
                $BundleTask->SnapshotCreationFailed("Cannot send rebundle message to server. Please check event log for more details.");
                return;
            }
            else {
                $BundleTask->Log(sprintf(_("Snapshot creating initialized (MessageID: %s). Bundle task status changed to: %s"),
                    $msg->messageId, $BundleTask->status
                ));
            }
        }

        if ($createImage) {
            $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;

            try {
                $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);
                $volList = $cs->volume->describe(array('virtualmachineid' => $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID), 'type' => 'ROOT'));
                $volInfo = $volList[0];
                /* @var $volInfo VolumeResponseData */

            } catch (Exception $e) {
                $BundleTask->SnapshotCreationFailed(sprintf("Getting ROOT volume details for new snapshot failed: %s", $e->getMessage()));
                return false;
            }

            $BundleTask->Log(sprintf(_("Creating snapshot of ROOT volume (ID: %s). Bundle task status changed to: %s"),
                $volInfo->id,
                $BundleTask->status
            ));

            try {
                $createSnapshotData = new CreateSnapshotData($volInfo->id);
                $createSnapshotData->domainid = $volInfo->domainid;
                $createSnapshotData->account = $volInfo->account;
                $snapInfo = $cs->snapshot->create($createSnapshotData);
            } catch (Exception $e) {
                $BundleTask->SnapshotCreationFailed(sprintf("Unable to initialize snapshot creation process: %s", $e->getMessage()));
                return false;
            }

            $BundleTask->Log(sprintf(_("Snapshot initialized (ID: %s)"),
                $snapInfo->id
            ));

            $BundleTask->setMetaData(array(
                'cloudStackSnapshotId' => $snapInfo->id,
                'cloudStackSnapshotJobId' => $snapInfo->jobid
            ));
        }

        $BundleTask->setDate('started');
        $BundleTask->Save();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerConsoleOutput()
     */
    public function GetServerConsoleOutput(DBServer $DBServer)
    {
        //NOT SUPPORTED
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerExtendedInformation()
     */
    public function GetServerExtendedInformation(DBServer $DBServer, $extended = false)
    {
        $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);

        if (!$DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)) {
            return false;
        }

        try {
            $iinfo = $cs->instance->describe(array('id' => $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)));
            $iinfo = (!empty($iinfo[0])) ? $iinfo[0] : null;

            if (!empty($iinfo->id)) {
                $localIp = null;
                $remoteIp = null;
                $addr = $iinfo->nic[0]->ipaddress;
                if (strpos($addr, "10.") === 0 || strpos($addr, "192.168") === 0) {
                        $localIp = $addr;
                }
                else {
                        $remoteIp = $addr;
                }
                $retval = array(
                    'Cloud Server ID'       => $iinfo->id,
                    'Name'			=> $iinfo->name,
                    'State'			=> $iinfo->state,
                    'Group'			=> $iinfo->group,
                    'Zone'			=> $iinfo->zonename,
                    'Template name'         => $iinfo->templatename,
                    'Template ID'           => $iinfo->templateid,
                    'Offering name'         => $iinfo->serviceofferingname,
                    'Root device type'      => $iinfo->rootdevicetype,
                    'Internal IP'           => $localIp,
                    'Public IP'		=> $remoteIp,
                    'Hypervisor'            => $iinfo->hypervisor
                );

                if (!empty($iinfo->publicip)) {
                    $retval['Public IP'] = $iinfo->publicip;
                }
                if (!empty($iinfo->securitygroup)) {
                    $retval['Security groups'] = "";
                    foreach ($iinfo->securitygroup as $sg) {
                        $retval['Security groups'] .= "{$sg->name}, ";
                    }

                    $retval['Security groups'] = trim($retval['Security groups'], ", ");
                }

                return $retval;
            }
        } catch (NotFoundException $e) {
            return false;
        } catch (Exception $e) {
            throw $e;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::LaunchServer()
     */
    public function LaunchServer(DBServer $DBServer, Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $environment = $DBServer->GetEnvironmentObject();
        $governance = new Scalr_Governance($environment->id);

        $diskOffering = null;
        $size = null;

        $cs = $environment->cloudstack($this->platform);

        if (!$launchOptions){
            $farmRole = $DBServer->GetFarmRoleObject();

            $launchOptions = new Scalr_Server_LaunchOptions();
            $dbRole = $farmRole->GetRoleObject();

            $launchOptions->imageId = $dbRole->__getNewRoleObject()->getImage($this->platform, $DBServer->GetFarmRoleObject()->CloudLocation)->imageId;
            $launchOptions->serverType = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::INSTANCE_TYPE);
            $launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;

            /*
             * User Data
             */
            $u_data = '';

            foreach ($DBServer->GetCloudUserData() as $k=>$v) {
                $u_data .= "{$k}={$v};";
            }

            $launchOptions->userData = trim($u_data, ";");

            $diskOffering = $farmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_DISK_OFFERING_ID);

            if ($diskOffering === false || $diskOffering === null) {
                $diskOffering = null;
            }

            $sharedIp = $farmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_SHARED_IP_ID);
            $networkType = $farmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_NETWORK_TYPE);
            $networkId = $farmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_NETWORK_ID);

            $useStaticNat = $farmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_USE_STATIC_NAT);

            $roleName = $farmRole->GetRoleObject()->name;

            $osType = $farmRole->GetRoleObject()->getOs()->family == 'windows' ? 'windows' : 'linux';

        } else {
            $launchOptions->userData = '';
            $roleName = 'TemporaryScalrServer'.rand(100,999);
            $osType = 'linux';
        }

        $launchOptions->architecture = 'x86_64';

        \Scalr::getContainer()->logger("CloudStack")->warn(new FarmLogMessage(
            $DBServer,
            "[ServerLaunch] Network ID: {$networkId}, NetworkType: {$networkType}, SharedIp: {$sharedIp}"
        ));

        if (!$sharedIp && !$useStaticNat && $networkId != 'SCALR_MANUAL') {
            if ($networkId && !$networkType) {
                foreach ($cs->network->describe(array('id' => $networkId)) as $network) {
                    if ($network->id == $networkId) {
                        $farmRole->SetSetting(Entity\FarmRoleSetting::CLOUDSTACK_NETWORK_TYPE, $network->type, Entity\FarmRoleSetting::TYPE_LCL);
                        $networkType = $network->type;

                        \Scalr::getContainer()->logger("CloudStack")->warn(new FarmLogMessage($DBServer, "[ServerLaunch] Network found. Type: {$networkType}"));
                    }
                }
            }

            if (($networkId && ($networkType == 'Virtual' || $networkType == 'Isolated')) || !$networkType) {
                $sharedIpId = $environment->keychain($DBServer->platform)->properties[Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_ID . ".{$launchOptions->cloudLocation}"];

                $sharedIpFound = false;

                if ($sharedIpId) {
                    try {
                        $requestObject = new ListIpAddressesData();
                        $requestObject->id = $sharedIpId;
                        $info = $cs->listPublicIpAddresses($requestObject);
                    } catch (Exception $e) {
                        \Scalr::getContainer()->logger('CLOUDSTACK')->error("SHARED IP CHECK: {$e->getMessage()}");
                    }

                    if (!empty($info[0])) {
                        $sharedIpFound = true;
                        \Scalr::getContainer()->logger('CLOUDSTACK')->error("SHARED IP CHECK: ". json_encode($info));
                    }
                }

                $config = \Scalr::getContainer()->config;

                $instancesConnectionPolicy = $config->defined("scalr.{$this->platform}.instances_connection_policy") ? $config("scalr.{$this->platform}.instances_connection_policy") : null;

                if ($instancesConnectionPolicy === null)
                    $instancesConnectionPolicy = $config('scalr.instances_connection_policy');

                \Scalr::getContainer()->logger("CloudStack")->warn(new FarmLogMessage($DBServer, "[ServerLaunch] Shared IP ID: {$sharedIpId}"));

                if ((!$sharedIpId || !$sharedIpFound) && $instancesConnectionPolicy != 'local') {
                    \Scalr::getContainer()->logger('CLOUDSTACK')->error("No shared IP. Generating new one");

                    $requestObject = new AssociateIpAddressData();
                    $requestObject->zoneid = $launchOptions->cloudLocation;
                    if ($networkId)
                        $requestObject->networkid = $networkId;

                    $ipResult = $cs->associateIpAddress($requestObject);
                    $ipId = $ipResult->id;

                    \Scalr::getContainer()->logger('CLOUDSTACK')->error("New IP allocated: {$ipId}");

                    if ($ipId) {
                        while (true) {
                            $requestObject = new ListIpAddressesData();
                            $requestObject->id = $ipId;
                            $ipInfo = $cs->listPublicIpAddresses($requestObject);
                            $ipInfo = !empty($ipInfo[0]) ? $ipInfo[0] : null;

                            if (!$ipInfo) {
                                throw new Exception("Cannot allocate IP address: listPublicIpAddresses -> failed");
                            }
                            if ($ipInfo->state == 'Allocated') {
                                $environment->keychain($DBServer->platform)->properties->saveSettings([
                                    Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_ID . ".{$launchOptions->cloudLocation}"    => $ipId,
                                    Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP . ".{$launchOptions->cloudLocation}"       => $ipInfo->ipaddress,
                                    Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_INFO . ".{$launchOptions->cloudLocation}"  => serialize($ipInfo),
                                ]);

                                $sharedIpId = $ipId;
                                break;
                            } else if ($ipInfo->state == 'Allocating') {
                                sleep(1);
                            } else {
                                throw new Exception("Cannot allocate IP address: ipAddress->state = {$ipInfo->state}");
                            }
                        }
                    } else {
                        throw new Exception("Cannot allocate IP address: associateIpAddress -> failed");
                    }
                }
            }
        }

        if ($DBServer->status == SERVER_STATUS::TEMPORARY) {
            $keyName = "SCALR-ROLESBUILDER-".SCALR_ID;
            $farmId = NULL;
        } else {
            $keyName = "FARM-{$DBServer->farmId}-".SCALR_ID;
            $farmId = $DBServer->farmId;
        }

        //
        $sgs = null;
        try {
            $zones = $cs->zone->describe(['name' => $launchOptions->cloudLocation]);
            $zoneSecurityGroupsEnabled = 1;
            if ($zones[0]) {
                if (isset($zones[0]->securitygroupsenabled))
                    $zoneSecurityGroupsEnabled = (int)$zones[0]->securitygroupsenabled;
            }

            $features = (array)$cs->listCapabilities();
            if ($features['securitygroupsenabled'] && $zoneSecurityGroupsEnabled == 1) {
                $sgs = $this->GetServerSecurityGroupsList($DBServer, $cs, $governance);
            }
        } catch (Exception $e) {
            \Scalr::getContainer()->logger("CloudStack")->error(new FarmLogMessage($DBServer, "Unable to get list of securoty groups: {$e->getMessage()}"));
        }

        $sshKey = new SshKey();
        try {
            if (!$sshKey->loadGlobalByName(
                $DBServer->envId,
                $this->platform,
                "",
                $keyName
            )) {
                $result = $cs->sshKeyPair->create(array('name' => $keyName));

                if (!empty($result->privatekey)) {
                    $sshKey->farmId = $farmId;
                    $sshKey->envId = $DBServer->envId;
                    $sshKey->type = SshKey::TYPE_GLOBAL;
                    $sshKey->platform = $this->platform;
                    $sshKey->cloudLocation = "";//$launchOptions->cloudLocation;
                    $sshKey->cloudKeyName = $keyName;

                    $sshKey->privateKey = $result->privatekey;
                    $sshKey->publicKey = $sshKey->generatePublicKey();

                    $sshKey->save();
                }
            }
        } catch (Exception $e) {
            \Scalr::getContainer()->logger("CloudStack")->error(new FarmLogMessage($DBServer, "Unable to generate keypair: {$e->getMessage()}"));
        }

        $vResult = $cs->instance->deploy([
            'serviceofferingid' => $launchOptions->serverType,
            'templateid'        => $launchOptions->imageId,
            'zoneid'            => $launchOptions->cloudLocation,
            'diskofferingid'    => $diskOffering,
            'displayname'       => $DBServer->serverId,
            'group'             => $roleName,
            'keypair'           => $keyName,
            'networkids'        => ($networkId != 'SCALR_MANUAL') ? $networkId : null,
            'size'              => $size, //size
            'userdata'          => base64_encode($launchOptions->userData),
            'securitygroupids'  => !empty($sgs) ? implode(",", $sgs) : null
        ]);

        if (!empty($vResult->id)) {
            $instanceTypeInfo = $this->getInstanceType(
                $launchOptions->serverType,
                $environment,
                $launchOptions->cloudLocation
            );
            /* @var $instanceTypeInfo CloudInstanceType */
            $DBServer->SetProperties([
                CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID      => $vResult->id,
                CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $launchOptions->cloudLocation,
                CLOUDSTACK_SERVER_PROPERTIES::LAUNCH_JOB_ID  => $vResult->jobid,
                SERVER_PROPERTIES::ARCHITECTURE              => $launchOptions->architecture,
                SERVER_PROPERTIES::INFO_INSTANCE_VCPUS       => $instanceTypeInfo ? $instanceTypeInfo->vcpus : null,
            ]);

            $DBServer->cloudLocation = $launchOptions->cloudLocation;
            $DBServer->imageId = $launchOptions->imageId;

            $params = ['type' => $launchOptions->serverType];

            if ($instanceTypeInfo) {
                $params['instanceTypeName'] = $instanceTypeInfo->name;
            }

            $DBServer->setOsType($osType);
            $DBServer->update($params);

            //We set/update server history here
            $DBServer->getServerHistory()->update(['cloudServerId' => $vResult->id]);

            return $DBServer;
        } else {
            throw new Exception(sprintf("Cannot launch new instance: %s", $vResult->errortext));
        }
    }

    private function GetServerSecurityGroupsList(DBServer $DBServer, \Scalr\Service\CloudStack\CloudStack $csClient, Scalr_Governance $governance = null)
    {
        $retval = array();
        $checkGroups = array();
        $sgGovernance = false;
        $allowAdditionalSgs = true;

        if ($governance) {
            $sgs = $governance->getValue($DBServer->platform, Scalr_Governance::CLOUDSTACK_SECURITY_GROUPS);
            if ($sgs !== null) {
                $governanceSecurityGroups = @explode(",", $sgs);
                if (!empty($governanceSecurityGroups)) {
                    foreach ($governanceSecurityGroups as $sg) {
                        if ($sg != '')
                            array_push($checkGroups, trim($sg));
                    }
                }

                if (!empty($checkGroups)) {
                    $sgGovernance = true;
                }
                $allowAdditionalSgs = $governance->getValue($DBServer->platform, Scalr_Governance::CLOUDSTACK_SECURITY_GROUPS, 'allow_additional_sec_groups');
            }
        }

        if (!$sgGovernance || $allowAdditionalSgs) {
            if ($DBServer->farmRoleId != 0) {
                $dbFarmRole = $DBServer->GetFarmRoleObject();
                if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_SECURITY_GROUPS_LIST) !== null) {
                    // New SG management
                    $sgs = @json_decode($dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_SECURITY_GROUPS_LIST));
                    if (!empty($sgs)) {
                        foreach ($sgs as $sg) {
                            array_push($checkGroups, $sg);
                        }
                    }
                }
            } else
                array_push($checkGroups, 'scalr-rb-system');
        }

        try {
            $sgroups = array();
            $sgroupIds = array();
            $list = $csClient->securityGroup->describe();
            foreach ($list as $sg) {
                /* @var $sg SecurityGroupData */
                $sgroups[strtolower($sg->name)] = $sg;
                $sgroupIds[strtolower($sg->id)] = $sg;
            }
        } catch (Exception $e) {
            throw new Exception("GetServerSecurityGroupsList failed: {$e->getMessage()}");
        }

        foreach ($checkGroups as $groupName) {
            // || !in_array($groupName, array('scalr-rb-system', 'default', \Scalr::config('scalr.aws.security_group_name')))
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/si', $groupName)) {
                if (isset($sgroupIds[strtolower($groupName)]))
                    $groupName = $sgroupIds[$groupName]->name;
                else
                    throw new Exception(sprintf(_("Security group '%s' is not found (1)"), $groupName));
            }

            // Check default SG
            if ($groupName == 'default') {
                array_push($retval, $sgroups[$groupName]->id);

                // Check Roles builder SG
            } elseif ($groupName == 'scalr-rb-system' || $groupName == \Scalr::config('scalr.aws.security_group_name')) {
                if (!isset($sgroups[strtolower($groupName)])) {

                    $request = new CreateSecurityGroupData($groupName);
                    $request->description = _("Scalr system security group");

                    $sg = $csClient->securityGroup->create($request);

                    $sgroups[strtolower($groupName)] = $sg;
                    $sgroupIds[strtolower($sg->id)] = $sg;
                }
                array_push($retval, $sgroups[$groupName]->id);
            } else {
                if (!isset($sgroups[strtolower($groupName)])) {
                    throw new Exception(sprintf(_("Security group '%s' is not found (2)"), $groupName));
                } else
                    array_push($retval, $sgroups[strtolower($groupName)]->id);
            }
        }

        return $retval;
    }

    /**
     * @param Scalr_environment $environment
     * @param DBServer          $DBServer
     *
     * @return \stdClass
     */
    public function GetPlatformAccessData($environment, DBServer $DBServer)
    {
        $ccProps = $environment->keychain($DBServer->platform)->properties;

        $accessData = new stdClass();
        $accessData->apiKey = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY];
        $accessData->secretKey = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY];
        $accessData->apiUrl = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL];

        $config = \Scalr::getContainer()->config;

        if ($config->defined("scalr.{$this->platform}.use_proxy") &&
            $config("scalr.{$this->platform}.use_proxy") &&
            in_array($config('scalr.connections.proxy.use_on'), ['both', 'instance'])) {
            $proxySettings = $config('scalr.connections.proxy');
            $accessData->proxy = new \stdClass();
            $accessData->proxy->host     = $proxySettings['host'];
            $accessData->proxy->port     = $proxySettings['port'];
            $accessData->proxy->user     = $proxySettings['user'];
            $accessData->proxy->pass     = $proxySettings['pass'];
            $accessData->proxy->type     = $proxySettings['type'];
            $accessData->proxy->authtype = $proxySettings['authtype'];
        }

        return $accessData;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::PutAccessData()
     */
    public function PutAccessData(DBServer $DBServer, Scalr_Messaging_Msg $message)
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
        $this->instancesListCache = array();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceTypes()
     */
    public function getInstanceTypes(Scalr_Environment $env = null, $cloudLocation = null, $details = false)
    {
        if (!($env instanceof Scalr_Environment)) {
            throw new InvalidArgumentException(sprintf(
                "Method %s requires environment to be specified.", __METHOD__
            ));
        }

        $ret = [];
        $detailed = [];

        //Trying to retrieve instance types from the cache
        $url = $env->keychain($this->platform)->properties[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL];
        $collection = $this->getCachedInstanceTypes($this->platform, $url, ($cloudLocation ?: ''));

        if ($collection === false || $collection->count() == 0) {
            //No cache. Fetching data from the cloud
            $client = $env->cloudstack($this->platform);

            foreach ($client->listServiceOfferings() as $offering) {
                $detailed[(string)$offering->id] = array(
                    'name'  => (string) $offering->name,
                    'ram'   => (string) $offering->memory,
                    'vcpus' => (string) $offering->cpunumber,
                    'disk'  => "",
                    'type'  => strtoupper((string) $offering->storagetype),
                );

                if (!$details) {
                    $ret[(string)$offering->id] = (string)$offering->name;
                } else {
                    $ret[(string)$offering->id] = $detailed[(string)$offering->id];
                }
            }

            //Refreshes/creates a cache
            CloudLocation::updateInstanceTypes($this->platform, $url, ($cloudLocation ?: ''), $detailed);
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
     * Gets endpoint url for private cloud
     *
     * @param Scalr_Environment $env       The scalr environment object
     * @param string             $group     optional The group name
     * @return string Returns endpoint url for cloudstack.
     */
    public function getEndpointUrl(Scalr_Environment $env, $group = null)
    {
        return $env->keychain($this->platform)->properties[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceIdPropertyName()
     */
    public function getInstanceIdPropertyName()
    {
        return CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID;
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getgetClientByDbServer()
     *
     * @return QueryClient
     */
    public function getHttpClient(DBServer $dbServer)
    {
        return $dbServer->GetEnvironmentObject()
                        ->cloudstack($dbServer->platform)
                        ->getClient();
    }
}
