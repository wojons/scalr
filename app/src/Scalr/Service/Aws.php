<?php
namespace Scalr\Service;

use Scalr\Service\Aws\Plugin\EventObserver;
use Scalr\Service\Aws\EntityManager;
use Scalr\DependencyInjection\Container;
use Scalr\Service\Aws\ServiceInterface;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Ec2\DataType\RegionInfoList;

/**
 * Amazon Web Services software development kit
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    18.09.2012
 *
 * @property-read  \Scalr\Service\Aws\Elb        $elb        Amazon Elastic Load Balancer (ELB) service interface instance
 * @property-read  \Scalr\Service\Aws\CloudWatch $cloudWatch Amazon CloudWatch service interface instance
 * @property-read  \Scalr\Service\Aws\Sqs        $sqs        Amazon Simple Queue Service (SQS) interface instance
 * @property-read  \Scalr\Service\Aws\S3         $s3         Amazon Simple Storage Service (S3) interface instance
 * @property-read  \Scalr\Service\Aws\Iam        $iam        Amazon Identity and Access Management Service (IAM) interface instance
 * @property-read  \Scalr\Service\Aws\Ec2        $ec2        Amazon Elastic Compute Cloud (EC2) service interface instance
 * @property-read  \Scalr\Service\Aws\CloudFront $cloudFront Amazon CloudFront service interface instance
 * @property-read  \Scalr\Service\Aws\Rds        $rds        Amazon Relational Database Service (RDS) interface instance
 * @property-read  \Scalr\Service\Aws\Route53    $route53    Amazon Route53 service interface instance
 * @property-read  \Scalr\Service\Aws\Kms        $kms        Amazon KMS interface instance
 */
class Aws
{

    const CLIENT_QUERY = 'Query';

    const CLIENT_SOAP  = 'Soap';

    /**
     * United States East (Northern Virginia) Region.
     */
    const REGION_US_EAST_1 = 'us-east-1';

    /**
     * United States West (Northern California) Region.
     */
    const REGION_US_WEST_1 = 'us-west-1';

    /**
     * United States West (Oregon) Region.
     */
    const REGION_US_WEST_2 = 'us-west-2';

    /**
     * Europe West (Ireland) Region.
     */
    const REGION_EU_WEST_1 = 'eu-west-1';

    /**
     * Europe Central (Frankfurt) Region.
     */
    const REGION_EU_CENTRAL_1 = 'eu-central-1';

    /**
     * Asia Pacific Southeast (Singapore) Region.
     */
    const REGION_AP_SOUTHEAST_1 = 'ap-southeast-1';

    /**
     * Sydney
     */
    const REGION_AP_SOUTHEAST_2 = 'ap-southeast-2';

    /**
     * Asia Pacific Northeast (Tokyo) Region.
     */
    const REGION_AP_NORTHEAST_1 = 'ap-northeast-1';

    /**
     * South America (Sao Paulo) Region.
     */
    const REGION_SA_EAST_1 = 'sa-east-1';

    /**
     * China North 1 (China)
     */
    const REGION_CN_NORTH_1 = 'cn-north-1';

    /**
     * GovCloud (US)
     */
    const REGION_US_GOV_WEST_1 = 'us-gov-west-1';

    /**
     * United States East (Northern Virginia) Region Hosted Zone Id.
     */
    const ZONE_ID_US_EAST_1 = 'Z3AQBSTGFYJSTF';

    /**
     * United States West (Northern California) Region Hosted Zone Id.
     */
    const ZONE_ID_US_WEST_1 = 'Z2F56UZL2M1ACD';

    /**
     * United States West (Oregon) Region Hosted Zone Id.
     */
    const ZONE_ID_US_WEST_2 = 'Z3BJ6K6RIION7M';

    /**
     * Europe West (Ireland) Region Hosted Zone Id.
     */
    const ZONE_ID_EU_WEST_1 = 'Z1BKCTXD74EZPE';

    /**
     * Europe Central (Frankfurt) Region Hosted Zone Id.
     */
    const ZONE_ID_EU_CENTRAL_1 = 'Z21DNDUVLTQW6Q';

    /**
     * Asia Pacific Southeast (Singapore) Region Hosted Zone Id.
     */
    const ZONE_ID_AP_SOUTHEAST_1 = 'Z3O0J2DXBE1FTB';

    /**
     * Sydney Hosted Zone Id
     */
    const ZONE_ID_AP_SOUTHEAST_2 = 'Z1WCIGYICN2BYD';

