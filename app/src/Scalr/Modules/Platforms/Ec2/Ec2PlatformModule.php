<?php

namespace Scalr\Modules\Platforms\Ec2;

use FarmLogMessage;
use Scalr\Model\Entity\CloudInstanceType;
use Scalr\Service\Aws\Ec2\DataType\ReservationData;
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
use Scalr\Service\Aws\Ec2\DataType\IamInstanceProfileResponseData;
use Scalr\Service\Aws\Ec2\DataType\ReservationList;
use Scalr\Service\Aws\Ec2\DataType\ResourceTagSetData;
use Scalr\Service\Aws\Ec2\DataType\VolumeData;
use Scalr\Service\Aws\Ec2\DataType\VolumeFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\InstanceData;
use Scalr\Modules\Platforms\AbstractAwsPlatformModule;
use Scalr\Modules\Platforms\Ec2\Adapters\StatusAdapter;
use Scalr\Service\Aws\Ec2\DataType\RouteData;
use Scalr\Model\Entity;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\SshKey;
use EC2_SERVER_PROPERTIES;
use DBServer;
use Exception;
use Scalr\Util\CryptoTool;
use Scalr_Environment;
use Scalr_Governance;
use SERVER_PLATFORMS;
use DBRole;
use SERVER_SNAPSHOT_CREATION_TYPE;
use BundleTask;
use ROLE_TAGS;
use SERVER_PROPERTIES;
use SERVER_SNAPSHOT_CREATION_STATUS;
use ROLE_BEHAVIORS;
use SERVER_STATUS;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Ec2\DataType\EbsBlockDeviceData;
use Scalr\Service\Aws\Ec2\DataType\BlockDeviceMappingList;
use Scalr\Farm\Role\FarmRoleStorageConfig;
use Scalr\Modules\Platforms\OrphanedServer;
use Scalr\Service\Aws\Ec2\DataType\ImageData;
use Scalr_Role_Behavior_Router;
use Scalr_Server_LaunchOptions;

class Ec2PlatformModule extends AbstractAwsPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{
    /** Properties **/
    const ACCOUNT_ID 	= 'ec2.account_id';
    const ACCESS_KEY	= 'ec2.access_key';
    const SECRET_KEY	= 'ec2.secret_key';
    const PRIVATE_KEY	= 'ec2.private_key';
    const CERTIFICATE	= 'ec2.certificate';
    const ACCOUNT_TYPE  = 'ec2.account_type';

    const DETAILED_BILLING_BUCKET           = 'ec2.detailed_billing.bucket';
    const DETAILED_BILLING_ENABLED          = 'ec2.detailed_billing.enabled';
    const DETAILED_BILLING_PAYER_ACCOUNT    = 'ec2.detailed_billing.payer_account';
    const DETAILED_BILLING_REGION           = 'ec2.detailed_billing.region';

    const DEFAULT_VPC_ID            = 'ec2.vpc.default';

    const ACCOUNT_TYPE_REGULAR      = 'regular';
    const ACCOUNT_TYPE_GOV_CLOUD    = 'gov-cloud';
    const ACCOUNT_TYPE_CN_CLOUD     = 'cn-cloud';

    const MAX_TAGS_COUNT = 10;

    /**
     * @var array
     */
    public $instancesListCache;

