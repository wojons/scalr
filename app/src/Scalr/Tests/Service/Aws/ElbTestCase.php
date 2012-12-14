<?php
namespace Scalr\Tests\Service\Aws;

use Scalr\Tests\Service\AwsTestCase;
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
 * AWS Elb TestCase
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     10.10.2012
 */
class ElbTestCase extends AwsTestCase
{

	/**
	 * {@inheritdoc}
	 * @see Scalr\Tests\Service.AwsTestCase::setUp()
	 */
	public function setUp()
	{
		parent::setUp();
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Tests\Service.AwsTestCase::tearDown()
	 */
	public function tearDown()
	{
		parent::tearDown();
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Tests\Service.AwsTestCase::getFixtureFilePath()
	 */
	public function getFixtureFilePath($filename)
	{
		return $this->getFixturesDirectory() . '/' . Elb::API_VERSION_CURRENT . '/' . $filename;
	}

	/**
	 * Gets an Elb Repository instance
	 *
	 * @return    ElbLoadBalancerDescriptionRepository
	 */
	public function getElbLoadBalancerDescripitonRepository()
	{
		return $this->getEntityManager()->getRepository('Elb:LoadBalancerDescription');
	}

	/**
	 * Gets Elb Mock
	 *
	 * @param    callback $callback
	 * @return   Elb      Returns Elb Mock class
	 */
	public function getElbMock($callback = null)
	{
		return $this->getServiceInterfaceMock('Elb');
	}
}