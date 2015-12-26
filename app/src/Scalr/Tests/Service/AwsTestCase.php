<?php
namespace Scalr\Tests\Service;

use Scalr\Tests\TestCase;
use Scalr\Service\Aws\ServiceInterface;
use Scalr\DependencyInjection\Container;
use Scalr\Service\Aws\Client\QueryClientResponse;
use Scalr\Service\Aws\EntityManager;
use Scalr\Service\Aws;

/**
 * AWS TestCase
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     19.10.2012
 */
class AwsTestCase extends TestCase
{
    const AWS_NS = 'Scalr\\Service\\Aws';

    const REGION = 'us-east-1';

    const AVAILABILITY_ZONE_A = 'us-east-1a';

    const AVAILABILITY_ZONE_B = 'us-east-1b';

    const AVAILABILITY_ZONE_C = 'us-east-1c';

    const AVAILABILITY_ZONE_D = 'us-east-1d';

    const CLASS_ERROR_DATA =  "Scalr\\Service\\Aws\\DataType\\ErrorData";

    const CLASS_LOAD_BALANCER_DESCRIPTION_DATA = 'Scalr\\Service\\Aws\\Elb\\DataType\\LoadBalancerDescriptionData';

    const CLASS_INSTANCE_STATE_LIST = 'Scalr\\Service\\Aws\\Elb\\DataType\\InstanceStateList';

    const CLASS_APP_COOKIE_STICKINESS_POLICY_LIST = 'Scalr\\Service\\Aws\\Elb\\DataType\\AppCookieStickinessPolicyList';

    const CLASS_LB_COOKIE_STICKINESS_POLICY_LIST = 'Scalr\\Service\\Aws\\Elb\\DataType\\LbCookieStickinessPolicyList';

    /**
     * @var Container
     */
    private $container;

    /**
     * @var \Scalr_Environment
     */
    private $environment;

    /**
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();

        $this->container = \Scalr::getContainer();
        $this->environment = new \Scalr_Environment();

        if (!$this->isSkipFunctionalTests()) {
            $this->environment->loadById(\Scalr::config('scalr.phpunit.envid'));
            $this->container->environment = $this->environment;
        }
    }

    /**
     * Skips test functionals tests are skipped or if Ec2 platform is not enabled.
     */
    protected function skipIfEc2PlatformDisabled()
    {
        if ($this->isSkipFunctionalTests() || !$this->environment ||
            !$this->environment->isPlatformEnabled(\SERVER_PLATFORMS::EC2)) {
            $this->markTestSkipped('Ec2 platform is not enabled.');
        }
    }

    /**
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown()
    {
        $this->environment = null;
        $this->container = null;
        parent::tearDown();
    }

    /**
     * Gets DI Container
     *
     * @return \Scalr\DependencyInjection\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Gets Environment
     *
     * @return \Scalr_Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Gets an Aws mock
     *
     * @return \Scalr\Service\Aws Returns Aws Mock stub
     */
    public function getAwsMock()
    {
        $container = $this->getContainer();
        $awsStub = $this->getMock(
            AwsTestCase::AWS_NS,
            array('__get', 'getEntityManager', 'getContainer'),
            array(AwsTestCase::REGION),
            '',
            false
        );

        $em = new EntityManager();
        $awsStub->expects($this->any())->method('getEntityManager')->will($this->returnValue($em));

        $awsStub->expects($this->any())->method('getContainer')->will($this->returnValue($container));

        foreach (array(
            'region'          => self::REGION,
            'accessKeyId'     => ($container->initialized('environment') ? $container->awsAccessKeyId : 'fakeAccessKeyId'),
            'secretAccessKey' => ($container->initialized('environment') ? $container->awsSecretAccessKey : 'fakeAwsSecretAccessKey'),
        ) as $k => $v) {
            $r = new \ReflectionProperty(AwsTestCase::AWS_NS, 'region');
            $r->setAccessible(true);
            $r->setValue($awsStub, self::REGION);
            $r->setAccessible(false);
            unset($r);
        }

        return $awsStub;
    }


