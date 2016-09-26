<?php
namespace Scalr\Model\Type;

/**
 * DecimalType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (07.02.2014)
 */
class DecimalType extends StringType
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) return null;
        if (isset($this->field->column->scale)) {
            return sprintf('%0.' . intval($this->field->column->scale) . 'f', $value);
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
        return floatval($value);
    }
}