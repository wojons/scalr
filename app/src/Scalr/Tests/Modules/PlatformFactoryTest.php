<?php

namespace Scalr\Tests\Modules;

use Scalr\Modules\PlatformFactory;
use Scalr\Tests\WebTestCase;

/**
 * PlatformFactory test
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.0.0 (03.03.2014)
 */
class PlatformFactoryTest extends WebTestCase
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\WebTestCase::tearDown()
     */
    protected function tearDown()
    {
        $this->env = null;
        $this->user = null;
    }

    /**
     * Data provider for testNewPlatform
     *
     * @return array
     */
    public function providerNewPlatform()
    {
        $ret = [];
        foreach (\SERVER_PLATFORMS::GetList() as $platform => $name) {
            $ret[] = [$platform];
        }
        return $ret;
    }

    /**
     * @test
     * @dataProvider providerNewPlatform
     */
    public function testNewPlatform($platformName)
    {
        $platform = PlatformFactory::NewPlatform($platformName);
        $this->assertInstanceOf('Scalr\\Modules\\PlatformModuleInterface', $platform);

        $this->markTestSkippedIfPlatformDisabled($platformName);

        $locations = $platform->getLocations($this->getEnvironment());
        $this->assertInternalType('array', $locations, sprintf('%s::getLocations() should return array.', get_class($platform)));

        if (\Scalr::getContainer()->analytics->enabled) {
            $ret = $platform->hasCloudPrices($this->getEnvironment());
        }

        if (!empty($locations)) {
            $region = key($locations);
            $list = $platform->getInstanceTypes($this->getEnvironment(), $region);
            $this->assertTrue((is_array($list) || $list instanceof \Traversable), sprintf('%::getInstanceTypes() should return array.', get_class($platform)));
            $this->assertNotEmpty($list);
        }
    }

    /**
     * @test
     * @expectedException Exception
     */
    public function testNewPlatformException()
    {
        PlatformFactory::NewPlatform('unsupportedPlatformCalled');
    }
}