    /**
     * Asia Pacific Northeast (Tokyo) Region Hosted Zone Id.
     */
    const ZONE_ID_AP_NORTHEAST_1 = 'Z2M4EHUR26P7ZW';

    /**
     * South America (Sao Paulo) Region Hosted Zone Id.
     */
    const ZONE_ID_SA_EAST_1 = 'Z7KQH4QJS55SO';

    /**
     * GovCloud (US) Hosted Zone Id
     */
    const ZONE_ID_US_GOV_WEST_1 = 'Z31GFT0UA1I2HV';

    /**
     * Elastic Load Balancer Web service interface
     */
    const SERVICE_INTERFACE_ELB = 'elb';

    /**
     * Amazon CloudWatch Web service interface
     */
    const SERVICE_INTERFACE_CLOUD_WATCH = 'cloudWatch';

    /**
     * Amazon Simple Queue Service interface
     */
    const SERVICE_INTERFACE_SQS = 'sqs';

    /**
     * Amazon Simple Storage Service interface
     */
    const SERVICE_INTERFACE_S3 = 's3';

    /**
     * Amazon Identity and Access Management Service interface
     */
    const SERVICE_INTERFACE_IAM = 'iam';

    /**
     * Amazon Elastic Compute Cloud service interface
     */
    const SERVICE_INTERFACE_EC2 = 'ec2';

    /**
     * Amazon CloudFront service interface
     */
    const SERVICE_INTERFACE_CLOUD_FRONT = 'cloudFront';

    /**
     * Amazon Route53 service interface
     */
    const SERVICE_INTERFACE_ROUTE53 = 'route53';

    /**
     * Amazon RDS service interface
     */
    const SERVICE_INTERFACE_RDS = 'rds';

    /**
     * Amazon Key Management Service interface
     */
    const SERVICE_INTERFACE_KMS = 'kms';

    /**
     * Access Key Id
     * @var string
     */
    private $accessKeyId;

    /**
     * Secret Access Key
     * @var string
     */
    private $secretAccessKey;

    /**
     * X.509 certificate
     * @var string
     */
    private $certificate;

    /**
     * Private key for certificate
     * @var string
     */
    private $privateKey;

    /**
     * AWS Entity Manager
     * @var EntityManager
     */
    private $entityManager;

    /**
     * Whether debug is enabled or not.
     *
     * @var bool
     */
    private $debug = false;

    /**
     * Region for AWS
     *
     * @var string
     */
    private $region;

    /**
     * @var Container
     */
    protected $container;

    /**
     * Reflection class of Aws
     *
     * @var \ReflectionClass
     */
    private static $reflection;

    /**
     * Array of the instances of the service interfaces
     *
     * @var array
     */
    private $serviceInterfaces = array();

    /**
     * The quantity of the processed queries to the AWS API
     *
     * @var int
     */
    public $queriesQuantity = 0;

    /**
     * AWS Client event observer
     *
     * @var EventObserver
     */
    private $eventObserver;

    /**
     * An environment object
     *
     * @var \Scalr_Environment
     */
    private $environment;

    /**
     * The number of AWS Account
     *
     * @var string
     */
    private $awsAccountNumber;

    /**
     * Proxy Host
     *
     * @var string
     */
    private $proxyHost;

    /**
     * Proxy Port
     *
     * @var int
     */
    private $proxyPort;

    /**
     * The username that is used for proxy
     *
     * @var string
     */
    private $proxyUser;

    /**
     * Proxy password
     *
     * @var string
     */
    private $proxyPass;

    /**
     * The type of the proxy
     *
     * @var int
     */
    private $proxyType;

    /**
     * Constructor
     *
     * @param   string     $accessKeyId      AWS access key id
     * @param   string     $secretAccessKey  AWS secret access key
     * @param   string     $region           optional An AWS region. (Aws::REGION_US_EAST_1)
     * @param   string     $certificate      optional AWS x.509 certificate (It's used only for Soap API)
     * @param   string     $privateKey       optional Private Key (It's used only for Soap API)
     */
    public function __construct($accessKeyId, $secretAccessKey, $region = null, $certificate = null, $privateKey = null)
    {
        $this->container = \Scalr::getContainer();
        $this->region = $region;
        $this->accessKeyId = $accessKeyId;
        $this->secretAccessKey = $secretAccessKey;
        $this->entityManager = new EntityManager();
        $this->certificate = $certificate;
        $this->privateKey = $privateKey;
    }

