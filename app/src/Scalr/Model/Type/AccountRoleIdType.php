<?php

namespace Scalr\Model\Type;

use Scalr\Acl\Acl;

/**
 * AccountRoleIdType
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.18 (5.03.2016)
 */
class AccountRoleIdType extends StringType implements GeneratedValueTypeInterface
{
    /**
     * {@inheritdoc}
     * @see GeneratedValueTypeInterface::generateValue()
     */
    public function generateValue($entity = null)
    {
        return Acl::generateAccountRoleId();
    }
}