    /**
     * Gets an service interface mock object
     *
     * @param   string                   $serviceName  Service name (Elb, CloudWatch etc..)
     * @param   \Closure|callback|string $callback     optional callback for QueryClientResponse mock
     * @return  ServiceInterface Returns service interface mock
     * @throws  \RuntimeException
     */
    public function getServiceInterfaceMock($serviceName, $callback = null)
    {
        $me = $this;
        $serviceName = lcfirst($serviceName);
        $ucServiceName = ucfirst($serviceName);
        $serviceClass = AwsTestCase::AWS_NS . '\\' . $ucServiceName;
        $container = $this->getContainer();
        $awsStub = $this->getAwsMock();
        $serviceInterfaceStub = $this->getMock(
            $serviceClass,
            array('getApiHandler', '__get'),
            array($awsStub)
        );
        $serviceInterfaceStub->enableEntityManager();

        $aHandlers = array();

        $serviceInterfaceStub
            ->expects($this->any())
            ->method('__get')
            ->will($this->returnCallback(function ($name) use (&$aHandlers, $serviceName, $serviceInterfaceStub) {
                if (in_array($name, $serviceInterfaceStub->getAllowedEntities())) {
                    if (!isset($aHandlers[$serviceName][$name])) {
                        $className = sprintf(
                            AwsTestCase::AWS_NS . '\\%s\\Handler\\%sHandler',
                            ucfirst($serviceName), ucfirst($name)
                        );
                        $aHandlers[$serviceName][$name] = new $className($serviceInterfaceStub);
                    }
                    return $aHandlers[$serviceName][$name];
                } else {
                    throw new \InvalidArgumentException(sprintf(
                        'Unknown handler "%s" for the Mock object "%s"', $name, get_class($serviceInterfaceStub)
                    ));
                }
            }))
        ;

        $awsStub
            ->expects($this->any())
            ->method('__get')
            ->will($this->returnValue($serviceInterfaceStub))
        ;
        if (is_readable(SRCPATH . '/Scalr/Service/Aws/Client/QueryClient/' . ucfirst($serviceName) . 'QueryClient.php')) {
            $queryClientClass = AwsTestCase::AWS_NS . '\\Client\\QueryClient\\' . ucfirst($serviceName) . 'QueryClient';
        } else {
            $queryClientClass = AwsTestCase::AWS_NS . '\\Client\\QueryClient';
        }
        $queryClientStub = $this->getMock(
            $queryClientClass,
            array('call'),
            array(
                ($container->initialized('environment') ? $container->awsAccessKeyId : 'fakeAccessKeyId'),
                ($container->initialized('environment') ? $container->awsSecretAccessKey : 'fakeAwsSecretAccessKey'),
                $serviceClass::API_VERSION_CURRENT,
                $serviceInterfaceStub->getUrl(),
            )
        );

        if ($callback === null) {
            $callback = array($this, 'getQueryClientStandartCallResponseMock');
        } else if (is_string($callback) && substr($callback, -4) == '.xml') {
            $mth = $callback;
            $callback = function() use ($me, $awsStub, $mth) {
                return $me->getQueryClientResponseMock($me->getFixtureFileContent($mth), null, $awsStub);
            };
        } else if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Invalid callback');
        }

        $queryClientStub
            ->expects($this->any())
            ->method('call')
            ->will($this->returnCallback($callback))
        ;


        $apiClass = $serviceClass . '\\V' . $serviceClass::API_VERSION_CURRENT . '\\' . $ucServiceName . 'Api';
        $elbApi = new $apiClass($serviceInterfaceStub, $queryClientStub);
        $serviceInterfaceStub
            ->expects($this->any())
            ->method('getApiHandler')
            ->will($this->returnValue($elbApi))
        ;