    /**
     * Set proxy configuration to connect to AWS services
     * @param string $host
     * @param integer $port
     * @param string $user
     * @param string $pass
     * @param string $type Allowed values 4 - SOCKS4, 5 - SOCKS5, 0 - HTTP
     */
    public function setProxy($host, $port = 3128, $user = null, $pass = null, $type = 0)
    {
        $this->proxyHost = $host;
        $this->proxyPort = $port;
        $this->proxyUser = $user;
        $this->proxyPass = $pass;
        $this->proxyType = $type;
    }

    public function getProxy()
    {
        return ($this->proxyHost) ? array(
            'host' => $this->proxyHost,
            'port' => $this->proxyPort,
            'user' => $this->proxyUser,
            'pass' => $this->proxyPass,
            'type' => $this->proxyType
        ) : false;
    }

    /**
     * Gets container
     *
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Retrieves AWS Account Number
     *
     * @return  string  Returns AWS Account number for current user
     * @throws  ClientException
     */
    public function getAccountNumber()
    {
        if ($this->awsAccountNumber === null) {
            try {
                $arr = preg_split('/\:/', $this->iam->user->fetch()->arn);
                $this->awsAccountNumber = $arr[4];
            } catch (ClientException $e) {
                if (preg_match('/arn\:aws[\w-]*\:iam\:\:(\d+)\:user/', $e->getMessage(), $matches)) {
                    $this->awsAccountNumber = $matches[1];
                } else {
                    throw $e;
                }
            }
        }
        return $this->awsAccountNumber;
    }

    /**
     * Retrieves AWS username
     *
     * @return  string  Returns AWS username
     * @throws  ClientException
     */
    public function getUsername()
    {
        try {
            return $this->iam->user->fetch()->userName;
        } catch (ClientException $e) {}
    }

    /**
     * Retrieves AWS user arn
     *
     * @return  string  Returns AWS user arn
     * @throws  ClientException
     */
    public function getUserArn()
    {
        try {
             return $this->iam->user->fetch()->arn;
        } catch (ClientException $e) {
            if (preg_match('/arn\:aws[\w-]*\:iam\:\:(\d+)\:user/', $e->getMessage(), $matches)) {
                return $matches[0];
            } else {
                throw $e;
            }
        }
    }

    /**
     * Gets region
     *
     * @return    string   Returns region that has been provided for instance
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * Gets implemented web service interfaces
     *
     * @return     array Returns Returns the list of available (implemented) web service interfaces
     */
    public function getAvailableServiceInterfaces()
    {
        return [
            self::SERVICE_INTERFACE_ELB,
            self::SERVICE_INTERFACE_CLOUD_WATCH,
            self::SERVICE_INTERFACE_CLOUD_FRONT,
            self::SERVICE_INTERFACE_SQS,
            self::SERVICE_INTERFACE_S3,
            self::SERVICE_INTERFACE_IAM,
            self::SERVICE_INTERFACE_EC2,
            self::SERVICE_INTERFACE_RDS,
            self::SERVICE_INTERFACE_ROUTE53,
            self::SERVICE_INTERFACE_KMS,
        ];
    }

    /**
     * Gets available regions
     *
     * @param     bool    $ignoreCache  optional If true it will ignore cache
     * @return    array   Returns list of available regions
     */
    public function getAvailableRegions($ignoreCache = false)
    {
        return self::getCloudLocations();
    }

    /**
     * Gets defined AWS cloud locations
     *
     * @return   array
     */
    public static function getCloudLocations()
    {
        return [
            self::REGION_AP_NORTHEAST_1,
            self::REGION_AP_SOUTHEAST_1,
            self::REGION_AP_SOUTHEAST_2,
            self::REGION_EU_WEST_1,
            self::REGION_EU_CENTRAL_1,
            self::REGION_SA_EAST_1,
            self::REGION_US_EAST_1,
            self::REGION_US_WEST_1,
            self::REGION_US_WEST_2,
            self::REGION_US_GOV_WEST_1,
            self::REGION_CN_NORTH_1,
        ];
    }

