<?php

namespace Scalr\Modules\Platforms\Ec2;

use FarmLogMessage;
use \Logger;
use Scalr\Service\Aws\Exception\InstanceNotFoundException;
use Scalr\Service\Aws\S3\DataType\ObjectData;
use Scalr\Service\Aws\Client\ClientException as AwsClientException;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\Ec2\DataType\SecurityGroupFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\IpPermissionData;
use Scalr\Service\Aws\Ec2\DataType\IpRangeList;
use Scalr\Service\Aws\Ec2\DataType\IpRangeData;
use Scalr\Service\Aws\Ec2\DataType\UserIdGroupPairList;
use Scalr\Service\Aws\Ec2\DataType\UserIdGroupPairData;
use Scalr\Service\Aws\Ec2\DataType\CreateImageRequestData;
use Scalr\Service\Aws\Ec2\DataType\RunInstancesRequestData;
use Scalr\Service\Aws\Ec2\DataType\BlockDeviceMappingData;
use Scalr\Service\Aws\Ec2\DataType\PlacementResponseData;
use Scalr\Service\Aws\Ec2\DataType\InstanceNetworkInterfaceSetRequestData;
use Scalr\Service\Aws\Ec2\DataType\SubnetFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\InternetGatewayFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\RouteTableFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\IamInstanceProfileRequestData;
use Scalr\Service\Aws\Ec2\DataType\ReservationList;
use Scalr\Service\Aws\Ec2\DataType\VolumeData;
use Scalr\Service\Aws\Ec2\DataType\VolumeFilterNameType;
use Scalr\Modules\Platforms\AbstractAwsPlatformModule;
use Scalr\Modules\Platforms\Ec2\Adapters\StatusAdapter;
use Scalr\Service\Aws\Ec2\DataType\RouteData;
use Scalr\Model\Entity\Image;
use \EC2_SERVER_PROPERTIES;
use \DBServer;
use \DBFarm;
use \Exception;
use \SERVER_PLATFORMS;
use \DBRole;
use \SERVER_SNAPSHOT_CREATION_TYPE;
use \BundleTask;
use \ROLE_TAGS;
use \SERVER_PROPERTIES;
use \SERVER_SNAPSHOT_CREATION_STATUS;
use \ROLE_BEHAVIORS;
use \SERVER_STATUS;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Ec2\DataType\EbsBlockDeviceData;
use Scalr\Service\Aws\Ec2\DataType\BlockDeviceMappingList;

class Ec2PlatformModule extends AbstractAwsPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{
    /** Properties **/
    const ACCOUNT_ID 	= 'ec2.account_id';
    const ACCESS_KEY	= 'ec2.access_key';
    const SECRET_KEY	= 'ec2.secret_key';
    const PRIVATE_KEY	= 'ec2.private_key';
    const CERTIFICATE	= 'ec2.certificate';
    const ACCOUNT_TYPE  = 'ec2.account_type';

    const DEFAULT_VPC_ID = 'ec2.vpc.default';
    const ACCOUNT_TYPE_GOV_CLOUD = 'gov-cloud';
    const ACCOUNT_TYPE_CN_CLOUD = 'cn-cloud';

    /**
     * @var array
     */
    public $instancesListCache;

    protected $resumeStrategy = \Scalr_Role_Behavior::RESUME_STRATEGY_INIT;

    public function __construct()
    {
        parent::__construct();
        $this->instancesListCache = array();
    }

