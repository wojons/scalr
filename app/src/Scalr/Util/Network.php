<?php

class Scalr_Util_Network
{
    /**
     * Convert ip mask (17.16.*) to binary net (17.16.255.255) and mask (255.255.0.0)
     *
     * @param $mask string
     * @return array|null (net, mask)
     */
    public static function convertMaskToSubnet($mask)
    {
        $mask = trim($mask);
        $maskArr = explode('.', $mask);
        $result = array();

        if (count($maskArr) > 1) {
            foreach ($maskArr as $el) {
                if (0 < $el && $el < 256) {
                    $result[] = $el;
                } else if ($el == '*') {
                    break;
                } else {
                    $result = null;
                }
            }

            if (is_array($result) && count($result) < 5) {
                if (count($result) < 4) {
                    $mask = array_merge(array_fill(0, count($result), '255'), array_fill(count($result), 4 - count($result), '0'));
                    $net = array_merge($result, array_fill(count($result), 4 - count($result), '0'));
                } else {
                    $mask = array_fill(0, 4, '255');
                    $net = $result;
                }

                return array('net' => ip2long(join('.', $net)), 'mask' => ip2long(join('.', $mask)));
            }
        }

        return null;
    }

    /**
     * Reverse procedure
     *
     * @param $binary array
     * @return string
     */
    public static function convertSubnetToMask($binary)
    {
        $mask = long2ip($binary['net']);
        $maskArr = explode('.', $mask);
        $result = array();

        foreach ($maskArr as $m) {
            if (0 < $m && $m < 255)
                $result[] = $m;
            else
                break;
        }

        if (count($result) < 4)
            $result[] = '*';

        return join('.', $result);
    }

    /**
     * Test if given IP in subnets
     *
     * @param $ip string
     * @param $subnets array or subnet
     * @return bool
     */
    public static function isIpInSubnets($ip, $subnets)
    {
        if (isset($subnets['net']))
            $subnets = array($subnets);

        foreach ($subnets as $subnet) {
            if (($subnet['net'] & $subnet['mask']) == (ip2long($ip) & $subnet['mask']))
                return true;
        }

        return false;
    }

    /**
     * Check if given CIRD is valid
     *
     * @param $cidr
     * @return bool
     */
    public static function isValidCidr($cidr)
    {
        $ar = explode('/', $cidr);

        if (count($ar) != 2)
            return false;

        $base = ip2long($ar[0]);
        if ($base === FALSE)
            return false;

        $bits = $ar[1];
        if ($bits == 32)
            return true;
        else if ($bits == 0 && $base == 0)
            return true;
        else if ($bits < 1 || $bits > 31)
            return false;

        $mask = $bits == 0 ? 0: (~0 << (32 - $bits));

        return ($base & $mask) == $base;
    }
}
