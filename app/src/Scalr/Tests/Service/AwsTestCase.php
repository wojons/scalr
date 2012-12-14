<?php
namespace Scalr\Tests\Service;

use Scalr\Tests\TestCase;
use Scalr\Service\Aws\CloudWatch\Handler\MetricHandler;
use Scalr\Service\Aws\ServiceInterface;
use Scalr\DependencyInjection\Container;
use Scalr\Service\Aws\Elb\DataType\AppCookieStickinessPolicyList;
use Scalr\Service\Aws\Elb\DataType\ListenerData;
use Scalr\Service\Aws\Client\QueryClientException;
use Scalr\Service\Aws\Repository\ElbLoadBalancerDescriptionRepository;
use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Client\QueryClientResponse;
use Scalr\Service\Aws\Elb\Handler\LoadBalancerHandler;
use Scalr\Service\Aws\Elb\DataType\LoadBalancerDescriptionData;
use Scalr\Service\Aws\EntityManager;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\Elb\DataType\LoadBalancerDescriptionList;
use Scalr\Service\Aws\Elb;
use Scalr\Service\Aws;

/**
 * AWS TestCase
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     19.10.2012
 */
class AwsTestCase extends TestCase
{

	const ENV_ID = 7783;

	const REGION = Aws::REGION_US_EAST_1;

	const CLASS_ERROR_DATA = 'Scalr\\Service\\Aws\\DataType\\ErrorData';

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
	public function setUp()
	{
		parent::setUp();
		$this->container = Container::getInstance();
		$this->environment = new \Scalr_Environment();
		$this->environment->loadById(self::ENV_ID);
	}

	/**
	 * {@inheritdoc}
	 * @see PHPUnit_Framework_TestCase::tearDown()
	 */
	public function tearDown()
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
	 * Gets instance of EntityManager
	 *
	 * @return \Scalr\Service\Aws\EntityManager
	 */
	public function getEntityManager()
	{
		return EntityManager::getInstance();
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
			'Scalr\\Service\\Aws',
			array('__get'),
			array(self::REGION),
			'',
			false
		);
		foreach (array(
			'region'          => self::REGION,
			'accessKeyId'     => $container->awsAccessKeyId,
			'secretAccessKey' => $container->awsSecretAccessKey
		) as $k => $v) {
			$r = new \ReflectionProperty('Scalr\\Service\\Aws', 'region');
			$r->setAccessible(true);
			$r->setValue($awsStub, self::REGION);
			$r->setAccessible(false);
			unset($r);
		}

		return $awsStub;
	}


	/**
	 * Gets an ser vice interface mock object
	 *
	 * @param   string            $serviceName  Service name (Elb, CloudWatch etc..)
	 * @param   Closure|callback  $callback     optional callback for QueryClientResponse mock
	 * @return  ServiceInterface Returns service interface mock
	 * @throws  \RuntimeException
	 */
	public function getServiceInterfaceMock ($serviceName, $callback = null)
	{
		$serviceName = lcfirst($serviceName);
		$ucServiceName = ucfirst($serviceName);
		$serviceClass = 'Scalr\\Service\\Aws\\' . $ucServiceName;
		if (!in_array($serviceName, Aws::getAvailableServiceInterfaces())) {
			throw new \RuntimeException(sprintf('Unknown service name %s', $serviceName));
		}
		$container = $this->getContainer();
		$awsStub = $this->getAwsMock();
		$serviceInterfaceStub = $this->getMock(
			$serviceClass,
			array('getApiHandler', '__get'),
			array($awsStub)
		);
		if ($serviceName == Aws::SERVICE_INTERFACE_ELB) {
			$loadBalancerHandler = new LoadBalancerHandler($serviceInterfaceStub);
			$serviceInterfaceStub
				->expects($this->any())
				->method('__get')
				->will($this->returnCallback(function ($name) use ($loadBalancerHandler) {
					if ($name == 'loadBalancer') {
						return $loadBalancerHandler;
					}
				}))
			;
		} else if ($serviceName == Aws::SERVICE_INTERFACE_CLOUD_WATCH) {
			$metricHandler = new MetricHandler ($serviceInterfaceStub);
			$serviceInterfaceStub
				->expects($this->any())
				->method('__get')
				->will($this->returnCallback(function ($name) use ($metricHandler) {
					if ($name == 'metric') {
						return $metricHandler;
					}
				}))
			;
		}
		$awsStub
			->expects($this->any())
			->method('__get')
			->will($this->returnValue($serviceInterfaceStub))
		;
		$queryClientStub = $this->getMock(
			'Scalr\\Service\\Aws\\Client\\QueryClient',
			array('call'),
			array(
				$container->awsAccessKeyId,
				$container->awsSecretAccessKey,
				$serviceClass::API_VERSION_CURRENT,
				$serviceInterfaceStub->getUrl(),
			)
		);
		$queryClientStub
			->expects($this->any())
			->method('call')
			->will($this->returnCallback($callback === null ? array($this, 'getQueryClientStandartCallResponseMock') : $callback))
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
	 * @param     string    $body
	 * @return    QueryClientResponse Returns response mock object
	 */
	public function getQueryClientResponseMock($body)
	{
		$response = $this->getMock(
			'Scalr\\Service\\Aws\\Client\\QueryClientResponse',
			array(
				'getRawContent',
				'getError'
			),
			array(
				$this->getMock('HttpMessage')
			)
		);
		$response->expects($this->any())->method('getError')->will($this->returnValue(false));
		$response->expects($this->any())->method('getRawContent')->will($this->returnValue($body));

		return $response;
	}

	/**
	 * Returns fixtures file path
	 *
	 * @param  string $filename A fixture file name
	 * @return string Returns fixtures file path
	 */
	public function getFixtureFilePath($filename)
	{
		return $this->getFixturesDirectory() . '/Aws/' . $filename;
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
}