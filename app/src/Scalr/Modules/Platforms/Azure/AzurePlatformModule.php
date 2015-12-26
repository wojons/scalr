<?php

namespace Scalr\Modules\Platforms\Azure;

use Scalr\Model\Entity\CloudCredentialsProperty;
use Scalr\Model\Entity\CloudInstanceType;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\FarmRoleSetting;
use \DBServer;
use \BundleTask;
use Scalr\Service\Azure\Client\QueryClient;
use \SERVER_PLATFORMS;
use Scalr\Modules\AbstractPlatformModule;
use Scalr\Modules\PlatformModuleInterface;
use Scalr\Service\Exception\InstanceNotFound;
use \Scalr_Server_LaunchOptions;
use Scalr\Model\Entity\CloudLocation;
use Scalr\Service\Azure\Services\Compute\DataType\CreateVirtualMachine;
use Scalr\Service\Azure\Services\Compute\DataType\VirtualMachineProperties;
use Scalr\Service\Azure\Services\Compute\DataType\OsDisk;
use Scalr\Service\Azure\Services\Compute\DataType\OsProfile;
use Scalr\Service\Azure\Services\Compute\DataType\StorageProfile;
use Scalr\Service\Azure\Services\Network\DataType\CreateInterface;
use Scalr\Service\Azure\Services\Network\DataType\InterfaceIpConfigurationsData;
use Scalr\Service\Azure\Services\Network\DataType\InterfaceProperties;
use Scalr\Service\Azure\Services\Network\DataType\IpConfigurationProperties;
use Scalr\Service\Azure\Services\Network\DataType\SecurityGroupData;
use Scalr\Service\Azure\Exception\InstanceNotFoundException;
use Scalr\Service\Azure\Exception\NotFoundException;
use Scalr\Modules\Platforms\Azure\Adapters\StatusAdapter;
use Scalr\Service\Azure\Services\Network\DataType\CreatePublicIpAddress;
use Scalr\Service\Azure\Services\Network\DataType\PublicIpAddressProperties;
use Scalr\Service\Exception\NotFound;

class AzurePlatformModule extends AbstractPlatformModule implements PlatformModuleInterface
{
    /** Properties **/
    const TENANT_NAME = 'azure.tenant_name';

    const AUTH_CODE = 'azure.auth_code';

    const ACCESS_TOKEN = 'azure.access_token';
    const ACCESS_TOKEN_EXPIRE = 'azure.access_token_expire';

    const REFRESH_TOKEN = 'azure.refresh_token';
    const REFRESH_TOKEN_EXPIRE = 'azure.refresh_token_expire';

    const CLIENT_TOKEN = 'azure.client_token';
    const CLIENT_TOKEN_EXPIRE = 'azure.client_token_expire';

    const SUBSCRIPTION_ID = 'azure.subscription_id';
    const STORAGE_ACCOUNT_NAME = 'azure.storage_account_name';

    const CLIENT_OBJECT_ID = 'azure.client_object_id';

    const CONTRIBUTOR_ID = 'azure.contributor_id';

    const ROLE_ASSIGNMENT_ID = 'azure.role_assignment_id';

    const AUTH_STEP = 'azure.step';

