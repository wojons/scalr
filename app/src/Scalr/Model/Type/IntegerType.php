<?php
namespace Scalr\Model\Type;

/**
 * IntegerType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (07.02.2014)
 */
class IntegerType extends StringType
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) return null;
        return intval($value);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        if ($value === null) return null;
        return intval($value);
    }
}