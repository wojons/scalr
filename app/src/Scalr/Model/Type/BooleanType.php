<?php
namespace Scalr\Model\Type;

/**
 * BooleanType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (07.02.2014)
 */
class BooleanType extends StringType
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) return null;
        return (is_string($value) && strlen($value) > 1 && strtoupper($value) == 'TRUE' || $value) ? 1 : 0;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        if ($value === null) return null;
        return (is_string($value) && strlen($value) > 1 && strtoupper($value) == 'TRUE' || $value) ? true : false;
    }
}