    public function getPropsList()
    {
        return array(
            self::ACCOUNT_ID	=> 'AWS Account ID',
            self::ACCESS_KEY	=> 'AWS Access Key',
            self::SECRET_KEY	=> 'AWS Secret Key',
            self::CERTIFICATE	=> 'AWS x.509 Certificate',
            self::PRIVATE_KEY	=> 'AWS x.509 Private Key'
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceTypes()
     */
    public function getInstanceTypes(\Scalr_Environment $env = null, $cloudLocation = null, $details = false)
    {
        $definition = array(
            't1.micro' => array(
               'name' => 't1.micro',
               'ram' => '625',
               'vcpus' => '1',
               'disk' => '',
               'type' => '',
               'note' => 'SHARED CPU'
            ),

            't2.micro' => array(
                'name' => 't2.micro',
                'ram' => '1024',
                'vcpus' => '1',
                'disk' => '',
                'type' => '',
                'note' => 'SHARED CPU'
            ),

            't2.small' => array(
                'name' => 't2.small',
                'ram' => '2048',
                'vcpus' => '1',
                'disk' => '',
                'type' => '',
                'note' => 'SHARED CPU'
            ),

            't2.medium' => array(
                'name' => 't2.medium',
                'ram' => '4096',
                'vcpus' => '2',
                'disk' => '',
                'type' => '',
                'note' => 'SHARED CPU'
            ),

            'm1.small' => array(
               'name' => 'm1.small',
               'ram' => '1740',
               'vcpus' => '1',
               'disk' => '160',
               'type' => 'HDD'
            ),
            'm1.medium' => array(
               'name' => 'm1.medium',
               'ram' => '3840',
               'vcpus' => '1',
               'disk' => '410',
               'type' => 'HDD'
            ),
            'm1.large' => array(
               'name' => 'm1.large',
               'ram' => '7680',
               'vcpus' => '2',
               'disk' => '840',
               'type' => 'HDD'
            ),
            'm1.xlarge' => array(
               'name' => 'm1.xlarge',
               'ram' => '15360',
               'vcpus' => '4',
               'disk' => '1680',
               'type' => 'HDD'
            ),

            'm2.xlarge' => array(
               'name' => 'm2.xlarge',
               'ram' => '17510',
               'vcpus' => '2',
               'disk' => '420',
               'type' => 'HDD'
            ),
            'm2.2xlarge' => array(
               'name' => 'm2.2xlarge',
               'ram' => '35021',
               'vcpus' => '4',
               'disk' => '850',
               'type' => 'HDD'
            ),
            'm2.4xlarge' => array(
               'name' => 'm2.4xlarge',
               'ram' => '66355',
               'vcpus' => '8',
               'disk' => '1680',
               'type' => 'HDD'
            ),

            'm3.medium' => array(
               'name' => 'm3.medium',
               'ram' => '3840',
               'vcpus' => '1',
               'disk' => '4',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'm3.large' => array(
               'name' => 'm3.large',
               'ram' => '7680',
               'vcpus' => '2',
               'disk' => '32',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'm3.xlarge' => array(
               'name' => 'm3.xlarge',
               'ram' => '15360',
               'vcpus' => '4',
               'disk' => '80',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'm3.2xlarge' => array(
               'name' => 'm3.2xlarge',
               'ram' => '30720',
               'vcpus' => '8',
               'disk' => '160',
               'type' => 'SSD',
               'ebsencryption' => true
            ),

            'c1.medium' => array(
               'name' => 'c1.medium',
               'ram' => '1741',
               'vcpus' => '2',
               'disk' => '350',
               'type' => 'HDD'
            ),
            'c1.xlarge' => array(
               'name' => 'c1.xlarge',
               'ram' => '7168',
               'vcpus' => '8',
               'disk' => '1680',
               'type' => 'HDD'
            ),

            'c3.large' => array(
               'name' => 'c3.large',
               'ram' => '3840',
               'vcpus' => '2',
               'disk' => '32',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'c3.xlarge' => array(
               'name' => 'c3.xlarge',
               'ram' => '7680',
               'vcpus' => '4',
               'disk' => '80',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'c3.2xlarge' => array(
               'name' => 'c3.2xlarge',
               'ram' => '15360',
               'vcpus' => '8',
               'disk' => '160',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'c3.4xlarge' => array(
               'name' => 'c3.4xlarge',
               'ram' => '30720',
               'vcpus' => '16',
               'disk' => '320',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'c3.8xlarge' => array(
               'name' => 'c3.8xlarge',
               'ram' => '61440',
               'vcpus' => '32',
               'disk' => '640',
               'type' => 'SSD',
               'ebsencryption' => true
            ),

            'r3.large' => array(
               'name' => 'r3.large',
               'ram' => '15360',
               'vcpus' => '2',
               'disk' => '32',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'r3.xlarge' => array(
               'name' => 'r3.xlarge',
               'ram' => '31232',
               'vcpus' => '4',
               'disk' => '80',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'r3.2xlarge' => array(
               'name' => 'r3.2xlarge',
               'ram' => '62464',
               'vcpus' => '8',
               'disk' => '160',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'r3.4xlarge' => array(
               'name' => 'r3.4xlarge',
               'ram' => '124928',
               'vcpus' => '16',
               'disk' => '320',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'r3.8xlarge' => array(
               'name' => 'r3.8xlarge',
               'ram' => '249856',
               'vcpus' => '32',
               'disk' => '640',
               'type' => 'SSD',
               'ebsencryption' => true
            ),

            'i2.xlarge' => array(
               'name' => 'i2.xlarge',
               'ram' => '31232',
               'vcpus' => '4',
               'disk' => '800',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'i2.2xlarge' => array(
               'name' => 'i2.2xlarge',
               'ram' => '62464',
               'vcpus' => '8',
               'disk' => '1600',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'i2.4xlarge' => array(
               'name' => 'i2.4xlarge',
               'ram' => '124928',
               'vcpus' => '16',
               'disk' => '3200',
               'type' => 'SSD',
               'ebsencryption' => true
            ),
            'i2.8xlarge' => array(
               'name' => 'i2.8xlarge',
               'ram' => '249856',
               'vcpus' => '32',
               'disk' => '6400',
               'type' => 'SSD',
               'ebsencryption' => true
            ),

            'g2.2xlarge' => array(
               'name' => 'g2.2xlarge',
               'ram' => '15360',
               'vcpus' => '8',
               'disk' => '60',
               'type' => 'SSD',
               'note' => 'GPU',
               'ebsencryption' => true
            ),

            'hs1.8xlarge' => array(
                'name' => 'hs1.8xlarge',
                'ram' => '119808',
                'vcpus' => '16',
                'disk' => '49152',
                'type' => 'SSD'
            ),

            'cc2.8xlarge' => array(
               'name' => 'cc2.8xlarge',
               'ram' => '61952',
               'vcpus' => '32',
               'disk' => '3360',
               'type' => 'HDD'
            ),
            'cg1.4xlarge' => array(
               'name' => 'cg1.4xlarge',
               'ram' => '23040',
               'vcpus' => '16',
               'disk' => '1680',
               'type' => 'HDD',
               'note' => 'GPU'
            ),
            'hi1.4xlarge' => array(
               'name' => 'hi1.4xlarge',
               'ram' => '61952',
               'vcpus' => '16',
               'disk' => '2048',
               'type' => 'SSD'
            ),
            'cr1.8xlarge' => array(
               'name' => 'cr1.8xlarge',
               'ram' => '249856',
               'vcpus' => '32',
               'disk' => '240',
               'type' => 'SSD',
               'ebsencryption' => true
            )
        );

        // New region supports only new instance types
        if ($cloudLocation == Aws::REGION_EU_CENTRAL_1) {
            foreach ($definition as $key => $value) {
                if (!in_array(substr($key, 0, 2), array('t2','m3','c3','r3','i2')))
                    unset($definition[$key]);
            }
        }


        if (!$details)
            return array_combine(array_keys($definition), array_keys($definition));
        else
            return $definition;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::hasCloudPrices()
     */
    public function hasCloudPrices(\Scalr_Environment $env)
    {
        if (!$this->container->analytics->enabled) return false;

        if (in_array($env->getPlatformConfigValue(self::ACCOUNT_TYPE), array(self::ACCOUNT_TYPE_GOV_CLOUD, self::ACCOUNT_TYPE_CN_CLOUD))) {
            $locations = $this->getLocations($env);
            $cloudLocation = key($locations);
        }

        return $this->container->analytics->prices->hasPriceForUrl(
            \SERVER_PLATFORMS::EC2, '', (isset($cloudLocation) ? $cloudLocation : null)
        );
    }

    public function getRoutingTables(\Scalr\Service\Aws\Ec2 $ec2, $vpcId)
    {
        $filter = array(array(
            'name'  => RouteTableFilterNameType::vpcId(),
            'value' => $vpcId,
        ));

        $retval = array();
        $list = $ec2->routeTable->describe(null, $filter);

        if ($list->count() > 0) {
            /* @var $rTable \Scalr\Service\Aws\Ec2\DataType\RouteTableData */
            foreach($list as $rTable) {

                $main = false;
                $subnets = array();
                foreach ($rTable->associationSet as $association) {
                    if($association->subnetId)
                        $subnets[] = $association->subnetId;

                    if ($association->main == true)
                        $main = true;
                }

                /* @var $route \Scalr\Service\Aws\Ec2\DataType\RouteData */
                $type = 'private';
                $destination = null;
                foreach ($rTable->routeSet as $route) {
                    if ($route->state == RouteData::STATE_ACTIVE) {
                        if ($route->destinationCidrBlock == '0.0.0.0/0') {
                            if (substr($route->gatewayId, 0, 3) == 'igw')
                                $type = 'public';
                            else {
                                if ($route->networkInterfaceId)
                                    $destination = $route->networkInterfaceId;
                                elseif ($route->gatewayId)
                                    $destination = $route->gatewayId;
                                else
                                    $destination = $route->instanceId;
                            }
                        }
                    }
                }

                $name = null;
                foreach ($rTable->tagSet as $tag) {
                    if ($tag->key == 'Name')
                        $name = $tag->value;
                }

                $retval[] = array(
                    'id' => $rTable->routeTableId,
                    'subnets' => $subnets,
                    'type' => $type,
                    'lastResortDestination' => $destination,
                    'tags' => $rTable->tagSet->toArray(),
                    'vpcId' => $rTable->vpcId,
                    'main' => $main,
                    'name' => $name
                );
            }
        }

        return $retval;
    }


    public function listSubnets(\Scalr_Environment $env, $cloudLocation, $vpcId, $extended = true, $subnetId = null)
    {
        $aws = $env->aws($cloudLocation);

        if ($extended)
            $routingTables = $this->getRoutingTables($aws->ec2, $vpcId);

        $filter = array(array(
            'name'  => SubnetFilterNameType::vpcId(),
            'value' => $vpcId
        ));

        $subnets = $aws->ec2->subnet->describe($subnetId, $filter);
        $retval = array();
        /* @var $subnet \Scalr\Service\Aws\Ec2\DataType\SubnetData  */
        foreach ($subnets as $subnet) {
            $item = array(
                'id'          => $subnet->subnetId,
                'description' => "{$subnet->subnetId} ({$subnet->cidrBlock} in {$subnet->availabilityZone})",
                'cidr'        => $subnet->cidrBlock,
                'availability_zone' => $subnet->availabilityZone,
                'ips_left' => $subnet->availableIpAddressCount
            );

            foreach ($subnet->tagSet as $tag) {
                if ($tag->key == 'Name')
                    $item['name'] = $tag->value;
            }

            if ($extended) {
                foreach ($routingTables as $table) {
                    if (in_array($subnet->subnetId, $table['subnets']))
                        $item['type'] = $table['type'];

                    if ($table['main'])
                        $mainTableType = $table['type'];
                }
            }

            if (!$item['type'])
                $item['type'] = $mainTableType;

            $retval[] = $item;
        }

        return ($subnetId) ? $retval[0] : $retval;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerCloudLocation()
     */
    public function GetServerCloudLocation(DBServer $DBServer)
    {
        return $DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerID()
     */
    public function GetServerID(DBServer $DBServer)
    {
        return $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerFlavor()
     */
    public function GetServerFlavor(DBServer $DBServer)
    {
        return $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_TYPE);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::IsServerExists()
     */
    public function IsServerExists(DBServer $DBServer, $debug = false)
    {
        $list = $this->GetServersList(
            $DBServer->GetEnvironmentObject(),
            $DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION)
        );
        return !is_array($list) ? false :
            in_array($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID), array_keys($list));
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerIPAddresses()
     */
    public function GetServerIPAddresses(DBServer $DBServer)
    {
        $instanceId = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
        $list = $DBServer->GetEnvironmentObject()->aws($DBServer)
                         ->ec2->instance->describe($instanceId);

        if (!($list instanceof ReservationList)) {
            throw new Exception(sprintf(
                "ReservationList object is expected for describe instance response. InstanceId:%s, Environment:%d",
                $instanceId, $DBServer->envId
            ));
        } else if (count($list) == 0) {
            throw new Exception(sprintf(
                "Instance %s has not been found in the cloud. Environment:%d",
                $instanceId, $DBServer->envId
            ));
        }

        $instance = $list->get(0)->instancesSet->get(0);

        return array(
            'localIp'  => $instance->privateIpAddress,
            'remoteIp' => $instance->ipAddress
        );
    }

    /**
     * Gets the list of the EC2 instances
     * for the specified environment and AWS location
     *
     * @param   \Scalr_Environment $environment Environment Object
     * @param   string            $region      EC2 location name
     * @param   bool              $skipCache   Whether it should skip the cache.
     * @return  array Returns array looks like array(InstanceId => stateName)
     */
    public function GetServersList(\Scalr_Environment $environment, $region, $skipCache = false)
    {
        if (!$region)
            return array();

        $aws = $environment->aws($region);

        $cacheKey = sprintf('%s:%s', $environment->id, $region);

        if (!isset($this->instancesListCache[$cacheKey]) || $skipCache) {
            $cacheValue = array();
            $nextToken = null;
            $results = null;

            do {
                try {
                    if (isset($results)) {
                        $nextToken = $results->getNextToken();
                    }
                    $results = $aws->ec2->instance->describe(null, null, $nextToken);
                } catch (Exception $e) {
                    throw new Exception(sprintf("Cannot get list of servers for platfrom ec2: %s", $e->getMessage()));
                }

                if (count($results)) {
                    foreach ($results as $reservation) {
                        /* @var $reservation Scalr\Service\Aws\Ec2\DataType\ReservationData */
                        foreach ($reservation->instancesSet as $instance) {
                            /* @var $instance Scalr\Service\Aws\Ec2\DataType\InstanceData */
                            $cacheValue[sprintf('%s:%s', $environment->id, $region)][$instance->instanceId] = $instance->instanceState->name;
                        }
                    }
                }
            } while ($results->getNextToken());

            foreach ($cacheValue as $offset => $value) {
                $this->instancesListCache[$offset] = $value;
            }
        }

        return isset($this->instancesListCache[$cacheKey]) ? $this->instancesListCache[$cacheKey] : array();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerRealStatus()
     */
    public function GetServerRealStatus(DBServer $DBServer)
    {
        $region = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION);
        $iid = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);

        $cacheKey = sprintf('%s:%s', $DBServer->GetEnvironmentObject()->id, $region);

        if (!$iid || !$region) {
            $status = 'not-found';
        } elseif (empty($this->instancesListCache[$cacheKey][$iid])) {
            $aws = $DBServer->GetEnvironmentObject()->aws($region);

            try {
                $reservations = $aws->ec2->instance->describe($iid);

                if ($reservations && count($reservations) > 0 && $reservations->get(0)->instancesSet &&
                    count($reservations->get(0)->instancesSet) > 0) {
                    $status = $reservations->get(0)->instancesSet->get(0)->instanceState->name;
                } else {
                    $status = 'not-found';
                }

            } catch (InstanceNotFoundException $e) {
                $status = 'not-found';
            }
        } else {
            $status = $this->instancesListCache[$cacheKey][$iid];
        }

        return StatusAdapter::load($status);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::ResumeServer()
     */
    public function ResumeServer(DBServer $DBServer)
    {
        $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);
        $aws->ec2->instance->start($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID));

        parent::ResumeServer($DBServer);

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::SuspendServer()
     */
    public function SuspendServer(DBServer $DBServer)
    {
        $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);
        $aws->ec2->instance->stop($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID));

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::TerminateServer()
     */
    public function TerminateServer(DBServer $DBServer)
    {
        $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);
        $aws->ec2->instance->terminate($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID));

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RebootServer()
     */
    public function RebootServer(DBServer $DBServer, $soft = true)
    {
        $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);
        $aws->ec2->instance->reboot($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID));

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RemoveServerSnapshot()
     */
    public function RemoveServerSnapshot(Image $image)
    {
        try {
            if (! $image->getEnvironment())
                return true;

            $aws = $image->getEnvironment()->aws($image->cloudLocation);
            try {
                $ami = $aws->ec2->image->describe($image->id)->get(0);
            } catch (Exception $e) {
                if (stristr($e->getMessage(), "is no longer available") ||
                    stristr($e->getMessage(), "does not exist")) {
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
                    $aws->ec2->snapshot->delete($snapshotId);
                }
            } else {
                $image_path = $ami->imageLocation;
                $chunks = explode("/", $image_path);

                $bucketName = array_shift($chunks);
                $manifestObjectName = implode('/', $chunks);

                $prefix = str_replace(".manifest.xml", "", $manifestObjectName);

                try {
                    $bucket_not_exists = false;
                    $objects = $aws->s3->bucket->listObjects($bucketName, null, null, null, $prefix);
                } catch (\Exception $e) {
                    if ($e instanceof AwsClientException &&
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
                        $aws->ec2->image->deregister($image->id);
                    }
                }
            }

            unset($aws);
            unset($ami);

        } catch (Exception $e) {
            if (stristr($e->getMessage(), "is no longer available")) {
            } else {
                throw $e;
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CheckServerSnapshotStatus()
     */
    public function CheckServerSnapshotStatus(BundleTask $BundleTask)
    {
        if ($BundleTask->bundleType == SERVER_SNAPSHOT_CREATION_TYPE::EC2_WIN2003) {

        } else if ($BundleTask->bundleType == SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM) {
            try {
                $DBServer = DBServer::LoadByID($BundleTask->serverId);

                $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);

                $ami = $aws->ec2->image->describe($BundleTask->snapshotId)->get(0);

                $BundleTask->Log(sprintf("Checking snapshot creation status: %s", $ami->imageState));

                $metaData = $BundleTask->getSnapshotDetails();
                if ($ami->imageState == 'available') {

                    $metaData['szr_version'] = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_VESION);

                    if ($ami->rootDeviceType == 'ebs') {
                        $tags[] = ROLE_TAGS::EC2_EBS;
                    }

                    if ($ami->virtualizationType == 'hvm') {
                        $tags[] = ROLE_TAGS::EC2_HVM;
                    }

                    $metaData['tags'] = $tags;

                    $BundleTask->SnapshotCreationComplete($BundleTask->snapshotId, $metaData);
                } else {
                    if ($ami->imageState == 'failed') {
                        $BundleTask->SnapshotCreationFailed("AMI in FAILED state. Reason: {$ami->stateReason->message}");
                    } else {
                        $BundleTask->Log("CheckServerSnapshotStatus: AMI status = {$ami->imageState}. Waiting...");
                    }
                }
            } catch (Exception $e) {
                \Logger::getLogger(__CLASS__)->fatal("CheckServerSnapshotStatus ({$BundleTask->id}): {$e->getMessage()}");
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CreateServerSnapshot()
     */
    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        $DBServer = DBServer::LoadByID($BundleTask->serverId);
        $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);

        if (!$BundleTask->prototypeRoleId) {
            $proto_image_id = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::AMIID);

        } else {
            $protoRole = DBRole::loadById($BundleTask->prototypeRoleId);

            $image = $protoRole->__getNewRoleObject()->getImage(
                SERVER_PLATFORMS::EC2,
                $DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION)
            );
            $proto_image_id = $image->imageId;

            //Bundle EC2 in AWS way
            if (in_array($image->getImage()->osFamily, array('oel', 'redhat', 'scientific'))) {
                $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
            }
           
            if ($image->getImage()->osFamily == 'centos' && $image->getImage()->osGeneration == '7') {
                $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
            }
        
            if ($image->getImage()->osFamily == 'amazon' && $image->getImage()->osVersion == '2014.09') {
                $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
            }
        }

        $callEc2CreateImage = false;
        $reservationSet = $aws->ec2->instance->describe($DBServer->GetCloudServerID())->get(0);
        $ec2Server = $reservationSet->instancesSet->get(0);
        
        if ($ec2Server->platform == 'windows') {
            if ($ec2Server->rootDeviceType != 'ebs') {
                $BundleTask->SnapshotCreationFailed("Only EBS root filesystem supported for Windows servers.");
                return;
            }
            if ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::PENDING) {
                $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                $BundleTask->Log(sprintf(_("Selected platfrom snapshoting type: %s"), $BundleTask->bundleType));
                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::PREPARING;
                
                $msg = $DBServer->SendMessage(new \Scalr_Messaging_Msg_Win_PrepareBundle($BundleTask->id));
                $BundleTask->Log(sprintf(
                    _("PrepareBundle message sent. MessageID: %s. Bundle task status changed to: %s"),
                    $msg->messageId, $BundleTask->status
                ));
                $BundleTask->Save();
            } elseif ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::PREPARING) {
                $callEc2CreateImage = true;
            }
        } else {
            if ($image) {
                $BundleTask->Log(sprintf(
                    _("Image OS: %s %s"),
                    $image->getImage()->osFamily, $image->getImage()->osGeneration
                ));
            }
            
            $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
            if (!$BundleTask->bundleType) {
                if ($ec2Server->rootDeviceType == 'ebs') {
                    if ($ec2Server->virtualizationType == 'hvm') {
                        $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    } else {
                        $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS;
                    }
                } else {
                    $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_S3I;
                }
                
                $BundleTask->Log(sprintf(_("Selected platfrom snapshoting type: %s"), $BundleTask->bundleType));
            }
            $BundleTask->Save();
            
            if ($BundleTask->bundleType == SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM) {
                $callEc2CreateImage = true;
            } else {
                $msg = new \Scalr_Messaging_Msg_Rebundle($BundleTask->id, $BundleTask->roleName, array());
                $metaData = $BundleTask->getSnapshotDetails();

                if ($DBServer->IsSupported('2.11.4')) {
                    $msg->rootVolumeTemplate = $metaData['rootBlockDeviceProperties'];
                } else if ($metaData['rootBlockDeviceProperties']) {
                    $msg->volumeSize = $metaData['rootBlockDeviceProperties']['size'];
                }
                
                $DBServer->SendMessage($msg);
                    
                $BundleTask->Log(sprintf(
                    _("Snapshot creation started (MessageID: %s). Bundle task status changed to: %s"),
                    $msg->messageId, $BundleTask->status
                ));
            }
        }
        
        if ($callEc2CreateImage) {
            try {
                $metaData = $BundleTask->getSnapshotDetails();
            
                $ebs = new EbsBlockDeviceData();
                $bSetEbs = false;
                if ($metaData['rootBlockDeviceProperties']) {
                    if ($metaData['rootBlockDeviceProperties']['size']) {
                        $ebs->volumeSize = $metaData['rootBlockDeviceProperties']['size'];
                        $bSetEbs = true;
                    }
            
                    if ($metaData['rootBlockDeviceProperties']['volume_type']) {
                        $ebs->volumeType = $metaData['rootBlockDeviceProperties']['volume_type'];
                        $bSetEbs = true;
                    }
            
                    if ($metaData['rootBlockDeviceProperties']['iops']) {
                        $ebs->iops = $metaData['rootBlockDeviceProperties']['iops'];
                        $bSetEbs = true;
                    }
                }
            
                $blockDeviceMapping = new BlockDeviceMappingList();
                
                if ($bSetEbs)
                    $blockDeviceMapping->append(new BlockDeviceMappingData($ec2Server->rootDeviceName, null, null, $ebs));
                
                //TODO: Remove all attached devices other than root device from blockDeviceMapping
                $currentMapping = $ec2Server->blockDeviceMapping;
                $BundleTask->Log(sprintf(
                    _("Server block device mapping: %s"),
                    json_encode($currentMapping->toArray())
                ));
                foreach ($currentMapping as $blockDeviceMappingData) {
                    /* @var $blockDeviceMappingData \Scalr\Service\Aws\Ec2\DataType\InstanceBlockDeviceMappingResponseData */
                    
                    if ($blockDeviceMappingData->deviceName != $ec2Server->rootDeviceName) {
                        $blockDeviceMapping->append(['deviceName' => $blockDeviceMappingData->deviceName, 'noDevice' => '']);
                    } else {
                        if (!$bSetEbs) {
                            $ebsInfo = $aws->ec2->volume->describe($blockDeviceMappingData->ebs->volumeId)->get(0);
                            $ebs->volumeSize = $ebsInfo->size;
                            $ebs->deleteOnTermination = true;
                            $ebs->volumeType = $ebsInfo->volumeType;
                            $ebs->encrypted = $ebsInfo->encrypted;
                            if ($ebsInfo->volumeType == 'io1')
                                $ebs->iops = $ebsInfo->iops;
                            
                            $blockDeviceMapping->append(new BlockDeviceMappingData($ec2Server->rootDeviceName, null, null, $ebs));
                        }
                    }
                }
                
                if ($blockDeviceMapping->count() == 0)
                    $blockDeviceMapping = null;
                else {
                    /*
                    $BundleTask->Log(sprintf(
                        _("New block device mapping: %s"),
                        json_encode($blockDeviceMapping->toArray())
                    ));
                    */
                }
                
                $request = new CreateImageRequestData(
                    $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID),
                    $BundleTask->roleName . "-" . date("YmdHi"),
                    $blockDeviceMapping
                );
            
                $request->description = $BundleTask->roleName;
                $request->noReboot = false;
            
                $imageId = $aws->ec2->image->create($request);
            
                $BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
                $BundleTask->snapshotId = $imageId;
                $BundleTask->Log(sprintf(
                    _("Snapshot creating initialized (AMIID: %s). Bundle task status changed to: %s"),
                    $BundleTask->snapshotId, $BundleTask->status
                ));
                $BundleTask->Save();
            } catch (Exception $e) {
                $BundleTask->SnapshotCreationFailed($e->getMessage() . "(".json_encode($request).")");
                return;
            }
        }
        
        $BundleTask->setDate('started');
        $BundleTask->Save();
    }

    private function ApplyAccessData(\Scalr_Messaging_Msg $msg)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerConsoleOutput()
     */
    public function GetServerConsoleOutput(DBServer $DBServer)
    {
        $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);
        $c = $aws->ec2->instance->getConsoleOutput($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID));

        if ($c->output) {
            $ret = $c->output;
        } else {
            $ret = false;
        }
        return $ret;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerExtendedInformation()
     */
    public function GetServerExtendedInformation(DBServer $DBServer, $extended = false)
    {
        try {
            $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);

            $iid = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
            if (!$iid)
                return false;

            $iinfo = $aws->ec2->instance->describe($iid)->get(0);
            $blockStorage = null;

            if (isset($iinfo->instancesSet)) {

                if ($extended) {
                    $filter = array(array(
                        'name'  => VolumeFilterNameType::attachmentInstanceId(),
                        'value' => $iid,
                    ));
                    
                    $ebs = $aws->ec2->volume->describe(null, $filter);
                    foreach ($ebs as $volume) {
                        /* @var $volume \Scalr\Service\Aws\Ec2\DataType\VolumeData */                    
                        
                        $blockStorage[] = $volume->attachmentSet->get(0)->device . " - {$volume->size} Gb"
                        . " (<a href='#/tools/aws/ec2/ebs/volumes/" . $volume->volumeId . "/view"
                            . "?cloudLocation=" . $DBServer->GetCloudLocation()
                            . "&platform=ec2'>" . $volume->volumeId . "</a>)";
                        
                        //array('id' => $volume->volumeId, 'size' => $volume->size, 'device' => $volume->attachmentSet->get(0)->device);
                    }
                }
                
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
                      . "?cloudLocation=" . $DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION)
                      . "&platform=ec2'>" . $sg->groupId . "</a>)";
                }

                $tags = array();
                if ($instanceData->tagSet->count() > 0) {
                    foreach ($instanceData->tagSet as $tag) {
                        /* @var $tag \Scalr\Service\Aws\Ec2\DataType\ResourceTagSetData */
                        if ($tag->value)
                            $tags[] = "{$tag->key}={$tag->value}";
                        else
                            $tags[] = "{$tag->key}";
                    }
                }

                //monitoring isn't mandatory data set in the InstanceData
                $monitoring = isset($instanceData->monitoring->state) ?
                    $instanceData->monitoring->state : null;

                if ($monitoring == 'disabled')
                    $monitoring = "Disabled";
                else
                    $monitoring = "Enabled";

                $retval = array(
                    'Cloud Server ID'         => $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID),
                    'Owner ID'                => $iinfo->ownerId,
                    'Image ID (AMI)'          => $instanceData->imageId,
                    'Public DNS name'         => $instanceData->dnsName,
                    'Private DNS name'        => $instanceData->privateDnsName,
                    'Public IP'               => $instanceData->ipAddress,
                    'Private IP'              => $instanceData->privateIpAddress,
                    'Key name'                => $instanceData->keyName,
                    //'AMI launch index'        => $instanceData->amiLaunchIndex,
                    'Instance type'           => $instanceData->instanceType,
                    'Launch time'             => $instanceData->launchTime->format('Y-m-d\TH:i:s.000\Z'),
                    'Architecture'            => $instanceData->architecture,
                    'IAM Role'                => $instanceData->iamInstanceProfile->arn,
                    'Root device type'        => $instanceData->rootDeviceType,
                    'Instance state'          => $instanceData->instanceState->name . " ({$instanceData->instanceState->code})",
                    'Placement'               => isset($instanceData->placement) ? $instanceData->placement->availabilityZone : null,
                    'Tenancy'                 => isset($instanceData->placement) ? $instanceData->placement->tenancy : null,
                    'EBS Optimized'           => $instanceData->ebsOptimized ? "Yes" : "No",
                    'Monitoring (CloudWatch)' => $monitoring,
                    'Security groups'         => implode(', ', $groups),
                    'Tags'                    => implode(', ', $tags)
                );
                
                if ($extended) {
                    try {
                        $statusInfo = $aws->ec2->instance->describeStatus(
                            $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID)
                        )->get(0);
                    } catch (Exception $e) {}
                
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
                    
                    $retval['AWS System Status'] = $systemStatus;
                    $retval['AWS Instance Status'] = $iStatus;
                }
                
                if ($blockStorage) {
                    $retval['Block storage'] = implode(', ', $blockStorage);
                }
                
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
        } catch (Exception $e) {
        }

        return false;
    }

    public function getRoutingTable($type, $aws, $networkInterfaceId = null, $vpcId)
    {
        //Check for routing table
        $filter = array(array(
            'name'  => RouteTableFilterNameType::vpcId(),
            'value' => $vpcId,
        ), array(
            'name'  => RouteTableFilterNameType::tagKey(),
            'value' => 'scalr-rt-type'
        ), array(
            'name'  => RouteTableFilterNameType::tagValue(),
            'value' => $type
        ));

        $list = $aws->ec2->routeTable->describe(null, $filter);
        if ($list->count() > 0) {
            if ($type == \Scalr_Role_Behavior_Router::INTERNET_ACCESS_FULL)
                $routingTable = $list->get(0);
            else {
                /* @var $routingTable \Scalr\Service\Aws\Ec2\DataType\RouteTableData */
                foreach($list as $rTable) {
                    foreach ($rTable->tagSet as $tag) {
                        if ($tag->key == 'scalr-vpc-nid' && $tag->value == $networkInterfaceId) {
                            $routingTable = $rTable;
                            break;
                        }
                    }

                    if ($routingTable)
                        break;
                }
            }
        }

        $tags = array(
            array('key' => "scalr-id", 'value' => SCALR_ID),
            array('key' => "scalr-rt-type", 'value' => $type),
            array('key' => "Name", 'value' => "Scalr System Routing table for {$type} internet access")
        );

        if (!$routingTable) {
            // Create routing table for FULL internet access
            $routingTable = $aws->ec2->routeTable->create($vpcId);
            // Add new route for internet
            if ($type == \Scalr_Role_Behavior_Router::INTERNET_ACCESS_FULL) {
                // GET IGW
                $igwList = $aws->ec2->internetGateway->describe(null, array(array(
                    'name'  => InternetGatewayFilterNameType::attachmentVpcId(),
                    'value' => $vpcId,
                )));
                $igw = $igwList->get(0);
                if (!$igw) {
                    $igw = $aws->ec2->internetGateway->create();
                    $aws->ec2->internetGateway->attach($igw->internetGatewayId, $vpcId);

                    try {
                        $igw->createTags(array(
                            array('key' => "scalr-id", 'value' => SCALR_ID),
                            array('key' => "Name", 'value' => 'Scalr System IGW')
                        ));
                    } catch (Exception $e) {}
                }
                $igwId = $igw->internetGatewayId;

                // Add new route for internet
                $aws->ec2->routeTable->createRoute($routingTable->routeTableId, '0.0.0.0/0', $igwId);
            } else {
                //outbound-only
                $aws->ec2->routeTable->createRoute($routingTable->routeTableId, '0.0.0.0/0', null, null,
                    $networkInterfaceId
                );

                $tags[] = array('key' => "scalr-vpc-nid", 'value' => $networkInterfaceId);
            }

            try {
                $routingTable->createTags($tags);
            } catch (Exception $e) {}
        }

        return $routingTable->routeTableId;
    }

    public function getDefaultVpc(\Scalr_Environment $environment, $cloudLocation)
    {
        $vpcId = $environment->getPlatformConfigValue(self::DEFAULT_VPC_ID.".{$cloudLocation}");
        if ($vpcId === null || $vpcId === false) {
            $vpcId = "";

            $aws = $environment->aws($cloudLocation);
            $list = $aws->ec2->describeAccountAttributes(array('default-vpc'));
            foreach ($list as $item) {
                if ($item->attributeName == 'default-vpc')
                    $vpcId = $item->attributeValueSet[0]->attributeValue;
            }

            if ($vpcId == 'none')
                $vpcId = '';

            $environment->setPlatformConfig(array(
                self::DEFAULT_VPC_ID . ".{$cloudLocation}" => $vpcId
            ));
        }

        return $vpcId;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::LaunchServer()
     */
    public function LaunchServer(DBServer $DBServer, \Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $runInstanceRequest = new RunInstancesRequestData(
            (isset($launchOptions->imageId) ? $launchOptions->imageId : null), 1, 1
        );

        $environment = $DBServer->GetEnvironmentObject();
        $governance = new \Scalr_Governance($DBServer->envId);


        $placementData = null;
        $noSecurityGroups = false;

        if (!$launchOptions) {
            $launchOptions = new \Scalr_Server_LaunchOptions();

            $dbFarmRole = $DBServer->GetFarmRoleObject();
            $DBRole = $dbFarmRole->GetRoleObject();

            $runInstanceRequest->setMonitoring(
                $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_ENABLE_CW_MONITORING)
            );

            $image = $DBRole->__getNewRoleObject()->getImage(
                SERVER_PLATFORMS::EC2,
                $dbFarmRole->CloudLocation
            );

            $launchOptions->imageId = $image->imageId;

            // Need OS Family to get block device mapping for OEL roles
            $launchOptions->osFamily = $image->getImage()->osFamily;
            $launchOptions->cloudLocation = $dbFarmRole->CloudLocation;

            $akiId = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::AKIID);
            if (!$akiId)
                $akiId = $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_AKI_ID);

            if ($akiId)
                $runInstanceRequest->kernelId = $akiId;

            $ariId = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::ARIID);
            if (!$ariId)
                $ariId = $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_ARI_ID);

            if ($ariId)
                $runInstanceRequest->ramdiskId = $ariId;

            $iType = $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_INSTANCE_TYPE);
            $launchOptions->serverType = $iType;

            // Check governance of instance types
            $types = $governance->getValue('ec2', 'aws.instance_type');
            if (count($types) > 0) {
                if (!in_array($iType, $types))
                    throw new Exception(sprintf(
                        "Instance type '%s' was prohibited to use by scalr account owner",
                        $iType
                    ));
            }
            /*
            $iamProfileName = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_IAM, 'iam_instance_profile_arn');
            if ($iamProfileName) {
                $iamInstanceProfile = new IamInstanceProfileRequestData(null, $iamProfileName);
                $runInstanceRequest->setIamInstanceProfile($iamInstanceProfile);
            } else {
            */
                $iamProfileArn = $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_IAM_INSTANCE_PROFILE_ARN);
                if ($iamProfileArn) {
                    $iamInstanceProfile = new IamInstanceProfileRequestData($iamProfileArn);
                    $runInstanceRequest->setIamInstanceProfile($iamInstanceProfile);
                }
            //}

            if ($dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_EBS_OPTIMIZED) == 1)
                $runInstanceRequest->ebsOptimized = true;
            else
                $runInstanceRequest->ebsOptimized = false;

            // Custom user-data (base.custom_user_data)
            foreach ($DBServer->GetCloudUserData() as $k => $v)
                $u_data .= "{$k}={$v};";
            $u_data = trim($u_data, ";");

            $customUserData = $dbFarmRole->GetSetting('base.custom_user_data');
            if ($customUserData) {
                $repos = $DBServer->getScalarizrRepository();
                
                $userData = str_replace(array(
                    '{SCALR_USER_DATA}', 
                    '{RPM_REPO_URL}',
                    '{DEB_REPO_URL}'
                ), array(
                    $u_data,
                    $repos['rpm_repo_url'],
                    $repos['deb_repo_url']
                ), $customUserData);
            } else {
                $userData = $u_data;
            }

            $runInstanceRequest->userData = base64_encode($userData);

            $vpcId = $dbFarmRole->GetFarmObject()->GetSetting(DBFarm::SETTING_EC2_VPC_ID);
            if ($vpcId) {
                if ($DBRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER)) {
                    $networkInterface = new InstanceNetworkInterfaceSetRequestData();
                    $networkInterface->networkInterfaceId = $dbFarmRole->GetSetting(\Scalr_Role_Behavior_Router::ROLE_VPC_NID);
                    $networkInterface->deviceIndex = 0;
                    $networkInterface->deleteOnTermination = false;

                    $runInstanceRequest->setNetworkInterface($networkInterface);
                    $noSecurityGroups = true;
                } else {

                    $vpcSubnetId = $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_VPC_SUBNET_ID);

                    // VPC Support v2
                    if ($vpcSubnetId && substr($vpcSubnetId, 0, 6) != 'subnet') {
                        $subnets = json_decode($vpcSubnetId);

                        $servers = $DBServer->GetFarmRoleObject()->GetServersByFilter(array("status" => array(
                            SERVER_STATUS::RUNNING,
                            SERVER_STATUS::INIT,
                            SERVER_STATUS::PENDING
                        )));
                        $subnetsDistribution = array();
                        foreach ($servers as $cDbServer) {
                            if ($cDbServer->serverId != $DBServer->serverId)
                                $subnetsDistribution[$cDbServer->GetProperty(EC2_SERVER_PROPERTIES::SUBNET_ID)]++;
                        }

                        $sCount = 1000000;
                        foreach ($subnets as $subnet) {
                            if ((int)$subnetsDistribution[$subnet] <= $sCount) {
                                $sCount = (int)$subnetsDistribution[$subnet];
                                $selectedSubnetId = $subnet;
                            }
                        }

                    } else {
                        $vpcInternetAccess = $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_VPC_INTERNET_ACCESS);
                        if (!$vpcSubnetId) {
                            $aws = $environment->aws($launchOptions->cloudLocation);

                            $subnet = $this->AllocateNewSubnet(
                                $aws->ec2,
                                $vpcId,
                                $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_VPC_AVAIL_ZONE),
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

                                $routeTableId = $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_VPC_ROUTING_TABLE_ID);

                                \Logger::getLogger('VPC')->warn(new \FarmLogMessage($DBServer->farmId, "Internet access: {$vpcInternetAccess}"));

                                if (!$routeTableId) {
                                    if ($vpcInternetAccess == \Scalr_Role_Behavior_Router::INTERNET_ACCESS_OUTBOUND) {
                                        $routerRole = $DBServer->GetFarmObject()->GetFarmRoleByBehavior(ROLE_BEHAVIORS::VPC_ROUTER);
                                        if (!$routerRole) {
                                            if (\Scalr::config('scalr.instances_connection_policy') != 'local')
                                                throw new Exception("Outbound access require VPC router role in farm");
                                        }

                                        $networkInterfaceId = $routerRole->GetSetting(\Scalr_Role_Behavior_Router::ROLE_VPC_NID);

                                        \Logger::getLogger('EC2')->warn(new \FarmLogMessage($DBServer->farmId, "Requesting outbound routing table. NID: {$networkInterfaceId}"));

                                        $routeTableId = $this->getRoutingTable($vpcInternetAccess, $aws, $networkInterfaceId, $vpcId);

                                        \Logger::getLogger('EC2')->warn(new \FarmLogMessage($DBServer->farmId, "Routing table ID: {$routeTableId}"));

                                    } elseif ($vpcInternetAccess == \Scalr_Role_Behavior_Router::INTERNET_ACCESS_FULL) {
                                        $routeTableId = $this->getRoutingTable($vpcInternetAccess, $aws, null, $vpcId);
                                    }
                                }

                                $aws->ec2->routeTable->associate($routeTableId, $subnet->subnetId);

                            } catch (Exception $e) {

                                \Logger::getLogger('EC2')->warn(new \FarmLogMessage($DBServer->farmId, "Removing allocated subnet, due to routing table issues"));

                                $aws->ec2->subnet->delete($subnet->subnetId);
                                throw $e;
                            }

