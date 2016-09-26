<?php
namespace Scalr\Model\Type;

/**
 * UuidStringType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (07.02.2014)
 */
class UuidStringType extends StringType implements GeneratedValueTypeInterface
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\GeneratedValueTypeInterface::generateValue()
     */
    public function generateValue($entity = null)
    {
        return \Scalr::GenerateUID();
    }
}