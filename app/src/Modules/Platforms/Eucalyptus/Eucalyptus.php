<?php

use Scalr\Service\Aws\S3\DataType\ObjectData;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\Ec2\DataType\SecurityGroupFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\IpPermissionData;
use Scalr\Service\Aws\Ec2\DataType\IpRangeList;
use Scalr\Service\Aws\Ec2\DataType\IpRangeData;
use Scalr\Service\Aws\Ec2\DataType\UserIdGroupPairList;
use Scalr\Service\Aws\Ec2\DataType\UserIdGroupPairData;
use Scalr\Service\Aws\Ec2\DataType\RunInstancesRequestData;
use Scalr\Service\Aws\Ec2\DataType\PlacementResponseData;

class Modules_Platforms_Eucalyptus implements IPlatformModule
{
    /**
     * @var \ADODB_mysqli
     */
    private $db;

    /** Properties **/
    const ACCOUNT_ID 		= 'eucalyptus.account_id';
    const ACCESS_KEY		= 'eucalyptus.access_key';
    const SECRET_KEY		= 'eucalyptus.secret_key';
    const PRIVATE_KEY		= 'eucalyptus.private_key';
    const CERTIFICATE		= 'eucalyptus.certificate';
    const CLOUD_CERTIFICATE = 'eucalyptus.cloud_certificate';
    const EC2_URL			= 'eucalyptus.ec2_url';
    const S3_URL			= 'eucalyptus.s3_url';

    private $instancesListCache = array();

    /**
     * {@inheritdoc}
     * @see IPlatformModule::getLocations()
     */
    public function getLocations()
    {
        if (!Scalr::getContainer()->initialized('environment') ||
            !(Scalr::getContainer()->environment instanceof Scalr_Environment)) {
            return array();
        }

        $envId = Scalr::getContainer()->environment->id;

        //Eucalyptus locations defined by client. Admin cannot get them
        $db = $this->db;
        $locations = $db->GetAll("
            SELECT DISTINCT(`group`) as `name`
            FROM client_environment_properties
            WHERE `name` = ? AND env_id = ?
        ", array(
            self::EC2_URL, $envId
        ));
        $retval = array();
        foreach ($locations as $location)
            $retval[$location['name']] = "Eucalyptus / {$location['name']}";

