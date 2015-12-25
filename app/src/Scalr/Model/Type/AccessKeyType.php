<?php
namespace Scalr\Model\Type;

/**
 * AccessKeyType
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.4 (17.02.2015)
 */
class AccessKeyType extends StringType implements GeneratedValueTypeInterface
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\GeneratedValueTypeInterface::generateValue()
     */
    public function generateValue($entity = null)
    {
        $ret = '';

        $allow = [
            'A','B','C','D','E','F','G','H','I','J','K','L','M',
            'N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
            '0','1','2','3','4','5','6','7','8','9'
        ];

        $max = count($allow) - 1;

        for ($i = 0; $i < 16; $i++) {
            $ret .= $allow[mt_rand(0, $max)];
        }

        return 'APIK' . $ret;
    }
}