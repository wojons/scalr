<?php

namespace Scalr\Tests\Functional\Modules\Platforms;

use Scalr\Service\Aws;
use Scalr\Tests\WebTestCase;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use \SERVER_PLATFORMS;
use \ReflectionClass;


/**
 * Functional test for the Ec2PlatformModule class.
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    18.04.2013
 */
class Ec2Test extends WebTestCase
{

    const REGION = Aws::REGION_US_EAST_1;

    /**
     * @var Ec2PlatformModule
     */
    private $module;

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkippedIfPlatformDisabled(SERVER_PLATFORMS::EC2);
        $this->module = new Ec2PlatformModule();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::tearDown(
     */
    protected function tearDown()
    {
        $this->module = null;
        parent::tearDown();
    }

    /**
     * @test
     */
    public function testGetServersList()
    {
        $env = $this->getEnvironment();
        $servers = $this->module->GetServersList($env, self::REGION, true);
        $this->assertInternalType('array', $servers);
        $ref = new ReflectionClass('Scalr\\Service\\Aws\\Ec2\\DataType\\InstanceStateData');
        if (count($servers)) {
            foreach ($servers as $instanceid => $v) {
                $this->assertStringStartsWith('i-', $instanceid);
                $this->assertNotEmpty($v);
                $this->assertTrue(is_string($v));
            }
        }
    }
}