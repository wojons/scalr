<?php
namespace Scalr\Model\Type;

/**
 * SerializeType
 *
 * @author   Igor Vodiasov <invar@scalr.com>
 * @since    5.0 (05.05.2014)
 */
class SerializeType extends StringType
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) return null;
        return serialize($value);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        if ($value === null) return null;
        return unserialize($value);
    }
}