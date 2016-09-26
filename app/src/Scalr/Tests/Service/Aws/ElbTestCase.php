<?php
namespace Scalr\Tests\Service\Aws;

use Scalr\Tests\Service\AwsTestCase;
use Scalr\Service\Aws\Elb;

/**
 * AWS Elb TestCase
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     10.10.2012
 */
class ElbTestCase extends AwsTestCase
{

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service.AwsTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service.AwsTestCase::tearDown()
     */
    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service.AwsTestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Elb';
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