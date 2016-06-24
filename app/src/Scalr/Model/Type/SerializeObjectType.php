<?php

namespace Scalr\Model\Type;

use Scalr\Util\ObjectAccess;

/**
 * SerializeType
 *
 * @author Andrii Penchuk  <a.penchuk@scalr.com>
 * @since  5.11.9 (04.02.2016)
 */
class SerializeObjectType extends SerializeType
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        return $value instanceof ObjectAccess ? $value->serialize() : parent::toDb($value);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        $objectAccess = new ObjectAccess();
        if ($value !== null) {
            $objectAccess->unserialize($value);
        }
        return $objectAccess;
    }
}