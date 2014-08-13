<?php
namespace Scalr\Modules\Platforms\Cloudstack;

use Scalr\Modules\Platforms\AbstractCloudstackPlatformModule;
use Scalr\Modules\Platforms\Cloudstack\Adapters\StatusAdapter;
use Scalr\Service\CloudStack\DataType\ListIpAddressesData;
use Scalr\Service\CloudStack\DataType\AssociateIpAddressData;
use \DBServer;
use \SERVER_PLATFORMS;
use \SERVER_PROPERTIES;
use \CLOUDSTACK_SERVER_PROPERTIES;
use \SERVER_SNAPSHOT_CREATION_STATUS;
use \SERVER_SNAPSHOT_CREATION_TYPE;
use \DBRole;
use \BundleTask;
use \DBFarmRole;
use \Scalr_Server_LaunchOptions;
use \Logger;
use \SERVER_STATUS;
use \Scalr_SshKey;
use \FarmLogMessage;

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

    private $instancesListCache;

    /**
     * Constructor
     *
     * @param   string    $platform  The name of the cloudstack based platform
     */
    public function __construct($platform = \SERVER_PLATFORMS::CLOUDSTACK)
    {
        parent::__construct($platform);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getLocations()
     */
    public function getLocations(\Scalr_Environment $environment = null)
    {
        if (!$environment || !$environment->isPlatformEnabled($this->platform)) {
            return array();
        }
        try {
            $cs = $environment->cloudstack($this->platform);

            foreach ($cs->zone->describe() as $zone) {
                $retval[$zone->name] = ucfirst($this->platform)." / {$zone->name}";
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

        $platform = $this->platform ?: \SERVER_PLATFORMS::CLOUDSTACK;

        $url = $this->getConfigVariable(static::API_URL, $env);

        if (empty($url)) return false;

        return $this->container->analytics->prices->hasPriceForUrl($platform, $url) ?: $url;
    }

    public function getPropsList()
    {
        return array(
            self::API_URL    => 'API URL',
            self::API_KEY    => 'API Key',
            self::SECRET_KEY => 'Secret Key',
        );
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
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerFlavor()
     */
    public function GetServerFlavor(DBServer $DBServer)
    {
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
        } catch (\Exception $e) {}

        if (!empty($info) && property_exists($info, 'id') && $info->id == $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID))
            return $this->determineServerIps($cs, $info);

        return array(
            'localIp'   => null,
            'remoteIp'  => null
        );
    }

    public function GetServersList(\Scalr_Environment $environment, $region, $skipCache = false)
    {
        if (!$region) {
            return array();
        }
        if (!$this->instancesListCache[$environment->id][$region] || $skipCache) {
            $cs = $environment->cloudstack($this->platform);

            try {
                $results = $cs->instance->describe(array('zoneid' => $region));
            }
            catch(\Exception $e) {
                throw new \Exception(sprintf("Cannot get list of servers for platfrom {$this->platform}: %s", $e->getMessage()));
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
        }
        elseif (!$this->instancesListCache[$DBServer->GetEnvironmentObject()->id][$region][$iid]) {

            $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);

            try {
                $iinfo = $cs->instance->describe(array('id' => $iid));
                $iinfo = (!empty($iinfo)) ? $iinfo[0] : null;

                if (!empty($iinfo)) {
                    $status = $iinfo->state;
                }
                else {
                    $status = 'not-found';
                }
            } catch(\Exception $e) {
                if (stristr($e->getMessage(), "Not Found"))
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

        $cs->instance->destroy($DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RebootServer()
     */
    public function RebootServer(DBServer $DBServer, $soft = true)
    {
        if ($soft) {
            throw new \Exception("Soft reboot not supported by cloud");
        }
        $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);
        $cs->instance->reboot($DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RemoveServerSnapshot()
     */
    public function RemoveServerSnapshot(DBRole $DBRole)
    {
        foreach ($DBRole->getImageId(SERVER_PLATFORMS::CLOUDSTACK) as $location => $imageId) {

            $cs = $DBRole->getEnvironmentObject()->cloudstack($this->platform);

            try {
                $cs->template->delete($imageId, $location);
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CheckServerSnapshotStatus()
     */
    public function CheckServerSnapshotStatus(BundleTask $BundleTask)
    {
        //TODO:
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CreateServerSnapshot()
     */
    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        $DBServer = DBServer::LoadByID($BundleTask->serverId);
        $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
        $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::CSTACK_DEF;

        $msg = new \Scalr_Messaging_Msg_Rebundle(
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
    public function GetServerExtendedInformation(DBServer $DBServer)
    {
        $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);

        if (!$DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)) {
            return false;
        }
        try {
            $iinfo = $cs->instance->describe(array('id' => $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)));
            $iinfo = (!empty($iinfo[0])) ? $iinfo[0] : null;
        } catch (\Exception $e) {}

        if (!empty($iinfo->id) /*&& $iinfo->id == $DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)*/)
        {
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

        return false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::LaunchServer()
     */
    public function LaunchServer(DBServer $DBServer, Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $environment = $DBServer->GetEnvironmentObject();

        $diskOffering = null;
        $size = null;

        if (!$launchOptions)
        {
            $farmRole = $DBServer->GetFarmRoleObject();

            $launchOptions = new Scalr_Server_LaunchOptions();
            $dbRole = DBRole::loadById($DBServer->roleId);

            $launchOptions->imageId = $dbRole->getImageId($this->platform, $DBServer->GetFarmRoleObject()->CloudLocation);
            $launchOptions->serverType = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_CLOUDSTACK_SERVICE_OFFERING_ID);
            $launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;

            /*
             * User Data
             */
            foreach ($DBServer->GetCloudUserData() as $k=>$v) {
                $u_data .= "{$k}={$v};";
            }
            $launchOptions->userData = trim($u_data, ";");

            $diskOffering = $farmRole->GetSetting(DBFarmRole::SETTING_CLOUDSTACK_DISK_OFFERING_ID);
            if ($diskOffering === false || $diskOffering === null) {
                $diskOffering = null;
            }
            $sharedIp = $farmRole->GetSetting(DBFarmRole::SETTING_CLOUDSTACK_SHARED_IP_ID);
            $networkType = $farmRole->GetSetting(DBFarmRole::SETTING_CLOUDSTACK_NETWORK_TYPE);
            $networkId = $farmRole->GetSetting(DBFarmRole::SETTING_CLOUDSTACK_NETWORK_ID);

            $useStaticNat = $farmRole->GetSetting(DBFarmRole::SETIING_CLOUDSTACK_USE_STATIC_NAT);

            $roleName = $farmRole->GetRoleObject()->name;
        } else {
            $launchOptions->userData = '';
            $roleName = 'TemporaryScalrServer'.rand(100,999);
        }

        $launchOptions->architecture = 'x86_64';

        $cs = $environment->cloudstack($this->platform);

        if (!$sharedIp && !$useStaticNat && $networkId != 'SCALR_MANUAL') {
            if (($networkId && ($networkType == 'Virtual' || $networkType == 'Isolated')) || !$networkType) {
                $sharedIpId = $this->getConfigVariable(self::SHARED_IP_ID.".{$launchOptions->cloudLocation}", $environment, false);
                $sharedIpFound = false;
                if ($sharedIpId) {
                    try {
                        $requestObject = new ListIpAddressesData();
                        $requestObject->id = $sharedIpId;
                        $info = $cs->listPublicIpAddresses($requestObject);
                    } catch (\Exception $e) {
                        Logger::getLogger('CLOUDSTACK')->error("SHARED IP CHECK: {$e->getMessage()}");
                    }

                    if (!empty($info[0])) {
                        $sharedIpFound = true;
                        Logger::getLogger('CLOUDSTACK')->error("SHARED IP CHECK: ". json_encode($info));
                    }
                }

                if (!$sharedIpId || !$sharedIpFound) {
                    Logger::getLogger('CLOUDSTACK')->error("No shared IP. Generating new one");

                    $requestObject = new AssociateIpAddressData();
                    $requestObject->zoneid = $launchOptions->cloudLocation;
                    $ipResult = $cs->associateIpAddress($requestObject);
                    $ipId = $ipResult->id;

                    Logger::getLogger('CLOUDSTACK')->error("New IP allocated: {$ipId}");

                    if ($ipId) {
                        while (true) {
                            $requestObject = new ListIpAddressesData();
                            $requestObject->id = $ipId;
                            $ipInfo = $cs->listPublicIpAddresses($requestObject);
                            $ipInfo = !empty($ipInfo[0]) ? $ipInfo[0] : null;

                            if (!$ipInfo) {
                                throw new \Exception("Cannot allocate IP address: listPublicIpAddresses -> failed");
                            }
                            if ($ipInfo->state == 'Allocated') {
                                $this->setConfigVariable(array(self::SHARED_IP_ID.".{$launchOptions->cloudLocation}" => $ipId), $environment, false);
                                $this->setConfigVariable(array(self::SHARED_IP.".{$launchOptions->cloudLocation}" => $ipInfo->ipaddress), $environment, false);
                                $this->setConfigVariable(array(self::SHARED_IP_INFO.".{$launchOptions->cloudLocation}" => serialize($ipInfo)), $environment, false);

                                $sharedIpId = $ipId;
                                break;
                            } else if ($ipInfo->state == 'Allocating') {
                                sleep(1);
                            } else {
                                throw new \Exception("Cannot allocate IP address: ipAddress->state = {$ipInfo->state}");
                            }
                        }
                    }
                    else
                        throw new \Exception("Cannot allocate IP address: associateIpAddress -> failed");
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

        $sshKey = Scalr_SshKey::init();
        try {
            if (!$sshKey->loadGlobalByName($keyName, "", $DBServer->envId, $this->platform))
            {
                $result = $cs->sshKeyPair->create(array('name' => $keyName));
                if (!empty($result->privatekey))
                {
                    $sshKey->farmId = $farmId;
                    $sshKey->envId = $DBServer->envId;
                    $sshKey->type = Scalr_SshKey::TYPE_GLOBAL;
                    $sshKey->cloudLocation = "";//$launchOptions->cloudLocation;
                    $sshKey->cloudKeyName = $keyName;
                    $sshKey->platform = $this->platform;

                    $sshKey->setPrivate($result->privatekey);
                    $sshKey->setPublic($sshKey->generatePublicKey());

                    $sshKey->save();
                }
            }
        } catch (\Exception $e) {
            Logger::getLogger("CloudStack")->error(new FarmLogMessage($DBServer->farmId, "Unable to generate keypair: {$e->getMessage()}"));
        }

        $vResult = $cs->instance->deploy(
            array(
                'serviceofferingid' => $launchOptions->serverType,
                'templateid' => $launchOptions->imageId,
                'zoneid' => $launchOptions->cloudLocation,
                'diskofferingid' => $diskOffering,
                'displayname' => $DBServer->serverId,
                'group' => $roleName,
                'keypair' => $keyName,
                'networkids' => ($networkId != 'SCALR_MANUAL') ? $networkId : null,
                'size' => $size, //size
                'userdata' => base64_encode($launchOptions->userData)
            )
        );
        if (!empty($vResult->id)) {
            $DBServer->SetProperties([
                CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID      => $vResult->id,
                CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $launchOptions->cloudLocation,
                CLOUDSTACK_SERVER_PROPERTIES::LAUNCH_JOB_ID  => $vResult->jobid,
                SERVER_PROPERTIES::ARCHITECTURE              => $launchOptions->architecture,
            ]);

            $DBServer->cloudLocation = $launchOptions->cloudLocation;

            return $DBServer;
        } else {
            throw new \Exception(sprintf("Cannot launch new instance: %s", $vResult->errortext));
        }
    }

    public function GetPlatformAccessData($environment, DBServer $DBServer)
    {
        $accessData = new \stdClass();
        $accessData->apiKey = $this->getConfigVariable(self::API_KEY, $environment);
        $accessData->secretKey = $this->getConfigVariable(self::SECRET_KEY, $environment);
        $accessData->apiUrl = $this->getConfigVariable(self::API_URL, $environment);

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
        $this->instancesListCache = array();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceTypes()
     */
    public function getInstanceTypes(\Scalr_Environment $env = null, $cloudLocation = null, $details = false)
    {
        if (!($env instanceof \Scalr_Environment)) {
            throw new \InvalidArgumentException(sprintf(
                "Method %s requires environment to be specified.", __METHOD__
            ));
        }
        $ret = array();

        $client = $env->cloudstack($this->platform);
        foreach ($client->listServiceOfferings() as $offering) {
            if (!$details) {
                $ret[(string)$offering->id] = (string)$offering->name;
            }
            else {
                $ret[(string)$offering->id] = array(
                    'name' => (string) $offering->name,
                    'ram' => (string) $offering->memory,
                    'vcpus' => (string) $offering->cpunumber,
                    'disk' => "",
                    'type' => strtoupper((string) $offering->storagetype)
                );
            }
        }

        return $ret;
    }
}
