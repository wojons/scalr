<?php

namespace Scalr\Model\Type;

/**
 * InetType
 * Type for storage IPv6 and IPv4 addresses.
 * DB Column for it must be of type VARBINARY(16).
 *
 * @author N.V.
 */
class InetType extends BinaryType
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        parent::toDb(inet_pton($value));
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        parent::toPhp(inet_ntop($value));
    }
}