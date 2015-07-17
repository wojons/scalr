<?php
namespace Scalr\Model\Type;

/**
 * Bin4Type
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.5 (19.05.2015)
 */
class Bin4Type extends StringType implements GeneratedValueTypeInterface
{

    protected $wh = 'UNHEX(?)';

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        if ($value === null) return null;
        return bin2hex($value);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\GeneratedValueTypeInterface::generateValue()
     */
    public function generateValue($entity = null)
    {
        return substr(md5(rand()), 0, 8);
    }
}