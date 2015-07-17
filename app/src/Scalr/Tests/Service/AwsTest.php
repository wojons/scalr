<?php
namespace Scalr\Tests\Service;

use Scalr\Service\Aws;

/**
 * AWS Test
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     25.09.2012
 */
class AwsTest extends AwsTestCase
{

    const CLASS_AWS = 'Scalr\\Service\\Aws';

    /**
     * @var Aws
     */
    protected $aws;

    /**
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();

        if (!$this->isSkipFunctionalTests()) {
            $this->aws = $this->getEnvironment()->aws(self::REGION);
            $this->assertInstanceOf(self::CLASS_AWS, $this->aws);
        }
    }

    /**
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown()
    {
        $this->aws = null;
        parent::tearDown();
    }

    /**
     * @test
     * @functional
     */
    public function testGetAvailableServiceInterfaces()
    {
        $awsRefClass = new \ReflectionClass(self::CLASS_AWS);
        $available = $this->aws->getAvailableServiceInterfaces();
        $interfaces = array();
        foreach ($awsRefClass->getConstants() as $k => $v) {
            if (strpos($k, 'SERVICE_INTERFACE_') === 0) {
                $interfaces[$k] = $v;
                $this->assertContains($v, $available, 'Missing interface ' . $v . ' in available.');
            }
        }
        foreach ($interfaces as $serviceInterfaceName) {
            $instance = $this->aws->{$serviceInterfaceName};
            $this->assertInstanceOf('Scalr\\Service\\Aws\\' . ucfirst($serviceInterfaceName),
                $instance, 'Illegal service interface instance object');
        }
        //Test that region does not need here.
        $this->getEnvironment()->aws->iam;
    }

    /**
     * @test
     * @expectedException Scalr\Service\AwsException
     * @functional
     */
    public function testSetRegionInvalid()
    {
        $this->getEnvironment()->aws('invalid-region')->elb;
    }

    /**
     * Data Provider
     *
     * @return array
     */
    public function providerRegion()
    {
        $data = array();

        $reflection = new \ReflectionClass(self::CLASS_AWS);
        foreach ($reflection->getConstants() as $name => $value) {
            if (strpos($name, 'REGION_') !== 0) continue;
            $data[] = array($name);
        }

        return $data;
    }

    /**
     * @test
     * @dataProvider  providerRegion
     * @functional
     */
    public function testSetRegion($region)
    {
        $aws = $this->getEnvironment()->aws($region);
        $this->assertEquals($region, $aws->getRegion());
    }

    /**
     * @test
     * @dataProvider  providerRegion
     * @functional
     */
    public function testGetInstance($region)
    {
        $aws = $this->getEnvironment()->aws($region);
        $this->assertEquals($region, $aws->getRegion());
    }

    /**
     * @test
     */
    public function testGetReflectionClass()
    {
        $refl = Aws::getReflectionClass();
        $this->assertEquals(self::CLASS_AWS, $refl->getName(), 'Invalid reflection class.');
    }
}