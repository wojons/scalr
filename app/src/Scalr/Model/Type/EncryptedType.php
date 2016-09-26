<?php
namespace Scalr\Model\Type;

/**
 * EncryptedType
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.4 (17.02.2015)
 */
class EncryptedType extends StringType
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toDb()
     */
    public function toDb($value)
    {
        if ($value === null) return null;
        return \Scalr::getContainer()->crypto->encrypt($value);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\AbstractType::toPhp()
     */
    public function toPhp($value)
    {
        if ($value === null) return null;
        return \Scalr::getContainer()->crypto->decrypt($value);
    }
}