    /**
     * Instance status cache
     *
     * @var array
     */
    public $instancesListCache;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->platform = SERVER_PLATFORMS::AZURE;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getLocations()
     */
    public function getLocations(\Scalr_Environment $environment = null)
    {
        if ($environment === null || !$environment->isPlatformEnabled(\SERVER_PLATFORMS::AZURE))
            return [];

        $locationsResponse = $environment->azure()->getLocationsList();
        $retval = [];

        foreach ($locationsResponse->resourceTypes as $rt) {
            /* @var $rt \Scalr\Service\Azure\DataType\ResourceTypeData */
            if ($rt->resourceType == 'locations/vmSizes') {
                foreach ($rt->locations as $location) {
                    $retval[strtolower(str_replace(" ", "", $location))] = $location;
                }
            }
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

        return $this->container->analytics->prices->hasPriceForUrl(
            \SERVER_PLATFORMS::AZURE, ''
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerCloudLocation()
     */
    public function GetServerCloudLocation(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::CLOUD_LOCATION);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerID()
     */
    public function GetServerID(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::SERVER_NAME);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::IsServerExists()
     */
    public function IsServerExists(DBServer $DBServer)
    {
        return in_array(
            $DBServer->serverId,
            array_keys($this->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP), true))
        );
    }

    public function GetServersList(\Scalr_Environment $environment, $resourceGroup, $skipCache = false)
    {
        $cacheKey = sprintf('%s:%s', $environment->id, $resourceGroup);

        if (!$this->instancesListCache[$cacheKey] || $skipCache) {
            $this->instancesListCache[$cacheKey] = array();
            $azure = $environment->azure();

            $subscriptionId = $environment->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID];

            $vmList = $azure->compute->virtualMachine->getList(
                $subscriptionId,
                $resourceGroup
            );

            foreach ($vmList as $vm) {
                $info = $azure->compute->virtualMachine->getModelViewInfo(
                    $subscriptionId,
                    $resourceGroup,
                    $vm->name,
                    true
                );

                $statuses = [];
                foreach ($info->properties->instanceView->statuses as $status) {
                    $statusInfo = explode("/", $status->code);
                    $statuses[$statusInfo[0]] = $statusInfo[1];
                }

                $this->instancesListCache[$cacheKey][$vm->name] = $statuses;
            }
        }

        return $this->instancesListCache[$cacheKey];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerIPAddresses()
     */
    public function GetServerIPAddresses(DBServer $DBServer)
    {
        $env = $DBServer->GetEnvironmentObject();
        $azure = $env->azure();

        $nicInfo = $azure->network->interface->getInfo(
            $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
            $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP),
            $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::NETWORK_INTERFACE),
            true
        );

        if (!empty($nicInfo->properties->ipConfigurations)) {
            foreach ($nicInfo->properties->ipConfigurations as $ipConfig) {
                $privateIp = $ipConfig->properties->privateIPAddress;
                if ($ipConfig->properties->publicIPAddress) {
                    $publicIp = $ipConfig->properties->publicIPAddress->properties->ipAddress;
                    if ($publicIp)
                        break;
                }
            }
        }

        return array(
            'localIp'	=> $privateIp,
            'remoteIp'	=> $publicIp
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerRealStatus()
     */
    public function GetServerRealStatus(DBServer $DBServer)
    {
        $env = $DBServer->GetEnvironmentObject();

        $serverName = $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::SERVER_NAME);
        $resourceGroup = $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP);

        $cacheKey = sprintf('%s:%s', $DBServer->GetEnvironmentObject()->id, $resourceGroup);

        if (!$serverName || !$resourceGroup) {
            $statuses = ['ProvisioningState' => 'not-found'];
        } elseif (empty($this->instancesListCache[$cacheKey][$serverName])) {
            $azure = $env->azure();
            try {
                $info = $azure->compute->virtualMachine->getModelViewInfo(
                    $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                    $resourceGroup,
                    $serverName,
                    true
                );

                $statuses = [];
                foreach ($info->properties->instanceView->statuses as $status) {
                    $statusInfo = explode("/", $status->code);
                    $statuses[$statusInfo[0]] = $statusInfo[1];
                }

            } catch (NotFoundException $e) {
                $statuses = ['ProvisioningState' => 'not-found'];
            }
        } else {
            $statuses = $this->instancesListCache[$cacheKey][$serverName];
        }

        return StatusAdapter::load($statuses);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::TerminateServer()
     */
    public function TerminateServer(DBServer $DBServer)
    {
        $env = $DBServer->GetEnvironmentObject();
        $azure = $env->azure();
        try {
            return $azure->compute->virtualMachine->delete(
                $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP),
                $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::SERVER_NAME)
            );
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::ResumeServer()
     */
    public function ResumeServer(DBServer $DBServer)
    {
        $env = $DBServer->GetEnvironmentObject();
        $azure = $env->azure();

        try {
            $res = $azure->compute->virtualMachine->start(
                $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP),
                $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::SERVER_NAME)
            );