        return $retval;
    }

    public function __construct()
    {
        $this->db = \Scalr::getDb();
    }

    public function GetServerCloudLocation(DBServer $DBServer)
    {
        return $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::REGION);
    }

    public function GetServerID(DBServer $DBServer)
    {
        return $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID);
    }

    public function GetServerFlavor(DBServer $DBServer)
    {
        return $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_TYPE);
    }

    public function IsServerExists(DBServer $DBServer, $debug = false)
    {
        return in_array(
            $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID),
            array_keys($this->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::REGION)))
        );
    }

    /**
     * {@inheritdoc}
     * @see IPlatformModule::GetServerIPAddresses()
     */
    public function GetServerIPAddresses(DBServer $DBServer)
    {
        $instance = $DBServer->GetEnvironmentObject()->eucalyptus($DBServer)
                             ->ec2->instance->describe($DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID))
                             ->get(0)->instancesSet->get(0);

        return array(
            'localIp'  => $instance->privateIpAddress,
            'remoteIp' => $instance->ipAddress
        );
    }

    /**
     * Gets the list of the Eucalyptus instances
     * for the specified environment and Euca location
     *
     * @param   Scalr_Environment $environment Environment Object
     * @param   string            $region      Eucalyptus location name
     * @param   bool              $skipCache   Whether it should skip the cache.
     * @return  array Returns array looks like array(InstanceId => stateName)
     */
    public function GetServersList(Scalr_Environment $environment, $region, $skipCache = false)
    {
        if (!$region) {
            return array();
        }
        if (empty($this->instancesListCache[$environment->id][$region]) || $skipCache) {
            try {
                $results = $environment->eucalyptus($region)->ec2->instance->describe();
            } catch (Exception $e) {
                throw new Exception(sprintf("Cannot get list of servers for platfrom euca: %s", $e->getMessage()));
            }
            if (count($results)) {
                foreach ($results as $reservation) {
                    /* @var $reservation Scalr\Service\Aws\Ec2\DataType\ReservationData */
                    foreach ($reservation->instancesSet as $instance) {
                        /* @var $instance Scalr\Service\Aws\Ec2\DataType\InstanceData */
                        $this->instancesListCache[$environment->id][$region][$instance->instanceId] =
                            $instance->instanceState->name;
                    }
                }
            }
        }

        return !empty($this->instancesListCache[$environment->id][$region]) ?
            $this->instancesListCache[$environment->id][$region] : array();
    }

    /**
     * {@inheritdoc}
     * @see IPlatformModule::GetServerRealStatus()
     */
    public function GetServerRealStatus(DBServer $DBServer)
    {
        $region = $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::REGION);
        $iid = $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID);

        if (!$iid || !$region) {
            $status = 'not-found';
        } elseif (empty($this->instancesListCache[$DBServer->GetEnvironmentObject()->id][$region][$iid])) {
            $euca = $DBServer->GetEnvironmentObject()->eucalyptus($region);

            try {
                $reservations = $euca->ec2->instance->describe($iid);

                if ($reservations && count($reservations) > 0 && $reservations->get(0)->instancesSet &&
                    count($reservations->get(0)->instancesSet) > 0) {
                    $status = $reservations->get(0)->instancesSet->get(0)->instanceState->name;
                } else {
                    $status = 'not-found';
                }

            } catch (Exception $e) {
                if (stristr($e->getMessage(), "does not exist")) {
                    $status = 'not-found';
                } else {
                    throw $e;
                }
            }
        } else {
            $status = $this->instancesListCache[$DBServer->GetEnvironmentObject()->id][$region][$iid];
        }

        return Modules_Platforms_Eucalyptus_Adapters_Status::load($status);
    }

    /**
     * {@inheritdoc}
     * @see IPlatformModule::TerminateServer()
     */
    public function TerminateServer(DBServer $DBServer)
    {
        $euca = $DBServer->GetEnvironmentObject()->eucalyptus($DBServer);
        $euca->ec2->instance->terminate($DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID));

        return true;
    }

    /**
     * {@inheritdoc}
     * @see IPlatformModule::RebootServer()
     */
    public function RebootServer(DBServer $DBServer)
    {
        $euca = $DBServer->GetEnvironmentObject()->eucalyptus($DBServer);
        $euca->ec2->instance->reboot($DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID));

        return true;
    }

    /**
     * {@inheritdoc}
     * @see IPlatformModule::RemoveServerSnapshot()
     */
    public function RemoveServerSnapshot(DBRole $DBRole)
    {
        foreach ($DBRole->getImageId(SERVER_PLATFORMS::EUCALYPTUS) as $location => $imageId) {
            try {
                $euca = $DBRole->getEnvironmentObject()->eucalyptus($location);
                try {
                    $ami = $euca->ec2->image->describe($imageId)->get(0);
                } catch (Exception $e) {
                    if (stristr($e->getMessage(), "Failure Signing Data") ||
                        stristr($e->getMessage(), "is no longer available") ||
                        stristr($e->getMessage(), "does not exist") ||
                        stristr($e->getMessage(), "Not authorized for image")) {

                        return true;
                    } else {

                        throw $e;
                    }
                }

                //$ami variable is expected to be defined here

                $platfrom = $ami->platform;
                $rootDeviceType = $ami->rootDeviceType;

                if ($rootDeviceType == 'ebs') {
                    $ami->deregister();

                    //blockDeviceMapping is not mandatory option in the response as well as ebs data set.
                    $snapshotId = $ami->blockDeviceMapping && count($ami->blockDeviceMapping) > 0 &&
                                  $ami->blockDeviceMapping->get(0)->ebs ?
                                  $ami->blockDeviceMapping->get(0)->ebs->snapshotId : null;

                    if ($snapshotId) {
                        $euca->ec2->snapshot->delete($snapshotId);
                    }
                } else {
                    $image_path = $ami->imageLocation;
                    $chunks = explode("/", $image_path);

                    $bucketName = array_shift($chunks);
                    $manifestObjectName = implode('/', $chunks);

                    $prefix = str_replace(".manifest.xml", "", $manifestObjectName);

                    try {
                        $bucket_not_exists = false;
                        $objects = $euca->s3->bucket->listObjects($bucketName, null, null, null, $prefix);
                    } catch (\Exception $e) {
                        if ($e instanceof ClientException &&
                            $e->getErrorData() instanceof ErrorData &&
                            $e->getErrorData()->getCode() == 404) {
                            $bucket_not_exists = true;
                        }
                    }

                    if ($ami) {
                        if (!$bucket_not_exists) {
                            /* @var $object ObjectData */
                            foreach ($objects as $object) {
                                $object->delete();
                            }
                            $bucket_not_exists = true;
                        }

                        if ($bucket_not_exists) {
                            $euca->ec2->image->deregister($imageId);
                        }
                    }
                }

                unset($euca);
                unset($ami);

            } catch (Exception $e) {
                if (stristr($e->getMessage(), "is no longer available") ||
                    stristr($e->getMessage(), "Not authorized for image")) {
                    continue;
                } else {
                    throw $e;
                }
            }
        }
    }

    public function CheckServerSnapshotStatus(BundleTask $BundleTask)
    {
        //    NOT SUPPORTED
    }

    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        $DBServer = DBServer::LoadByID($BundleTask->serverId);

        $euca = $DBServer->GetEnvironmentObject()->eucalyptus($DBServer);

        if (!$BundleTask->prototypeRoleId) {
            $protoImageId = $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::EMIID);
        }
        else {
            $protoImageId = DBRole::loadById($BundleTask->prototypeRoleId)->getImageId(
                SERVER_PLATFORMS::EUCALYPTUS,
                $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::REGION)
            );
        }

        $ami = $euca->ec2->image->describe($protoImageId)->get(0);
        $platfrom = $ami->platform;
        $rootDeviceType = $ami->rootDeviceType;

        if ($rootDeviceType == 'ebs') {
            $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EUCA_EBS;
            $BundleTask->Log(sprintf(_("Selected platfrom snapshoting type: %s"), $BundleTask->bundleType));
            $BundleTask->SnapshotCreationFailed("Not supported yet");
            return;
        }
        else {
            if ($platfrom == 'windows') {
                //TODO: Windows platfrom is not supported yet.
                $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EUCA_WIN;
                $BundleTask->Log(sprintf(_("Selected platfrom snapshoting type: %s"), $BundleTask->bundleType));
                $BundleTask->SnapshotCreationFailed("Not supported yet");
                return;
            } else {
                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
                $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EUCA_WSI;

                $BundleTask->Save();

                $BundleTask->Log(sprintf(_("Selected platfrom snapshoting type: %s"), $BundleTask->bundleType));

                $msg = new Scalr_Messaging_Msg_Rebundle(
                    $BundleTask->id,
                    $BundleTask->roleName,
                    array()
                );


                if (!$DBServer->SendMessage($msg)) {
                    $BundleTask->SnapshotCreationFailed("Cannot send rebundle message to server. Please check event log for more details.");
                    return;
                } else {
                    $BundleTask->Log(sprintf(_("Snapshot creation started (MessageID: %s). Bundle task status changed to: %s"),
                        $msg->messageId, $BundleTask->status
                    ));
                }
            }
        }

        $BundleTask->setDate('started');
        $BundleTask->Save();
    }

    private function ApplyAccessData(Scalr_Messaging_Msg $msg)
    {


    }

    public function GetServerConsoleOutput(DBServer $DBServer)
    {
        $euca = $DBServer->GetEnvironmentObject()->eucalyptus($DBServer);
        $c = $euca->ec2->instance->getConsoleOutput($DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID));

        if ($c->output) {
            $ret = $c->output;
        } else {
            $ret = false;
        }
        return $ret;
    }

    /**
     * {@inheritdoc}
     * @see IPlatformModule::GetServerExtendedInformation()
     */
    public function GetServerExtendedInformation(DBServer $DBServer)
    {
        try {
            $euca = $DBServer->GetEnvironmentObject()->eucalyptus($DBServer);

            $iid = $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID);
            if (!$iid)
                return false;

            $iinfo = $euca->ec2->instance->describe($iid)->get(0);

            if (isset($iinfo->instancesSet)) {

                $instanceData = $iinfo->instancesSet->get(0);

                if (isset($iinfo->groupSet[0]->groupId)) {
                    $infoGroups = $iinfo->groupSet;
                } elseif (isset($iinfo->instancesSet[0]->groupSet[0]->groupId)) {
                    $infoGroups = $instanceData->groupSet;
                } else {
                    $infoGroups = array();
                }

                $groups = array();
                foreach ($infoGroups as $sg) {
                    /* @var $sg \Scalr\Service\Aws\Ec2\DataType\SecurityGroupData */
                    $groups[] = $sg->groupName
                      . " (<a href='#/security/groups/" . $sg->groupId . "/edit"
                      . "?cloudLocation=" . $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::REGION)
                      . "&platform=eucalyptus'>" . $sg->groupId . "</a>)";
                }

                //monitoring isn't mandatory data set in the InstanceData
                $monitoring = isset($instanceData->monitoring->state) ?
                    $instanceData->monitoring->state : null;

                if ($monitoring == 'disabled')
                    $monitoring = "Disabled";
                else
                    $monitoring = "Enabled";

                try {
                    $statusInfo = $euca->ec2->instance->describeStatus(
                        $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID)
                    )->get(0);
                } catch (Exception $e) {
                }

                if (!empty($statusInfo)) {

                    if ($statusInfo->systemStatus->status == 'ok') {
                        $systemStatus = '<span style="color:green;">OK</span>';
                    } else {
                        $txtDetails = "";
                        if (!empty($statusInfo->systemStatus->details)) {
                            foreach ($statusInfo->systemStatus->details as $d) {
                                /* @var $d \Scalr\Service\Aws\Ec2\DataType\InstanceStatusDetailsSetData */
                                $txtDetails .= " {$d->name} is {$d->status},";
                            }
                        }
                        $txtDetails = trim($txtDetails, " ,");
                        $systemStatus = "<span style='color:red;'>"
                                      . $statusInfo->systemStatus->status
                                      . "</span> ({$txtDetails})";
                    }

                    if ($statusInfo->instanceStatus->status == 'ok') {
                        $iStatus = '<span style="color:green;">OK</span>';
                    } else {
                        $txtDetails = "";
                        foreach ($statusInfo->instanceStatus->details as $d) {
                            $txtDetails .= " {$d->name} is {$d->status},";
                        }
                        $txtDetails = trim($txtDetails, " ,");
                        $iStatus = "<span style='color:red;'>"
                                 . $statusInfo->instanceStatus->status
                                 . "</span> ({$txtDetails})";
                    }

                } else {
                    $systemStatus = "Unknown";
                    $iStatus = "Unknown";
                }

                $retval = array(
                    'AWS System Status'       => $systemStatus,
                    'AWS Instance Status'     => $iStatus,
                    'Cloud Server ID'         => $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID),
                    'Owner ID'                => $iinfo->ownerId,
                    'Image ID (EMI)'          => $instanceData->imageId,
                    'Public DNS name'         => $instanceData->dnsName,
                    'Private DNS name'        => $instanceData->privateDnsName,
                    'Public IP'               => $instanceData->ipAddress,
                    'Private IP'              => $instanceData->privateIpAddress,
                    'Key name'                => $instanceData->keyName,
                    //'AMI launch index'        => $instanceData->amiLaunchIndex,
                    'Instance type'           => $instanceData->instanceType,
                    'Launch time'             => $instanceData->launchTime->format('Y-m-d\TH:i:s.000\Z'),
                    'Architecture'            => $instanceData->architecture,
                    'Root device type'        => $instanceData->rootDeviceType,
                    'Instance state'          => $instanceData->instanceState->name . " ({$instanceData->instanceState->code})",
                    'Placement'               => isset($instanceData->placement) ? $instanceData->placement->availabilityZone : null,
                    'Tenancy'                 => isset($instanceData->placement) ? $instanceData->placement->tenancy : null,
                    'EBS Optimized'           => $instanceData->ebsOptimized ? "Yes" : "No",
                    'Monitoring (CloudWatch)' => $monitoring,
                    'Security groups'         => implode(', ', $groups)
                );
                if ($instanceData->subnetId) {
                    $retval['VPC ID'] = $instanceData->vpcId;
                    $retval['Subnet ID'] = $instanceData->subnetId;
                    $retval['SourceDesk Check'] = $instanceData->sourceDestCheck;

                    $ni = $instanceData->networkInterfaceSet->get(0);
                    if ($ni)
                        $retval['Network Interface'] = $ni->networkInterfaceId;
                }
                if ($instanceData->reason) {
                    $retval['Reason'] = $instanceData->reason;
                }

                return $retval;
            }
        } catch (Exception $e) {}

        return false;
    }

    public function LaunchServer(DBServer $DBServer, Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $runInstanceRequest = new RunInstancesRequestData(
            (isset($launchOptions->imageId) ? $launchOptions->imageId : null), 1, 1
        );

        $environment = $DBServer->GetEnvironmentObject();

        $placementData = null;
        $noSecurityGroups = false;

        if (!$launchOptions) {
            $launchOptions = new Scalr_Server_LaunchOptions();
            $DBRole = DBRole::loadById($DBServer->roleId);

            $dbFarmRole = $DBServer->GetFarmRoleObject();

            /*
            $runInstanceRequest->setMonitoring(
                $dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_ENABLE_CW_MONITORING)
            );
            */

            $launchOptions->imageId = $DBRole->getImageId(
                SERVER_PLATFORMS::EUCALYPTUS,
                $dbFarmRole->CloudLocation
            );

            // Need OS Family to get block device mapping for OEL roles
            $imageInfo = $DBRole->getImageDetails(
                SERVER_PLATFORMS::EUCALYPTUS,
                $dbFarmRole->CloudLocation
            );
            $launchOptions->osFamily = $imageInfo['os_family'];
            $launchOptions->cloudLocation = $dbFarmRole->CloudLocation;

            $akiId = $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::EKIID);
            if (!$akiId)
                $akiId = $dbFarmRole->GetSetting(DBFarmRole::SETTING_EUCA_EKI_ID);

            if ($akiId)
                $runInstanceRequest->kernelId = $akiId;

            $ariId = $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::ERIID);
            if (!$ariId)
                $ariId = $dbFarmRole->GetSetting(DBFarmRole::SETTING_EUCA_ERI_ID);

            if ($ariId)
                $runInstanceRequest->ramdiskId = $ariId;

            $launchOptions->serverType = $dbFarmRole->GetSetting(DBFarmRole::SETTING_EUCA_INSTANCE_TYPE);

            /*
            if ($dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_EBS_OPTIMIZED) == 1) {
                $runInstanceRequest->ebsOptimized = true;
            } else {
                $runInstanceRequest->ebsOptimized = false;
            }
            */

            foreach ($DBServer->GetCloudUserData() as $k => $v) {
                $u_data .= "{$k}={$v};";
            }

            $runInstanceRequest->userData = base64_encode(trim($u_data, ";"));

            /*
            $vpcId = $dbFarmRole->GetFarmObject()->GetSetting(DBFarm::SETTING_EC2_VPC_ID);
            if ($vpcId) {
                if ($DBRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER)) {
                    $networkInterface = new InstanceNetworkInterfaceSetRequestData();
                    $networkInterface->networkInterfaceId = $dbFarmRole->GetSetting(Scalr_Role_Behavior_Router::ROLE_VPC_NID);
                    $networkInterface->deviceIndex = 0;
                    $networkInterface->deleteOnTermination = false;

                    $runInstanceRequest->setNetworkInterface($networkInterface);
                    $noSecurityGroups = true;
                } else {

                    $vpcSubnetId = $dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_VPC_SUBNET_ID);
                    $vpcInternetAccess = $dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_VPC_INTERNET_ACCESS);
                    if (!$vpcSubnetId) {
                        $aws = $environment->aws($launchOptions->cloudLocation);

                        $subnet = $this->AllocateNewSubnet(
                            $aws->ec2,
                            $vpcId,
                            $dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_VPC_AVAIL_ZONE),
                            24
                        );

                        try {
                            $subnet->createTags(array(
                                array('key' => "scalr-id", 'value' => SCALR_ID),
                                array('key' => "scalr-sn-type", 'value' => $vpcInternetAccess),
                                array('key' => "Name", 'value' => 'Scalr System Subnet')
                            ));
                        } catch (Exception $e) {}

                        try {

                            $routeTableId = $dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_VPC_ROUTING_TABLE_ID);

                            Logger::getLogger('VPC')->warn(new FarmLogMessage($DBServer->farmId, "Internet access: {$vpcInternetAccess}"));

                            if (!$routeTableId) {
                                if ($vpcInternetAccess == Scalr_Role_Behavior_Router::INTERNET_ACCESS_OUTBOUND) {
                                    $routerRole = $DBServer->GetFarmObject()->GetFarmRoleByBehavior(ROLE_BEHAVIORS::VPC_ROUTER);
                                    if (!$routerRole) {
                                        if (\Scalr::config('scalr.instances_connection_policy') != 'local')
                                            throw new Exception("Outbound access require VPC router role in farm");
                                    }

                                    $networkInterfaceId = $routerRole->GetSetting(Scalr_Role_Behavior_Router::ROLE_VPC_NID);

                                    Logger::getLogger('EC2')->warn(new FarmLogMessage($DBServer->farmId, "Requesting outbound routing table. NID: {$networkInterfaceId}"));

                                    $routeTableId = $this->getRoutingTable($vpcInternetAccess, $aws, $networkInterfaceId, $vpcId);

                                    Logger::getLogger('EC2')->warn(new FarmLogMessage($DBServer->farmId, "Routing table ID: {$routeTableId}"));

                                } elseif ($vpcInternetAccess == Scalr_Role_Behavior_Router::INTERNET_ACCESS_FULL) {
                                    $routeTableId = $this->getRoutingTable($vpcInternetAccess, $aws, null, $vpcId);
                                }
                            }

                            $aws->ec2->routeTable->associate($routeTableId, $subnet->subnetId);

                        } catch (Exception $e) {

                            Logger::getLogger('EC2')->warn(new FarmLogMessage($DBServer->farmId, "Removing allocated subnet, due to routing table issues"));

                            $aws->ec2->subnet->delete($subnet->subnetId);
                            throw $e;
                        }

                        $vpcSubnetId = $subnet->subnetId;
                        $dbFarmRole->SetSetting(DBFarmRole::SETTING_AWS_VPC_SUBNET_ID, $vpcSubnetId, DBFarmRole::TYPE_LCL);
                    }

                    if ($vpcSubnetId) {
                        $runInstanceRequest->subnetId = $vpcSubnetId;
                    } else
                        throw new Exception("Unable to define subnetId for role in VPC");
                }
            }
            */
            $vpcId = false;
        } else {
            $runInstanceRequest->userData = base64_encode(trim($launchOptions->userData));
        }

        $governance = new Scalr_Governance($DBServer->envId);

        $euca = $environment->eucalyptus($launchOptions->cloudLocation);

        // Set AMI, AKI and ARI ids
        $runInstanceRequest->imageId = $launchOptions->imageId;

        $runInstanceRequest->instanceInitiatedShutdownBehavior = 'terminate';

        if (!$noSecurityGroups) {

            foreach ($this->GetServerSecurityGroupsList($DBServer, $euca->ec2, $vpcId, $governance) as $sgroup) {
                $runInstanceRequest->appendSecurityGroupId($sgroup);
            }

            if (!$runInstanceRequest->subnetId) {
                // Set availability zone
                if (!$launchOptions->availZone) {
                    $avail_zone = $this->GetServerAvailZone($DBServer, $euca->ec2, $launchOptions);
                    if ($avail_zone) {
                        $placementData = new PlacementResponseData($avail_zone);
                    }
                } else {
                    $placementData = new PlacementResponseData($launchOptions->availZone);
                }
            }
        }

        $runInstanceRequest->minCount = 1;
        $runInstanceRequest->maxCount = 1;

        // Set instance type
        $runInstanceRequest->instanceType = $launchOptions->serverType;

        if ($placementData !== null) {
            $runInstanceRequest->setPlacement($placementData);
        }

        $sshKey = Scalr_SshKey::init();
        if ($DBServer->status == SERVER_STATUS::TEMPORARY) {
            $keyName = "SCALR-ROLESBUILDER-" . SCALR_ID;
            $farmId = 0;
        } else {
            $keyName = $governance->getValue(Scalr_Governance::AWS_KEYPAIR);
            if ($keyName) {
                $skipKeyValidation = true;
            } else {
                $keyName = "FARM-{$DBServer->farmId}-" . SCALR_ID;
                $farmId = $DBServer->farmId;
                $oldKeyName = "FARM-{$DBServer->farmId}";
                if ($sshKey->loadGlobalByName($oldKeyName, $launchOptions->cloudLocation, $DBServer->envId, SERVER_PLATFORMS::EUCALYPTUS)) {
                    $keyName = $oldKeyName;
                    $skipKeyValidation = true;
                }
            }
        }
        if (!$skipKeyValidation && !$sshKey->loadGlobalByName($keyName, $launchOptions->cloudLocation, $DBServer->envId, SERVER_PLATFORMS::EUCALYPTUS)) {
            $result = $euca->ec2->keyPair->create($keyName);
            if ($result->keyMaterial) {
                $sshKey->farmId = $farmId;
                $sshKey->clientId = $DBServer->clientId;
                $sshKey->envId = $DBServer->envId;
                $sshKey->type = Scalr_SshKey::TYPE_GLOBAL;
                $sshKey->cloudLocation = $launchOptions->cloudLocation;
                $sshKey->cloudKeyName = $keyName;
                $sshKey->platform = SERVER_PLATFORMS::EUCALYPTUS;
                $sshKey->setPrivate($result->keyMaterial);
                $sshKey->setPublic($sshKey->generatePublicKey());
                $sshKey->save();
            }
        }

        $runInstanceRequest->keyName = $keyName;

        try {
            $result = $euca->ec2->instance->run($runInstanceRequest);
        } catch (Exception $e) {
            if (stristr($e->getMessage(), "The key pair") && stristr($e->getMessage(), "does not exist")) {
                $sshKey->delete();
                throw $e;
            }

            if (stristr($e->getMessage(), "The requested Availability Zone is no longer supported") ||
                stristr($e->getMessage(), "is not supported in your requested Availability Zone") ||
                stristr($e->getMessage(), "is currently constrained and we are no longer accepting new customer requests")) {

                $availZone = $runInstanceRequest->getPlacement() ?
                    $runInstanceRequest->getPlacement()->availabilityZone : null;

                if ($availZone) {
                    $DBServer->GetEnvironmentObject()->setPlatformConfig(
                        array("eucalyptus.{$launchOptions->cloudLocation}.{$availZone}.unavailable" => time())
                    );
                }

                throw $e;

            } else {
                throw $e;
            }
        }

        if ($result->instancesSet->get(0)->instanceId) {
            $DBServer->SetProperty(EUCA_SERVER_PROPERTIES::AVAIL_ZONE, $result->instancesSet->get(0)->placement->availabilityZone);
            $DBServer->SetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_ID, $result->instancesSet->get(0)->instanceId);
            $DBServer->SetProperty(EUCA_SERVER_PROPERTIES::INSTANCE_TYPE, $runInstanceRequest->instanceType);
            $DBServer->SetProperty(EUCA_SERVER_PROPERTIES::EMIID, $runInstanceRequest->imageId);
            $DBServer->SetProperty(EUCA_SERVER_PROPERTIES::REGION, $launchOptions->cloudLocation);
            $DBServer->SetProperty(EUCA_SERVER_PROPERTIES::ARCHITECTURE, $result->instancesSet->get(0)->architecture);

            $DBServer->osType = $result->instancesSet->get(0)->platform ? $result->instancesSet->get(0)->platform : 'linux';

            return $DBServer;
        } else {
            throw new Exception(sprintf(_("Cannot launch new instance. %s"), serialize($result)));
        }
    }

    /*********************************************************************/
    /*********************************************************************/
    /*********************************************************************/
    /*********************************************************************/
    /*********************************************************************/

