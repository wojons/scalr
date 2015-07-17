<?php
namespace Scalr\Tests\Service\CloudStack;

use Scalr\DependencyInjection\Container;
use Scalr\Service\CloudStack\CloudStack;
use Scalr\Tests\TestCase;

/**
 * CloudStack TestCase
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
class CloudStackTestCase extends TestCase
{

    const CLOUDSTACK_NS = 'Scalr\\Service\\CloudStack';

    const PLATFORM = 'idcf';

    /**
     * CloudStack instance
     * @var CloudStack
     */
    protected $cloudstack;

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
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown()
    {
        unset($this->environment);
        parent::tearDown();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.TestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Service/CloudStack';
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
     * Gets full class name by its suffix after CloudStack\\
     *
     * @param   string   $classSuffix
     * @return  string
     */
    public function getCloudStackClassName($classSuffix)
    {
        return 'Scalr\\Service\\CloudStack\\' . $classSuffix;
    }

    /**
     * Gets full FIXTURE class name  by its suffix after CloudStack\\
     *
     * @param   string   $classSuffix
     * @return  string
     */
    public function getCloudStackFixtureClassName($classSuffix)
    {
        return 'Scalr\\Tests\\Fixtures\\Service\\CloudStack\\' . $classSuffix;
    }

    /**
     * Gets an CloudStack mock
     *
     * @param   string                   $serviceName  Service name (Network, Volume etc..)
     * @param   \Closure|callback|string $callback     optional callback for QueryClientResponse mock
     * @return  CloudStack Returns CloudStack Mock stub
     * @throws  \RuntimeException
     */
    public function getCloudStackMock($serviceName = null, $callback = null)
    {
        $me = $this;
        $serviceInstance = null;

        $csStub = $this->getMock(
            self::CLOUDSTACK_NS . '\\CloudStack',
            array('__get', 'getClient'),
            array('fakeEndpoint', 'fakeApiKey', 'fakeSecretKey', self::PLATFORM)
        );

        $queryClientClass = self::CLOUDSTACK_NS . '\\Client\\QueryClient';

        $queryClientStub = $this->getMock(
            $queryClientClass,
            array('call'),
            array(
                'fakeEndPoint',
                'fakeApiKey',
                'fakeSecretKey',
                self::PLATFORM,
            )
        );

        if (is_string($callback)) {
            $mth = $callback;
            $callback = function() use ($me, $mth) {
                return $me->getQueryClientResponseMock($me->getFixtureFileContent($mth), null);
            };
        } else if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Invalid callback');
        }

        $queryClientStub
            ->expects($this->any())
            ->method('call')
            ->will($this->returnCallback($callback))
        ;

        $csStub
            ->expects($this->any())
            ->method('getClient')
            ->will($this->returnValue($queryClientStub))
        ;

        if ($serviceName !== null) {
            $serviceInstance = $this->getServiceInterfaceMock($serviceName, $csStub);
        }

        $csStub
            ->expects($this->any())
            ->method('__get')
            ->will($this->returnValue($serviceInstance))
        ;

        return $csStub;
    }

    /**
     * Gets an service interface mock object
     *
     * @param   string          $serviceName  Service name (Network, Volume etc..)
     * @param   CloudStack      $csStub       CloudStack Mock stub
     * @return  ServiceInterface Returns service interface mock
     * @throws  \RuntimeException
     */
    public function getServiceInterfaceMock($serviceName, $csStub)
    {
        $serviceName = lcfirst($serviceName);
        $ucServiceName = ucfirst($serviceName);
        $serviceClass = self::CLOUDSTACK_NS . '\\Services\\' . $ucServiceName . 'Service';

        $serviceInterfaceStub = $this->getMock(
            $serviceClass,
            array('getApiHandler'),
            array($csStub)
        );

        $apiClass = self::CLOUDSTACK_NS . '\\Services\\' . $ucServiceName . '\\' . $serviceClass::VERSION_DEFAULT . '\\' . $ucServiceName . 'Api';
        $csApi = new $apiClass($serviceInterfaceStub);
        $serviceInterfaceStub
            ->expects($this->any())
            ->method('getApiHandler')
            ->will($this->returnValue($csApi))
        ;

        return $serviceInterfaceStub;
    }

    /**
     * Gets QueryClientResponse Mock.
     *
     * @param     string             $body
     * @param     int                $responseCode optional The code of the http response
     * @return    QueryClientResponse Returns response mock object
     */
    public function getQueryClientResponseMock($body, $command, $responseCode = null)
    {
        $response = $this->getMock(
            self::CLOUDSTACK_NS . '\\Client\\QueryClientResponse',
            array(
                'getContent',
                'getResponseCode',
            ),
            array(
                $this->getMock('HttpMessage'),
                $command
            )
        );

        if ($responseCode === null) {
            if (preg_match('/errorresponse/i', $body)) {
                $responseCode = 500;
            } else {
                $responseCode = 200;
            }
        }
        $response->expects($this->any())->method('getResponseCode')->will($this->returnValue($responseCode));
        $response->expects($this->any())->method('getContent')->will($this->returnValue($body));

        return $response;
    }
    /**
     * Gets DI Container
     *
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Gets Scalr Environment
     *
     * @return Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }
}