    /**
     * Gets defined AWS cloud locations hosted zone ids
     *
     * @return   array
     */
    public static function getCloudLocationsZoneIds()
    {
        return [
            self::REGION_AP_NORTHEAST_1 => self::ZONE_ID_AP_NORTHEAST_1,
            self::REGION_AP_SOUTHEAST_1 => self::ZONE_ID_AP_SOUTHEAST_1,
            self::REGION_AP_SOUTHEAST_2 => self::ZONE_ID_AP_SOUTHEAST_2,
            self::REGION_EU_WEST_1      => self::ZONE_ID_EU_WEST_1,
            self::REGION_EU_CENTRAL_1   => self::ZONE_ID_EU_CENTRAL_1,
            self::REGION_SA_EAST_1      => self::ZONE_ID_SA_EAST_1,
            self::REGION_US_EAST_1      => self::ZONE_ID_US_EAST_1,
            self::REGION_US_WEST_1      => self::ZONE_ID_US_WEST_1,
            self::REGION_US_WEST_2      => self::ZONE_ID_US_WEST_2,
            self::REGION_US_GOV_WEST_1  => self::ZONE_ID_US_GOV_WEST_1,
            //TODO add zone id for China region
        ];
    }

    /**
     * Checks whether provided region is valid.
     *
     * @param    string    $region   AWS region  (Aws::REGION_US_EAST_1)
     * @return   boolean   Returns boolean true if region is valid or false otherwise.
     */
    public function isValidRegion($region)
    {
        if (!in_array($region, $this->getAvailableRegions())) {
            $ret = false;
        } else {
            $ret = true;
        }
        return $ret;
    }

    /**
     * DescribeRegions action
     *
     * @return  RegionInfoList  Returns the list of the RegionInfoData objects on success
     * @throws  ClientException
     * @throws  Ec2Exception
     */
    public function describeRegions()
    {
        return $this->ec2->describeRegions();
    }

    /**
     * Gets reflection class of Aws
     *
     * @return \ReflectionClass  Returns reflection class of Aws
     */
    static public function getReflectionClass()
    {
        if (!isset(self::$reflection)) {
            self::$reflection = new \ReflectionClass(__CLASS__);
        }
        return self::$reflection;
    }

    /**
     * Magic getter
     *
     * @param     string      $name
     * @return    mixed|null
     */
    public function __get($name)
    {
        //Retrieves service provider object
        if (in_array(($n = lcfirst($name)), $this->getAvailableServiceInterfaces())) {
            if (!isset($this->serviceInterfaces[$n])) {
                //It validates region only for the services which it is necessary for.
                if (!in_array($n, array(self::SERVICE_INTERFACE_IAM, self::SERVICE_INTERFACE_S3, self::SERVICE_INTERFACE_CLOUD_FRONT))) {
                    if (!$this->isValidRegion($this->region)) {
                        throw new AwsException(sprintf('Invalid region "%s" for the service "%s"', $this->region, $n));
                    }
                }

                $class = __CLASS__ . '\\' . ucfirst($n);

                try {
                    /* @var $service ServiceInterface */
                    $service = new $class($this);
                    $this->serviceInterfaces[$n] = $service;
                } catch (\Exception $e) {
                    throw new AwsException('Cannot create service interface instance of ' . $class . ' ' . $e->getMessage());
                }
            } else {
                $service = $this->serviceInterfaces[$n];
            }
            return $service;
        }
        return null;
    }

    /**
     * Gets Access Key Id
     *
     * @return string Returns Access Key Id
     */
    public function getAccessKeyId()
    {
        return $this->accessKeyId;
    }

    /**
     * Gets Secret Access Key
     *
     * @return string Returns Secret Access Key
     */
    public function getSecretAccessKey()
    {
        return $this->secretAccessKey;
    }

    /**
     * Calculates an MD5.Base64digest for the given string.
     *
     * @param   string     $string A string which should digest be calculated for.
     * @return  string     Returns MD5 Base64 digest
     */
    public static function getMd5Base64Digest ($string)
    {
        return base64_encode(pack('H*', md5($string)));
    }

    /**
     * Calculates an MD5.Base64digest for the given file.
     *
     * @param   string     $file   A file path which should digest be calculated for.
     * @return  string     Returns MD5 Base64 digest
     */
    public static function getMd5Base64DigestFile ($file)
    {
        return base64_encode(pack('H*', md5_file($file)));
    }

    /**
     * Gets an AWS Entity Manager
     *
     * This manager helps manipulate with retrieved from AWS objects.
     * These object are stored in the cache.
     *
     * @return  EntityManager Returns an AWS Entity Manager object.
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * Get x.509 certificate
     *
     * @return  string Returns x.509 certificate
     */
    public function getCertificate()
    {
        return $this->certificate;
    }

