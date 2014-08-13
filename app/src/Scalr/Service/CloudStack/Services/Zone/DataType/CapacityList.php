<?php
namespace Scalr\Service\CloudStack\Services\Zone\DataType;

use Scalr\Service\CloudStack\DataType\AbstractListDataType;

/**
 * CapacityList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class CapacityList extends AbstractListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\CapacityData';
    }

}