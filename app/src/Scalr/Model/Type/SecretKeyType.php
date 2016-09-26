<?php
namespace Scalr\Model\Type;

/**
 * SecretKeyType
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.4 (17.02.2015)
 */
class SecretKeyType extends EncryptedType implements GeneratedValueTypeInterface
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\GeneratedValueTypeInterface::generateValue()
     */
    public function generateValue($entity = null)
    {
        return \Scalr::GenerateRandomKey(40);
    }
}