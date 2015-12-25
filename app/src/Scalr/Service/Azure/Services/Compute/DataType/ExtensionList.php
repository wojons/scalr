<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractListDataType;

/**
 * ExtensionList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.9
 *
 */
class ExtensionList extends AbstractListDataType
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\Azure\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\ExtensionData';
    }

}