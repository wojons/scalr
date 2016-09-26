<?php

namespace Scalr\Tests\Modules;

use ReflectionClass;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Service\Aws\Ec2\DataType\BlockDeviceMappingData;
use Scalr\Tests\WebTestCase;

/**
 * Ec2PlatformModule test
 *
 * @author N.V
 */
class Ec2PlatformModuleTest extends WebTestCase
{

    /**
     * Data provider for blockDevicesTest
     */
    public function providerBlockDeviceByType()
    {
        return [
            [ 'm1.small', [ 'b' ] ],
            [ 'm1.medium', [ 'b' ] ],
            [ 'm1.large', [ 'b', 'c' ] ],
            [ 'm1.xlarge', [ 'b', 'c', 'e', 'f' ] ],
            [ 'm2.xlarge', [ 'b' ] ],
            [ 'm2.2xlarge', [ 'b' ] ],
            [ 'm2.4xlarge', [ 'b', 'c' ] ],
            [ 'm3.medium', [ 'b' ] ],
            [ 'm3.large', [ 'b' ] ],
            [ 'm3.xlarge', [ 'b', 'c' ] ],
            [ 'm3.2xlarge', [ 'b', 'c' ] ],
            [ 'c1.medium', [ 'b' ] ],
            [ 'c1.xlarge', [ 'b', 'c', 'e', 'f' ] ],
            [ 'c3.large', [ 'b', 'c' ] ],
            [ 'c3.xlarge', [ 'b', 'c' ] ],
            [ 'c3.2xlarge', [ 'b', 'c' ] ],
            [ 'c3.4xlarge', [ 'b', 'c' ] ],
            [ 'c3.8xlarge', [ 'b', 'c' ] ],
            [ 'r3.large', [ 'b' ] ],
            [ 'r3.xlarge', [ 'b' ] ],
            [ 'r3.2xlarge', [ 'b' ] ],
            [ 'r3.4xlarge', [ 'b' ] ],
            [ 'r3.8xlarge', [ 'b', 'c' ] ],
            [ 'i2.xlarge', [ 'b' ] ],
            [ 'i2.2xlarge', [ 'b', 'c' ] ],
            [ 'i2.4xlarge', [ 'b', 'c', 'e', 'f' ] ],
            [ 'i2.8xlarge', [ 'b', 'c', 'e', 'f', 'g', 'h', 'i', 'j' ] ],
            [ 'd2.xlarge', [ 'b', 'c', 'e' ] ],
            [ 'd2.2xlarge', [ 'b', 'c', 'e', 'f', 'g', 'h' ] ],
            [ 'd2.4xlarge', [ 'b', 'c', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n' ] ],
            [ 'd2.8xlarge', [ 'b', 'c', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'd' ] ],
            [ 'g2.2xlarge', [ 'b' ] ],
            [ 'g2.8xlarge', [ 'b', 'c' ] ],
            [ 'hs1.8xlarge', [ 'b', 'c', 'e', 'f', 'g', 'h', 'i', 'j', 'k1', 'k2', 'k3', 'k4', 'k5', 'k6', 'k7', 'k8', 'k9', 'l1', 'l2', 'l3', 'l4', 'l5', 'l6', 'l7' ] ],
            [ 'cc2.8xlarge', [ 'b', 'c', 'e', 'f' ] ],
            [ 'cg1.4xlarge', [ 'b', 'c' ] ],
            [ 'hi1.4xlarge', [ 'b', 'c' ] ],
            [ 'cr1.8xlarge', [ 'b', 'c' ] ]
        ];
    }

    /**
     * @test
     * @dataProvider providerBlockDeviceByType
     *
     * @param string $instanceType
     * @param array $expectedBlockDeviceConfiguration
     */
    public function blockDevicesTest($instanceType, array $expectedBlockDeviceConfiguration)
    {
        /* @var $pm Ec2PlatformModule */
        $pm = PlatformFactory::NewPlatform('ec2');

        $reflection = new ReflectionClass(get_class($pm));
        $method = $reflection->getMethod('GetBlockDeviceMapping');
        $method->setAccessible(true);

        /* @var $mapping  BlockDeviceMappingData[] */
        $mapping = $method->invoke($pm, $instanceType, '');

        $this->assertEquals(count($expectedBlockDeviceConfiguration), count($mapping), "Wrong count");

        foreach ($mapping as $num => $blockDevice) {
            $this->assertTrue(isset($expectedBlockDeviceConfiguration[$num]), "Invalid device position");

            $this->assertEquals($expectedBlockDeviceConfiguration[$num], $blockDevice->deviceName, "Device name mismatch");
        }
    }
}