            if ($res) {
                parent::ResumeServer($DBServer);
                return true;
            } else {
                //TODO: Throw Exception
            }
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::SuspendServer()
     */
    public function SuspendServer(DBServer $DBServer)
    {
        $env = $DBServer->GetEnvironmentObject();
        $azure = $env->azure();

        try {
            return $azure->compute->virtualMachine->poweroff(
                $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP),
                $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::SERVER_NAME)
            );
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RebootServer()
     */
    public function RebootServer(DBServer $DBServer, $soft = true)
    {
        //NOT SUPPORTED
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RemoveServerSnapshot()
     */
    public function RemoveServerSnapshot(Image $image)
    {
        //NOT SUPPORTED
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CheckServerSnapshotStatus()
     */
    public function CheckServerSnapshotStatus(BundleTask $BundleTask)
    {
        //NOT SUPPORTED
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CreateServerSnapshot()
     */
    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        //NOT SUPPORTED
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
        try {
            $env = $DBServer->GetEnvironmentObject();
            $azure = $env->azure();
            $info = $azure->compute->virtualMachine->getModelViewInfo(
                $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP),
                $DBServer->GetProperty(\AZURE_SERVER_PROPERTIES::SERVER_NAME),
                true
            );

            $statuses = [];
            foreach ($info->properties->instanceView->statuses as $status) {
                $statusInfo = explode("/", $status->code);
                $statuses[$statusInfo[0]] = $statusInfo[1];
            }

            $networkInterfaceChunks = explode("/", $info->properties->networkProfile->networkInterfaces[0]->id);
            $networkInterface = array_pop($networkInterfaceChunks);

            $availabilitySetChunks = explode("/", $info->properties->availabilitySet->id);
            $availabilitySet = array_pop($availabilitySetChunks);

            $ips = $this->GetServerIPAddresses($DBServer);

            $tags = [];

            if (count($info->tags) > 0) {
                foreach ($info->tags as $key => $value) {
                    if (!empty($value))
                        $tags[] = "{$key}={$value}";
                    else
                        $tags[] = "{$key}";
                }
            }

            return [
                'Server Name'		    => $info->name,
                'Server Type'           => $info->properties->hardwareProfile->vmSize,
                'Availability Set'      => $availabilitySet,
                'Provisioning State'    => $statuses['ProvisioningState'],
                'Power State'           => $statuses['PowerState'],
                'Network Interface'     => $networkInterface,
                'Private IP'            => $ips['localIp'],
                'Public IP'             => $ips['remoteIp'],
                'Image'                 => sprintf("%s/%s/%s/%s",
                    $info->properties->storageProfile->imageReference->publisher,
                    $info->properties->storageProfile->imageReference->offer,
                    $info->properties->storageProfile->imageReference->sku,
                    $info->properties->storageProfile->imageReference->version
                 ),
                'Azure Agent'           => $info->properties->instanceView->vmAgent->vmAgentVersion,
                'Tags'                  => implode(', ', $tags)
            ];
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
    public function LaunchServer(DBServer $DBServer, Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $environment = $DBServer->GetEnvironmentObject();
        $governance = new \Scalr_Governance($DBServer->envId);
        $azure = $environment->azure();
        $subscriptionId = $environment->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID];

        if (!$launchOptions) {
            $dbFarmRole = $DBServer->GetFarmRoleObject();
            $DBRole = $dbFarmRole->GetRoleObject();

            $launchOptions = new \Scalr_Server_LaunchOptions();
            $launchOptions->cloudLocation = $dbFarmRole->CloudLocation;
            $launchOptions->serverType = $dbFarmRole->GetSetting(FarmRoleSetting::SETTING_AZURE_VM_SIZE);
            $launchOptions->availZone = $dbFarmRole->GetSetting(FarmRoleSetting::SETTING_AZURE_AVAIL_SET);

            $launchOptions->imageId = $DBRole->__getNewRoleObject()->getImage(\SERVER_PLATFORMS::AZURE, "")->imageId;

            // Set User Data
            $u_data = "";

            foreach ($DBServer->GetCloudUserData() as $k => $v) {
                $u_data .= "{$k}={$v};";
            }

            $launchOptions->userData = trim($u_data, ";");

            $launchOptions->azureResourceGroup = $dbFarmRole->GetSetting(FarmRoleSetting::SETTING_AZURE_RESOURCE_GROUP);
            $launchOptions->azureStorageAccount = $dbFarmRole->GetSetting(FarmRoleSetting::SETTING_AZURE_STORAGE_ACCOUNT);

            //Create NIC
            try {
                $ipConfigProperties = new IpConfigurationProperties(
                    ["id" => sprintf("/subscriptions/%s/resourceGroups/%s/providers/Microsoft.Network/virtualNetworks/%s/subnets/%s",
                        $subscriptionId,
                        $launchOptions->azureResourceGroup,
                        $dbFarmRole->GetSetting(FarmRoleSetting::SETTING_AZURE_VIRTUAL_NETWORK),
                        $dbFarmRole->GetSetting(FarmRoleSetting::SETTING_AZURE_SUBNET)
                    )], "Dynamic"
                );

                $publicIpName = null;
                if ($dbFarmRole->GetSetting(FarmRoleSetting::SETTING_AZURE_USE_PUBLIC_IPS)) {
                    //Create Public IP object
                    $publicIpName = "scalr-{$DBServer->serverId}";
                    $createPublicIpAddressRequest = new CreatePublicIpAddress(
                        $launchOptions->cloudLocation,
                        new PublicIpAddressProperties('Dynamic')
                    );

                    $ipCreateResult = $azure->network->publicIPAddress->create(
                        $subscriptionId,
                        $launchOptions->azureResourceGroup,
                        $publicIpName,
                        $createPublicIpAddressRequest
                    );
                }
                if ($publicIpName) {
                    $ipConfigProperties->publicIPAddress = ["id" => sprintf("/subscriptions/%s/resourceGroups/%s/providers/Microsoft.Network/publicIPAddresses/%s",
                        $subscriptionId,
                        $launchOptions->azureResourceGroup,
                        $publicIpName
                    )];
                }

                $nicProperties = new InterfaceProperties(
                    [new InterfaceIpConfigurationsData('public1', $ipConfigProperties)]
                );

                //Security group
                $sg = $dbFarmRole->GetSetting(FarmRoleSetting::SETTING_AZURE_SECURITY_GROUPS_LIST);

                if ($sg) {
                    $sgName = json_decode($sg);

                    if ($sgName) {
                        $sgroup = new SecurityGroupData();
                        $sgroup->id = sprintf('/subscriptions/%s/resourceGroups/%s/providers/Microsoft.Network/networkSecurityGroups/%s',
                            $subscriptionId,
                            $launchOptions->azureResourceGroup,
                            $sgName[0]
                        );
                        $nicProperties->setNetworkSecurityGroup($sgroup);
                    }
                }

                $createNicData = new CreateInterface($launchOptions->cloudLocation, $nicProperties);

                $nicResponse = $azure->network->interface->create(
                    $subscriptionId,
                    $launchOptions->azureResourceGroup,
                    "scalr-{$DBServer->serverId}",
                    $createNicData
                );
            } catch (\Exception $e) {
                throw new \Exception("Scalr is unable to create NetworkInterface: {$e->getMessage()}");
            }

            $launchOptions->azureNicName = "scalr-{$DBServer->serverId}";
            $launchOptions->azurePublicIpName = $publicIpName;
        }

        // Configure OS Profile
        // Make sure that password always have 1 special character.
        $adminPassword = \Scalr::GenerateSecurePassword(16, ['D' => '.']);
        $osProfile = new OsProfile('scalr', $adminPassword);
        $osProfile->computerName = \Scalr::GenerateUID(true);
        $osProfile->customData = base64_encode(trim($launchOptions->userData));

        // Configure Network Profile
        $networkProfile = [
            "networkInterfaces" => [
                ["id" => sprintf("/subscriptions/%s/resourceGroups/%s/providers/Microsoft.Network/networkInterfaces/%s",
                    $subscriptionId,
                    $launchOptions->azureResourceGroup,
                    $launchOptions->azureNicName
                )]
            ]
        ];

        // Configure Storage Profile
        $osDiskName = "scalr-{$DBServer->serverId}";
        $vhd = ['uri' => sprintf("https://%s.blob.core.windows.net/vhds/%s.vhd",
            $launchOptions->azureStorageAccount,
            $osDiskName
        )];
        $storageProfile = new StorageProfile(new OsDisk($osDiskName, $vhd, 'FromImage'));

        $isWindows = false;
        if (preg_match("/^([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)$/i", $launchOptions->imageId, $imageChunks)) {
            $storageProfile->setImageReference([
                'publisher' => $imageChunks[1],
                'offer'     => $imageChunks[2],
                'sku'       => $imageChunks[3],
                'version'   => $imageChunks[4]
            ]);

            $isWindows = ($imageChunks[1] == 'MicrosoftWindowsServer');
        } else {
            throw new \Exception("Image definition '{$launchOptions->imageId}' is not supported");
        }

        $vmProps = new VirtualMachineProperties(["vmSize" => $launchOptions->serverType], $networkProfile, $storageProfile, $osProfile);

        // Set availability set if configured.
        if ($launchOptions->availZone) {
            $vmProps->availabilitySet = [
                'id' => sprintf("/subscriptions/%s/resourceGroups/%s/providers/Microsoft.Compute/availabilitySets/%s",
                    $subscriptionId,
                    $launchOptions->azureResourceGroup,
                    $launchOptions->availZone
                )
            ];
        }

        $vmData = new CreateVirtualMachine($DBServer->serverId, $launchOptions->cloudLocation, $vmProps);

        $vmData->tags = $DBServer->getAzureTags();

        $azure->compute->virtualMachine->create(
            $subscriptionId,
            $launchOptions->azureResourceGroup,
            $vmData
        );

        $DBServer->setOsType($isWindows ? 'windows' : 'linux');

        $instanceTypeInfo = $this->getInstanceType(
            $launchOptions->serverType,
            $environment,
            $launchOptions->cloudLocation
        );
        /* @var $instanceTypeInfo CloudInstanceType */
        $DBServer->SetProperties([
            \AZURE_SERVER_PROPERTIES::SERVER_NAME       => $DBServer->serverId,
            \AZURE_SERVER_PROPERTIES::ADMIN_PASSWORD    => $adminPassword,
            \AZURE_SERVER_PROPERTIES::RESOURCE_GROUP    => $launchOptions->azureResourceGroup,
            \AZURE_SERVER_PROPERTIES::CLOUD_LOCATION    => $launchOptions->cloudLocation,
            \AZURE_SERVER_PROPERTIES::AVAIL_SET         => $launchOptions->availZone,
            \AZURE_SERVER_PROPERTIES::NETWORK_INTERFACE => $launchOptions->azureNicName,
            \AZURE_SERVER_PROPERTIES::PUBLIC_IP_NAME    => $launchOptions->azurePublicIpName,
            \SERVER_PROPERTIES::INFO_INSTANCE_VCPUS     => $instanceTypeInfo ? $instanceTypeInfo->vcpus : null,
        ]);

        $params = ['type' => $launchOptions->serverType];

        if ($instanceTypeInfo) {
            $params['instanceTypeName'] = $instanceTypeInfo->name;
        }

        $DBServer->imageId = $launchOptions->imageId;
        $DBServer->update($params);
        $DBServer->cloudLocation = $launchOptions->cloudLocation;
        $DBServer->cloudLocationZone = $launchOptions->availZone;

        // we set server history here
        $DBServer->getServerHistory();

        return $DBServer;
    }

    public function GetPlatformAccessData($environment, $DBServer)
    {
        $accessData = new \stdClass();
        //TODO: implement GetPlatformAccessData

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
        if (!($env instanceof \Scalr_Environment) || empty($cloudLocation)) {
            throw new \InvalidArgumentException(sprintf(
                "Method %s requires both environment object and cloudLocation to be specified.", __METHOD__
            ));
        }

        $collection = $this->getCachedInstanceTypes(\SERVER_PLATFORMS::AZURE, '', $cloudLocation);

        if ($collection === false || $collection->count() == 0) {
            $instanceTypesResult = $env->azure()->compute->location->getInstanceTypesList(
                $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                $cloudLocation
            );
            $ret = [];
            foreach ($instanceTypesResult as $instanceType) {
                $detailed[$instanceType->name] = [
                    'name' => $instanceType->name,
                    'ram' => $instanceType->memoryInMB,
                    'vcpus' => $instanceType->numberOfCores,
                    'disk' => $instanceType->resourceDiskSizeInMB / 1024,
                    'type' => '',
                    'maxdatadiskcount' => $instanceType->maxDataDiskCount,
                    'rootdevicesize' => $instanceType->osDiskSizeInMB / 1024
                ];

                if (!$details) {
                    $ret[$instanceType->name] = array($instanceType->name => $instanceType->name);
                } else {
                    $ret[$instanceType->name] = $detailed[$instanceType->name];
                }
            }

            CloudLocation::updateInstanceTypes(\SERVER_PLATFORMS::AZURE, '', $cloudLocation, $detailed);
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
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceIdPropertyName()
     */
    public function getInstanceIdPropertyName()
    {
        return \AZURE_SERVER_PROPERTIES::SERVER_NAME;
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
                        ->azure()
                        ->getClient();
    }
}