                            $selectedSubnetId = $subnet->subnetId;
                            $dbFarmRole->SetSetting(\DBFarmRole::SETTING_AWS_VPC_SUBNET_ID, $selectedSubnetId, \DBFarmRole::TYPE_LCL);
                        } else
                            $selectedSubnetId = $vpcSubnetId;
                    }

                    if ($selectedSubnetId) {
                        $networkInterface = new InstanceNetworkInterfaceSetRequestData();
                        $networkInterface->deviceIndex = 0;
                        $networkInterface->deleteOnTermination = true;

                        //
                        //Check network private or public
                        //
                        // We don't need public IP for private subnets
                        $info = $this->listSubnets($environment, $launchOptions->cloudLocation, $vpcId, true, $selectedSubnetId);
                        if ($info && $info['type'] == 'public')
                            $networkInterface->associatePublicIpAddress = true;

                        $networkInterface->subnetId = $selectedSubnetId;

                        $aws = $environment->aws($launchOptions->cloudLocation);
                        $sgroups = $this->GetServerSecurityGroupsList($DBServer, $aws->ec2, $vpcId, $governance);
                        $networkInterface->setSecurityGroupId($sgroups);

                        $runInstanceRequest->setNetworkInterface($networkInterface);
                        $noSecurityGroups = true;

                        //$runInstanceRequest->subnetId = $selectedSubnetId;
                    } else
                        throw new Exception("Unable to define subnetId for role in VPC");
                }
            }
        } else {
            $runInstanceRequest->userData = base64_encode(trim($launchOptions->userData));
        }

        $aws = $environment->aws($launchOptions->cloudLocation);

        if (!$vpcId)
            $vpcId = $this->getDefaultVpc($environment, $launchOptions->cloudLocation);

        // Set AMI, AKI and ARI ids
        $runInstanceRequest->imageId = $launchOptions->imageId;

        $runInstanceRequest->instanceInitiatedShutdownBehavior = 'terminate';

        if (!$noSecurityGroups) {

            foreach ($this->GetServerSecurityGroupsList($DBServer, $aws->ec2, $vpcId, $governance) as $sgroup) {
                $runInstanceRequest->appendSecurityGroupId($sgroup);
            }

            if (!$runInstanceRequest->subnetId) {
                // Set availability zone
                if (!$launchOptions->availZone) {
                    $avail_zone = $this->GetServerAvailZone($DBServer, $aws->ec2, $launchOptions);
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

        if (substr($launchOptions->serverType, 0, 2) == 'm3' ||
            substr($launchOptions->serverType, 0, 2) == 'i2' ||
            $launchOptions->serverType == 'hi1.4xlarge' ||
            $launchOptions->serverType == 'cc2.8xlarge' ||
            $launchOptions->osFamily == 'oel') {
            foreach ($this->GetBlockDeviceMapping($launchOptions->serverType) as $bdm) {
                $runInstanceRequest->appendBlockDeviceMapping($bdm);
            }
        }

        if (in_array($runInstanceRequest->instanceType, array(
            'c3.large', 'c3.xlarge', 'c3.2xlarge', 'c3.4xlarge', 'c3.8xlarge', 'cc2.8xlarge',
            'cg1.4xlarge', 'g2.2xlarge',
            'cr1.8xlarge', 'r3.large', 'r3.xlarge', 'r3.2xlarge', 'r3.4xlarge', 'r3.8xlarge',
            'hi1.4xlarge', 'hs1.8xlarge', 'i2.xlarge', 'i2.2xlarge', 'i2.4xlarge', 'i2.8xlarge'
            ))) {

            $placementGroup = $DBServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_AWS_CLUSTER_PG);
            if ($placementGroup) {
                if ($placementData === null) {
                    $placementData = new PlacementResponseData(null, $placementGroup);
                } else {
                    $placementData->groupName = $placementGroup;
                }
            }
        }

        if ($placementData !== null) {
            $runInstanceRequest->setPlacement($placementData);
        }

        $sshKey = \Scalr_SshKey::init();
        if ($DBServer->status == SERVER_STATUS::TEMPORARY) {
            $keyName = "SCALR-ROLESBUILDER-" . SCALR_ID;
            if (!$sshKey->loadGlobalByName($keyName, $launchOptions->cloudLocation, $DBServer->envId, SERVER_PLATFORMS::EC2))
                $keyName = "SCALR-ROLESBUILDER-" . SCALR_ID . "-{$DBServer->envId}";
                
            $farmId = NULL;
        } else {
            $keyName = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_KEYPAIR);
            if ($keyName) {
                $skipKeyValidation = true;
            } else {
                $keyName = "FARM-{$DBServer->farmId}-" . SCALR_ID;
                $farmId = $DBServer->farmId;
                $oldKeyName = "FARM-{$DBServer->farmId}";
                if ($sshKey->loadGlobalByName($oldKeyName, $launchOptions->cloudLocation, $DBServer->envId, SERVER_PLATFORMS::EC2)) {
                    $keyName = $oldKeyName;
                    $skipKeyValidation = true;
                }
            }
        }
        if (!$skipKeyValidation && !$sshKey->loadGlobalByName($keyName, $launchOptions->cloudLocation, $DBServer->envId, SERVER_PLATFORMS::EC2)) {
            $result = $aws->ec2->keyPair->create($keyName);
            if ($result->keyMaterial) {
                $sshKey->farmId = $farmId;
                $sshKey->envId = $DBServer->envId;
                $sshKey->type = \Scalr_SshKey::TYPE_GLOBAL;
                $sshKey->cloudLocation = $launchOptions->cloudLocation;
                $sshKey->cloudKeyName = $keyName;
                $sshKey->platform = SERVER_PLATFORMS::EC2;
                $sshKey->setPrivate($result->keyMaterial);
                $sshKey->setPublic($sshKey->generatePublicKey());
                $sshKey->save();
            }
        }

        $runInstanceRequest->keyName = $keyName;

        try {
            $result = $aws->ec2->instance->run($runInstanceRequest);
        } catch (Exception $e) {
            if (stristr($e->getMessage(), "The key pair") && stristr($e->getMessage(), "does not exist")) {
                $sshKey->delete();
                throw $e;
            }

            if (stristr($e->getMessage(), "The requested Availability Zone is no longer supported") ||
                stristr($e->getMessage(), "is not supported in your requested Availability Zone") ||
                stristr($e->getMessage(), "capacity in the Availability Zone you requested") ||
                stristr($e->getMessage(), "Our system will be working on provisioning additional capacity") ||
                stristr($e->getMessage(), "is currently constrained and we are no longer accepting new customer requests")) {

                $availZone = $runInstanceRequest->getPlacement() ?
                    $runInstanceRequest->getPlacement()->availabilityZone : null;

                if ($availZone) {
                    $DBServer->GetEnvironmentObject()->setPlatformConfig(
                        array("aws.{$launchOptions->cloudLocation}.{$availZone}.unavailable" => time())
                    );
                }

                throw $e;

            } else {
                throw $e;
            }
        }

        if ($result->instancesSet->get(0)->instanceId) {
            $DBServer->SetProperties([
                EC2_SERVER_PROPERTIES::REGION        => $launchOptions->cloudLocation,
                EC2_SERVER_PROPERTIES::AVAIL_ZONE    => $result->instancesSet->get(0)->placement->availabilityZone,
                EC2_SERVER_PROPERTIES::INSTANCE_ID   => $result->instancesSet->get(0)->instanceId,
                EC2_SERVER_PROPERTIES::INSTANCE_TYPE => $runInstanceRequest->instanceType,
                EC2_SERVER_PROPERTIES::AMIID         => $runInstanceRequest->imageId,
                EC2_SERVER_PROPERTIES::VPC_ID        => $result->instancesSet->get(0)->vpcId,
                EC2_SERVER_PROPERTIES::SUBNET_ID     => $result->instancesSet->get(0)->subnetId,
                EC2_SERVER_PROPERTIES::ARCHITECTURE  => $result->instancesSet->get(0)->architecture,
            ]);

            $DBServer->setOsType($result->instancesSet->get(0)->platform ? $result->instancesSet->get(0)->platform : 'linux');
            $DBServer->cloudLocation = $launchOptions->cloudLocation;
            $DBServer->cloudLocationZone = $result->instancesSet->get(0)->placement->availabilityZone;
            $DBServer->imageId = $launchOptions->imageId;

            return $DBServer;
        } else {
            throw new Exception(sprintf(_("Cannot launch new instance. %s"), serialize($result)));
        }
    }

    public function AllocateNewSubnet(\Scalr\Service\Aws\Ec2 $ec2, $vpcId, $availZone, $subnetLength = 24)
    {
        // HARDCODE THIS
        $subnetLength = 24;

        $subnetsList = $ec2->subnet->describe(null, array(array(
            'name'  => SubnetFilterNameType::vpcId(),
            'value' => $vpcId,
        )));
        $subnets = array();
        foreach ($subnetsList as $subnet) {
            @list($ip, $len) = explode('/', $subnet->cidrBlock);
            $subnets[] = array('min' => ip2long($ip), 'max' => (ip2long($ip) | (1<<(32-$len))-1));
        }

        $vpcInfo = $ec2->vpc->describe($vpcId);
        /* @var $vpc \Scalr\Service\Aws\Ec2\DataType\VpcData */
        $vpc = $vpcInfo->get(0);

        $info = explode("/", $vpc->cidrBlock);
        $startIp = ip2long($info[0]);
        $maxIp = ($startIp | (1<<(32-$info[1]))-1);
        while ($startIp < $maxIp) {
            $sIp = $startIp;
            $eIp = ($sIp | (1<<(32-$subnetLength))-1);
            foreach ($subnets as $subnet) {
                $checkRange = ($subnet['min'] <= $sIp) && ($sIp <= $subnet['max']) && ($subnet['min'] <= $eIp) && ($eIp <= $subnet['max']);
                if ($checkRange)
                    break;
            }
            if ($checkRange) {
                $startIp = $eIp+1;
            } else {
                $subnetIp = long2ip($startIp);
                break;
            }
        }

        return $ec2->subnet->create($vpcId, "{$subnetIp}/{$subnetLength}", $availZone);
    }

    /**
     * Gets block device mapping
     *
     * @param   string     $instanceType The type of the instance
     * @param   string     $prefix       The prefix
     * @return  array      Returns array of the BlockDeviceMappingData
     */
    private function GetBlockDeviceMapping($instanceType, $prefix = '/dev/sd')
    {
        $retval = array();

        //b
        if (in_array($instanceType, array(
                'm1.small', 'c1.medium', 'm1.medium', 'm1.large', 'm1.xlarge',
                'm2.xlarge', 'm2.2xlarge', 'm2.4xlarge',
                'm3.large', 'm3.xlarge', 'm3.2xlarge',
                'i2.xlarge', 'i2.2xlarge', 'i2.4xlarge', 'i2.8xlarge',
                'r3.large', 'r3.xlarge', 'r3.2xlarge', 'r3.4xlarge', 'r3.8xlarge',
                'c1.xlarge', 'cc1.4xlarge', 'cc2.8xlarge', 'cr1.8xlarge',
                'hi1.4xlarge', 'cr1.8xlarge'
            ))) {
            $retval[] = new BlockDeviceMappingData("{$prefix}b", 'ephemeral0');
        }

        //c
        if (in_array($instanceType, array(
                'm1.large', 'm1.xlarge',
                'm3.xlarge', 'm3.2xlarge',
                'cc2.8xlarge', 'cc1.4xlarge',
                'i2.2xlarge','i2.4xlarge','i2.8xlarge',
                'r3.8xlarge',
                'c1.xlarge', 'cr1.8xlarge', 'hi1.4xlarge', 'm2.2xlarge', 'cr1.8xlarge'))) {
            $retval[] = new BlockDeviceMappingData("{$prefix}c", 'ephemeral1');
        }

        //e
        if (in_array($instanceType, array('m1.xlarge', 'c1.xlarge', 'cc2.8xlarge', 'i2.4xlarge', 'i2.8xlarge'))) {
             $retval[] = new BlockDeviceMappingData("{$prefix}e", 'ephemeral2');
        }

        //f
        if (in_array($instanceType, array('m1.xlarge', 'c1.xlarge', 'cc2.8xlarge', 'i2.4xlarge', 'i2.8xlarge'))) {
            $retval[] = new BlockDeviceMappingData("{$prefix}f", 'ephemeral3');
        }


        //g
        if (in_array($instanceType, array('i2.8xlarge'))) {
            $retval[] = new BlockDeviceMappingData("{$prefix}g", 'ephemeral4');
        }

        //h
        if (in_array($instanceType, array('i2.8xlarge'))) {
            $retval[] = new BlockDeviceMappingData("{$prefix}h", 'ephemeral5');
        }

        //i
        if (in_array($instanceType, array('i2.8xlarge'))) {
            $retval[] = new BlockDeviceMappingData("{$prefix}i", 'ephemeral6');
        }

        //j
        if (in_array($instanceType, array('i2.8xlarge'))) {
            $retval[] = new BlockDeviceMappingData("{$prefix}j", 'ephemeral7');
        }

        return $retval;
    }

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
    private function GetServerSecurityGroupsList(DBServer $DBServer, \Scalr\Service\Aws\Ec2 $ec2, $vpcId = "", \Scalr_Governance $governance = null)
    {
        $retval = array();
        $checkGroups = array();
        $sgGovernance = true;
        $allowAdditionalSgs = true;
        $roleBuiledSgName = \Scalr::config('scalr.aws.security_group_name') . "-rb";

        if ($governance && $DBServer->farmRoleId) {
            $sgs = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_SECURITY_GROUPS);
            if ($sgs !== null) {
                $governanceSecurityGroups = @explode(",", $sgs);
                if (!empty($governanceSecurityGroups)) {
                    foreach ($governanceSecurityGroups as $sg) {
                        if ($sg != '')
                            array_push($checkGroups, trim($sg));
                    }
                }

                $sgGovernance = false;
                $allowAdditionalSgs = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_SECURITY_GROUPS, 'allow_additional_sec_groups');
            }
        } else
            $sgGovernance = false;

        if (!$sgGovernance || $allowAdditionalSgs) {
            if ($DBServer->farmRoleId != 0) {
                $dbFarmRole = $DBServer->GetFarmRoleObject();
                if ($dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_SECURITY_GROUPS_LIST) !== null) {
                    // New SG management
                    $sgs = @json_decode($dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_SECURITY_GROUPS_LIST));
                    if (!empty($sgs)) {
                        foreach ($sgs as $sg) {
                            if (stripos($sg, 'sg-') === 0)
                                array_push($retval, $sg);
                            else
                                array_push($checkGroups, $sg);
                        }
                    }
                } else {
                    // Old SG management
                    array_push($checkGroups, 'default');
                    array_push($checkGroups, \Scalr::config('scalr.aws.security_group_name'));
                    if (!$vpcId) {
                        array_push($checkGroups, "scalr-farm.{$DBServer->farmId}");
                        array_push($checkGroups, "scalr-role.{$DBServer->farmRoleId}");
                    }

                    $additionalSgs = trim($dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_SG_LIST));
                    if ($additionalSgs) {
                        $sgs = explode(",", $additionalSgs);
                        if (!empty($sgs)) {
                            foreach ($sgs as $sg) {
                                $sg = trim($sg);
                                if (stripos($sg, 'sg-') === 0)
                                    array_push($retval, $sg);
                                else
                                    array_push($checkGroups, $sg);
                            }
                        }
                    }
                }
            } else
                array_push($checkGroups, $roleBuiledSgName);
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
            } elseif ($groupName == $roleBuiledSgName) {
                if (!isset($sgList[$groupName])) {
                    try {
                        $securityGroupId = $ec2->securityGroup->create(
                            $roleBuiledSgName, "Security group for Roles Builder", $vpcId
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

                        $sgList[$roleBuiledSgName] = $securityGroupId;
                    } catch (Exception $e) {
                        throw new Exception(sprintf(_("Cannot create security group '%s': %s"), $roleBuiledSgName, $e->getMessage()));
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
                            $groupRules[\Scalr_Util_CryptoTool::hash($rule['rule'])] = $rule;
                        }

                        // Behavior rules
                        foreach (\Scalr_Role_Behavior::getListForFarmRole($DBServer->GetFarmRoleObject()) as $bObj) {
                            $bRules = $bObj->getSecurityRules();
                            foreach ($bRules as $r) {
                                if ($r) {
                                    $groupRules[\Scalr_Util_CryptoTool::hash($r)] = array('rule' => $r);
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
     * @param   \Scalr_Server_LaunchOptions $launchOptions
     */
    private function GetServerAvailZone(DBServer $DBServer, \Scalr\Service\Aws\Ec2 $ec2,
                                        \Scalr_Server_LaunchOptions $launchOptions)
    {
        if ($DBServer->status == SERVER_STATUS::TEMPORARY)
            return false;

        $aws = $DBServer->GetEnvironmentObject()->aws($DBServer);

        $server_avail_zone = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE);

        if ($DBServer->replaceServerID && !$server_avail_zone) {
            try {
                $rDbServer = DBServer::LoadByID($DBServer->replaceServerID);
                $server_avail_zone = $rDbServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE);
            } catch (Exception $e) {
            }
        }

        $role_avail_zone = $this->db->GetOne("
            SELECT ec2_avail_zone FROM ec2_ebs
            WHERE server_index=? AND farm_roleid=?
            LIMIT 1
        ",
            array($DBServer->index, $DBServer->farmRoleId)
        );

        if (!$role_avail_zone) {
            $DBServer->SetProperty("tmp.ec2.avail_zone.algo1", "[S={$server_avail_zone}][R1:{$role_avail_zone}]");

            if ($server_avail_zone &&
                $server_avail_zone != 'x-scalr-diff' &&
                !stristr($server_avail_zone, "x-scalr-custom")) {
                return $server_avail_zone;
            }

            $role_avail_zone = $DBServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_AWS_AVAIL_ZONE);
        }

        $DBServer->SetProperty("tmp.ec2.avail_zone.algo2", "[S={$server_avail_zone}][R2:{$role_avail_zone}]");

        if (!$role_avail_zone) {
            return false;
        }

        if ($role_avail_zone == "x-scalr-diff" || stristr($role_avail_zone, "x-scalr-custom")) {
            //TODO: Elastic Load Balancer
            $avail_zones = array();
            if (stristr($role_avail_zone, "x-scalr-custom")) {
                $zones = explode("=", $role_avail_zone);
                foreach (explode(":", $zones[1]) as $zoneName) {
                    if ($zoneName != "") {
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

            } else {
                // Get list of all available zones
                $avail_zones_resp = $ec2->availabilityZone->describe();
                foreach ($avail_zones_resp as $zone) {
                    /* @var $zone \Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData */
                    $zoneName = $zone->zoneName;

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
                    $availZoneDistribution[$cDbServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE)]++;
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

            $DBServer->SetProperty("tmp.ec2.avail_zone.algo2", "[A:{$aZones}][D:{$dZones}][S:{$availZone}]");

            return $availZone;
        } else {
            return $role_avail_zone;
        }
    }

    public function GetPlatformAccessData($environment, DBServer $DBServer)
    {
        $config = \Scalr::getContainer()->config;

        $accessData = new \stdClass();
        $accessData->accountId = $environment->getPlatformConfigValue(self::ACCOUNT_ID);
        $accessData->keyId = $environment->getPlatformConfigValue(self::ACCESS_KEY);
        $accessData->key = $environment->getPlatformConfigValue(self::SECRET_KEY);
        $accessData->cert = $environment->getPlatformConfigValue(self::CERTIFICATE);
        $accessData->pk = $environment->getPlatformConfigValue(self::PRIVATE_KEY);

        if ($config('scalr.aws.use_proxy')) {
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
        $put |= $message instanceof \Scalr_Messaging_Msg_MountPointsReconfigure;

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
}
