<?php
namespace Scalr\Model\Type;

/**
 * JsonType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0.1 (02.10.2014)
 */
class JsonType extends StringType
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) return null;

        return json_encode($value);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        if ($value === null) return null;

        return json_decode($value);
    }
}