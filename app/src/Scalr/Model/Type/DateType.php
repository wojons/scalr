<?php
namespace Scalr\Model\Type;

/**
 * DateType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (07.02.2014)
 */
class DateType extends StringType
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) return null;
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }
        return (string) $value;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        if ($value === null) return null;
        return new \DateTime($value);
    }
}