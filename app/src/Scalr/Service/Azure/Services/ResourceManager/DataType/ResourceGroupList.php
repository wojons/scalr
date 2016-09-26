<?php

namespace Scalr\Service\Azure\Services\ResourceManager\DataType;

use Scalr\Service\Azure\DataType\AbstractListDataType;

/**
 * ResourceGroupList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.6.8
 *
 */
class ResourceGroupList extends AbstractListDataType
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Azure\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\ResourceGroupData';
    }

}