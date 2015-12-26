<?php

namespace Scalr\Service\Azure\DataType;

/**
 * ProviderList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 *
 */
class ProviderList extends AbstractListDataType
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Azure\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\ProviderData';
    }

}