/**
     * Gets the list of the security groups for the specified db server.
     *
     * If server does not have required security groups this method will create them.
     *
     * @param   DBServer               $DBServer The DB Server instance
     * @param   \Scalr\Service\Aws\Ec2 $ec2      Ec2 Client instance
     * @param   string                 $vpcId    optional The ID of VPC
     * @return  array  Returns array looks like array(groupid-1, groupid-2, ..., groupid-N)
     */
    private function GetServerSecurityGroupsList(DBServer $DBServer, \Scalr\Service\Aws\Ec2 $ec2, $vpcId = "", Scalr_Governance $governance = null)
    {
        $retval = array();
        $checkGroups = array();
        $sgGovernance = true;
        $allowAdditionalSgs = true;

        if ($governance) {
            $sgs = $governance->getValue(Scalr_Governance::EUCALYPTUS_SECURITY_GROUPS);
            if ($sgs !== null) {
                $governanceSecurityGroups = @explode(",", $sgs);
                if (!empty($governanceSecurityGroups)) {
                    foreach ($governanceSecurityGroups as $sg) {
                        if ($sg != '')
                            array_push($checkGroups, trim($sg));
                    }
                }

                $sgGovernance = false;
                $allowAdditionalSgs = $governance->getValue(Scalr_Governance::EUCALYPTUS_SECURITY_GROUPS, 'allow_additional_sec_groups');
            }
        }

        if (!$sgGovernance || $allowAdditionalSgs) {
            if ($DBServer->farmRoleId != 0) {
                $dbFarmRole = $DBServer->GetFarmRoleObject();
                if ($dbFarmRole->GetSetting(DBFarmRole::SETTING_EUCA_SECURITY_GROUPS_LIST) !== null) {
                    // New SG management
                    $sgs = @json_decode($dbFarmRole->GetSetting(DBFarmRole::SETTING_EUCA_SECURITY_GROUPS_LIST));
                    if (!empty($sgs)) {
                        foreach ($sgs as $sg) {
                            if (stripos($sg, 'sg-') === 0)
                                array_push($retval, $sg);
                            else
                                array_push($checkGroups, $sg);
                        }
                    }
                }
            } else
                array_push($checkGroups, 'scalr-rb-system');
        }

        // No name based security groups, return only SG ids.
        if (empty($checkGroups))
            return $retval;

        // Filter groups
        $filter = array(
            array(
                'name' => SecurityGroupFilterNameType::groupName(),
                'value' => $checkGroups,
            )
        );

        // If instance run in VPC, add VPC filter
        if ($vpcId != '') {
            $filter[] = array(
                'name'  => SecurityGroupFilterNameType::vpcId(),
                'value' => $vpcId
            );
        }

        // Get filtered list of SG required by scalr;
        try {
            $list = $ec2->securityGroup->describe(null, null, $filter);
            $sgList = array();
            foreach ($list as $sg) {
                /* @var $sg \Scalr\Service\Aws\Ec2\DataType\SecurityGroupData */
                if (($vpcId == '' && !$sg->vpcId) || ($vpcId && $sg->vpcId == $vpcId)) {
                    $sgList[$sg->groupName] = $sg->groupId;
                }
            }
            unset($list);
        } catch (Exception $e) {
            throw new Exception("Cannot get list of security groups (1): {$e->getMessage()}");
        }

        foreach ($checkGroups as $groupName) {
            // Check default SG
            if ($groupName == 'default') {
                array_push($retval, $sgList[$groupName]);

            // Check Roles builder SG
            } elseif ($groupName == 'scalr-rb-system') {
                if (!isset($sgList[$groupName])) {
                    try {
                        $securityGroupId = $ec2->securityGroup->create(
                            'scalr-rb-system', "Security group for Roles Builder", $vpcId
                        );
                        $ipRangeList = new IpRangeList();
                        foreach (\Scalr::config('scalr.aws.ip_pool') as $ip) {
                            $ipRangeList->append(new IpRangeData($ip));
                        }

                        sleep(2);

                        $ec2->securityGroup->authorizeIngress(array(
                            new IpPermissionData('tcp', 22, 22, $ipRangeList),
                            new IpPermissionData('tcp', 8008, 8013, $ipRangeList)
                        ), $securityGroupId);

                        $sgList['scalr-rb-system'] = $securityGroupId;
                    } catch (Exception $e) {
                        throw new Exception(sprintf(_("Cannot create security group '%s': %s"), 'scalr-rb-system', $e->getMessage()));
                    }
                }
                array_push($retval, $sgList[$groupName]);

            //Check scalr-farm.* security group
            } elseif (stripos($groupName, 'scalr-farm.') === 0) {
                if (!isset($sgList[$groupName])) {
                    try {
                        $securityGroupId = $ec2->securityGroup->create(
                            $groupName, sprintf("Security group for FarmID N%s", $DBServer->farmId), $vpcId
                        );

                        sleep(2);

                        $userIdGroupPairList = new UserIdGroupPairList(new UserIdGroupPairData(
                            $DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID),
                            null,
                            $groupName
                        ));

                        $ec2->securityGroup->authorizeIngress(array(
                            new IpPermissionData('tcp', 0, 65535, null, $userIdGroupPairList),
                            new IpPermissionData('udp', 0, 65535, null, $userIdGroupPairList)
                        ), $securityGroupId);

                        $sgList[$groupName] = $securityGroupId;

                    } catch (Exception $e) {
                        throw new Exception(sprintf(
                            _("Cannot create security group '%s': %s"), $groupName, $e->getMessage()
                        ));
                    }
                }
                array_push($retval, $sgList[$groupName]);

            //Check scalr-role.* security group
            } elseif (stripos($groupName, 'scalr-role.') === 0) {
                if (!isset($sgList[$groupName])) {
                    try {
                        $securityGroupId = $ec2->securityGroup->create(
                            $groupName,
                            sprintf("Security group for FarmRoleID N%s on FarmID N%s", $DBServer->GetFarmRoleObject()->ID, $DBServer->farmId),
                            $vpcId
                        );

                        sleep(2);

                        // DB rules
                        $dbRules = $DBServer->GetFarmRoleObject()->GetRoleObject()->getSecurityRules();
                        $groupRules = array();
                        foreach ($dbRules as $rule) {
                            $groupRules[Scalr_Util_CryptoTool::hash($rule['rule'])] = $rule;
                        }

                        // Behavior rules
                        foreach (Scalr_Role_Behavior::getListForFarmRole($DBServer->GetFarmRoleObject()) as $bObj) {
                            $bRules = $bObj->getSecurityRules();
                            foreach ($bRules as $r) {
                                if ($r) {
                                    $groupRules[Scalr_Util_CryptoTool::hash($r)] = array('rule' => $r);
                                }
                            }
                        }

                        // Default rules
                        $userIdGroupPairList = new UserIdGroupPairList(new UserIdGroupPairData(
                            $DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID),
                            null,
                            $groupName
                        ));
                        $rules = array(
                            new IpPermissionData('tcp', 0, 65535, null, $userIdGroupPairList),
                            new IpPermissionData('udp', 0, 65535, null, $userIdGroupPairList)
                        );

                        foreach ($groupRules as $rule) {
                            $group_rule = explode(":", $rule["rule"]);
                            $rules[] = new IpPermissionData(
                                $group_rule[0], $group_rule[1], $group_rule[2],
                                new IpRangeData($group_rule[3])
                            );
                        }

                        $ec2->securityGroup->authorizeIngress($rules, $securityGroupId);

                        $sgList[$groupName] = $securityGroupId;

                    } catch (Exception $e) {
                        throw new Exception(sprintf(_("Cannot create security group '%s': %s"), $groupName, $e->getMessage()));
                    }
                }
                array_push($retval, $sgList[$groupName]);
            } elseif ($groupName == \Scalr::config('scalr.aws.security_group_name')) {
                if (!isset($sgList[$groupName])) {
                    try {
                        $securityGroupId = $ec2->securityGroup->create(
                            $groupName, "Security rules needed by Scalr", $vpcId
                        );

                        $ipRangeList = new IpRangeList();
                        foreach (\Scalr::config('scalr.aws.ip_pool') as $ip) {
                            $ipRangeList->append(new IpRangeData($ip));
                        }
                        // TODO: Open only FOR VPC ranges
                        $ipRangeList->append(new IpRangeData('10.0.0.0/8'));

                        sleep(2);

                        $ec2->securityGroup->authorizeIngress(array(
                            new IpPermissionData('tcp', 3306, 3306, $ipRangeList),
                            new IpPermissionData('tcp', 8008, 8013, $ipRangeList),
                            new IpPermissionData('udp', 8014, 8014, $ipRangeList),
                        ), $securityGroupId);

                        $sgList[$groupName] = $securityGroupId;

                    } catch (Exception $e) {
                        throw new Exception(sprintf(_("Cannot create security group '%s': %s"), $groupName, $e->getMessage()));
                    }
                }
                array_push($retval, $sgList[$groupName]);
            } else {
                if (!isset($sgList[$groupName])) {
                    throw new Exception(sprintf(_("Security group '%s' is not found"), $groupName));
                } else
                    array_push($retval, $sgList[$groupName]);
            }
        }

        return $retval;
    }


    /**
     * Gets Avail zone for the specified DB server
     *
     * @param   DBServer                   $DBServer
     * @param   \Scalr\Service\Aws\Ec2     $ec2
     * @param   Scalr_Server_LaunchOptions $launchOptions
     */
    private function GetServerAvailZone(DBServer $DBServer, \Scalr\Service\Aws\Ec2 $ec2,
                                        Scalr_Server_LaunchOptions $launchOptions)
    {
        if ($DBServer->status == SERVER_STATUS::TEMPORARY)
            return false;

        $euca = $DBServer->GetEnvironmentObject()->eucalyptus($DBServer);

        $server_avail_zone = $DBServer->GetProperty(EUCA_SERVER_PROPERTIES::AVAIL_ZONE);

        if ($DBServer->replaceServerID && !$server_avail_zone) {
            try {
                $rDbServer = DBServer::LoadByID($DBServer->replaceServerID);
                $server_avail_zone = $rDbServer->GetProperty(EUCA_SERVER_PROPERTIES::AVAIL_ZONE);
            } catch (Exception $e) {
            }
        }

        if ($server_avail_zone &&
            $server_avail_zone != 'x-scalr-diff' &&
            !stristr($server_avail_zone, "x-scalr-custom")) {
            return $server_avail_zone;
        }

        $role_avail_zone = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_EUCA_AVAIL_ZONE);

        $DBServer->SetProperty("tmp.euca.avail_zone.algo2", "[S={$server_avail_zone}][R2:{$role_avail_zone}]");

        if (!$role_avail_zone) {
            return false;
        }

        if ($role_avail_zone == "x-scalr-diff" || stristr($role_avail_zone, "x-scalr-custom")) {
            //TODO: Elastic Load Balancer
            $avail_zones = array();
            if (stristr($role_avail_zone, "x-scalr-custom")) {
                $zones = explode("=", $role_avail_zone);
                foreach (explode(":", $zones[1]) as $zone) {
                    if ($zone != "") {
                        array_push($avail_zones, $zone);
                    }
                }

            } else {
                // Get list of all available zones
                $avail_zones_resp = $ec2->availabilityZone->describe();
                foreach ($avail_zones_resp as $zone) {
                    /* @var $zone \Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData */
                    $zoneName = $zone->zoneName;
                    $zone->zoneState = 'available';

                    if (strstr($zone->zoneState, 'available')) {
                        $isUnavailable = $DBServer->GetEnvironmentObject()->getPlatformConfigValue(
                            "aws.{$launchOptions->cloudLocation}.{$zoneName}.unavailable",
                            false
                        );
                        if ($isUnavailable && $isUnavailable + 3600 < time()) {
                            $DBServer->GetEnvironmentObject()->setPlatformConfig(
                                array(
                                    "aws.{$launchOptions->cloudLocation}.{$zoneName}.unavailable" => false
                                ),
                                false
                            );
                            $isUnavailable = false;
                        }

                        if (!$isUnavailable) {
                            array_push($avail_zones, $zoneName);
                        }
                    }
                }
            }

            sort($avail_zones);
            $avail_zones = array_reverse($avail_zones);

            $servers = $DBServer->GetFarmRoleObject()->GetServersByFilter(array("status" => array(
                SERVER_STATUS::RUNNING,
                SERVER_STATUS::INIT,
                SERVER_STATUS::PENDING
            )));
            $availZoneDistribution = array();
            foreach ($servers as $cDbServer) {
                if ($cDbServer->serverId != $DBServer->serverId) {
                    $availZoneDistribution[$cDbServer->GetProperty(EUCA_SERVER_PROPERTIES::AVAIL_ZONE)]++;
                }
            }

            $sCount = 1000000;
            foreach ($avail_zones as $zone) {
                if ((int)$availZoneDistribution[$zone] <= $sCount) {
                    $sCount = (int)$availZoneDistribution[$zone];
                    $availZone = $zone;
                }
            }

            $aZones = implode(",", $avail_zones);
            $dZones = "";
            foreach ($availZoneDistribution as $zone => $num) {
                $dZones .= "({$zone}:{$num})";
            }

            $DBServer->SetProperty("tmp.euca.avail_zone.algo2", "[A:{$aZones}][D:{$dZones}][S:{$availZone}]");

            return $availZone;
        } else {
            return $role_avail_zone;
        }
    }

    public function GetPlatformAccessData($environment, DBServer $DBServer)
    {
        $accessData = new stdClass();
        $accessData->accountId = $environment->getPlatformConfigValue(self::ACCOUNT_ID, true, $DBServer->GetCloudLocation());
        $accessData->keyId = $environment->getPlatformConfigValue(self::ACCESS_KEY, true, $DBServer->GetCloudLocation());
        $accessData->key = $environment->getPlatformConfigValue(self::SECRET_KEY, true, $DBServer->GetCloudLocation());
        $accessData->cert = $environment->getPlatformConfigValue(self::CERTIFICATE, true, $DBServer->GetCloudLocation());
        $accessData->pk = $environment->getPlatformConfigValue(self::PRIVATE_KEY, true, $DBServer->GetCloudLocation());
        $accessData->ec2_url = $environment->getPlatformConfigValue(self::EC2_URL, true, $DBServer->GetCloudLocation());
        $accessData->s3_url = $environment->getPlatformConfigValue(self::S3_URL, true, $DBServer->GetCloudLocation());
        $accessData->cloud_cert = $environment->getPlatformConfigValue(self::CLOUD_CERTIFICATE, true, $DBServer->GetCloudLocation());

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
        $put |= $message instanceof Scalr_Messaging_Msg_MountPointsReconfigure;

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
}

