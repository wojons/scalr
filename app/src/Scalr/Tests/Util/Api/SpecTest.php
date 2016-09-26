<?php

namespace Scalr\Tests\Util\Api;

use Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes\ObjectEntity;
use Scalr\Tests\Functional\Api\V2\SpecSchema\SpecManager;
use Scalr\Tests\TestCase;

/**
 * SpecTest
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.20 (15.03.2016)
 */
class SpecTest extends TestCase
{
    /**
     * Current api version
     */
    const  API_VERSION = 'v1beta0';

    /**
     * Provider for testSpecks
     *
     * @return array
     */
    public function specksProvider()
    {
        return [
            [static::API_VERSION, 'user'],
            [static::API_VERSION, 'account']
        ];
    }

    /**
     * Check readOnly vs required Object properties
     *
     * @test
     * @dataProvider specksProvider
     *
     * @param string $version api version
     * @param string $provider specifications type
     */
    public function testSpecks($version, $provider)
    {
        $specs = new SpecManager($version, $provider);
        $definitions = $specs->getDefinitions('#^Api.*|Response$#');
        $this->assertNotEmpty($definitions);
        /* @var  $definition ObjectEntity */
        foreach ($definitions as $name => $definition) {
            $intersectProp = array_intersect($definition->required, $definition->readOnly);
            $this->assertEmpty($intersectProp, sprintf(
                'The property %s is mutually exclusive. should be required or read-only. Object %s in %s spec',
                implode(', ', $intersectProp), $name, $provider
            ));

            //discriminator must be in the required property list
            if (!empty($definition->discriminator)) {
                $this->assertTrue(in_array($definition->discriminator, $definition->required), sprintf(
                    'Property discriminator must be required Object %s in %s spec',  $name, $provider
                ));
            }
        }
    }
}