    /**
     * Get private key
     *
     * @return  string Returns private key from certificate
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * Validates certificate and privatekey making AWS SOAP request
     *
     * @return  bool      Returns true on success or throws an exception
     * @throws  \Exception
     */
    public function validateCertificateAndPrivateKey()
    {
        $prevClient = $this->ec2->getApiClientType();
        $this->ec2->setApiClientType(self::CLIENT_SOAP);

        try {
            $exc = null;
            $this->ec2->availabilityZone->describe();
        } catch (\Exception $e) {
            $exc = $e;
        }

        $this->ec2->setApiClientType($prevClient);

        if (isset($exc)) throw $exc;

        return true;
    }

    /**
     * Sets debug flag
     *
     * @param   bool     $debug optional If true it will enable debug mode
     * @return  Aws
     */
    public function setDebug($debug = true)
    {
        $this->debug = (bool) $debug;
        return $this;
    }

    /**
     * Gets debug flag value
     *
     * @return  bool Returns true if debug is enabled.
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * Resets debug
     *
     * @return  Aws
     */
    public function resetDebug()
    {
        $this->debug = false;
        return $this;
    }

    /**
     * Gets an AWS client event observer
     *
     * @return  \Scalr\Service\Aws\Plugin\EventObserver Returns AWS client event observer
     */
    public function getEventObserver()
    {
        return $this->eventObserver;
    }

    /**
     * Sets an AWS client event observer object associated with this instance
     *
     * @param   \Scalr\Service\Aws\Plugin\EventObserver $eventObserver The event observer
     * @return  Aws
     */
    public function setEventObserver(EventObserver $eventObserver = null)
    {
        $this->eventObserver = $eventObserver;
        return $this;
    }

    /**
     * Sets an Scalr environment object which is associated with the AWS client instance
     *
     * @param   \Scalr_Environment $environment An environment object
     * @return  Aws
     */
    public function setEnvironment(\Scalr_Environment $environment = null)
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * Gets an Scalr Environment object which is associated with the AWS client instance
     *
     * @return  \Scalr_Environment  Returns Scalr Environment object
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Gets presigned url signed with aws4 version signing algorithm
     *
     * @param string $service       Service name (ec2, s3)
     * @param string $action        Action name (CopySnapshot)
     * @param string $destRegion    Destination region
     * @param string $objectId      Id of the snapshot, image , etc
     */
    public function getPresignedUrl($service, $action, $destRegion, $objectId)
    {
        $canonicalizedQueryString = '';
        $time = time();
        $options = [
            'X-Amz-Algorithm'       => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'      => $this->getAccessKeyId() . '/' . gmdate('Ymd', $time) . '/' . $this->getRegion() . '/' . $service . '/aws4_request',
            'X-Amz-Date'            => gmdate('Ymd\THis\Z', $time),
            'X-Amz-Expires'         => '86400',
            'X-Amz-SignedHeaders'   => 'host',
            'SourceRegion'          => $this->getRegion(),
            'Action'                => $action,
            'SourceSnapshotId'      => $objectId,
            'DestinationRegion'     => $destRegion,
        ];

        ksort($options);

        foreach ($options as $k => $v) {
            $canonicalizedQueryString .= '&' . rawurlencode($k) . '=' . rawurlencode($v);
        }

        if ($canonicalizedQueryString !== '') {
            $canonicalizedQueryString = substr($canonicalizedQueryString, 1);
        }

        $canonicalRequest =
            'GET' . "\n"
          . "/\n"
          . $canonicalizedQueryString . "\n"
          . "host:" . $this->{$service}->getUrl() . "\n"
          . "\n"
          . "host" . "\n"
          . hash('sha256', '')
        ;

        $stringToSign = "AWS4-HMAC-SHA256" . "\n"
                      . gmdate('Ymd\THis\Z', $time) . "\n"
                      . gmdate('Ymd', $time) . "/" .  $this->getRegion() . "/" . $service . "/aws4_request" . "\n"
                      . hash('sha256', $canonicalRequest);

        $dateKey = hash_hmac('sha256', gmdate('Ymd', $time), "AWS4" . $this->getSecretAccessKey(), true);
        $dateRegionKey = hash_hmac('sha256', $this->getRegion(), $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', $service, $dateRegionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $presignedUrl = "https://" . $this->{$service}->getUrl() . "/?" . $canonicalizedQueryString
                      . "&X-Amz-Signature=" . rawurlencode($signature);

        return $presignedUrl;
    }

}