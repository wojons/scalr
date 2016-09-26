<?php

namespace Scalr\Tests\Util;

use Scalr\Tests\TestCase;
use Scalr_Util_Network;

class NetworkTest extends TestCase
{
    /**
     * Data provider for testNetwork()
     */
    public function providerTestNetwork()
    {
        $cases = array();

        $cases[] = array('167.45.3.7', array('net' => 2804744967, 'mask' => 4294967295), '167.45.3.7', '167.45.3.9');
        $cases[] = array('67.46.*', array('net' => 1127088128, 'mask' => 4294901760), '67.46.1.1', '67.45.1.1');
        $cases[] = array('91.*', array('net' => 1526726656, 'mask' => 4278190080), '91.1.12.13', '256.1.2.4');
        $cases[] = array('256.*', null, null, null);

        return $cases;
    }

    /**
     * @test
     * @dataProvider providerTestNetwork
     */
    public function testNetwork($mask, $subnet, $ipAllow, $ipDeny)
    {
        $sub = Scalr_Util_Network::convertMaskToSubnet($mask);

        if ($subnet) {
            $this->assertArrayHas($subnet['net'], 'net', $sub);
            $this->assertArrayHas($subnet['mask'], 'mask', $sub);

            $this->assertTrue(Scalr_Util_Network::isIpInSubnets($ipAllow, $subnet));
            $this->assertFalse(Scalr_Util_Network::isIpInSubnets($ipDeny, $subnet));

            $this->assertEquals($mask, Scalr_Util_Network::convertSubnetToMask($subnet));
        } else {
            $this->assertEmpty($subnet);
        }
    }

    /**
     * Data provider for testCidr()
     */
    public function providerTestCidr()
    {
        $cases = array();

        $cases[] = array('167.45.3.7/32', true);
        $cases[] = array('127.1.0.0/8', false);
        $cases[] = array('127.0.0.0/32', true);
        $cases[] = array('127.0.0.1/16', false);
        $cases[] = array('127', false);
        $cases[] = array('127.0.0.0/56', false);
        $cases[] = array('0.0.0.0/0', true);
        $cases[] = array('86.216.79.80/28', true);
        $cases[] = array('70.228.6.0/26', true);
        $cases[] = array('86.216.79.80/27', false);
        $cases[] = array('70.228.6.0/22', false);

        return $cases;
    }

    /**
     * @test
     * @dataProvider providerTestCidr
     */
    public function testCidr($mask, $result)
    {
        $this->assertEquals($result, Scalr_Util_Network::isValidCidr($mask));
    }

}
