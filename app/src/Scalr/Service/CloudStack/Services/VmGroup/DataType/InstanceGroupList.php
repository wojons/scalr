<?php
namespace Scalr\Service\CloudStack\Services\VmGroup\DataType;

use Scalr\Service\CloudStack\DataType\AbstractListDataType;

/**
 * InstanceGroupList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class InstanceGroupList extends AbstractListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\InstanceGroupData';
    }

}