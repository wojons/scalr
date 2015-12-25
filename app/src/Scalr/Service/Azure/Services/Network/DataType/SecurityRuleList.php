<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractListDataType;

/**
 * SecurityRuleList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.9
 *
 */
class SecurityRuleList extends AbstractListDataType
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Azure\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\SecurityRuleData';
    }

}