    public function __construct()
    {
        parent::__construct();
        $this->instancesListCache = array();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceTypes()
     */
    public function getInstanceTypes(\Scalr_Environment $env = null, $cloudLocation = null, $details = false)
    {
        //http://aws.amazon.com/amazon-linux-ami/instance-type-matrix/
        //http://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instance-types.html
        //http://docs.aws.amazon.com/AWSEC2/latest/UserGuide/EBSOptimized.html
        static $restrictions = [
            't1' => ['ebs' => true, 'hvm' => false],
            't2' => [
                't2.nano'   => ['ebs' => true, 'hvm' => true, 'vpc' => true],
                't2.micro'  => ['ebs' => true, 'hvm' => true, 'vpc' => true],
                't2.small'  => ['ebs' => true, 'hvm' => true, 'vpc' => true],
                't2.medium' => ['ebs' => true, 'hvm' => true, 'vpc' => true],
                't2.large'  => ['ebs' => true, 'hvm' => true, 'vpc' => true, 'x64' => true],
            ],
            'm1' => [
                'm1.small'  =>  ['hvm' => false],
                'm1.medium' => ['hvm' => false],
                'm1.large'  =>  ['hvm' => false, 'x64' => true],
                'm1.xlarge' => ['hvm' => false, 'x64' => true],
            ],
            'm2' => ['hvm' => false, 'x64' => true],
            'm3' => ['x64' => true],
            'm4' => ['ebs' => true, 'x64' => true, 'vpc' => true, 'hvm' => true],
            'c1' => [
                'c1.medium' =>  ['hvm' => false],
                'c1.xlarge' =>  ['hvm' => false, 'x64' => true],
            ],
            'c4'  => ['ebs' => true, 'hvm' => true, 'vpc' => true, 'x64' => true],
            'c3'  => ['x64' => true],
            'd2'  => ['hvm' => true, 'x64' => true],
            'r3'  => ['hvm' => true, 'x64' => true],
            'i2'  => ['hvm' => true, 'x64' => true],
            'g2'  => ['ebs' => true, 'hvm' => true, 'x64' => true],
            'hs1' => ['x64' => true],
            'cc2' => ['hvm' => true, 'x64' => true],
            'cg1' => ['hvm' => true, 'x64' => true],
            'hi1' => ['x64' => true],
            'cr1' => ['ebs' => true, 'hvm' => true, 'x64' => true],
        ];

        static $definition = array(
            't1.micro' => array(
                'name' => 't1.micro',
                'ram' => '625',
                'vcpus' => '1',
                'disk' => '',
                'type' => '',
                'note' => 'SHARED CPU'
            ),

            't2.nano' => array(
                'name' => 't2.nano',
                'ram' => '512',
                'vcpus' => '1',
                'disk' => '',
                'type' => '',
                'note' => 'SHARED CPU',
                'ebsencryption' => true
            ),

            't2.micro' => array(
                'name' => 't2.micro',
                'ram' => '1024',
                'vcpus' => '1',
                'disk' => '',
                'type' => '',
                'note' => 'SHARED CPU',
                'ebsencryption' => true
            ),

            't2.small' => array(
                'name' => 't2.small',
                'ram' => '2048',
                'vcpus' => '1',
                'disk' => '',
                'type' => '',
                'note' => 'SHARED CPU',
                'ebsencryption' => true
            ),

            't2.medium' => array(
                'name' => 't2.medium',
                'ram' => '4096',
                'vcpus' => '2',
                'disk' => '',
                'type' => '',
                'note' => 'SHARED CPU',
                'ebsencryption' => true
            ),

            't2.large' => array(
                'name' => 't2.large',
                'ram' => '8192',
                'vcpus' => '2',
                'disk' => '',
                'type' => '',
                'note' => 'SHARED CPU',
                'ebsencryption' => true
            ),

            'm1.small' => array(
                'name' => 'm1.small',
                'ram' => '1740',
                'vcpus' => '1',
                'disk' => '160',
                'type' => 'HDD',
                'instancestore' => [
                    'number' => 1,
                    'size'   => 160
                ]
            ),
            'm1.medium' => array(
                'name' => 'm1.medium',
                'ram' => '3840',
                'vcpus' => '1',
                'disk' => '410',
                'type' => 'HDD',
                'instancestore' => [
                    'number' => 1,
                    'size'   => 410
                ]
            ),
            'm1.large' => array(
                'name' => 'm1.large',
                'ram' => '7680',
                'vcpus' => '2',
                'disk' => '840',
                'type' => 'HDD',
                'ebsoptimized' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 420
                ]
            ),
            'm1.xlarge' => array(
                'name' => 'm1.xlarge',
                'ram' => '15360',
                'vcpus' => '4',
                'disk' => '1680',
                'type' => 'HDD',
                'ebsoptimized' => true,
                'instancestore' => [
                    'number' => 4,
                    'size'   => 420
                ]
            ),

            'm2.xlarge' => array(
                'name' => 'm2.xlarge',
                'ram' => '17510',
                'vcpus' => '2',
                'disk' => '420',
                'type' => 'HDD',
                'instancestore' => [
                    'number' => 1,
                    'size'   => 420
                ]
            ),
            'm2.2xlarge' => array(
                'name' => 'm2.2xlarge',
                'ram' => '35021',
                'vcpus' => '4',
                'disk' => '850',
                'type' => 'HDD',
                'ebsoptimized' => true,
                'instancestore' => [
                    'number' => 1,
                    'size'   => 850
                ]
            ),
            'm2.4xlarge' => array(
                'name' => 'm2.4xlarge',
                'ram' => '66355',
                'vcpus' => '8',
                'disk' => '1680',
                'type' => 'HDD',
                'ebsoptimized' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 840
                ]
            ),

            'm3.medium' => array(
                'name' => 'm3.medium',
                'ram' => '3840',
                'vcpus' => '1',
                'disk' => '4',
                'type' => 'SSD',
                'ebsencryption' => true,
                'instancestore' => [
                    'number' => 1,
                    'size'   => 4
                ]
            ),
            'm3.large' => array(
                'name' => 'm3.large',
                'ram' => '7680',
                'vcpus' => '2',
                'disk' => '32',
                'type' => 'SSD',
                'ebsencryption' => true,
                'instancestore' => [
                    'number' => 1,
                    'size'   => 32
                ]
            ),
            'm3.xlarge' => array(
                'name' => 'm3.xlarge',
                'ram' => '15360',
                'vcpus' => '4',
                'disk' => '80',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 40
                ]
            ),
            'm3.2xlarge' => array(
                'name' => 'm3.2xlarge',
                'ram' => '30720',
                'vcpus' => '8',
                'disk' => '160',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 80
                ]
            ),

            ///
            'm4.large' => array(
                'name' => 'm4.large',
                'ram' => '8192',
                'vcpus' => '2',
                'disk' => '',
                'type' => '',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),
            'm4.xlarge' => array(
                'name' => 'm4.xlarge',
                'ram' => '16384',
                'vcpus' => '4',
                'disk' => '',
                'type' => '',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),
            'm4.2xlarge' => array(
                'name' => 'm4.2xlarge',
                'ram' => '32768',
                'vcpus' => '8',
                'disk' => '',
                'type' => '',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),
            'm4.4xlarge' => array(
                'name' => 'm4.4xlarge',
                'ram' => '65536',
                'vcpus' => '16',
                'disk' => '',
                'type' => '',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),
            'm4.10xlarge' => array(
                'name' => 'm4.10xlarge',
                'ram' => '163840',
                'vcpus' => '40',
                'disk' => '',
                'type' => '',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),

            'c1.medium' => array(
                'name' => 'c1.medium',
                'ram' => '1741',
                'vcpus' => '2',
                'disk' => '350',
                'type' => 'HDD',
                'instancestore' => [
                    'number' => 1,
                    'size'   => 350
                ]
            ),
            'c1.xlarge' => array(
                'name' => 'c1.xlarge',
                'ram' => '7168',
                'vcpus' => '8',
                'disk' => '1680',
                'type' => 'HDD',
                'ebsoptimized' => true,
                'instancestore' => [
                    'number' => 4,
                    'size'   => 420
                ]
            ),

            'c4.large' => array(
                'name' => 'c4.large',
                'ram' => '3840',
                'vcpus' => '2',
                'disk' => '32',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),
            'c4.xlarge' => array(
                'name' => 'c4.xlarge',
                'ram' => '7680',
                'vcpus' => '4',
                'disk' => '80',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),
            'c4.2xlarge' => array(
                'name' => 'c4.2xlarge',
                'ram' => '15360',
                'vcpus' => '8',
                'disk' => '160',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),
            'c4.4xlarge' => array(
                'name' => 'c4.4xlarge',
                'ram' => '30720',
                'vcpus' => '16',
                'disk' => '320',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),
            'c4.8xlarge' => array(
                'name' => 'c4.8xlarge',
                'ram' => '61440',
                'vcpus' => '36',
                'disk' => '640',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true
            ),
            'c3.large' => array(
                'name' => 'c3.large',
                'ram' => '3840',
                'vcpus' => '2',
                'disk' => '32',
                'type' => 'SSD',
                'ebsencryption' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 16
                ]
            ),
            'c3.xlarge' => array(
                'name' => 'c3.xlarge',
                'ram' => '7680',
                'vcpus' => '4',
                'disk' => '80',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 40
                ]
            ),
            'c3.2xlarge' => array(
                'name' => 'c3.2xlarge',
                'ram' => '15360',
                'vcpus' => '8',
                'disk' => '160',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 80
                ]
            ),
            'c3.4xlarge' => array(
                'name' => 'c3.4xlarge',
                'ram' => '30720',
                'vcpus' => '16',
                'disk' => '320',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 160
                ]
            ),
            'c3.8xlarge' => array(
                'name' => 'c3.8xlarge',
                'ram' => '61440',
                'vcpus' => '32',
                'disk' => '640',
                'type' => 'SSD',
                'ebsencryption' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 320
                ]
            ),

            'r3.large' => array(
                'name' => 'r3.large',
                'ram' => '15360',
                'vcpus' => '2',
                'disk' => '32',
                'type' => 'SSD',
                'ebsencryption' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 1,
                    'size'   => 32
                ]
            ),
            'r3.xlarge' => array(
                'name' => 'r3.xlarge',
                'ram' => '31232',
                'vcpus' => '4',
                'disk' => '80',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 1,
                    'size'   => 80
                ]
            ),
            'r3.2xlarge' => array(
                'name' => 'r3.2xlarge',
                'ram' => '62464',
                'vcpus' => '8',
                'disk' => '160',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 1,
                    'size'   => 160
                ]
            ),
            'r3.4xlarge' => array(
                'name' => 'r3.4xlarge',
                'ram' => '124928',
                'vcpus' => '16',
                'disk' => '320',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 1,
                    'size'   => 320
                ]
            ),
            'r3.8xlarge' => array(
                'name' => 'r3.8xlarge',
                'ram' => '249856',
                'vcpus' => '32',
                'disk' => '640',
                'type' => 'SSD',
                'ebsencryption' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 320
                ]
            ),

            'i2.xlarge' => array(
                'name' => 'i2.xlarge',
                'ram' => '31232',
                'vcpus' => '4',
                'disk' => '800',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 1,
                    'size'   => 800
                ]
            ),
            'i2.2xlarge' => array(
                'name' => 'i2.2xlarge',
                'ram' => '62464',
                'vcpus' => '8',
                'disk' => '1600',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 800
                ]
            ),
            'i2.4xlarge' => array(
                'name' => 'i2.4xlarge',
                'ram' => '124928',
                'vcpus' => '16',
                'disk' => '3200',
                'type' => 'SSD',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 4,
                    'size'   => 800
                ]
            ),
            'i2.8xlarge' => array(
                'name' => 'i2.8xlarge',
                'ram' => '249856',
                'vcpus' => '32',
                'disk' => '6400',
                'type' => 'SSD',
                'ebsencryption' => true,
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 8,
                    'size'   => 800
                ]
            ),

            'd2.xlarge' => array(
                'name' => 'd2.xlarge',
                'ram' => '31232',
                'vcpus' => '4',
                'disk' => '4000',
                'type' => 'HDD',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 3,
                    'size'   => 2000
                ]
            ),
            'd2.2xlarge' => array(
                'name' => 'd2.2xlarge',
                'ram' => '62464',
                'vcpus' => '8',
                'disk' => '6000',
                'type' => 'HDD',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 6,
                    'size'   => 2000
                ]
            ),
            'd2.4xlarge' => array(
                'name' => 'd2.4xlarge',
                'ram' => '124928',
                'vcpus' => '16',
                'disk' => '24000',
                'type' => 'HDD',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 12,
                    'size'   => 2000
                ]
            ),
            'd2.8xlarge' => array(
                'name' => 'd2.8xlarge',
                'ram' => '249856',
                'vcpus' => '36',
                'disk' => '48000',
                'type' => 'HDD',
                'ebsencryption' => true,
                'ebsoptimized' => 'default',
                'placementgroups' => true,
                'enhancednetworking' => true,
                'instancestore' => [
                    'number' => 24,
                    'size'   => 2000
                ]
            ),

            'g2.2xlarge' => array(
                'name' => 'g2.2xlarge',
                'ram' => '15360',
                'vcpus' => '8',
                'disk' => '60',
                'type' => 'SSD',
                'note' => 'GPU',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'instancestore' => [
                    'number' => 1,
                    'size'   => 60
                ]
            ),

            'g2.8xlarge' => array(
                'name' => 'g2.8xlarge',
                'ram' => '61440',
                'vcpus' => '32',
                'disk' => '240',
                'type' => 'SSD',
                'note' => 'GPU',
                'ebsencryption' => true,
                'ebsoptimized' => true,
                'placementgroups' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 120
                ]
            ),

            'hs1.8xlarge' => array(
                'name' => 'hs1.8xlarge',
                'ram' => '119808',
                'vcpus' => '16',
                'disk' => '49152',
                'type' => 'SSD',
                'placementgroups' => true,
                'instancestore' => [
                    'number' => 24,
                    'size'   => 2000
                ]
            ),

            'cc2.8xlarge' => array(
                'name' => 'cc2.8xlarge',
                'ram' => '61952',
                'vcpus' => '32',
                'disk' => '3360',
                'type' => 'HDD',
                'placementgroups' => true,
                'instancestore' => [
                    'number' => 4,
                    'size'   => 840
                ]
            ),
            'cg1.4xlarge' => array(
                'name' => 'cg1.4xlarge',
                'ram' => '23040',
                'vcpus' => '16',
                'disk' => '1680',
                'type' => 'HDD',
                'note' => 'GPU',
                'placementgroups' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 840
                ]
            ),
            'hi1.4xlarge' => array(
                'name' => 'hi1.4xlarge',
                'ram' => '61952',
                'vcpus' => '16',
                'disk' => '2048',
                'type' => 'SSD',
                'placementgroups' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 1024
                ]
            ),
            'cr1.8xlarge' => array(
                'name' => 'cr1.8xlarge',
                'ram' => '249856',
                'vcpus' => '32',
                'disk' => '240',
                'type' => 'SSD',
                'ebsencryption' => true,
                'placementgroups' => true,
                'instancestore' => [
                    'number' => 2,
                    'size'   => 120
                ]
            )
        );

        static $supportedFamilies = [
            Aws::REGION_EU_CENTRAL_1 => ['t2','m3','m4','c3','r3','i2'],
            Aws::REGION_AP_NORTHEAST_2 => ['t2', 'm4', 'c4', 'r3', 'i2', 'd2']
        ];

        $filter = isset($supportedFamilies[$cloudLocation]) ? array_flip($supportedFamilies[$cloudLocation]) : null;

        return array_filter($definition, function (&$entry, $key) use ($filter, $restrictions, $details) {
            $family = explode('.', $key)[0];

            if (isset($filter) && !isset($filter[$family])) {
                return false;
            }

            if ($details) {
                $entry['family'] = $family;

                if (isset($restrictions[$family])) {
                    $entry['restrictions'] = isset($restrictions[$family][$key]) ? $restrictions[$family][$key] : $restrictions[$family];
                }
            } else {
                $entry = $key;
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::hasCloudPrices()
     */
    public function hasCloudPrices(\Scalr_Environment $env)
    {
        if (!$this->container->analytics->enabled) return false;

        if (in_array(
                $env->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE],
                [
                    Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_GOV_CLOUD,
                    Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_CN_CLOUD
                ]
            )) {
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

        if ($extended) {
            $routingTables = $this->getRoutingTables($aws->ec2, $vpcId);
        }

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

            if (empty($item['type'])) {
                $item['type'] = $mainTableType;
            }

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
        $instanceId = $DBServer->GetCloudServerID();
        $cacheKey = sprintf('%s:%s', $DBServer->envId, $DBServer->cloudLocation);

        if (!isset($this->instancesListCache[$cacheKey][$instanceId])) {
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

            $this->instancesListCache[$cacheKey][$instance->instanceId] = [
                'localIp' => $instance->privateIpAddress,
                'remoteIp' => $instance->ipAddress,
                'status' => $instance->instanceState->name,
                'type' => $instance->instanceType
            ];
        }

        return array(
            'localIp'  => $this->instancesListCache[$cacheKey][$instanceId]['localIp'],
            'remoteIp' => $this->instancesListCache[$cacheKey][$instanceId]['remoteIp']
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getOrphanedServers()
     */
    public function getOrphanedServers(Entity\Account\Environment $environment, $cloudLocation, $instanceIds = null)
    {
        if (empty($cloudLocation)) {
            return [];
        }

        $aws = $environment->aws($cloudLocation);

        $orphans = [];
        $nextToken = null;

        do {
            try {
                /* @var $results ReservationList */
                $results = $aws->ec2->instance->describe($instanceIds, null, $nextToken, empty($instanceIds) ? 1000 : null);
            } catch (Exception $e) {
                throw new Exception(sprintf("Cannot get list of servers for platform ec2: %s", $e->getMessage()));
            }

            if (count($results)) {
                foreach ($results as $reservation) {
                    /* @var $reservation ReservationData */
                    foreach ($reservation->instancesSet as $instance) {
                        /* @var $instance InstanceData */
                        if (StatusAdapter::load($instance->instanceState->name)->isTerminated()) {
                            continue;
                        }

                        // check whether scalr tag exists
                        foreach ($instance->tagSet as $tag) {
                            /* @var $tag ResourceTagSetData */
                            if ($tag->key == "scalr-meta") {
                                continue 2;
                            }
                        }

                        $orphans[] = new OrphanedServer(
                            $instance->instanceId,
                            $instance->instanceType,
                            $instance->imageId,
                            $instance->instanceState->name,
                            $instance->launchTime->setTimezone(new \DateTimeZone("UTC")), // !important
                            $instance->privateIpAddress,
                            $instance->ipAddress,
                            $instance->keyName,
                            $instance->vpcId,
                            $instance->subnetId,
                            $instance->architecture,
                            $instance->groupSet->toArray(),
                            $instance->tagSet->toArray()
                        );
                    }
                }
            }

            if (empty($instanceIds)) {
                $nextToken = $results->getNextToken();
            }

            unset($results);
        } while ($nextToken);

        return $orphans;
    }

    /**
     * Gets the list of the EC2 instances
     * for the specified environment and AWS location
     *
     * @param  \Scalr_Environment $environment          Environment Object
     * @param  string             $region               EC2 location name
     * @param  bool               $skipCache   optional Whether it should skip the cache.
     * @return array Returns array looks like array(InstanceId => stateName)
     */
    public function GetServersList(\Scalr_Environment $environment, $region, $skipCache = false)
    {
        if (!$region)
            return [];

        $aws = $environment->aws($region);
        $cacheKey = sprintf('%s:%s', $environment->id, $region);

        if (!isset($this->instancesListCache[$cacheKey]) || $skipCache) {
            $cacheValue = array();

            /* @var $results ReservationList */
            $results = null;
            do {
                try {
                    $results = $aws->ec2->instance->describe(null, null, (isset($results) ? $results->getNextToken() : null), 1000);
                } catch (Exception $e) {
                    throw new Exception(sprintf("Cannot get list of servers for platform ec2: %s", $e->getMessage()));
                }

                if (count($results)) {
                    foreach ($results as $reservation) {
                        /* @var $reservation ReservationData */
                        foreach ($reservation->instancesSet as $instance) {
                            /* @var $instance InstanceData */

                            $cacheValue[$cacheKey][$instance->instanceId] = [
                                'localIp'    => $instance->privateIpAddress,
                                'remoteIp'   => $instance->ipAddress,
                                'status'     => $instance->instanceState->name,
                                'type'       => $instance->instanceType,
                                '_timestamp' => time()
                            ];
                        }
                    }
                }
            } while ($results->getNextToken());

            foreach ($cacheValue as $offset => $value)
                $this->instancesListCache[$offset] = $value;
        }

        return isset($this->instancesListCache[$cacheKey]) ? $this->instancesListCache[$cacheKey] : [];
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
                    $instance = $reservations->get(0)->instancesSet->get(0);
                    $status = $instance->instanceState->name;

                    $this->instancesListCache[$cacheKey][$instance->instanceId] = [
                        'localIp' => $instance->privateIpAddress,
                        'remoteIp' => $instance->ipAddress,
                        'status' => $instance->instanceState->name,
                        'type' => $instance->instanceType
                    ];
                } else {
                    $status = 'not-found';
                }

            } catch (InstanceNotFoundException $e) {
                $status = 'not-found';
            }
        } else {
            $status = $this->instancesListCache[$cacheKey][$iid]['status'];
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
            if (! $image->getEnvironment()) {
                return true;
            }

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
                        if (!empty($objects)) {
                            /* @var $object ObjectData */
                            foreach ($objects as $object) {
                                $object->delete();
                            }
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

                    $tags = [];

                    if ($ami->rootDeviceType == 'ebs') {
                        $tags[] = ROLE_TAGS::EC2_EBS;
                    }

                    if ($ami->virtualizationType == 'hvm') {
                        $tags[] = ROLE_TAGS::EC2_HVM;
                    }

                    $metaData['tags'] = empty($tags) ? null : $tags;

                    $BundleTask->SnapshotCreationComplete($BundleTask->snapshotId, $metaData);
                } else {
                    if ($ami->imageState == 'failed') {
                        $BundleTask->SnapshotCreationFailed("AMI in FAILED state. Reason: {$ami->stateReason->message}");
                    } else {
                        $BundleTask->Log("CheckServerSnapshotStatus: AMI status = {$ami->imageState}. Waiting...");
                    }
                }
            } catch (Exception $e) {
                \Scalr::getContainer()->logger(__CLASS__)->fatal("CheckServerSnapshotStatus ({$BundleTask->id}): {$e->getMessage()}");
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

        if ($BundleTask->prototypeRoleId) {
            $protoRole = DBRole::loadById($BundleTask->prototypeRoleId);

            $image = $protoRole->__getNewRoleObject()->getImage(
                SERVER_PLATFORMS::EC2,
                $DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION)
            );

            //Bundle EC2 in AWS way
            $BundleTask->designateType(\SERVER_PLATFORMS::EC2, $image->getImage()->getOs()->family, $image->getImage()->getOs()->generation);
        }

        $callEc2CreateImage = false;
        $reservationSet = $aws->ec2->instance->describe($DBServer->GetCloudServerID())->get(0);
        $ec2Server = $reservationSet->instancesSet->get(0);

        if ($ec2Server->platform == 'windows') {
            if ($ec2Server->rootDeviceType != 'ebs') {
                $BundleTask->SnapshotCreationFailed("Only EBS root filesystem supported for Windows servers.");
                return;
            }

            $BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
            $callEc2CreateImage = true;
        } else {
            if ($image) {
                $BundleTask->Log(sprintf(
                    _("Image OS: %s %s"),
                    $image->getImage()->getOs()->family, $image->getImage()->getOs()->generation
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

                $BundleTask->Log(sprintf(_("Selected platform snapshotting type: %s"), $BundleTask->bundleType));
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
                        /* @var $volume VolumeData */

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
                    'IAM Role'                => isset($instanceData->iamInstanceProfile) && $instanceData->iamInstanceProfile instanceof IamInstanceProfileResponseData ? $instanceData->iamInstanceProfile->arn : null,
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
        } catch (InstanceNotFoundException $e) {
            return false;
        } catch (Exception $e) {
            throw $e;
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
            if ($type == Scalr_Role_Behavior_Router::INTERNET_ACCESS_FULL)
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
            if ($type == Scalr_Role_Behavior_Router::INTERNET_ACCESS_FULL) {
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
    public function LaunchServer(DBServer $DBServer, Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $runInstanceRequest = new RunInstancesRequestData(
            (isset($launchOptions->imageId) ? $launchOptions->imageId : null), 1, 1
        );

        $environment = $DBServer->GetEnvironmentObject();
        $governance = new \Scalr_Governance($DBServer->envId);


        $placementData = null;
        $noSecurityGroups = false;

        if (!$launchOptions) {
            $launchOptions = new Scalr_Server_LaunchOptions();

            $dbFarmRole = $DBServer->GetFarmRoleObject();
            $DBRole = $dbFarmRole->GetRoleObject();

            $runInstanceRequest->setMonitoring(
                $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ENABLE_CW_MONITORING)
            );

            $image = $DBRole->__getNewRoleObject()->getImage(
                SERVER_PLATFORMS::EC2,
                $dbFarmRole->CloudLocation
            );
            $launchOptions->imageId = $image->imageId;

            if ($DBRole->isScalarized) {
                if (!$image->getImage()->isScalarized && $image->getImage()->hasCloudInit) {
                    $useCloudInit = true;
                }
            }

            // Need OS Family to get block device mapping for OEL roles
            $launchOptions->osFamily = $image->getImage()->getOs()->family;
            $launchOptions->cloudLocation = $dbFarmRole->CloudLocation;

            $akiId = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::AKIID);
            if (!$akiId)
                $akiId = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_AKI_ID);

            if ($akiId)
                $runInstanceRequest->kernelId = $akiId;

            $ariId = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::ARIID);
            if (!$ariId)
                $ariId = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ARI_ID);

            if ($ariId)
                $runInstanceRequest->ramdiskId = $ariId;

            $iType = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::INSTANCE_TYPE);
            $launchOptions->serverType = $iType;

            // Check governance of instance types
            $types = $governance->getValue('ec2', Scalr_Governance::INSTANCE_TYPE);
            if (count($types) > 0) {
                if (!in_array($iType, $types))
                    throw new Exception(sprintf(
                        "Instance type '%s' was prohibited to use by scalr account owner",
                        $iType
                    ));
            }

            $iamProfileArn = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_IAM_INSTANCE_PROFILE_ARN);
            if ($iamProfileArn) {
                $iamInstanceProfile = new IamInstanceProfileRequestData($iamProfileArn);
                $runInstanceRequest->setIamInstanceProfile($iamInstanceProfile);
            }

            if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_OPTIMIZED) == 1)
                $runInstanceRequest->ebsOptimized = true;
            else
                $runInstanceRequest->ebsOptimized = false;

            // Custom user-data (base.custom_user_data)
            $u_data = '';

            foreach ($DBServer->GetCloudUserData() as $k => $v) {
                $u_data .= "{$k}={$v};";
            }

            $u_data = trim($u_data, ";");

            if (!empty($useCloudInit)) {
                $customUserData = file_get_contents(APPPATH . "/templates/services/cloud_init/config.tpl");
            } else {
                $customUserData = $dbFarmRole->GetSetting('base.custom_user_data');
            }

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

            if ($DBRole->isScalarized) {
                $runInstanceRequest->userData = base64_encode($userData);
            }

            $vpcId = $dbFarmRole->GetFarmObject()->GetSetting(Entity\FarmSetting::EC2_VPC_ID);
            if ($vpcId) {
                if ($DBRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER)) {
                    $networkInterface = new InstanceNetworkInterfaceSetRequestData();
                    $networkInterface->networkInterfaceId = $dbFarmRole->GetSetting(Scalr_Role_Behavior_Router::ROLE_VPC_NID);
                    $networkInterface->deviceIndex = 0;
                    $networkInterface->deleteOnTermination = false;

                    $runInstanceRequest->setNetworkInterface($networkInterface);
                    $noSecurityGroups = true;
                } else {

                    $vpcSubnetId = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID);

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
                        $vpcInternetAccess = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_VPC_INTERNET_ACCESS);
                        if (!$vpcSubnetId) {
                            $aws = $environment->aws($launchOptions->cloudLocation);

                            $subnet = $this->AllocateNewSubnet(
                                $aws->ec2,
                                $vpcId,
                                $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_VPC_AVAIL_ZONE),
                                24
                            );

                            try {
                                $subnet->createTags(array(
                                    array('key' => "scalr-id", 'value' => SCALR_ID),
                                    array('key' => "scalr-sn-type", 'value' => $vpcInternetAccess),
                                    array('key' => "Name", 'value' => 'Scalr System Subnet')
                                ));
                            } catch (Exception $e) {
                            }

                            try {
                                $routeTableId = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_VPC_ROUTING_TABLE_ID);

                                \Scalr::getContainer()->logger('VPC')->warn(new FarmLogMessage($DBServer, "Internet access: {$vpcInternetAccess}"));

                                if (!$routeTableId) {
                                    if ($vpcInternetAccess == Scalr_Role_Behavior_Router::INTERNET_ACCESS_OUTBOUND) {
                                        $routerRole = $DBServer->GetFarmObject()->GetFarmRoleByBehavior(ROLE_BEHAVIORS::VPC_ROUTER);

                                        if (!$routerRole) {
                                            if (\Scalr::config('scalr.instances_connection_policy') != 'local') {
                                                throw new Exception("Outbound access require VPC router role in farm");
                                            }
                                        }

                                        $networkInterfaceId = $routerRole->GetSetting(Scalr_Role_Behavior_Router::ROLE_VPC_NID);

                                        \Scalr::getContainer()->logger('EC2')->warn(new FarmLogMessage($DBServer, "Requesting outbound routing table. NID: {$networkInterfaceId}"));

                                        $routeTableId = $this->getRoutingTable($vpcInternetAccess, $aws, $networkInterfaceId, $vpcId);

                                        \Scalr::getContainer()->logger('EC2')->warn(new FarmLogMessage($DBServer, "Routing table ID: {$routeTableId}"));
                                    } elseif ($vpcInternetAccess == Scalr_Role_Behavior_Router::INTERNET_ACCESS_FULL) {
                                        $routeTableId = $this->getRoutingTable($vpcInternetAccess, $aws, null, $vpcId);
                                    }
                                }

                                $aws->ec2->routeTable->associate($routeTableId, $subnet->subnetId);
                            } catch (Exception $e) {
                                \Scalr::getContainer()->logger('EC2')->warn(new FarmLogMessage($DBServer, "Removing allocated subnet, due to routing table issues"));

                                $aws->ec2->subnet->delete($subnet->subnetId);
                                throw $e;
                            }

                            $selectedSubnetId = $subnet->subnetId;
                            $dbFarmRole->SetSetting(Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID, $selectedSubnetId, Entity\FarmRoleSetting::TYPE_LCL);
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

                        $staticPrivateIpsMap = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_PRIVATE_IPS_MAP);
                        if (!empty($staticPrivateIpsMap)) {
                            $map = @json_decode($staticPrivateIpsMap, true);
                            if (array_key_exists((int)$DBServer->index, $map)) {
                                $networkInterface->privateIpAddress = $map[$DBServer->index];
                            }
                        }

                        $aws = $environment->aws($launchOptions->cloudLocation);
                        $sgroups = $this->GetServerSecurityGroupsList($DBServer, $aws->ec2, $vpcId, $governance, $launchOptions->osFamily);
                        $networkInterface->setSecurityGroupId($sgroups);

                        $runInstanceRequest->setNetworkInterface($networkInterface);
                        $noSecurityGroups = true;

                        //$runInstanceRequest->subnetId = $selectedSubnetId;
                    } else
                        throw new Exception("Unable to define subnetId for role in VPC");
                }
            }

            $rootDevice = json_decode($DBServer->GetFarmRoleObject()->GetSetting(\Scalr_Role_Behavior::ROLE_BASE_ROOT_DEVICE_CONFIG), true);
            if ($rootDevice && $rootDevice['settings'])
                $rootDeviceSettings = $rootDevice['settings'];

            $instanceInitiatedShutdownBehavior = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_SHUTDOWN_BEHAVIOR);
        } else {
            $instanceInitiatedShutdownBehavior = null;
            $runInstanceRequest->userData = base64_encode(trim($launchOptions->userData));
        }

        $aws = $environment->aws($launchOptions->cloudLocation);

        if (!$vpcId)
            $vpcId = $this->getDefaultVpc($environment, $launchOptions->cloudLocation);

        // Set AMI, AKI and ARI ids
        $runInstanceRequest->imageId = $launchOptions->imageId;

        $runInstanceRequest->instanceInitiatedShutdownBehavior = $instanceInitiatedShutdownBehavior ?: 'terminate';

        if (!$noSecurityGroups) {
            foreach ($this->GetServerSecurityGroupsList($DBServer, $aws->ec2, $vpcId, $governance, $launchOptions->osFamily) as $sgroup) {
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

        if ($rootDeviceSettings) {
            $ebs = new EbsBlockDeviceData(
                array_key_exists(FarmRoleStorageConfig::SETTING_EBS_SIZE, $rootDeviceSettings) ? $rootDeviceSettings[FarmRoleStorageConfig::SETTING_EBS_SIZE] : null,
                null,//$rootDeviceSettings[FarmRoleStorageConfig::SETTING_EBS_SNAPSHOT],
                array_key_exists(FarmRoleStorageConfig::SETTING_EBS_TYPE, $rootDeviceSettings) ? $rootDeviceSettings[FarmRoleStorageConfig::SETTING_EBS_TYPE] : null,
                array_key_exists(FarmRoleStorageConfig::SETTING_EBS_IOPS, $rootDeviceSettings) ? $rootDeviceSettings[FarmRoleStorageConfig::SETTING_EBS_IOPS] : null,
                true,
                null
            );

            $deviceName = !empty($rootDeviceSettings[FarmRoleStorageConfig::SETTING_EBS_DEVICE_NAME]) ? $rootDeviceSettings[FarmRoleStorageConfig::SETTING_EBS_DEVICE_NAME] : '/dev/sda1';

            $rootBlockDevice = new BlockDeviceMappingData($deviceName, null, null, $ebs);
            $runInstanceRequest->appendBlockDeviceMapping($rootBlockDevice);
        }

        foreach ($this->GetBlockDeviceMapping($launchOptions->serverType) as $bdm) {
            $runInstanceRequest->appendBlockDeviceMapping($bdm);
        }

        $placementData = $this->GetPlacementGroupData($launchOptions->serverType, $DBServer, $placementData);

        if ($placementData !== null) {
            $runInstanceRequest->setPlacement($placementData);
        }

        $skipKeyValidation = false;
        $sshKey = new SshKey();
        $farmId = NULL;
        if ($DBServer->status == SERVER_STATUS::TEMPORARY) {
            $keyName = "SCALR-ROLESBUILDER-" . SCALR_ID;
            if (!$sshKey->loadGlobalByName($DBServer->envId, SERVER_PLATFORMS::EC2, $launchOptions->cloudLocation, $keyName))
                $keyName = "SCALR-ROLESBUILDER-" . SCALR_ID . "-{$DBServer->envId}";
        } else {
            $keyName = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_KEYPAIR);
            if ($keyName) {
                $skipKeyValidation = true;
            } else {
                $keyName = "FARM-{$DBServer->farmId}-" . SCALR_ID;
                $farmId = $DBServer->farmId;
                $oldKeyName = "FARM-{$DBServer->farmId}";
                if ($sshKey->loadGlobalByName($DBServer->envId, SERVER_PLATFORMS::EC2, $launchOptions->cloudLocation, $oldKeyName)) {
                    $keyName = $oldKeyName;
                    $skipKeyValidation = true;
                }
            }
        }
        if (!$skipKeyValidation && !$sshKey->loadGlobalByName($DBServer->envId, SERVER_PLATFORMS::EC2, $launchOptions->cloudLocation, $keyName)) {
            $result = $aws->ec2->keyPair->create($keyName);
            if ($result->keyMaterial) {
                $sshKey->farmId = $farmId;
                $sshKey->envId = $DBServer->envId;
                $sshKey->type = SshKey::TYPE_GLOBAL;
                $sshKey->platform = SERVER_PLATFORMS::EC2;
                $sshKey->cloudLocation = $launchOptions->cloudLocation;
                $sshKey->cloudKeyName = $keyName;
                $sshKey->privateKey = $result->keyMaterial;
                $sshKey->generatePublicKey();
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

            if (stristr($e->getMessage(), "The requested configuration is currently not supported for this AMI")) {
                \Scalr::getContainer()->logger(__CLASS__)->fatal(sprintf("Unsupported configuration: %s", json_encode($runInstanceRequest)));
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
            $instanceTypeInfo = $this->getInstanceType(
                $runInstanceRequest->instanceType,
                $environment,
                $launchOptions->cloudLocation
            );
            /* @var $instanceTypeInfo CloudInstanceType */
            $DBServer->SetProperties([
                \EC2_SERVER_PROPERTIES::REGION          => $launchOptions->cloudLocation,
                \EC2_SERVER_PROPERTIES::AVAIL_ZONE      => $result->instancesSet->get(0)->placement->availabilityZone,
                \EC2_SERVER_PROPERTIES::INSTANCE_ID     => $result->instancesSet->get(0)->instanceId,
                \EC2_SERVER_PROPERTIES::AMIID           => $runInstanceRequest->imageId,
                \EC2_SERVER_PROPERTIES::VPC_ID          => $result->instancesSet->get(0)->vpcId,
                \EC2_SERVER_PROPERTIES::SUBNET_ID       => $result->instancesSet->get(0)->subnetId,
                \EC2_SERVER_PROPERTIES::ARCHITECTURE    => $result->instancesSet->get(0)->architecture,
                \SERVER_PROPERTIES::INFO_INSTANCE_VCPUS => isset($instanceTypeInfo['vcpus']) ? $instanceTypeInfo['vcpus'] : null,
            ]);

            $DBServer->setOsType($result->instancesSet->get(0)->platform ? $result->instancesSet->get(0)->platform : 'linux');
            $DBServer->cloudLocation = $launchOptions->cloudLocation;
            $DBServer->cloudLocationZone = $result->instancesSet->get(0)->placement->availabilityZone;
            $DBServer->update(['type' => $runInstanceRequest->instanceType, 'instanceTypeName' => $runInstanceRequest->instanceType]);
            $DBServer->imageId = $launchOptions->imageId;
            // we set server history here
            $DBServer->getServerHistory()->update(['cloudServerId' => $result->instancesSet->get(0)->instanceId]);

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

        $instanceTypesInfo = $this->getInstanceTypes(null, null, true);

        if (isset($instanceTypesInfo[$instanceType]['instancestore'])) {
            $devicesNames = [ 'b', 'c', 'e', 'f', 'g', 'h', 'i', 'j', 'k1', 'k2', 'k3', 'k4', 'k5', 'k6', 'k7', 'k8', 'k9', 'l1', 'l2', 'l3', 'l4', 'l5', 'l6', 'l7' ];
            $namesOverrides = [
                'd2.4xlarge' => [ 8 => 'k', 9 => 'l', 10 => 'm', 11 => 'n' ],
                'd2.8xlarge' => [ 8 => 'k', 9 => 'l', 10 => 'm', 11 => 'n', 12 => 'o', 13 => 'p', 14 => 'q', 15 => 'r', 16 => 's', 17 => 't', 18 => 'u', 19 => 'v', 20 => 'w', 21 => 'x', 22 => 'y', 23 => 'd' ]
            ];

            if (isset($namesOverrides[$instanceType])) {
                $devicesNames = array_replace($devicesNames, $namesOverrides[$instanceType]);
            }

            if (isset($instanceTypesInfo[$instanceType]['instancestore']['number'])) {
                for ($i = 0; $i < $instanceTypesInfo[$instanceType]['instancestore']['number']; $i++) {
                    $retval[] = new BlockDeviceMappingData("{$prefix}{$devicesNames[$i]}", "ephemeral{$i}");
                }
            }
        }

        return $retval;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceType()
     */
    public function getInstanceType($instanceTypeId, \Scalr_Environment $env, $cloudLocation = null)
    {
        $instanceTypes = $this->getInstanceTypes($env, $cloudLocation, true);

        return $instanceTypes[$instanceTypeId];
    }

    /**
     * Gets pre filled PlacementResponseData
     *
     * @param string                $instanceType   The type of the instance
     * @param DBServer              $DBServer       DBServer instance
     * @param PlacementResponseData $placementData  optional PlacementResponseData to fill
     *
     * @return PlacementResponseData
     */
    public function GetPlacementGroupData($instanceType, DBServer $DBServer, PlacementResponseData &$placementData = null)
    {
        $instanceTypesInfo = $this->getInstanceTypes(null, null, true);

        if (!empty($instanceTypesInfo[$instanceType]['placementgroups'])) {
            $placementGroup = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::AWS_CLUSTER_PG);
            if ($placementGroup) {
                if (isset($placementData)) {
                    $placementData->groupName = $placementGroup;
                } else {
                    $placementData = new PlacementResponseData(null, $placementGroup);
                }
            }
        }

        return $placementData;
    }

    /**
     * Gets the list of the security groups for the specified db server.
     *
     * If server does not have required security groups this method will create them.
     *
     * @param   DBServer               $DBServer    The DB Server instance
     * @param   \Scalr\Service\Aws\Ec2 $ec2         Ec2 Client instance
     * @param   string                 $vpcId       optional The ID of VPC
     * @param   \Scalr_Governance      $governance  Governance
     * @param   string                 $osFamily    optional OS family of the instance
     * @return  array  Returns array looks like array(groupid-1, groupid-2, ..., groupid-N)
     */
    private function GetServerSecurityGroupsList(DBServer $DBServer, \Scalr\Service\Aws\Ec2 $ec2, $vpcId = "", \Scalr_Governance $governance = null, $osFamily = null)
    {
        $retval = array();
        $checkGroups = array();
        $wildCardSgs = [];
        $sgGovernance = false;
        $allowAdditionalSgs = true;
        $roleBuilderSgName = \Scalr::config('scalr.aws.security_group_name') . "-rb";

        if ($governance && $DBServer->farmRoleId) {
            $sgs = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_SECURITY_GROUPS);
            if ($osFamily == 'windows' && $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_SECURITY_GROUPS, 'windows')) {
                $sgs = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_SECURITY_GROUPS, 'windows');
            }
            if ($sgs !== null) {
                $governanceSecurityGroups = @explode(",", $sgs);
                if (!empty($governanceSecurityGroups)) {
                    foreach ($governanceSecurityGroups as $sg) {
                        if ($sg != '') {
                            array_push($checkGroups, trim($sg));
                            if (strpos($sg, '*') !== false) {
                                array_push($wildCardSgs, trim($sg));
                            }
                        }
                    }
                }
                if (!empty($checkGroups)) {
                    $sgGovernance = true;
                }
                $allowAdditionalSgs = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_SECURITY_GROUPS, 'allow_additional_sec_groups');
            }
        }

        if (!$sgGovernance || $allowAdditionalSgs) {
            if ($DBServer->farmRoleId != 0) {
                $dbFarmRole = $DBServer->GetFarmRoleObject();
                if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_SECURITY_GROUPS_LIST) !== null) {
                    // New SG management
                    $sgs = @json_decode($dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_SECURITY_GROUPS_LIST));
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

                    $additionalSgs = trim($dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_SG_LIST));
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
                array_push($checkGroups, $roleBuilderSgName);
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
            } elseif ($groupName == $roleBuilderSgName) {
                if (!isset($sgList[$groupName])) {
                    try {
                        $securityGroupId = $ec2->securityGroup->create(
                            $roleBuilderSgName, "Security group for Roles Builder", $vpcId
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

                        $sgList[$roleBuilderSgName] = $securityGroupId;
                    } catch (Exception $e) {
                        throw new Exception(sprintf(_("Cannot create security group '%s': %s"), $roleBuilderSgName, $e->getMessage()));
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
                            $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
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
                            $groupRules[CryptoTool::hash($rule['rule'])] = $rule;
                        }

                        // Behavior rules
                        foreach (\Scalr_Role_Behavior::getListForFarmRole($DBServer->GetFarmRoleObject()) as $bObj) {
                            $bRules = $bObj->getSecurityRules();
                            foreach ($bRules as $r) {
                                if ($r) {
                                    $groupRules[CryptoTool::hash($r)] = array('rule' => $r);
                                }
                            }
                        }

                        // Default rules
                        $userIdGroupPairList = new UserIdGroupPairList(new UserIdGroupPairData(
                            $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
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
                    if (!in_array($groupName, $wildCardSgs)) {
                        throw new Exception(sprintf(_("Security group '%s' is not found"), $groupName));
                    } else {
                        $wildCardMatchedSgs = [];
                        $groupNamePattern = \Scalr_Governance::convertAsteriskPatternToRegexp($groupName);
                        foreach ($sgList as $sgGroupName => $sgGroupId) {
                            if (preg_match($groupNamePattern, $sgGroupName) === 1) {
                                array_push($wildCardMatchedSgs, $sgGroupId);
                            }
                        }
                        if (empty($wildCardMatchedSgs)) {
                            throw new Exception(sprintf(_("Security group matched to pattern '%s' is not found."), $groupName));
                        } else if (count($wildCardMatchedSgs) > 1) {
                            throw new Exception(sprintf(_("There are more than one Security group matched to pattern '%s' found."), $groupName));
                        } else {
                            array_push($retval, $wildCardMatchedSgs[0]);
                        }
                    }
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
        if ($DBServer->status == SERVER_STATUS::TEMPORARY) {
            return false;
        }

        $server_avail_zone = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE);

        $role_avail_zone = $this->db->GetOne("
            SELECT ec2_avail_zone FROM ec2_ebs
            WHERE server_index=? AND farm_roleid=?
            LIMIT 1
        ", [$DBServer->index, $DBServer->farmRoleId]);

        if (!$role_avail_zone) {
            if ($server_avail_zone &&
                $server_avail_zone != 'x-scalr-diff' &&
                !stristr($server_avail_zone, "x-scalr-custom")) {
                return $server_avail_zone;
            }

            $role_avail_zone = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::AWS_AVAIL_ZONE);
        }

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

            return $availZone;
        } else {
            return $role_avail_zone;
        }
    }

    /**
     * @param Scalr_Environment $environment
     * @param DBServer $DBServer
     *
     * @return object
     */
    public function GetPlatformAccessData($environment, DBServer $DBServer)
    {
        $config = \Scalr::getContainer()->config;

        $cloudCredentials = $environment->keychain(SERVER_PLATFORMS::EC2);
        $ccProps = $cloudCredentials->properties;

        $accessData = new \stdClass();
        $accessData->accountId = $ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID];
        $accessData->keyId = $ccProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY];
        $accessData->key = $ccProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY];
        $accessData->cert = $ccProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE];
        $accessData->pk = $ccProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY];

        if ($config('scalr.aws.use_proxy') && in_array($config('scalr.connections.proxy.use_on'), ['both', 'instance'])) {
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

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceIdPropertyName()
     */
    public function getInstanceIdPropertyName()
    {
        return EC2_SERVER_PROPERTIES::INSTANCE_ID;
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getgetClientByDbServer()
     *
     * @return Aws\Client\ClientInterface
     */
    public function getHttpClient(DBServer $dbServer)
    {
        return $dbServer->GetEnvironmentObject()
                        ->aws($dbServer)
                        ->ec2
                        ->getApiHandler()
                        ->getClient();
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getImageInfo()
     */
    public function getImageInfo(\Scalr_Environment $environment, $cloudLocation, $imageId)
    {
        $info = [];

        $snap = $environment->aws($cloudLocation)->ec2->image->describe($imageId);

        if ($snap->count() > 0) {
            $sn = $snap->get(0);
            if ($sn->imageState == ImageData::STATE_AVAILABLE) {
                $info["name"]         = $sn->name;
                $info["architecture"] = $sn->architecture;

                if ($sn->description) {
                    $info["description"] = $sn->description;
                }

                if ($sn->platform) {
                    // platform could be windows or empty string
                    $info["osFamily"] = $sn->platform;
                }

                if ($sn->rootDeviceType == "ebs" || $sn->rootDeviceType == "instance-store") {
                    $info["type"] = $sn->rootDeviceType;

                    if ($sn->virtualizationType == "hvm") {
                        $info["type"] .= "-hvm";
                    }
                }

                foreach ($sn->blockDeviceMapping as $b) {
                    if (($b->deviceName == $sn->rootDeviceName) && $b->ebs) {
                        $info["size"]          = $b->ebs->volumeSize;
                        $info["ec2VolumeType"] = $b->ebs->volumeType;
                        $info["ec2VolumeIops"] = $b->ebs->iops;
                    }
                }
            }
        }

        return $info;
    }
}
