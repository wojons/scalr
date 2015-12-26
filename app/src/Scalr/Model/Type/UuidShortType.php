<?php

namespace Scalr\Model\Type;

/**
 * Short uuid type
 *
 * @author N.V.
 */
class UuidShortType extends UuidStringType
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\GeneratedValueTypeInterface::generateValue()
     */
    public function generateValue($entity = null)
    {
        return \Scalr::GenerateUID(true);
    }
}