        return $serviceInterfaceStub;
    }

    /**
     * Gets QueryClientResponse Mock.
     *
     * @param     string             $body
     * @param     int                $responseCode optional The code of the http response
     * @param     \Scalr\Service\Aws $awsStub optional Aws mock
     * @return    QueryClientResponse Returns response mock object
     */
    public function getQueryClientResponseMock($body, $responseCode = null, $awsStub = null)
    {
        $response = $this->getMock(
            AwsTestCase::AWS_NS . '\\Client\\QueryClientResponse',
            array(
                'getRawContent',
                'getResponseCode',
            ),
            array(
                $this->getMock('\http\Client\Response')
            )
        );

        if ($awsStub !== null) {
            $response->setQueryNumber(++$awsStub->queriesQuantity);
        }

        if ($responseCode === null) {
            if (preg_match('/<\/errors>/i', $body)) {
                $responseCode = 500;
            } else {
                $responseCode = 200;
            }
        }
        $response->expects($this->any())->method('getResponseCode')->will($this->returnValue($responseCode));
        $response->expects($this->any())->method('getRawContent')->will($this->returnValue($body));

        return $response;
    }


    /**
     * {@inheritdoc}
     * @see Scalr\Tests.TestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Service/Aws';
    }

    /**
     * Returns fixtures file path
     *
     * @param  string $filename A fixture file name
     * @return string Returns fixtures file path
     */
    public function getFixtureFilePath($filename)
    {
        return $this->getFixturesDirectory() . "/" . $filename;
    }

    /**
     * Gets fixture file content
     *
     * @param    string  $filename  A fixture file name
     * @return   string  Returns fixture file content
     */
    public function getFixtureFileContent($filename)
    {
        $path = $this->getFixtureFilePath($filename);
        if (!file_exists($path)) {
            throw new \RuntimeException('Could not find the file ' . $path);
        }
        return file_get_contents($path);
    }

    /**
     * Gets standart query client response mock
     *
     * @param    string    $method   AWS API action name
     * @return   \Scalr\Service\Aws\Client\QueryClientResponse  Returns QueryClientResponse Mock object
     */
    public function getQueryClientStandartCallResponseMock($method)
    {
        return $this->getQueryClientResponseMock($this->getFixtureFileContent($method . '.xml'));
    }

    /**
     * Gets Aws class name
     *
     * @param   string    $suffix
     * @return  string    Returns Aws class name strted with Namespace Scalr\\Service\\Aws\\
     */
    public function getAwsClassName($suffix)
    {
        return  AwsTestCase::AWS_NS . '\\' . $suffix;
    }

    /**
     * Gets Ec2 class name
     *
     * @param   string   $suffix Suffix
     * @return  string
     */
    public function getEc2ClassName($suffix)
    {
        return AwsTestCase::AWS_NS . '\\Ec2\\' . $suffix;
    }

    /**
     * Gets Rds class name
     *
     * @param   string   $suffix Suffix
     * @return  string
     */
    public function getRdsClassName($suffix)
    {
        return AwsTestCase::AWS_NS . '\\Rds\\' . $suffix;
    }

    /**
     * Gets CloudFront class name
     *
     * @param   string   $suffix Suffix
     * @return  string
     */
    public function getCloudFrontClassName($suffix)
    {
        return  AwsTestCase::AWS_NS . '\\CloudFront\\' . $suffix;
    }

    /**
     * Gets Route53 class name
     *
     * @param   string   $suffix Suffix
     * @return  string
     */
    public function getRoute53ClassName($suffix)
    {
        return  AwsTestCase::AWS_NS . '\\Route53\\' . $suffix;
    }

    /**
     * Data provider for client type tests
     *
     * @return array
     */
    public function providerClientType()
    {
        return [
            [Aws::CLIENT_QUERY],

            //SOAP client has been deprecated
            //[Aws::CLIENT_SOAP],
        ];
    }
}